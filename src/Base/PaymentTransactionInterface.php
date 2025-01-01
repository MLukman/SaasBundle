<?php

namespace MLukman\SaasBundle\Base;

use Serializable;

interface PaymentTransactionInterface extends Serializable
{

    public function getReference(): string;
}
