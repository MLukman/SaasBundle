<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Exception;

class CreditUsage
{
    protected int $id;
    protected string $wallet;
    protected int $points;
    protected string $usageType;
    protected string $usageReference;
    protected DateTime $created;
    protected Collection $creditParts;

    public function __construct(string $wallet, int $points, string $usageType, string $usageReference)
    {
        $this->wallet = $wallet;
        $this->points = $points;
        $this->usageType = $usageType;
        $this->usageReference = $usageReference;
        $this->created = new \DateTime();
        $this->creditParts = new ArrayCollection();
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

    public function getUsageType(): string
    {
        return $this->usageType;
    }

    public function getUsageReference(): string
    {
        return $this->usageReference;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getCreditParts(): Collection
    {
        return $this->creditParts;
    }

    public function prePersist(PrePersistEventArgs $evt): void
    {
        if ($this->creditParts->isEmpty()) {
            throw new Exception("Implementation Error: CreditUsage is invalid");
        }
    }
}
