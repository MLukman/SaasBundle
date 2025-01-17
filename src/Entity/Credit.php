<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Credit
{
    protected int $id;
    protected string $wallet;
    protected int $points;
    protected int $balance;
    protected string $sourceType;
    protected string $sourceReference;
    protected DateTime $created;
    protected ?DateTime $expiry;
    protected Collection $usageParts;
    protected ?CreditPurchase $purchase;
    protected ?CreditTransfer $transfer;

    public function __construct(string $wallet, int $points, string $sourceType, string $sourceReference)
    {
        $this->wallet = $wallet;
        $this->points = $points;
        $this->balance = $points;
        $this->sourceType = $sourceType;
        $this->sourceReference = $sourceReference;
        $this->usageParts = new ArrayCollection();
        $this->created = new \DateTime();
    }

    public function recalculateBalance()
    {
        $bal = $this->points;
        foreach ($this->usageParts as $usage) {
            /* @var $usage CreditUsagePart */
            $bal -= $usage->getPoints();
        }
        $this->balance = $bal;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getWallet(): string
    {
        return $this->wallet;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getSourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getExpiry(): ?DateTime
    {
        return $this->expiry;
    }

    public function setExpiry(?DateTime $expiry): void
    {
        $this->expiry = $expiry;
    }

    public function getUsageParts(): Collection
    {
        return $this->usageParts;
    }

    public function getPurchase(): ?CreditPurchase
    {
        return $this->purchase;
    }

    public function getTransfer(): ?CreditTransfer
    {
        return $this->transfer;
    }
}
