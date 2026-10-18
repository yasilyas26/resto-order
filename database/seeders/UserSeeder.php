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
        $accounts = [
            ['name' => 'Admin Resto', 'email' => 'admin@resto.test', 'role' => Role::Admin],
            ['name' => 'Staff Resto', 'email' => 'staff@resto.test', 'role' => Role::Staff],
        ];

        foreach ($accounts as $account) {
            $user = User::withTrashed()->firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'role' => $account['role'],
                'password' => 'Password123',
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
