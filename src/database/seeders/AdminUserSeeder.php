<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            ['name' => '管理者1', 'email' => 'admin1@example.com', 'role' => 'admin', 'password' => 'password'],
            ['name' => '管理者2', 'email' => 'admin2@example.com', 'role' => 'admin', 'password' => 'password'],
        ];

        foreach ($admins as $a) {
            User::updateOrCreate(
                ['email' => $a['email']],
                [
                    'name'              => $a['name'],
                    'role'              => $a['role'],
                    'password'          => Hash::make($a['password']),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
