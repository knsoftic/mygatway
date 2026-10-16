<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Append-only audit trail for sensitive actions (fees, approvals, keys, settings...).
 */
class AuditLogger
{
    /** Attribute names that must never be written to the audit trail. */
    private const REDACT = ['password', 'secret', 'secret_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    public function __construct(private readonly ?Request $request = null) {}

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        array $old = [],
        array $new = [],
        array $context = [],
        ?User $user = null,
        ?int $merchantId = null,
        string $actorType = 'user',
    ): AuditLog {
        $user ??= auth()->user();
        if ($user === null && $actorType === 'user') {
            $actorType = 'system';
        }

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'merchant_id' => $merchantId ?? ($auditable?->getAttribute('merchant_id')) ?? null,
            'actor_type' => $actorType,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $old === [] ? null : $this->redact($old),
            'new_values' => $new === [] ? null : $this->redact($new),
            'context' => $context === [] ? null : $context,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request ? mb_substr((string) $this->request->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    /** Log a model update with only the attributes that changed. */
    public function logChanges(string $action, Model $model, array $context = []): ?AuditLog
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if ($changes === []) {
            return null;
        }

        $old = Arr::only($model->getOriginal(), array_keys($changes));

        return $this->log($action, $model, $this->normalize($old), $this->normalize($changes), $context);
    }

    /** @param  array<string, mixed>  $values */
    private function redact(array $values): array
    {
        foreach (self::REDACT as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $this->normalize($values);
    }

    /** @param  array<string, mixed>  $values */
    private function normalize(array $values): array
    {
        return array_map(function ($v) {
            if ($v instanceof \BackedEnum) {
                return $v->value;
            }
            if ($v instanceof \DateTimeInterface) {
                return $v->format(DATE_ATOM);
            }

            return $v;
        }, $values);
    }
}
