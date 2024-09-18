<?php

namespace MLukman\SaasBundle\Entity;

class CreditUsagePart
{

    protected int $id;
    protected int $points;
    protected Credit $credit;
    protected CreditUsage $usage;

    public function __construct(Credit $credit, CreditUsage $usage, int $points)
    {
        $this->credit = $credit;
        $this->usage = $usage;
        $this->points = $points;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getCredit(): Credit
    {
        return $this->credit;
    }

    public function getUsage(): CreditUsage
    {
        return $this->usage;
    }
}
