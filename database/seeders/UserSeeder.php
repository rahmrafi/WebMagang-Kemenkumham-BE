<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create an admin user
        User::updateOrCreate([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@email.com',
            'password' => Hash::make('password@123'),
            'is_admin' => true,
        ]);

        User::updateOrCreate([
            'name' => 'Kemenkum',
            'username' => 'kemenkum',
            'email' => 'kemenkum@email.com',
            'password' => Hash::make('password@123'),
            'is_admin' => true,
        ]);

    }
}
