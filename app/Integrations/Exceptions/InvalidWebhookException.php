<?php

namespace App\Integrations\Exceptions;

use RuntimeException;

class InvalidWebhookException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 401)
    {
        parent::__construct($message);
    }
}
