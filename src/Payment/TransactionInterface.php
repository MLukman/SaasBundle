<?php

namespace MLukman\SaasBundle\Payment;

use Serializable;

interface TransactionInterface extends Serializable
{

    public function getReference(): string;
}
