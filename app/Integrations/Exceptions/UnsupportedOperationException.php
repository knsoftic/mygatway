<?php

namespace App\Integrations\Exceptions;

use RuntimeException;

/**
 * The provider's official API does not offer this operation. We never emulate it.
 */
class UnsupportedOperationException extends RuntimeException
{
    public function __construct(public readonly string $provider, public readonly string $operation, string $reason = '')
    {
        parent::__construct(trim("{$provider} does not support {$operation}. {$reason}"));
    }
}
