<?php

namespace MLukman\SaasBundle\DTO;

use MLukman\SaasBundle\Base\PaymentTransactionInterface;

class HitpayCheckoutTransaction implements PaymentTransactionInterface
{
    public function __construct(protected array $checkoutData) {}

    public function getReference(): string
    {
        return $this->checkoutData['id'];
    }

    public function getData(): array
    {
        return $this->checkoutData;
    }

    public function getCurrency(): string
    {
        return $this->checkoutData['currency'];
    }

    public function getAmount(): int
    {
        return $this->checkoutData['amount'];
    }

    public function getUrl(): string
    {
        return $this->checkoutData['url'];
    }
}
