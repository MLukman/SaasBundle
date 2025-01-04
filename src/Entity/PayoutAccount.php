<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class PayoutAccount
{
    protected DateTime $created;
    protected ?DateTime $updated;
    protected Collection $payments;
    protected bool $ready = false;

    public function __construct(protected string $id, protected array $data)
    {
        $this->created = new \DateTime();
        $this->payments = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getUpdated(): ?DateTime
    {
        return $this->updated;
    }

    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function setReady(bool $ready): void
    {
        $this->ready = $ready;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setUpdated(?DateTime $updated): void
    {
        $this->updated = $updated;
    }
}
