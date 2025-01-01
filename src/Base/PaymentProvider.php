<?php

namespace MLukman\SaasBundle\Base;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\Entity\CreditPurchase;
use MLukman\SaasBundle\Entity\Payment;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use UI\Exception\RuntimeException;

#[AutoconfigureTag('saas.payment.provider')]
abstract class PaymentProvider
{
    public function __construct(protected EntityManagerInterface $em)
    {
        
    }

    public function getPaymentByTransaction(string $transaction): ?Payment
    {
        try {
            return $this->em->createQuery('SELECT p FROM \MLukman\SaasBundle\Entity\Payment p WHERE p.transaction = :transaction')
                    ->setParameter('transaction', $transaction)
                    ->getSingleResult();
        } catch (NoResultException $ex) {
            return null;
        }
    }

    public function updatePaymentTransaction(string $transaction, int $status, ?string $statusMessage = null)
    {
        if (!($payment = $this->getPaymentByTransaction($transaction))) {
            throw new RuntimeException("Payment transaction not found");
        }
        $payment->setStatus($status);
        $payment->setStatusMessage($statusMessage);
        $payment->setUpdated(new DateTime());
        if ($status == 1) { // transaction completed
            switch (get_class($payment)) {
                case CreditPurchase::class:
                    $this->prepaidManager->creditPurchaseComplete($payment);
                    $this->prepaidManager->commitChanges();
                    break;
            }
        }
        $this->commitChanges();
    }

    public function commitChanges()
    {
        $this->em->flush();
    }

    abstract public function getId(): string;
    abstract public function initialize(array $paymentConfigParams): bool;
    abstract public function initiateCreditPurchaseTransaction(TopupConfig $topup, int $quantity, string $redirectBackUrl): ?PaymentTransactionInterface;
    abstract public function retrieveCreditPurchaseTransaction(string $reference): ?PaymentTransactionInterface;
    abstract public function generateRedirectForTransaction(PaymentTransactionInterface $transaction): Response;
    abstract public function handleWebhook(Request $request);
    abstract public function isTopupPurchasable(TopupConfig $topup): bool;
}
