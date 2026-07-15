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
<<<<<<< HEAD
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

=======
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => 'admin@email.com',
                'password' => Hash::make('password@123'),
                'is_admin' => true,
            ]
        );
        User::updateOrCreate(
            ['username' => 'rafif_admin'],
            [
                'name' => 'Rafif Admin',
                'email' => 'rafif@email.com',
                'password' => Hash::make('rafif@123'),
                'is_admin' => true,
            ]
        );
        User::updateOrCreate(
            ['username' => 'ahmad_admin'],
            [
                'name' => 'Ahmad Admin',
                'email' => 'ahmad@email.com',
                'password' => Hash::make('ahmad@123'),
                'is_admin' => true,
            ]
        );
        User::updateOrCreate(
            ['username' => 'rafi_admin'],
            [
                'name' => 'Rafi Admin',
                'email' => 'rafi@email.com',
                'password' => Hash::make('rafi@123'),
                'is_admin' => true,
            ]
        );
>>>>>>> 4dcf113ef1d53d60c3049369317da056dab3e193
    }
}
