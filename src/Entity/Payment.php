<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;

class Payment
{

    protected int $id;
    protected string $provider;
    protected string $transaction;
    protected int $status = 0;
    protected DateTime $created;
    protected ?DateTime $updated;

    public function __construct(string $provider, string $transaction)
    {
        $this->provider = $provider;
        $this->transaction = $transaction;
        $this->created = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getTransaction(): string
    {
        return $this->transaction;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getUpdated(): ?DateTime
    {
        return $this->updated;
    }

    public function setUpdated(?DateTime $updated): void
    {
        $this->updated = $updated;
    }
}
