<?php

namespace MLukman\SaasBundle\Entity;

use DateTime;

class CreditWithdrawal extends CreditUsage
{
    protected ?PayoutPayment $payment;

    public function getPayment(): ?PayoutPayment
    {
        return $this->payment;
    }

    public function setPayment(?PayoutPayment $payment): void
    {
        $this->payment = $payment;
    }
}
