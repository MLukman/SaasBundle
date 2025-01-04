<?php

namespace MLukman\SaasBundle\Entity;

use MLukman\SaasBundle\Base\PaymentTransactionInterface;

class CreditPurchase extends Payment
{
    protected ?string $wallet;
    protected ?string $topup;
    protected ?int $quantity;
    protected ?Credit $credit;

    public function __construct(string $provider, PaymentTransactionInterface $transaction, string $wallet, string $topup, int $quantity = 1)
    {
        parent::__construct($provider, $transaction);
        $this->wallet = $wallet;
        $this->topup = $topup;
        $this->quantity = $quantity;
    }

    public function getWallet(): ?string
    {
        return $this->wallet;
    }

    public function getTopup(): ?string
    {
        return $this->topup;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
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
