<?php

namespace MLukman\SaasBundle\Config;

use MLukman\SymfonyConfigOOP\Attribute\ArrayConfig;
use MLukman\SymfonyConfigOOP\Attribute\ObjectArrayConfig;

class PrepaidConfig
{

    #[ObjectArrayConfig(TopupConfig::class, isRequired: true)]
    private array $topups;

    #[ArrayConfig(type: "int", info: "Predefined mapping of credit usages to the points to deduct from the credit balance.", isRequired: true, example: ['downloadFile' => 10, 'convertFormat' => 20])]
    private array $usages;

    public function getTopups(): array
    {
        return $this->topups;
    }

    public function getUsages(): array
    {
        return $this->usages;
    }
}
