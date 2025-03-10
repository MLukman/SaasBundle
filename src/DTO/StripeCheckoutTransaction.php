<?php

namespace MLukman\SaasBundle\DTO;

use MLukman\SaasBundle\Base\PaymentTransactionInterface;
use Stripe\Checkout\Session;

class StripeCheckoutTransaction implements PaymentTransactionInterface
{
    public function __construct(protected Session $checkoutObject)
    {
        
    }

    public function getReference(): string
    {
        return $this->checkoutObject->id;
    }

    public function getData(): array
    {
        return $this->checkoutObject->toArray();
    }

    public function getCurrency(): string
    {
        return $this->checkoutObject->currency;
    }

    public function getAmount(): int
    {
        return $this->checkoutObject->amount_total;
    }

    public function getUrl(): string
    {
        return $this->checkoutObject->url;
    }
}
