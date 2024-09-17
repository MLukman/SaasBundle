<?php

namespace MLukman\SaasBundle\Config;

use MLukman\SymfonyConfigHelper\Attribute\ArrayConfig;
use MLukman\SymfonyConfigHelper\Attribute\IntegerConfig;
use MLukman\SymfonyConfigHelper\Attribute\ScalarConfig;

class TopupConfig
{

    public string $_id;

    #[ScalarConfig(info: "The user-friendly name of this topup.", example: "+100 Credits", isRequired: true)]
    private string $name;

    #[IntegerConfig(info: "The amount of credits to increase upon purchasing this topup.", example: 100, isRequired: true)]
    private int $credit;

    #[ArrayConfig(info: "A map of currencies to prices that are chargeable for this topup. Optional for display by application only. Actual prices should be configured in the payment provider.", example: ['USD' => 3, 'JPY' => 300])]
    private array $prices;

    #[ScalarConfig(info: "The duration the credits are valid for consumption. Omit for no expiry.", example: "3 months")]
    private string $validity;

    #[IntegerConfig(info: "Limit how many times this topup can be purchased by each account. Omit or 0 for unlimited.", defaultValue: 0)]
    private int $limit;

    #[ArrayConfig(info: "Arbitrary parameters needed by payment provider to process payment for this topup.", example: ['productId' => 'prod711', 'priceId' => 'price9121'])]
    private array $paymentParams;

    public function getName(): string
    {
        return $this->name;
    }

    public function getCredit(): int
    {
        return $this->credit;
    }

    public function getPrices(): array
    {
        return $this->prices;
    }

    public function getValidity(): string
    {
        return $this->validity;
    }

    public function getLimit(): int
    {
        return $this->limit ?? 0;
    }

    public function getPaymentParams(): array
    {
        return $this->paymentParams;
    }
}
