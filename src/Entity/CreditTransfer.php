<?php

namespace MLukman\SaasBundle\Entity;

class CreditTransfer extends CreditUsage
{
    protected Credit $destination;

    public function setDestination(Credit $destination): void
    {
        $this->destination = $destination;
    }

    public function getDestination(): Credit
    {
        return $this->destination;
    }
}
