<?php

namespace App\Integrations\Exceptions;

class ProviderNotConfiguredException extends ProviderException
{
    public function __construct(string $provider, string $missing)
    {
        parent::__construct(
            "{$provider} is not configured: missing {$missing}.",
            provider: $provider,
            errorCode: 'provider_unavailable',
            safeMessage: 'This payment provider is temporarily unavailable.',
        );
    }
}
