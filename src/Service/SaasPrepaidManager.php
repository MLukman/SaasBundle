<?php

namespace MLukman\SaasBundle\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use MLukman\SaasBundle\Config\PrepaidConfig;
use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\Entity\Credit;
use MLukman\SaasBundle\Entity\CreditPurchase;
use MLukman\SaasBundle\Entity\CreditUsage;
use MLukman\SaasBundle\Entity\CreditUsagePart;
use MLukman\SaasBundle\Event\CreditBalanceEvent;
use MLukman\SaasBundle\InsufficientCreditBalanceException;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use MLukman\SaasBundle\Payment\ProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class SaasPrepaidManager
{
    protected PrepaidConfig $configuration;
    protected ?ProviderInterface $paymentProvider = null;
    public static string $creditClass = Credit::class;
    public static string $creditUsageClass = CreditUsage::class;
    public static string $creditUsagePartClass = CreditUsagePart::class;
    public static string $creditPurchaseClass = CreditPurchase::class;

    public function __construct(
        protected EntityManagerInterface $em,
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $dispatcher
    ) {

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
            if (!$this->paymentProvider->isTopupPurchasable($topup)) {
                continue;
            }
            if ($topup->getLimit() > 0 && $this->getTopupCount($wallet, $topupId) >= $topup->getLimit()) {
                continue;
            }
            $topups[$topupId] = $topup;
        }
        return $topups;
    }

    public function initiateCreditPurchase(string $wallet, TopupConfig|string $topup, string $redirectBackUrl): ?RedirectResponse
    {
        if (is_string($topup)) {
            $topup = $this->getTopupConfig($topup);
        }

        $trans = null;
        if (($existing = $this->getPaymentByWalletAndTopup($wallet, $topup->_id, 0))) {
            if (!($trans = $this->paymentProvider->retrieveCreditPurchaseTransaction($existing->getTransaction()))) {
                $existing->setStatus(-1);
                $existing->setUpdated(new DateTime());
            }
        }
        if (!$trans) {
            $trans = $this->paymentProvider->initiateCreditPurchaseTransaction($topup, $redirectBackUrl);
            $payment = new CreditPurchase($this->paymentProvider->getId(), $trans->getReference(), $wallet, $topup->_id);
            $this->em->persist($payment);
            $this->em->flush();
        }
        $this->requestStack->getSession()->set('saas.credit.payment.transaction', $trans->getReference());
        return $this->paymentProvider->generateRedirectForTransaction($trans);
    }

    public function getSessionLastCreditPurchase()
    {
        $transaction = $this->requestStack->getSession()->get('saas.credit.payment.transaction', '');
        try {
            return $this->em->createQuery('SELECT p FROM ' . static::$creditPurchaseClass . ' p WHERE p.transaction = :transaction')
                    ->setParameter('transaction', $transaction)
                    ->getSingleResult();
        } catch (NoResultException $ex) {
            return null;
        }
    }

    public function completeTopupPayment(CreditPurchase $payment)
    {
        $payment->setCredit($this->addCredit($payment->getWallet(), $payment->getTopup(), $payment));
    }

    public function getCreditBalance(string $wallet): int
    {
        return intval($this->em->createQuery('SELECT SUM(c.balance) FROM ' . static::$creditClass . ' c WHERE c.wallet = :wallet AND c.balance > 0 AND (c.expiry IS NULL OR c.expiry > CURRENT_TIMESTAMP())')
                ->setParameter('wallet', $wallet)
                ->getSingleScalarResult());
    }

    public function getTopupCount(string $wallet, ?string $topupId = null): int
    {
        $qb = $this->em->createQueryBuilder()->select('count(c.id)')->from(static::$creditClass, 'c')->where('c.wallet = :wallet')->setParameter('wallet', $wallet);
        if ($topupId) {
            $qb->andWhere('c.topup = :topup')->setParameter('topup', $topupId);
        }
        return intval($qb->getQuery()->getSingleScalarResult());
    }

    public function getPaymentByWalletAndTopup(string $wallet, string $topup, int $status = 0): ?CreditPurchase
    {
        try {
            return $this->em->createQuery('SELECT p FROM ' . static::$creditPurchaseClass . ' p WHERE p.wallet = :wallet AND p.topup = :topup AND p.status = :status ORDER BY p.created DESC')
                    ->setParameter('wallet', $wallet)
                    ->setParameter('topup', $topup)
                    ->setParameter('status', $status)
                    ->setMaxResults(1)
                    ->getSingleResult();
        } catch (NoResultException $ex) {
            return null;
        }
    }

    public function addCredit(string $wallet, TopupConfig|string $topup, ?CreditPurchase $purchase = null): Credit
    {
        if (is_string($topup)) {
            $topup = $this->getTopupConfig($topup);
        }
        $balanceBefore = $this->getCreditBalance($wallet);
        $credit = new static::$creditClass($wallet, $topup->getCredit(), $topup->_id);
        if (!empty($topup->getValidity())) {
            $expiry = (clone $credit->getCreated());
            $expiry->modify($topup->getValidity());
            $credit->setExpiry($expiry);
        }
        $this->em->persist($credit);
        $balanceAfter = $this->getCreditBalance($wallet);
        $this->dispatcher->dispatch(new CreditBalanceEvent($wallet, $balanceBefore, $balanceAfter, $purchase));
        return $credit;
    }

    public function addCreditUsage(string $wallet, string $type, string $reference): CreditUsage
    {
        if (is_null($point = $this->configuration->getUsages()[$type] ?? null)) {
            throw new InvalidSaasConfigurationException("There is no prepaid usage with type '$type'");
        }
        $balanceBefore = $this->getCreditBalance($wallet);
        // first query those with expiry dates, followed by those without ones
        $queries = [
            'SELECT c FROM ' . static::$creditClass . ' c WHERE c.wallet = :wallet AND c.balance > 0 AND c.expiry > CURRENT_TIMESTAMP() ORDER BY c.expiry ASC',
            'SELECT c FROM ' . static::$creditClass . ' c WHERE c.wallet = :wallet AND c.balance > 0 AND c.expiry IS NULL ORDER BY c.created ASC'
        ];
        $usage = new static::$creditUsageClass($wallet, $point, $type, $reference);
        for ($i = 0; $i < 2 && $point > 0; $i++) {
            $credits = $this->em->createQuery($queries[$i])->setParameter('wallet', $wallet)->getResult();
            $count_credits = count($credits);
            for ($j = 0; $j < $count_credits && $point > 0; $j++) {
                $balance = $credits[$j]->getBalance();
                if ($balance >= $point) {
                    $part = new static::$creditUsagePartClass($credits[$j], $usage, $point);
                    $balance -= $point;
                    $point = 0;
                } else {
                    $part = new static::$creditUsagePartClass($credits[$j], $usage, $balance);
                    $point -= $balance;
                    $balance = 0;
                }
                $credits[$j]->getUsageParts()->add($part);
                $credits[$j]->recalculateBalance();
            }
        }
        if ($point > 0) {
            throw new InsufficientCreditBalanceException();
        }
        $this->em->persist($usage);
        $balanceAfter = $this->getCreditBalance($wallet);
        $this->dispatcher->dispatch(new CreditBalanceEvent($wallet, $balanceBefore, $balanceAfter, $usage));
        return $usage;
    }

    public function getCreditRecords(string $wallet, bool $positiveBalanceOnly = false, bool $validOnly = false): array
    {
        $qb = $this->em->createQueryBuilder()->select('c')->from(static::$creditClass, 'c')
                ->where('c.wallet = :wallet')->setParameter('wallet', $wallet)->orderBy('c.created', 'DESC');
        if ($positiveBalanceOnly) {
            $qb->andWhere('c.balance > 0');
        }
        if ($validOnly) {
            $qb->andWhere('c.expiry IS NULL OR c.expiry > CURRENT_TIMESTAMP()');
        }
        return $qb->getQuery()->getResult();
    }

    public function getCreditUsageRecords(string $wallet, int $limit = 0): array
    {
        $qb = $this->em->createQueryBuilder()->select('c')->from(static::$creditUsageClass, 'c')
                ->where('c.wallet = :wallet')->setParameter('wallet', $wallet)->orderBy('c.created', 'DESC');
        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        return $qb->getQuery()->getResult();
    }

    public function getLastCreditUsageRecord(string $wallet): ?CreditUsage
    {
        return $this->getCreditUsageRecords($wallet, 1)[0] ?? null;
    }

    public function commitChanges()
    {
        $this->em->flush();
    }
}
