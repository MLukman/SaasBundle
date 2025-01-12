<?php

namespace MLukman\SaasBundle;

use RuntimeException;
use Throwable;

class InsufficientCreditBalanceException extends RuntimeException
{

    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
