<?php

namespace MLukman\SaasBundle\Event;

use MLukman\SaasBundle\Entity\CreditPurchase;
use MLukman\SaasBundle\Entity\CreditUsage;
use Symfony\Contracts\EventDispatcher\Event;

class CreditBalanceEvent extends Event
{
    public function __construct(protected string $wallet, protected int $balanceBefore, protected int $balanceAfter, protected CreditPurchase|CreditUsage|null $source)
    {

    }

    public function getWallet(): string
    {
        return $this->wallet;
    }

    public function getBalanceBefore(): int
    {
        return $this->balanceBefore;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function getSource(): CreditPurchase|CreditUsage|null
    {
        return $this->source;
    }
}
