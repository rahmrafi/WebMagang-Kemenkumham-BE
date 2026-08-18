<?php

namespace Tests\Feature;

use App\Models\InternshipPeriod;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateV2Test extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    public function test_migration_adds_certificate_number_suffixes_column_and_default_settings(): void
    {
        $this->assertTrue(Schema::hasColumn('submissions', 'certificate_number_suffixes'));

        $this->assertEquals('W.15-UM.01.01-', Setting::where('key', 'certificate_prefix')->value('value'));
        $this->assertEquals('R. Prasetyo Wibowo, S.H., M.H.', Setting::where('key', 'certificate_pejabat')->value('value'));
        $this->assertStringContainsString('Telah menyelesaikan magang', (string) Setting::where('key', 'certificate_text_magang')->value('value'));
        $this->assertStringContainsString('Telah melaksanakan penelitian', (string) Setting::where('key', 'certificate_text_penelitian')->value('value'));
    }

    public function test_submission_model_fillable_and_array_casts_for_certificate_number_suffixes(): void
    {
        $submission = Submission::create([
            'type'                        => 'magang',
            'institution'                 => 'Universitas Brawijaya',
            'campus_city'                 => 'Malang',
            'study_program'               => 'Teknik Komputer',
            'education_level'             => 'S1',
            'start_date'                  => '2026-07-01',
            'end_date'                    => '2026-08-31',
            'member_1'                    => 'Ahmad|12345|ahmad@test.com',
            'letter_number'               => '001/UN/2026',
            'letter_date'                 => '2026-06-01',
            'document_path'               => 'documents/test.pdf',
            'phone_number'                => '081234567890',
            'status'                      => 'approved',
            'certificate_number_suffixes' => ['001', '002'],
        ]);

        $fresh = $submission->fresh();
        $this->assertIsArray($fresh->certificate_number_suffixes);
        $this->assertEquals(['001', '002'], $fresh->certificate_number_suffixes);
    }

    public function test_get_settings_returns_all_text_settings(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/certificate/settings');

        $response->assertOk()
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
        $this->assertStringContainsString('{periode}', $data['text_magang']);
        $this->assertStringContainsString('{periode}', $data['text_penelitian']);
    }

    public function test_save_text_settings_updates_database_successfully(): void
    {
        $payload = [
            'prefix'          => 'REG.15-CERT-',
            'pejabat'         => 'Dr. Hendra Gunawan, S.H., M.Hum.',
            'text_magang'     => 'Telah menyelesaikan PKL periode {periode}',
            'text_penelitian' => 'Telah menyelesaikan riset periode {periode}',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', $payload);

        $response->assertOk()
            ->assertJson(['message' => 'Pengaturan teks berhasil disimpan']);

        $this->assertEquals('REG.15-CERT-', Setting::where('key', 'certificate_prefix')->value('value'));
        $this->assertEquals('Dr. Hendra Gunawan, S.H., M.Hum.', Setting::where('key', 'certificate_pejabat')->value('value'));
        $this->assertEquals('Telah menyelesaikan PKL periode {periode}', Setting::where('key', 'certificate_text_magang')->value('value'));
        $this->assertEquals('Telah menyelesaikan riset periode {periode}', Setting::where('key', 'certificate_text_penelitian')->value('value'));
    }

    public function test_save_text_settings_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prefix', 'pejabat', 'text_magang', 'text_penelitian']);
    }

    public function test_extract_members_formats_asal_instansi_and_teks_kegiatan_for_magang(): void
    {
        $submission = new Submission([
            'type'          => 'magang',
            'institution'   => 'Universitas Airlangga',
            'study_program' => 'Sistem Informasi',
            'start_date'    => '2026-07-06',
            'end_date'      => '2026-08-28',
            'member_1'      => 'Qanita Shafiyah|1203230094|qanita@test.com',
            'member_2'      => 'Budi Santoso|1203230095|budi@test.com',
        ]);

        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, ['001', '002']);

        $this->assertCount(2, $members);

        // Member 1
        $this->assertEquals('Qanita Shafiyah', $members[0]['nama']);
        $this->assertEquals('1203230094', $members[0]['nim']);
        $this->assertEquals('Sistem Informasi, Universitas Airlangga', $members[0]['asal_instansi']);
        $this->assertEquals('W.15-UM.01.01-001', $members[0]['nomor_sertifikat']);
        $this->assertEquals('R. Prasetyo Wibowo, S.H., M.H.', $members[0]['nama_pejabat']);
        $this->assertStringContainsString('6 Juli 2026 – 28 Agustus 2026', $members[0]['teks_kegiatan']);
        $this->assertStringContainsString('Telah menyelesaikan magang', $members[0]['teks_kegiatan']);
        $this->assertArrayNotHasKey('institusi', $members[0]);
        $this->assertArrayNotHasKey('prodi', $members[0]);

        // Member 2
        $this->assertEquals('Budi Santoso', $members[1]['nama']);
        $this->assertEquals('1203230095', $members[1]['nim']);
        $this->assertEquals('W.15-UM.01.01-002', $members[1]['nomor_sertifikat']);
    }

    public function test_extract_members_formats_asal_instansi_and_teks_kegiatan_for_penelitian_without_study_program(): void
    {
        $submission = new Submission([
            'type'          => 'penelitian',
            'institution'   => 'SMA Negeri 1 Surabaya',
            'study_program' => null,
            'start_date'    => '2026-09-01',
            'end_date'      => '2026-09-30',
            'member_1'      => 'Citra Dewi|554433|citra@test.com',
        ]);

        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, ['R-100']);

        $this->assertCount(1, $members);
        $this->assertEquals('SMA Negeri 1 Surabaya', $members[0]['asal_instansi']);
        $this->assertEquals('W.15-UM.01.01-R-100', $members[0]['nomor_sertifikat']);
        $this->assertStringContainsString('Telah melaksanakan penelitian', $members[0]['teks_kegiatan']);
        $this->assertStringContainsString('1 September 2026 – 30 September 2026', $members[0]['teks_kegiatan']);
    }

    public function test_generate_certificate_requires_approved_submission_and_valid_suffixes(): void
    {
        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Indonesia',
            'campus_city'     => 'Depok',
            'study_program'   => 'Ilmu Hukum',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Dewi Sartika|112233|dewi@test.com',
            'letter_number'   => '002/UI/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'pending',
        ]);

        // Fails when status is not approved
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => ['001'],
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Submission belum disetujui']);

        // Fails when suffixes is missing
        $submission->update(['status' => 'approved']);
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['suffixes']);
    }

    public function test_generate_certificate_end_to_end_with_pdf_template_and_legacy_fields_compatibility(): void
    {
        Storage::fake('public');

        // Create a dummy template PDF using FPDI/TCPDF
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('L', [297, 210]);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Template Sertifikat', 0, 1, 'C');
        $pdfContent = $pdf->Output('', 'S');

        $templatePath = 'certificates/template/test_template.pdf';
        Storage::disk('public')->put($templatePath, $pdfContent);

        Setting::updateOrCreate(
            ['key' => 'certificate_template_path'],
            ['value' => $templatePath]
        );

        // Fields configuration including legacy fields ('institusi', 'prodi') and new fields ('teks_kegiatan', 'asal_instansi')
        $fields = [
            [
                'id'         => 'nama',
                'label'      => 'Nama Peserta',
                'x'          => 10,
                'y'          => 20,
                'font_size'  => 18,
                'font_color' => '#000000',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'nim',
                'label'      => 'NIM',
                'x'          => 10,
                'y'          => 30,
                'font_size'  => 14,
                'font_color' => '#333333',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'asal_instansi',
                'label'      => 'Asal Instansi',
                'x'          => 10,
                'y'          => 40,
                'font_size'  => 12,
                'font_color' => '#1a1a1a',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'teks_kegiatan',
                'label'      => 'Teks Kegiatan',
                'x'          => 10,
                'y'          => 50,
                'font_size'  => 11,
                'font_color' => '#1a1a1a',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'nomor_sertifikat',
                'label'      => 'Nomor Sertifikat',
                'x'          => 10,
                'y'          => 65,
                'font_size'  => 12,
                'font_color' => '#1a1a1a',
                'width'      => 80,
                'text_align' => 'center',
            ],
            // Legacy fields to test backward compatibility (should be safely skipped)
            [
                'id'         => 'institusi',
                'label'      => 'Institusi Legacy',
                'x'          => 10,
                'y'          => 75,
                'font_size'  => 12,
                'font_color' => '#1a1a1a',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'prodi',
                'label'      => 'Prodi Legacy',
                'x'          => 10,
                'y'          => 85,
                'font_size'  => 12,
                'font_color' => '#1a1a1a',
                'width'      => 80,
                'text_align' => 'center',
            ],
        ];

        Setting::updateOrCreate(
            ['key' => 'certificate_fields'],
            ['value' => json_encode($fields)]
        );

        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Institut Teknologi Bandung',
            'campus_city'     => 'Bandung',
            'study_program'   => 'Informatika',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Farhan Kamil|13520001|farhan@test.com',
            'member_2'        => 'Rina Melati|13520002|rina@test.com',
            'letter_number'   => '100/ITB/2026',
            'letter_date'     => '2026-06-15',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => ['001-A', '002-B'],
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Sertifikat berhasil di-generate',
                'data'    => [
                    'member_count' => 2,
                ],
            ]);

        $fresh = $submission->fresh();
        $this->assertEquals(['001-A', '002-B'], $fresh->certificate_number_suffixes);
        $this->assertNotNull($fresh->certificate_zip_path);
        $this->assertNotNull($fresh->certificate_generated_at);

        Storage::disk('public')->assertExists($fresh->certificate_zip_path);
    }
}
