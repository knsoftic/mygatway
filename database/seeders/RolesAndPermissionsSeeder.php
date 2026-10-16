<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permission::ALL as $name => [$label, $group]) {
            Permission::query()->updateOrCreate(['name' => $name], ['label' => $label, 'group' => $group]);
        }

        $roles = [
            'super_admin' => ['Super administrator', 'Full access to every admin feature.', array_keys(Permission::ALL)],
            'finance' => ['Finance', 'Payouts, fees, reconciliation and reports.', [
                'merchants.view', 'payments.view', 'payments.reconcile', 'payments.refund', 'payouts.view', 'payouts.approve',
                'fees.view', 'fees.manage', 'reports.export', 'webhooks.view',
            ]],
            'compliance' => ['Compliance', 'Merchant verification, restrictions and risk.', [
                'merchants.view', 'merchants.manage', 'merchants.verify', 'payments.view', 'payouts.view', 'risk.manage', 'audit.view',
            ]],
            'support' => ['Support', 'Read-only access for customer support.', [
                'merchants.view', 'payments.view', 'payouts.view', 'webhooks.view', 'webhooks.retry', 'fees.view',
            ]],
        ];

        foreach ($roles as $name => [$label, $description, $permissions]) {
            $role = Role::query()->updateOrCreate(['name' => $name], ['label' => $label, 'description' => $description]);
            $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));
        }
    }
}
