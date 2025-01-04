<?php

namespace MLukman\SaasBundle\DTO;

use MLukman\SaasBundle\Base\PaymentTransactionInterface;
use Stripe\Transfer;

class StripeTransferTransaction implements PaymentTransactionInterface
{
    public function __construct(protected Transfer $transferObject)
    {
        
    }

    public function getReference(): string
    {
        return $this->transferObject->id ?? 'N/A';
    }

    public function getData(): array
    {
        return $this->transferObject->toArray();
    }

    public function getCurrency(): string
    {
        return $this->transferObject->currency ?? 'N/A';
    }

    public function getAmount(): int
    {
        return $this->transferObject->amount ?? 'N/A';
    }

    public function getUrl(): ?string
    {
        return null;
    }
}
