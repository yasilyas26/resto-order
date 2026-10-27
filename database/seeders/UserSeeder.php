<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Enums\Role;
use App\Models\User;


class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun ini hanya untuk development. Di server asli, admin dibuat manual, bukan lewat seeder.
        if (! app()->environment('local')) {
            $this->command?->warn('UserSeeder dilewati: hanya berjalan di APP_ENV=local.');

            return;
        }

        $password = env('SEED_PASSWORD');

        if (blank($password)) {
            $this->command?->warn('UserSeeder dilewati: isi SEED_PASSWORD di .env dulu.');

            return;
        }

        $accounts = [
            ['name' => 'Admin Resto', 'email' => 'admin@resto.test', 'role' => Role::Admin],
            ['name' => 'Staff Resto', 'email' => 'staff@resto.test', 'role' => Role::Staff],
        ];

        foreach ($accounts as $account) {
            $user = User::withTrashed()->firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'role' => $account['role'],
                'password' => $password,
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
