<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ChangeAdmin extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find user with email admin@example.com and update their password
        $user = User::where('email', 'admin@example.com')->first();

        if ($user) {
            $user->update([
                'password' => Hash::make('malakaji')
            ]);

            echo "Password for user with email admin@example.com has been updated.\n";
        } else {
            echo "User with email admin@example.com not found.\n";
        }
    }
}
