<?php

namespace MLukman\SaasBundle\Entity;

class CreditPayment extends Payment
{

    protected ?string $wallet;
    protected ?string $topup;
    protected ?Credit $credit;

    public function __construct(string $provider, string $transaction, string $wallet, string $topup)
    {
        parent::__construct($provider, $transaction);
        $this->wallet = $wallet;
        $this->topup = $topup;
    }

    public function getWallet(): ?string
    {
        return $this->wallet;
    }

    public function getTopup(): ?string
    {
        return $this->topup;
    }

    public function getCredit(): ?Credit
    {
        return $this->credit;
    }

    public function setCredit(?Credit $credit): void
    {
        $this->credit = $credit;
    }
}
