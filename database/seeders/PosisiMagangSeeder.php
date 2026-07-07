<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosisiMagangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            // Kluster Teknologi & Data
            [
                'position_name' => 'Web & Mobile Developer',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_name' => 'Data Entry & Administrator Sistem',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_name' => 'IT Technical Support',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kluster Komunikasi & Kreatif
            [
                'position_name' => 'Social Media Specialist & Content Writer',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_name' => 'Graphic Designer & Videographer',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_name' => 'Public Relations & Kehumasan',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kluster Hukum & Kebijakan
            [
                'position_name' => 'Legal Analyst Intern',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_name' => 'Administrasi & Pelayanan Hukum Umum',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kluster Manajemen & Operasional
            [
                'position_name' => 'Human Resources & Administrasi Kepegawaian',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_name' => 'Finance & Keuangan Negara',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Memasukkan data ke tabel 'internship_positions' sesuai migration kamu
        DB::table('internship_positions')->insert($positions);
    }
}