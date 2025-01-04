<?php

namespace MLukman\SaasBundle\Base;

use Serializable;

interface PaymentTransactionInterface
{
    public function getReference(): string;
    public function getData(): array;
    public function getCurrency(): string;
    public function getAmount(): int;
    public function getUrl(): ?string;
}
