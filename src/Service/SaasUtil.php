<?php

namespace MLukman\SaasBundle\Service;

use MLukman\SaasBundle\Config\SaasConfig;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use MLukman\SaasBundle\Base\PaymentProvider;
use MLukman\SymfonyConfigOOP\ConfigUtil;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class SaasUtil
{
    protected SaasConfig $configuration;
    protected ?PaymentProvider $paymentProvider = null;
    protected bool $isReady = false;

    public function __construct(
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
            if ($paymentProvider->initialize($configuration->getPayment()->getParams())) {
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

    public function getPaymentProvider(): ?PaymentProvider
    {
        $this->checkReadiness();
        return $this->paymentProvider;
    }
}
