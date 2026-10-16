<?php

namespace App\Integrations\Data;

final readonly class HealthCheckResult
{
    public const HEALTHY = 'healthy';

    public const DEGRADED = 'degraded';

    public const DOWN = 'down';

    public const UNCONFIGURED = 'unconfigured';

    public function __construct(
        public string $status,
        public string $message,
        public ?int $latencyMs = null,
    ) {}
}
