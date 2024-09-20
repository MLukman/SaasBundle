<?php

namespace MLukman\SaasBundle\Config;

use MLukman\SymfonyConfigOOP\Attribute\ArrayConfig;
use MLukman\SymfonyConfigOOP\Attribute\ScalarConfig;

class PaymentConfig
{

    #[ScalarConfig(isRequired: true)]
    private string $driver;

    #[ArrayConfig(info: "Parameters that are specific to respective payment provider")]
    private array $params;

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
