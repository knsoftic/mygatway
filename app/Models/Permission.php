<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'label', 'group'])]
class Permission extends Model
{
    /**
     * Every admin capability. Seeded by RolesAndPermissionsSeeder.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const ALL = [
        'merchants.view' => ['View merchants', 'Merchants'],
        'merchants.manage' => ['Change merchant status and limits', 'Merchants'],
        'merchants.verify' => ['Review merchant verification', 'Merchants'],
        'payments.view' => ['View payments', 'Transactions'],
        'payments.reconcile' => ['Re-verify and reconcile payments', 'Transactions'],
        'payments.refund' => ['Issue refunds', 'Transactions'],
        'payouts.view' => ['View payouts', 'Transactions'],
        'payouts.approve' => ['Approve or reject payouts', 'Transactions'],
        'fees.view' => ['View fee rules', 'Fees'],
        'fees.manage' => ['Create and edit fee rules', 'Fees'],
        'providers.manage' => ['Configure providers and currencies', 'Providers'],
        'webhooks.view' => ['View webhook logs', 'Webhooks'],
        'webhooks.retry' => ['Retry webhook deliveries', 'Webhooks'],
        'audit.view' => ['View audit and API logs', 'Security'],
        'risk.manage' => ['Review risk events', 'Security'],
        'reports.export' => ['Export CSV reports', 'Reports'],
        'settings.manage' => ['Change system settings', 'System'],
        'users.manage' => ['Manage admin users and roles', 'System'],
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
