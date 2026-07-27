<?php
namespace Database\Seeders;

use App\Models\Submission;
use App\Models\SubmissionMember;
use Illuminate\Database\Seeder;

class MigrateSubmissionMembersSeeder extends Seeder
{
    public function run(): void
    {
        $migrated = 0;
        $skipped = 0;

        Submission::chunk(100, function ($submissions) use (&$migrated, &$skipped) {
            foreach ($submissions as $submission) {
                // Skip jika sudah ada members di tabel baru
                if (SubmissionMember::where('submission_id', $submission->id)->exists()) {
                    $skipped++;
                    continue;
                }

                for ($i = 1; $i <= 10; $i++) {
                    $raw = $submission->getAttribute('member_' . $i);
                    if (empty($raw)) continue;

                    $parts = array_map('trim', explode('|', (string) $raw));

                    // Format yang diketahui: "Nama|NIM|email" atau variasi
                    $nama  = $parts[0] ?? '';
                    $nim   = $parts[1] ?? null;
                    $email = $parts[2] ?? null;

                    if (empty($nama)) continue;

                    SubmissionMember::create([
                        'submission_id' => $submission->id,
                        'nama'          => $nama,
                        'nim'           => $nim ?: null,
                        'email'         => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                        'is_leader'     => ($i === 1),
                        'urutan'        => $i,
                    ]);
                }
                $migrated++;
            }
        });

        $this->command->info("Migration selesai: {$migrated} submissions diproses, {$skipped} dilewati.");
    }
}
