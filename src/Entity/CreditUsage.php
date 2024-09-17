<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class CreditUsage
{

    protected int $id;
    protected string $wallet;
    protected int $points;
    protected string $type;
    protected string $reference;
    protected DateTime $created;
    protected Collection $sources;

    public function __construct(string $wallet, int $points, string $type, string $reference)
    {
        $this->wallet = $wallet;
        $this->points = $points;
        $this->type = $type;
        $this->reference = $reference;
        $this->created = new \DateTime();
        $this->sources = new ArrayCollection();
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

    public function getType(): string
    {
        return $this->type;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getSources(): Collection
    {
        return $this->sources;
    }
}
