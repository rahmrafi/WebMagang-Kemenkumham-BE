<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateContractAdversarialTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->regularUser = User::factory()->create([
            'is_admin' => false,
        ]);
    }

    private function createTemplatePdf(): string
    {
        Storage::fake('public');

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('L', [297, 210]);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Template Sertifikat Test', 0, 1, 'C');
        $content = $pdf->Output('', 'S');

        $path = 'certificates/template/test_template.pdf';
        Storage::disk('public')->put($path, $content);

        Setting::updateOrCreate(
            ['key' => 'certificate_template_path'],
            ['value' => $path]
        );

        return $path;
    }

    private function createValidFieldsConfig(): array
    {
        $fields = [
            ['id' => 'nama', 'label' => 'Nama Peserta', 'x' => 10, 'y' => 20, 'font_size' => 18],
            ['id' => 'nim', 'label' => 'NIM / NISN', 'x' => 10, 'y' => 30, 'font_size' => 14],
            ['id' => 'asal_instansi', 'label' => 'Asal Instansi', 'x' => 10, 'y' => 40, 'font_size' => 12],
            ['id' => 'teks_kegiatan', 'label' => 'Teks Kegiatan', 'x' => 10, 'y' => 50, 'font_size' => 11],
            ['id' => 'nomor_sertifikat', 'label' => 'Nomor Sertifikat', 'x' => 10, 'y' => 60, 'font_size' => 12],
            ['id' => 'nama_pejabat', 'label' => 'Nama Pejabat', 'x' => 10, 'y' => 70, 'font_size' => 12],
            ['id' => 'tanggal_terbit', 'label' => 'Tanggal Terbit', 'x' => 10, 'y' => 80, 'font_size' => 12],
            ['id' => 'periode', 'label' => 'Periode Magang', 'x' => 10, 'y' => 90, 'font_size' => 12],
        ];

        Setting::updateOrCreate(
            ['key' => 'certificate_fields'],
            ['value' => json_encode($fields)]
        );

        return $fields;
    }

    private function createSampleSubmission(string $status = 'approved', int $memberCount = 2): Submission
    {
        $data = [
            'type'            => 'magang',
            'institution'     => 'Universitas Airlangga',
            'campus_city'     => 'Surabaya',
            'study_program'   => 'Sistem Informasi',
            'education_level' => 'S1',
            'start_date'      => '2026-07-06',
            'end_date'        => '2026-08-28',
            'letter_number'   => '001/UNAIR/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => $status,
        ];

        for ($i = 1; $i <= 10; $i++) {
            if ($i <= $memberCount) {
                $data["member_{$i}"] = "Peserta {$i}|NIM00{$i}|peserta{$i}@test.com";
            } else {
                $data["member_{$i}"] = null;
            }
        }

        return Submission::create($data);
    }

    // =========================================================================
    // 1. GET /api/admin/certificate/settings
    // =========================================================================

    public function test_get_settings_requires_admin_authentication(): void
    {
        $this->getJson('/api/admin/certificate/settings')
            ->assertStatus(401);

        $this->actingAs($this->regularUser)
            ->getJson('/api/admin/certificate/settings')
            ->assertStatus(403);
    }

    public function test_get_settings_returns_complete_structure_and_text_keys(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/certificate/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'template_path',
                    'template_url',
                    'fields',
                    'prefix',
                    'pejabat',
                    'text_magang',
                    'text_penelitian',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('W.15-UM.01.01-', $data['prefix']);
        $this->assertEquals('R. Prasetyo Wibowo, S.H., M.H.', $data['pejabat']);
        $this->assertStringContainsString('Telah menyelesaikan magang', $data['text_magang']);
        $this->assertStringContainsString('Telah melaksanakan penelitian', $data['text_penelitian']);
        $this->assertStringContainsString('{periode}', $data['text_magang']);
        $this->assertStringContainsString('{periode}', $data['text_penelitian']);
    }

    // =========================================================================
    // 2. POST /api/admin/certificate/text-settings
    // =========================================================================

    public function test_post_text_settings_requires_admin_authentication(): void
    {
        $payload = [
            'prefix'          => 'TEST-PREFIX-',
            'pejabat'         => 'Test Pejabat',
            'text_magang'     => 'Teks magang {periode}',
            'text_penelitian' => 'Teks penelitian {periode}',
        ];

        $this->postJson('/api/admin/certificate/text-settings', $payload)
            ->assertStatus(401);

        $this->actingAs($this->regularUser)
            ->postJson('/api/admin/certificate/text-settings', $payload)
            ->assertStatus(403);
    }

    public function test_post_text_settings_with_valid_payload_updates_database(): void
    {
        $payload = [
            'prefix'          => 'KEMENKUMHAM.JATIM/2026/',
            'pejabat'         => 'Dr. Moch. Ihsan, S.H., M.H.',
            'text_magang'     => 'Telah menyelesaikan magang aktif periode {periode}',
            'text_penelitian' => 'Telah melakukan riset lapangan periode {periode}',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Pengaturan teks berhasil disimpan',
            ]);

        $this->assertEquals($payload['prefix'], Setting::where('key', 'certificate_prefix')->value('value'));
        $this->assertEquals($payload['pejabat'], Setting::where('key', 'certificate_pejabat')->value('value'));
        $this->assertEquals($payload['text_magang'], Setting::where('key', 'certificate_text_magang')->value('value'));
        $this->assertEquals($payload['text_penelitian'], Setting::where('key', 'certificate_text_penelitian')->value('value'));
    }

    public function test_post_text_settings_missing_fields_returns_422(): void
    {
        // Completely empty payload
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prefix', 'pejabat', 'text_magang', 'text_penelitian']);

        // Missing prefix
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', [
                'pejabat'         => 'Pejabat Test',
                'text_magang'     => 'Magang test',
                'text_penelitian' => 'Penelitian test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prefix'])
            ->assertJsonMissingValidationErrors(['pejabat', 'text_magang', 'text_penelitian']);

        // Missing pejabat
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', [
                'prefix'          => 'P-',
                'text_magang'     => 'Magang test',
                'text_penelitian' => 'Penelitian test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pejabat']);

        // Missing text_magang
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', [
                'prefix'          => 'P-',
                'pejabat'         => 'Pejabat Test',
                'text_penelitian' => 'Penelitian test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text_magang']);

        // Missing text_penelitian
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', [
                'prefix'      => 'P-',
                'pejabat'     => 'Pejabat Test',
                'text_magang' => 'Magang test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text_penelitian']);
    }

    // =========================================================================
    // 3. POST /api/admin/submissions/{id}/certificate
    // =========================================================================

    public function test_generate_certificate_rejects_unapproved_submissions_with_422(): void
    {
        $this->createTemplatePdf();
        $this->createValidFieldsConfig();

        // 1. Pending submission
        $pendingSubmission = $this->createSampleSubmission('pending', 1);
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$pendingSubmission->id}/certificate", [
                'suffixes' => ['001'],
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Submission belum disetujui']);

        // 2. Rejected submission
        $rejectedSubmission = $this->createSampleSubmission('rejected', 1);
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$rejectedSubmission->id}/certificate", [
                'suffixes' => ['001'],
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Submission belum disetujui']);

        // 3. Need revision submission
        $revisionSubmission = $this->createSampleSubmission('need_revision', 1);
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$revisionSubmission->id}/certificate", [
                'suffixes' => ['001'],
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Submission belum disetujui']);
    }

    public function test_generate_certificate_rejects_missing_or_non_array_suffixes_with_422(): void
    {
        $submission = $this->createSampleSubmission('approved', 2);

        // Missing suffixes
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['suffixes']);

        // Suffixes is string
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => '001,002',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['suffixes']);

        // Suffixes is empty array
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['suffixes']);
    }

    public function test_generate_certificate_rejects_empty_string_suffixes_with_422(): void
    {
        $submission = $this->createSampleSubmission('approved', 2);

        // One empty string in array
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => ['001', ''],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['suffixes.1']);

        // All empty strings
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => ['', ''],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['suffixes.0', 'suffixes.1']);
    }

    /**
     * Suffix count mismatch test:
     * When a submission has N members, exactly N non-empty suffixes must be provided.
     * Sending fewer or more suffixes than members should return 422.
     */
    public function test_generate_certificate_rejects_suffix_count_mismatch_with_422(): void
    {
        $this->createTemplatePdf();
        $this->createValidFieldsConfig();

        $submission = $this->createSampleSubmission('approved', 2);

        // Case 1: 1 suffix provided for 2 members (fewer)
        $responseFewer = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => ['001'],
            ]);
        $responseFewer->assertStatus(422);

        // Case 2: 3 suffixes provided for 2 members (more)
        $responseMore = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => ['001', '002', '003'],
            ]);
        $responseMore->assertStatus(422);
    }

    // =========================================================================
    // 4. POST /api/admin/certificate/fields
    // =========================================================================

    public function test_save_fields_validates_and_stores_v2_field_ids(): void
    {
        $payload = [
            'fields' => [
                ['id' => 'nama', 'label' => 'Nama Peserta', 'x' => 10, 'y' => 20, 'font_size' => 18],
                ['id' => 'nim', 'label' => 'NIM / NISN', 'x' => 10, 'y' => 30, 'font_size' => 14],
                ['id' => 'asal_instansi', 'label' => 'Asal Instansi', 'x' => 10, 'y' => 40, 'font_size' => 12],
                ['id' => 'teks_kegiatan', 'label' => 'Teks Kegiatan', 'x' => 10, 'y' => 50, 'font_size' => 11],
                ['id' => 'nomor_sertifikat', 'label' => 'Nomor Sertifikat', 'x' => 10, 'y' => 60, 'font_size' => 12],
                ['id' => 'nama_pejabat', 'label' => 'Nama Pejabat', 'x' => 10, 'y' => 70, 'font_size' => 12],
                ['id' => 'tanggal_terbit', 'label' => 'Tanggal Terbit', 'x' => 10, 'y' => 80, 'font_size' => 12],
                ['id' => 'periode', 'label' => 'Periode Magang', 'x' => 10, 'y' => 90, 'font_size' => 12],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/fields', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Posisi field berhasil disimpan',
            ]);

        $saved = json_decode(Setting::where('key', 'certificate_fields')->value('value'), true);
        $savedIds = array_column($saved, 'id');
        $this->assertContains('nim', $savedIds);
        $this->assertContains('asal_instansi', $savedIds);
        $this->assertContains('teks_kegiatan', $savedIds);
        $this->assertContains('nama_pejabat', $savedIds);
    }
}
