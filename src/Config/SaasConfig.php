<?php

namespace MLukman\SaasBundle\Config;

use MLukman\SymfonyConfigOOP\Attribute\ObjectConfig;

class SaasConfig
{
    #[ObjectConfig(info: "Configurations related to credit based mechanism.")]
    private PrepaidConfig $prepaid;

    #[ObjectConfig(info: "Configurations for the payment provider.")]
    private PaymentConfig $payment;

    public function getPrepaid(): ?PrepaidConfig
    {
        return $this->prepaid ?? null;
    }

    public function getPayment(): ?PaymentConfig
    {
        return $this->payment ?? null;
    }
}
