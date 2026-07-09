<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PeriodeMagangSeeder extends Seeder
{
    public function run(): void
    {
        $periods = [
            [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'quota' => 20,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'start_date' => '2026-09-01',
                'end_date' => '2026-12-31',
                'quota' => 50,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('internship_periods')->insert($periods);
    }
}
