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
    protected ?string $topup;
    protected DateTime $created;
    protected ?DateTime $expiry;
    protected Collection $usages;

    public function __construct(string $wallet, int $points, ?string $topup = null)
    {
        $this->wallet = $wallet;
        $this->points = $points;
        $this->balance = $points;
        $this->topup = $topup;
        $this->usages = new ArrayCollection();
        $this->created = new \DateTime();
    }

    public function recalculateBalance()
    {
        $bal = $this->points;
        foreach ($this->usages as $usage) {
            /* @var $usage CreditUsageSource */
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

    public function getTopup(): ?string
    {
        return $this->topup;
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

    public function getUsages(): Collection
    {
        return $this->usages;
    }
}
