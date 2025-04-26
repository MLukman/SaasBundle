<?php

namespace MLukman\SaasBundle\Base;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\Entity\CreditPurchase;
use MLukman\SaasBundle\Entity\Payment;
use MLukman\SaasBundle\Entity\PayoutAccount;
use MLukman\SaasBundle\Entity\PayoutPayment;
use MLukman\SaasBundle\Service\SaasPrepaidManager;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use UI\Exception\RuntimeException;

#[AutoconfigureTag('saas.payment.provider')]
abstract class PaymentProvider
{
    public function __construct(protected EntityManagerInterface $em, protected SaasPrepaidManager $prepaidManager)
    {
        
    }

    public function getPaymentByTransactionId(string $transactionId): ?Payment
    {
        try {
            return $this->em->createQuery('SELECT p FROM \MLukman\SaasBundle\Entity\Payment p WHERE p.transactionId = :transaction')
                    ->setParameter('transaction', $transactionId)
                    ->getSingleResult();
        } catch (NoResultException $ex) {
            return null;
        }
    }

    public function updatePaymentTransaction(string $transactionId, array $transactionData, string $currency, int $amount, int $status, ?string $statusMessage = null)
    {
        if (!($payment = $this->getPaymentByTransactionId($transactionId))) {
            throw new RuntimeException("Payment transaction not found");
        }
        $payment->setTransactionData($transactionData);
        $payment->setCurrency($currency);
        $payment->setAmount($amount);
        $payment->setStatus($status);
        $payment->setStatusMessage($statusMessage);
        $this->commitChanges();
        if ($status == 1) { // transaction completed
            switch (get_class($payment)) {
                case CreditPurchase::class:
                    $this->prepaidManager->creditPurchaseComplete($payment);
                    $this->prepaidManager->commitChanges();
                    break;
            }
        }
    }

    public function getPayoutAccount(string $id): PayoutAccount
    {
        try {
            $account = $this->em->createQuery('SELECT p FROM \MLukman\SaasBundle\Entity\PayoutAccount p WHERE p.id = :id')
                ->setParameter('id', $id)
                ->getSingleResult();
            if (!$account->isReady() && ($accountData = $account->getData()) && $this->checkPayoutAccountReadiness($accountData)) {
                $account->setReady(true);
                $account->setData($accountData);
                $this->commitChanges();
            }
            return $account;
        } catch (NoResultException $ex) {
            $data = $this->createPayoutAccount();
            $pa = new PayoutAccount($id, $data);
            $this->em->persist($pa);
            $this->commitChanges();
            return $pa;
        }
    }

    public function getPayoutPaymentRecords(PayoutAccount $account, ?DateTime $from = null, ?DateTime $until = null): array
    {
        $qb = $this->em->createQueryBuilder()->select('p')->from(PayoutPayment::class, 'p')->where('p.account = :account')->setParameter('account', $account);
        if ($from) {
            $qb->andWhere('p.created >= :from')->setParameter('from', $from);
        }
        if ($until) {
            $qb->andWhere('p.created <= :until')->setParameter('until', $until);
        }
        $qb->addOrderBy('created', 'ASC');
        return $qb->getQuery()->getArrayResult();
    }

    public function getPayoutPaymentSum(PayoutAccount $account, ?DateTime $from = null, ?DateTime $until = null): int
    {
        $qb = $this->em->createQueryBuilder()->select('SUM(p.amount)')->from(PayoutPayment::class, 'p')->where('p.account = :account')->setParameter('account', $account);
        if ($from) {
            $qb->andWhere('p.created >= :from')->setParameter('from', $from);
        }
        if ($until) {
            $qb->andWhere('p.created <= :until')->setParameter('until', $until);
        }
        try {
            return intval($qb->getQuery()->getSingleScalarResult());
        } catch (NoResultException $ex) {
            return 0;
        }
    }

    public function getRedirectToPayoutAccountSetup(PayoutAccount $account, string $returnUrl, string $retryUrl): ?RedirectResponse
    {
        return $this->generateRedirectForPayoutAccountSetup($account->getData(), $returnUrl, $retryUrl);
    }

    public function commitChanges()
    {
        $this->em->flush();
    }

    abstract public function getId(): string;
    abstract public function initialize(array $paymentConfigParams): bool;
    abstract public function initiateCreditPurchaseTransaction(TopupConfig $topup, int $quantity, string $redirectBackUrl): ?PaymentTransactionInterface;
    abstract public function retrieveCreditPurchaseTransaction(string $reference): ?PaymentTransactionInterface;
    abstract public function generateRedirectForTransaction(PaymentTransactionInterface $transaction): ?Response;
    abstract public function handleWebhook(Request $request);
    abstract public function isTopupPurchasable(TopupConfig $topup): bool;
    abstract public function performPayoutToPayoutAccount(PayoutAccount $account, string $currency, int $amount): ?PayoutPayment;
    abstract protected function createPayoutAccount(): array;
    abstract protected function checkPayoutAccountReadiness(array &$accountData): bool;
    abstract protected function generateRedirectForPayoutAccountSetup(array $accountData, string $returnUrl, string $retryUrl): ?RedirectResponse;
}
