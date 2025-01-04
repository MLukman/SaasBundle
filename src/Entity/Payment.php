<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;
use MLukman\SaasBundle\Base\PaymentTransactionInterface;

class Payment
{
    protected int $id;
    protected string $provider;
    protected string $transactionId;
    protected array $transactionData;
    protected string $currency;
    protected int $amount;
    protected int $status = 0;
    protected ?string $statusMessage = null;
    protected DateTime $created;
    protected ?DateTime $updated;

    public function __construct(string $provider, PaymentTransactionInterface $transaction)
    {
        $this->provider = $provider;
        $this->created = new \DateTime();
        $this->updateTransaction($transaction);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getTransactionData(): array
    {
        return $this->transactionData;
    }

    public function setTransactionData(array $transactionData): void
    {
        $this->transactionData = $transactionData;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getStatusMessage(): ?string
    {
        return $this->statusMessage;
    }

    public function setStatusMessage(?string $statusMessage): void
    {
        $this->statusMessage = $statusMessage;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getUpdated(): ?DateTime
    {
        return $this->updated;
    }

    public function updated(): void
    {
        $this->updated = new \DateTime();
    }

    public function updateTransaction(PaymentTransactionInterface $transaction): void
    {
        $this->transactionId = $transaction->getReference();
        $this->transactionData = $transaction->getData();
        $this->currency = $transaction->getCurrency();
        $this->amount = $transaction->getAmount();
    }
}
