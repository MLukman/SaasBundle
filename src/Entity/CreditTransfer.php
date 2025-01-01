<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;

class CreditTransfer
{
    protected int $id;
    protected CreditUsage $source;
    protected Credit $destination;
    protected DateTime $created;

    public function __construct(CreditUsage $source, Credit $destination)
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->created = new DateTime();
    }
}
