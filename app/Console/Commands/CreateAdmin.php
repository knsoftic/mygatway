<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'gateway:create-admin {email} {--name=Administrator} {--role=super_admin}';

    protected $description = 'Create (or promote) an administrator account; the password is prompted, never passed as an argument';

    public function handle(AuditLogger $audit): int
    {
        $email = (string) $this->argument('email');
        $role = Role::query()->where('name', $this->option('role'))->first();
        if (! $role) {
            $this->error('Unknown role. Run the database seeders first.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $password = (string) $this->secret('Password (min 12 chars, mixed case, number, symbol)');
            $validator = Validator::make(['email' => $email, 'password' => $password], [
                'email' => ['required', 'email:rfc'],
                'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
            ]);
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $user = User::query()->create(['name' => $this->option('name'), 'email' => $email, 'password' => Hash::make($password)]);
        }

        $user->forceFill(['is_admin' => true, 'status' => 'active', 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->roles()->syncWithoutDetaching([$role->id]);
        $audit->log('admin.created', $user, new: ['role' => $role->name], actorType: 'system');

        $this->info("{$email} is now an administrator ({$role->name}). Two-factor authentication is required on first login.");

        return self::SUCCESS;
    }
}
