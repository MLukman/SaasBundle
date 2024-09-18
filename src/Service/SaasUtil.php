<?php

namespace MLukman\SaasBundle\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use MLukman\SaasBundle\Config\SaasConfig;
use MLukman\SaasBundle\Entity\CreditPurchase;
use MLukman\SaasBundle\Entity\Payment;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use MLukman\SaasBundle\Payment\ProviderInterface;
use MLukman\SymfonyConfigHelper\ConfigProcessor;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SaasUtil
{

    protected SaasConfig $configuration;
    protected ?ProviderInterface $paymentProvider = null;

    public function __construct(
            protected EntityManagerInterface $em,
            protected SaasPrepaidManager $prepaidManager,
            #[AutowireIterator('saas.payment.provider')] protected iterable $paymentProviders)
    {
        
    }

    public function getConfiguration(): SaasConfig
    {
        return $this->configuration;
    }

    public function setConfiguration(SaasConfig|array $configuration): void
    {
        if (is_array($configuration)) {
            $configuration = ConfigProcessor::process($configuration, SaasConfig::class);
        }
        $this->configuration = $configuration;
        foreach ($this->paymentProviders as $paymentProvider) {
            if ($paymentProvider->getId() != $configuration->getPayment()->getDriver()) {
                continue;
            }
            if ($paymentProvider->initialize($this, $configuration->getPayment()->getParams())) {
                $this->paymentProvider = $paymentProvider;
            } else {
                throw new InvalidSaasConfigurationException("Failed to initialize payment provider " . $configuration->getPayment()->driver);
            }
        }
        if (!$this->paymentProvider) {
            throw new InvalidSaasConfigurationException("No valid payment provider found");
        }
        $this->prepaidManager->setConfiguration($configuration->getPrepaid());
        $this->prepaidManager->setPaymentProvider($this->paymentProvider);
    }

    public function getPrepaidManager(): SaasPrepaidManager
    {
        return $this->prepaidManager;
    }

    public function getPaymentProvider(): ?ProviderInterface
    {
        return $this->paymentProvider;
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

    public function updatePaymentTransaction(string $transaction, int $status)
    {
        if (!($payment = $this->getPaymentByTransaction($transaction))) {
            throw new RuntimeException("Payment transaction not found");
        }
        $payment->setStatus($status);
        $payment->setUpdated(new DateTime());
        if ($status == 1) { // transaction completed
            switch (get_class($payment)) {
                case CreditPurchase::class:
                    $this->prepaidManager->completeTopupPayment($payment);
                    break;
            }
        }
        $this->commitChanges();
    }

    public function commitChanges()
    {
        $this->em->flush();
    }
}
