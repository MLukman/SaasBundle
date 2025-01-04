<?php

namespace MLukman\SaasBundle\Entity;

use MLukman\SaasBundle\Base\PaymentTransactionInterface;

class PayoutPayment extends Payment
{
    public function __construct(string $provider, PaymentTransactionInterface $transaction, protected PayoutAccount $account)
    {
        parent::__construct($provider, $transaction);
    }
}
