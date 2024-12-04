<?php

namespace MLukman\SaasBundle\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Exception;
use MLukman\SaasBundle\Config\SaasConfig;
use MLukman\SaasBundle\Entity\CreditPurchase;
use MLukman\SaasBundle\Entity\Payment;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use MLukman\SaasBundle\Payment\ProviderInterface;
use MLukman\SymfonyConfigOOP\ConfigUtil;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class SaasUtil
{
    protected SaasConfig $configuration;
    protected ?ProviderInterface $paymentProvider = null;
    protected bool $isReady = false;

    public function __construct(
        protected EntityManagerInterface $em,
        protected SaasPrepaidManager $prepaidManager,
        #[AutowireIterator('saas.payment.provider')] protected iterable $paymentProviders
    ) {

    }

    public function getConfiguration(): ?SaasConfig
    {
        return $this->configuration ?? null;
    }

    public function setConfiguration(SaasConfig|array $configuration): void
    {
        if (is_array($configuration)) {
            if (empty($configuration)) {
                return;
            }
            $configuration = ConfigUtil::process($configuration, SaasConfig::class);
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
        if (($prepaidConfig = $configuration->getPrepaid())) {
            $this->prepaidManager->setConfiguration($configuration->getPrepaid());
            $this->prepaidManager->setPaymentProvider($this->paymentProvider);
        }
        $this->isReady = true;
    }

    protected function checkReadiness(): void
    {
        if (!$this->isReady) {
            throw new InvalidSaasConfigurationException("SaasBundle is not ready. System admin needs to check configuration.");
        }
    }

    public function getPrepaidManager(): SaasPrepaidManager
    {
        $this->checkReadiness();
        return $this->prepaidManager;
    }

    public function getPaymentProvider(): ?ProviderInterface
    {
        $this->checkReadiness();
        return $this->paymentProvider;
    }

    public function getPaymentByTransaction(string $transaction): ?Payment
    {
        $this->checkReadiness();
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
        $this->checkReadiness();
        if (!($payment = $this->getPaymentByTransaction($transaction))) {
            throw new RuntimeException("Payment transaction not found");
        }
        $payment->setStatus($status);
        $payment->setStatusMessage($statusMessage);
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
        $this->checkReadiness();
        $this->em->flush();
    }
}
