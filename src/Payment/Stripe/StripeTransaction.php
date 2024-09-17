<?php

namespace MLukman\SaasBundle\Payment\Stripe;

use MLukman\SaasBundle\Payment\TransactionInterface;

class StripeTransaction implements TransactionInterface
{

    public string $reference;
    public string $redirect;

    public function getReference(): string
    {
        return $this->reference;
    }

    public function serialize(): ?string
    {
        return json_encode(['reference' => $this->reference, 'redirect' => $this->redirect]);
    }

    public function unserialize(string $data): void
    {
        $unserialized = \json_encode($data, true);
        $this->reference = $unserialized['reference'] ?? null;
        $this->redirect = $unserialized['redirect'] ?? null;
    }
}
