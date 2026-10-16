<?php

namespace App\Integrations\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A provider call failed. `safeMessage` is suitable for merchants; the raw
 * provider response is kept in `context` for logs only.
 */
class ProviderException extends RuntimeException
{
    /** @param  array<string, mixed>  $context */
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $errorCode = 'provider_error',
        public readonly string $safeMessage = 'The payment provider could not process the request.',
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
