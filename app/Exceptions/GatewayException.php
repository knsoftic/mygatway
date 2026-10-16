<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Business-rule failure with a stable, merchant-facing error code.
 * Rendered as a structured JSON error by the API exception handler.
 */
class GatewayException extends RuntimeException
{
    /** @param  array<string, mixed>  $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function insufficientFunds(string $currency, string $available, string $required): self
    {
        return new self('insufficient_balance', "Insufficient {$currency} balance for this operation.", 422, [
            'currency' => $currency,
            'available' => $available,
            'required' => $required,
        ]);
    }

    public static function duplicateReference(string $reference, string $existingId): self
    {
        return new self('duplicate_reference', "The reference [{$reference}] has already been used.", 409, [
            'reference' => $reference,
            'existing_id' => $existingId,
        ]);
    }

    public static function notAllowed(string $message, string $code = 'account_not_allowed'): self
    {
        return new self($code, $message, 403);
    }

    public static function unsupported(string $message, array $details = []): self
    {
        return new self('unsupported_operation', $message, 422, $details);
    }
}
