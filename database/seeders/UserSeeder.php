<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'username' => 'admin2',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('malakaji'),
            'role' => 'admin',
            'storageUsed' => 0,
            'storageLimit' => 5368709120, // 5GB
            'isActive' => true,
            'apiKey' => Str::random(64),
            'lastLoginAt' => null,
            'adsDisabled' => false,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        // Create first regular user
        User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'username' => 'user1',
            'email' => 'user1@example.com',
            'password' => Hash::make('malakaji'),
            'role' => 'user',
            'storageUsed' => 0,
            'storageLimit' => 5368709120, // 5GB
            'isActive' => true,
            'apiKey' => Str::random(64),
            'lastLoginAt' => null,
            'adsDisabled' => false,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        // Create second regular user
        User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'username' => 'user2',
            'email' => 'user2@example.com',
            'password' => Hash::make('malakaji'),
            'role' => 'user',
            'storageUsed' => 0,
            'storageLimit' => 5368709120, // 5GB
            'isActive' => true,
            'apiKey' => Str::random(64),
            'lastLoginAt' => null,
            'adsDisabled' => false,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);
    }
}
