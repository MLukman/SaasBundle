<?php

namespace MLukman\SaasBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class CreditBalanceEvent extends Event
{
    public function __construct(protected string $wallet, protected int $balanceBefore, protected int $balanceAfter, protected mixed $source)
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

    public function getSource(): mixed
    {
        return $this->source;
    }
}
