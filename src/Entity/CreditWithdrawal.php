<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;

class CreditWithdrawal
{
    protected int $id;
    protected CreditUsage $source;
    protected ?PayoutPayment $destination;
    protected DateTime $created;

    public function __construct(CreditUsage $source, ?PayoutPayment $destination = null)
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->created = new DateTime();
    }

    public function getSource(): CreditUsage
    {
        return $this->source;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getDestination(): ?PayoutPayment
    {
        return $this->destination;
    }

    public function setDestination(?PayoutPayment $destination): void
    {
        $this->destination = $destination;
    }
}
