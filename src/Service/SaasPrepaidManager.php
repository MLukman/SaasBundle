<?php

namespace MLukman\SaasBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use MLukman\SaasBundle\Config\PrepaidConfig;
use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\Entity\Credit;
use MLukman\SaasBundle\Entity\CreditPayment;
use MLukman\SaasBundle\Entity\CreditUsage;
use MLukman\SaasBundle\Entity\CreditUsageSource;
use MLukman\SaasBundle\InsufficientCreditBalanceException;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use MLukman\SaasBundle\Payment\ProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class SaasPrepaidManager
{

    protected PrepaidConfig $configuration;
    protected ?ProviderInterface $paymentProvider = null;

    public function __construct(
            protected EntityManagerInterface $em,
            protected RequestStack $requestStack)
    {
        
    }

    public function getConfiguration(): PrepaidConfig
    {
        return $this->configuration;
    }

    public function setConfiguration(PrepaidConfig $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getPaymentProvider(): ?ProviderInterface
    {
        return $this->paymentProvider;
    }

    public function setPaymentProvider(?ProviderInterface $paymentProvider): void
    {
        $this->paymentProvider = $paymentProvider;
    }

    public function getTopupConfig(string $topupId): TopupConfig
    {
        $topups = $this->configuration->getTopups();
        if (!isset($topups[$topupId])) {
            throw new InvalidSaasConfigurationException("Topup $topupId is invalid");
        }
        return $topups[$topupId];
    }

    public function getPurchasableTopups(string $wallet): array
    {
        $topups = [];
        foreach ($this->configuration->getTopups() as $topupId => $topup) {
            if (empty($topup->getPaymentParams())) {
                continue;
            }
            if ($topup->getLimit() > 0 && $this->getTopupCount($wallet, $topupId) >= $topup->getLimit()) {
                continue;
            }
            $topups[$topupId] = $topup;
        }
        return $topups;
    }

    public function initiateTopupPayment(string $wallet, TopupConfig|string $topup, string $redirectBackUrl): ?RedirectResponse
    {
        if (is_string($topup)) {
            $topup = $this->getTopupConfig($topup);
        }

        $trans = null;
        if (($existing = $this->getPaymentByWalletAndTopup($wallet, $topup->_id, 0))) {
            $trans = $this->paymentProvider->retrieveTopupTransaction($existing->getTransaction());
        }
        if (!$trans) {
            $trans = $this->paymentProvider->initiateTopupTransaction($topup, $redirectBackUrl);
            $payment = new CreditPayment($this->paymentProvider->getId(), $trans->getReference(), $wallet, $topup->_id);
            $this->em->persist($payment);
            $this->em->flush();
        }
        $this->requestStack->getSession()->set('saas.payment.transaction', $trans->getReference());
        return $this->paymentProvider->generateRedirectForTransaction($trans);
    }

    public function completeTopupPayment(CreditPayment $payment)
    {
        $payment->setCredit($this->addCredit($payment->getWallet(), $payment->getTopup()));
    }

    public function getCreditBalance(string $wallet): int
    {
        return intval($this->em->createQuery('SELECT SUM(c.balance) FROM MLukman\SaasBundle\Entity\Credit c WHERE c.wallet = :wallet AND c.balance > 0 AND (c.expiry IS NULL OR c.expiry > CURRENT_TIMESTAMP())')
                        ->setParameter('wallet', $wallet)
                        ->getSingleScalarResult());
    }

    public function getTopupCount(string $wallet, ?string $topupId = null): int
    {
        $qb = $this->em->createQueryBuilder()->select('count(c.id)')->from(Credit::class, 'c')->where('c.wallet = :wallet')->setParameter('wallet', $wallet);
        if ($topupId) {
            $qb->andWhere('c.topup = :topup')->setParameter('topup', $topupId);
        }
        return intval($qb->getQuery()->getSingleScalarResult());
    }

    public function getPaymentByWalletAndTopup(string $wallet, string $topup, int $status = 0): ?CreditPayment
    {
        try {
            return $this->em->createQuery('SELECT p FROM \MLukman\SaasBundle\Entity\CreditPayment p WHERE p.wallet = :wallet AND p.topup = :topup AND p.status = :status ORDER BY p.created DESC')
                            ->setParameter('wallet', $wallet)
                            ->setParameter('topup', $topup)
                            ->setParameter('status', $status)
                            ->setMaxResults(1)
                            ->getSingleResult();
        } catch (NoResultException $ex) {
            return null;
        }
    }

    public function addCredit(string $wallet, TopupConfig|string $topup): Credit
    {
        if (is_string($topup)) {
            $topup = $this->getTopupConfig($topup);
        }
        $credit = new Credit($wallet, $topup->getCredit(), $topup->_id);
        if (!empty($topup->getValidity())) {
            $expiry = (clone $credit->getCreated());
            $expiry->modify($topup->getValidity());
            $credit->setExpiry($expiry);
        }
        $this->em->persist($credit);
        return $credit;
    }

    public function addCreditUsage(string $wallet, string $type, string $reference): CreditUsage
    {
        if (is_null($point = $this->configuration->getUsages()[$type] ?? null)) {
            throw new InvalidSaasConfigurationException("There is no prepaid usage with type '$type'");
        }
        $usages = [];

        // first query those with expiry dates, followed by those without ones
        $queries = [
            'SELECT c FROM MLukman\SaasBundle\Entity\Credit c WHERE c.wallet = :wallet AND c.balance > 0 AND c.expiry > CURRENT_TIMESTAMP() ORDER BY c.expiry ASC',
            'SELECT c FROM MLukman\SaasBundle\Entity\Credit c WHERE c.wallet = :wallet AND c.balance > 0 AND c.expiry IS NULL ORDER BY c.created ASC'
        ];
        $usage = new CreditUsage($wallet, $point, $type, $reference);
        $this->em->persist($usage);
        for ($i = 0; $i < 2 && $point > 0; $i++) {
            $credits = $this->em->createQuery($queries[$i])->setParameter('wallet', $wallet)->getResult();
            foreach ($credits as $credit) {
                /* @var $credit Credit */
                $balance = $credit->getBalance();
                if ($balance >= $point) {
                    $source = new CreditUsageSource($credit, $usage, $point);
                    $balance -= $point;
                    $point = 0;
                } else {
                    $source = new CreditUsageSource($credit, $usage, $balance);
                    $point -= $balance;
                    $balance = 0;
                }
                $this->em->persist($source);
                $credit->getUsages()->add($source);
                $credit->recalculateBalance();
                if ($point <= 0) {
                    break 2;
                }
            }
        }
        if ($point > 0) {
            throw new InsufficientCreditBalanceException();
        }
        return $usage;
    }

    public function getCreditRecords(string $wallet): array
    {
        return $this->em->createQuery('SELECT c FROM MLukman\SaasBundle\Entity\Credit c WHERE c.wallet = :wallet ORDER BY c.created DESC')->setParameter('wallet', $wallet)->getResult();
    }

    public function getCreditUsageRecords(string $wallet): array
    {
        return $this->em->createQuery('SELECT c FROM MLukman\SaasBundle\Entity\CreditUsage c WHERE c.wallet = :wallet ORDER BY c.created DESC')->setParameter('wallet', $wallet)->getResult();
    }

    public function commitChanges()
    {
        $this->em->flush();
    }
}
