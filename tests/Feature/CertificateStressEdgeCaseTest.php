<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;
use ZipArchive;

class CertificateStressEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private string $templatePath = 'certificates/template/test_template.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'is_admin' => true,
        ]);

        Storage::fake('public');

        // Generate a standard PDF template using FPDI/TCPDF and save to storage
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('L', [297, 210]);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 15, 'Sertifikat Template Base', 0, 1, 'C');
        $pdfContent = $pdf->Output('', 'S');

        Storage::disk('public')->put($this->templatePath, $pdfContent);

        Setting::updateOrCreate(
            ['key' => 'certificate_template_path'],
            ['value' => $this->templatePath]
        );

        // Standard test field positions covering new and legacy fields
        $fields = [
            [
                'id'         => 'nomor_sertifikat',
                'label'      => 'Nomor Sertifikat',
                'x'          => 10,
                'y'          => 20,
                'font_size'  => 12,
                'font_color' => '#1a1a1a',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'nama',
                'label'      => 'Nama Peserta',
                'x'          => 10,
                'y'          => 35,
                'font_size'  => 18,
                'font_color' => '#000000',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'nim',
                'label'      => 'NIM / NISN',
                'x'          => 10,
                'y'          => 45,
                'font_size'  => 12,
                'font_color' => '#333333',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'asal_instansi',
                'label'      => 'Asal Instansi',
                'x'          => 10,
                'y'          => 55,
                'font_size'  => 12,
                'font_color' => '#1a1a1a',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'teks_kegiatan',
                'label'      => 'Teks Kegiatan',
                'x'          => 10,
                'y'          => 65,
                'font_size'  => 11,
                'font_color' => '#222222',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'tanggal_terbit',
                'label'      => 'Tanggal Terbit',
                'x'          => 60,
                'y'          => 80,
                'font_size'  => 11,
                'font_color' => '#1a1a1a',
                'width'      => 35,
                'text_align' => 'center',
            ],
            [
                'id'         => 'nama_pejabat',
                'label'      => 'Nama Pejabat',
                'x'          => 60,
                'y'          => 90,
                'font_size'  => 12,
                'font_color' => '#000000',
                'width'      => 35,
                'text_align' => 'center',
            ],
            // Legacy fields that should be silently ignored without breaking generation
            [
                'id'         => 'institusi',
                'label'      => 'Institusi (Legacy)',
                'x'          => 10,
                'y'          => 95,
                'font_size'  => 10,
                'font_color' => '#999999',
                'width'      => 80,
                'text_align' => 'center',
            ],
            [
                'id'         => 'prodi',
                'label'      => 'Program Studi (Legacy)',
                'x'          => 10,
                'y'          => 98,
                'font_size'  => 10,
                'font_color' => '#999999',
                'width'      => 80,
                'text_align' => 'center',
            ],
        ];

        Setting::updateOrCreate(
            ['key' => 'certificate_fields'],
            ['value' => json_encode($fields)]
        );
    }

    /**
     * Requirement 1: Multi-member submissions (1, 5, 10 members) with suffix mapping.
     */
    public function test_single_member_submission_suffix_mapping_and_generation(): void
    {
        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Gadjah Mada',
            'campus_city'     => 'Yogyakarta',
            'study_program'   => 'Teknologi Informasi',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Solo Adventurer|UGM-001|solo@ugm.ac.id',
            'letter_number'   => '001/UGM/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, ['SOLO-01']);

        $this->assertCount(1, $members);
        $this->assertEquals('Solo Adventurer', $members[0]['nama']);
        $this->assertEquals('UGM-001', $members[0]['nim']);
        $this->assertEquals('W.15-UM.01.01-SOLO-01', $members[0]['nomor_sertifikat']);

        // Generate ZIP
        $zipResult = $service->generateZip($submission, ['SOLO-01']);
        $this->assertEquals(1, $zipResult['memberCount']);
        Storage::disk('public')->assertExists($zipResult['zipStorePath']);

        // Inspect ZIP content
        $zipFile = Storage::disk('public')->path($zipResult['zipStorePath']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipFile) === true);
        $this->assertEquals(1, $zip->numFiles);
        $this->assertEquals('Sertifikat_Solo_Adventurer.pdf', $zip->getNameIndex(0));
        $zip->close();
    }

    public function test_five_members_submission_suffix_mapping_and_generation(): void
    {
        $membersData = [
            'member_1' => 'Alice Wonderland|NIM-01|alice@test.com',
            'member_2' => 'Bob Builder|NIM-02|bob@test.com',
            'member_3' => 'Charlie Chaplin|NIM-03|charlie@test.com',
            'member_4' => 'David Copperfield|NIM-04|david@test.com',
            'member_5' => 'Eva Green|NIM-05|eva@test.com',
        ];

        $submission = Submission::create(array_merge([
            'type'            => 'magang',
            'institution'     => 'Universitas Indonesia',
            'campus_city'     => 'Depok',
            'study_program'   => 'Sistem Informasi',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'letter_number'   => '005/UI/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ], $membersData));

        $suffixes = ['001-A', '002-B', '003-C', '004-D', '005-E'];
        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, $suffixes);

        $this->assertCount(5, $members);
        foreach ($members as $idx => $member) {
            $this->assertEquals('W.15-UM.01.01-' . $suffixes[$idx], $member['nomor_sertifikat']);
            $this->assertEquals('NIM-0' . ($idx + 1), $member['nim']);
        }

        $zipResult = $service->generateZip($submission, $suffixes);
        $this->assertEquals(5, $zipResult['memberCount']);

        $zipFile = Storage::disk('public')->path($zipResult['zipStorePath']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipFile) === true);
        $this->assertEquals(5, $zip->numFiles);
        $zip->close();
    }

    public function test_ten_members_maximum_capacity_submission_suffix_mapping_and_generation(): void
    {
        $membersData = [];
        $expectedNames = [];
        $suffixes = [];
        for ($i = 1; $i <= 10; $i++) {
            $name = "Peserta Nomor {$i}";
            $membersData["member_{$i}"] = "{$name}|1234567{$i}|peserta{$i}@test.com";
            $expectedNames[] = $name;
            $suffixes[] = sprintf('MAX-%03d', $i);
        }

        $submission = Submission::create(array_merge([
            'type'            => 'magang',
            'institution'     => 'Institut Teknologi Sepuluh Nopember',
            'campus_city'     => 'Surabaya',
            'study_program'   => 'Teknik Informatika',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'letter_number'   => '010/ITS/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ], $membersData));

        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, $suffixes);

        $this->assertCount(10, $members);
        for ($i = 0; $i < 10; $i++) {
            $this->assertEquals("Peserta Nomor " . ($i + 1), $members[$i]['nama']);
            $this->assertEquals("1234567" . ($i + 1), $members[$i]['nim']);
            $this->assertEquals("W.15-UM.01.01-" . $suffixes[$i], $members[$i]['nomor_sertifikat']);
        }

        // Test API generation for 10 members
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => $suffixes,
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Sertifikat berhasil di-generate',
                'data'    => [
                    'member_count' => 10,
                ],
            ]);

        $submission->refresh();
        $this->assertEquals($suffixes, $submission->certificate_number_suffixes);
        $this->assertNotNull($submission->certificate_zip_path);

        $zipFile = Storage::disk('public')->path($submission->certificate_zip_path);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipFile) === true);
        $this->assertEquals(10, $zip->numFiles);
        $zip->close();
    }

    public function test_discontinuous_member_columns_correctly_maps_sequential_suffixes(): void
    {
        // Submission where member_1, member_3, and member_7 are filled, but member_2, 4, 5, 6, 8, 9, 10 are empty
        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Brawijaya',
            'campus_city'     => 'Malang',
            'study_program'   => 'Ilmu Komputer',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'First Person|NIM1|first@test.com',
            'member_2'        => null,
            'member_3'        => 'Third Person|NIM3|third@test.com',
            'member_4'        => '',
            'member_5'        => null,
            'member_6'        => null,
            'member_7'        => 'Seventh Person|NIM7|seventh@test.com',
            'letter_number'   => '007/UB/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $suffixes = ['SUF-01', 'SUF-02', 'SUF-03'];
        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, $suffixes);

        $this->assertCount(3, $members);
        $this->assertEquals('First Person', $members[0]['nama']);
        $this->assertEquals('W.15-UM.01.01-SUF-01', $members[0]['nomor_sertifikat']);

        $this->assertEquals('Third Person', $members[1]['nama']);
        $this->assertEquals('W.15-UM.01.01-SUF-02', $members[1]['nomor_sertifikat']);

        $this->assertEquals('Seventh Person', $members[2]['nama']);
        $this->assertEquals('W.15-UM.01.01-SUF-03', $members[2]['nomor_sertifikat']);
    }

    /**
     * Requirement 2: Suffix formatting variations.
     */
    public function test_suffix_formatting_variations(): void
    {
        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Politeknik Negeri Malang',
            'campus_city'     => 'Malang',
            'study_program'   => 'Teknik Elektronika',
            'education_level' => 'D4',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Alfa User|NIM-A|a@test.com',
            'member_2'        => 'Beta User|NIM-B|b@test.com',
            'member_3'        => 'Gamma User|NIM-C|c@test.com',
            'member_4'        => 'Delta User|NIM-D|d@test.com',
            'letter_number'   => '008/POL/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        // Suffixes testing: alphanumeric, hyphenated, complex slash, roman numerals
        $testSuffixes = [
            '001ABC',
            'A-999-XYZ',
            '003/KANWIL/VIII/2026',
            'No. IV-2026',
        ];

        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, $testSuffixes);

        $this->assertEquals('W.15-UM.01.01-001ABC', $members[0]['nomor_sertifikat']);
        $this->assertEquals('W.15-UM.01.01-A-999-XYZ', $members[1]['nomor_sertifikat']);
        $this->assertEquals('W.15-UM.01.01-003/KANWIL/VIII/2026', $members[2]['nomor_sertifikat']);
        $this->assertEquals('W.15-UM.01.01-No. IV-2026', $members[3]['nomor_sertifikat']);

        // Test custom prefix
        Setting::updateOrCreate(['key' => 'certificate_prefix'], ['value' => 'KUMHAM-JATIM/']);
        $membersWithCustomPrefix = $service->extractMembers($submission, $testSuffixes);
        $this->assertEquals('KUMHAM-JATIM/001ABC', $membersWithCustomPrefix[0]['nomor_sertifikat']);
    }

    /**
     * Requirement 3: Asal instansi edge cases.
     */
    public function test_asal_instansi_edge_cases_null_empty_whitespace_and_normal(): void
    {
        $service = app(CertificateService::class);

        // Case 1: normal study program
        $sub1 = new Submission([
            'type'          => 'magang',
            'institution'   => 'Universitas Airlangga',
            'study_program' => 'S1 Ilmu Hukum',
            'start_date'    => '2026-07-01',
            'end_date'      => '2026-08-31',
            'member_1'      => 'Student One|111|s1@test.com',
        ]);
        $members1 = $service->extractMembers($sub1, ['001']);
        $this->assertEquals('S1 Ilmu Hukum, Universitas Airlangga', $members1[0]['asal_instansi']);

        // Case 2: null study program
        $sub2 = new Submission([
            'type'          => 'penelitian',
            'institution'   => 'SMA Negeri 5 Surabaya',
            'study_program' => null,
            'start_date'    => '2026-07-01',
            'end_date'      => '2026-08-31',
            'member_1'      => 'Student Two|222|s2@test.com',
        ]);
        $members2 = $service->extractMembers($sub2, ['002']);
        $this->assertEquals('SMA Negeri 5 Surabaya', $members2[0]['asal_instansi']);

        // Case 3: empty string study program
        $sub3 = new Submission([
            'type'          => 'penelitian',
            'institution'   => 'Dinas Pendidikan Jatim',
            'study_program' => '',
            'start_date'    => '2026-07-01',
            'end_date'      => '2026-08-31',
            'member_1'      => 'Researcher|333|r@test.com',
        ]);
        $members3 = $service->extractMembers($sub3, ['003']);
        $this->assertEquals('Dinas Pendidikan Jatim', $members3[0]['asal_instansi']);

        // Case 4: whitespace only study program
        $sub4 = new Submission([
            'type'          => 'magang',
            'institution'   => 'Universitas Terbuka',
            'study_program' => '   ',
            'start_date'    => '2026-07-01',
            'end_date'      => '2026-08-31',
            'member_1'      => 'Student Four|444|s4@test.com',
        ]);
        $members4 = $service->extractMembers($sub4, ['004']);
        $this->assertEquals('Universitas Terbuka', $members4[0]['asal_instansi']);

        // Case 5: special characters in study program and institution
        $sub5 = new Submission([
            'type'          => 'magang',
            'institution'   => 'Institut Bisnis & Informatika Stikom Surabaya',
            'study_program' => 'S1 Sistem Komputer / Hardware & Networking',
            'start_date'    => '2026-07-01',
            'end_date'      => '2026-08-31',
            'member_1'      => 'Student Five|555|s5@test.com',
        ]);
        $members5 = $service->extractMembers($sub5, ['005']);
        $this->assertEquals('S1 Sistem Komputer / Hardware & Networking, Institut Bisnis & Informatika Stikom Surabaya', $members5[0]['asal_instansi']);
    }

    /**
     * Requirement 4: Teks kegiatan formatting for 'magang' vs 'penelitian' and custom {periode}.
     */
    public function test_teks_kegiatan_formatting_for_magang_vs_penelitian_and_periode_variations(): void
    {
        $service = app(CertificateService::class);

        // Magang template
        Setting::updateOrCreate(
            ['key' => 'certificate_text_magang'],
            ['value' => 'Telah menyelesaikan Praktik Kerja Lapangan pada rentang waktu {periode} dengan hasil BAIK.']
        );

        // Penelitian template
        Setting::updateOrCreate(
            ['key' => 'certificate_text_penelitian'],
            ['value' => 'Telah melakukan riset ilmiah pada kurun waktu {periode} untuk penyusunan skripsi.']
        );

        // Test Magang
        $magangSub = new Submission([
            'type'          => 'magang',
            'institution'   => 'Universitas Brawijaya',
            'study_program' => 'Informatika',
            'start_date'    => '2026-07-06',
            'end_date'      => '2026-08-28',
            'member_1'      => 'Magang User|111|m@test.com',
        ]);
        $magangMembers = $service->extractMembers($magangSub, ['M-01']);
        $this->assertStringContainsString('Telah menyelesaikan Praktik Kerja Lapangan', $magangMembers[0]['teks_kegiatan']);
        $this->assertStringContainsString('6 Juli 2026 – 28 Agustus 2026', $magangMembers[0]['teks_kegiatan']);
        $this->assertStringNotContainsString('{periode}', $magangMembers[0]['teks_kegiatan']);

        // Test Penelitian
        $penelitianSub = new Submission([
            'type'          => 'penelitian',
            'institution'   => 'Universitas Airlangga',
            'study_program' => 'Biologi',
            'start_date'    => '2026-09-01',
            'end_date'      => '2026-11-30',
            'member_1'      => 'Peneliti User|222|p@test.com',
        ]);
        $penelitianMembers = $service->extractMembers($penelitianSub, ['P-01']);
        $this->assertStringContainsString('Telah melakukan riset ilmiah', $penelitianMembers[0]['teks_kegiatan']);
        $this->assertStringContainsString('1 September 2026 – 30 November 2026', $penelitianMembers[0]['teks_kegiatan']);
        $this->assertStringNotContainsString('{periode}', $penelitianMembers[0]['teks_kegiatan']);

        // Test Cross-Year Period
        $crossYearSub = new Submission([
            'type'          => 'magang',
            'institution'   => 'ITB',
            'study_program' => 'Teknik Elektro',
            'start_date'    => '2025-12-15',
            'end_date'      => '2026-02-15',
            'member_1'      => 'Cross Year User|333|c@test.com',
        ]);
        $crossYearMembers = $service->extractMembers($crossYearSub, ['C-01']);
        $this->assertStringContainsString('15 Desember 2025 – 15 Februari 2026', $crossYearMembers[0]['teks_kegiatan']);

        // Test Multiple {periode} tokens in setting
        Setting::updateOrCreate(
            ['key' => 'certificate_text_magang'],
            ['value' => 'Kegiatan Magang: ({periode}). Sertifikat berlaku untuk periode {periode}.']
        );
        $multiTokenMembers = $service->extractMembers($magangSub, ['M-02']);
        $this->assertEquals(
            'Kegiatan Magang: (6 Juli 2026 – 28 Agustus 2026). Sertifikat berlaku untuk periode 6 Juli 2026 – 28 Agustus 2026.',
            $multiTokenMembers[0]['teks_kegiatan']
        );
    }

    /**
     * Requirement 5: Legacy field IDs (institusi, prodi) and unknown fields in certificate_fields.
     */
    public function test_legacy_and_unknown_fields_in_settings_do_not_cause_errors_during_pdf_generation(): void
    {
        // Inject multiple legacy and unknown custom fields
        $fieldsWithLegacy = [
            ['id' => 'nama', 'label' => 'Nama', 'x' => 10, 'y' => 20, 'font_size' => 16, 'width' => 80],
            ['id' => 'institusi', 'label' => 'Institusi Lama', 'x' => 10, 'y' => 30, 'font_size' => 12, 'width' => 80],
            ['id' => 'prodi', 'label' => 'Prodi Lama', 'x' => 10, 'y' => 40, 'font_size' => 12, 'width' => 80],
            ['id' => 'non_existent_custom_id', 'label' => 'Unknown', 'x' => 10, 'y' => 50, 'font_size' => 12, 'width' => 80],
            ['id' => 'nim', 'label' => 'NIM', 'x' => 10, 'y' => 60, 'font_size' => 12, 'width' => 80],
            ['id' => 'asal_instansi', 'label' => 'Asal Instansi', 'x' => 10, 'y' => 70, 'font_size' => 12, 'width' => 80],
            ['id' => 'teks_kegiatan', 'label' => 'Teks Kegiatan', 'x' => 10, 'y' => 80, 'font_size' => 10, 'width' => 80],
            ['id' => 'nomor_sertifikat', 'label' => 'Nomor', 'x' => 10, 'y' => 90, 'font_size' => 10, 'width' => 80],
        ];

        Setting::updateOrCreate(
            ['key' => 'certificate_fields'],
            ['value' => json_encode($fieldsWithLegacy)]
        );

        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Udayana',
            'campus_city'     => 'Denpasar',
            'study_program'   => 'Teknologi Informasi',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Made Suardana|UDY-101|made@test.com',
            'member_2'        => 'Ketut Rahayu|UDY-102|ketut@test.com',
            'letter_number'   => '099/UDY/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $service = app(CertificateService::class);

        // This must run smoothly without any Undefined Array Key exceptions or notices
        $result = $service->generateZip($submission, ['001', '002']);

        $this->assertEquals(2, $result['memberCount']);
        Storage::disk('public')->assertExists($result['zipStorePath']);
    }

    /**
     * Requirement 6: PDF & ZIP integrity verification (validate binary headers, FPDI parsability).
     */
    public function test_generated_zip_contains_valid_parseable_pdf_documents(): void
    {
        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Diponegoro',
            'campus_city'     => 'Semarang',
            'study_program'   => 'Informatika',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Bambang Triatmojo|UNDIP-01|bambang@undip.ac.id',
            'member_2'        => "Qanita Shafiyah Qurrata'ain|UNDIP-02|qanita@undip.ac.id",
            'letter_number'   => '045/UNDIP/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $service = app(CertificateService::class);
        $result = $service->generateZip($submission, ['001', '002']);

        $zipPath = Storage::disk('public')->path($result['zipStorePath']);
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertEquals(2, $zip->numFiles);

        $tempExtractDir = sys_get_temp_dir() . '/test_cert_extract_' . uniqid();
        mkdir($tempExtractDir);
        $zip->extractTo($tempExtractDir);
        $zip->close();

        $extractedFiles = glob($tempExtractDir . '/*.pdf');
        $this->assertCount(2, $extractedFiles);

        foreach ($extractedFiles as $extractedPdfPath) {
            $this->assertFileExists($extractedPdfPath);
            $this->assertGreaterThan(500, filesize($extractedPdfPath), 'PDF file size should be non-trivial');

            // Verify standard PDF header magic bytes
            $handle = fopen($extractedPdfPath, 'rb');
            $header = fread($handle, 5);
            fclose($handle);
            $this->assertEquals('%PDF-', $header, 'File must have valid PDF magic bytes');

            // Verify with FPDI parser that the generated PDF can be opened and parsed
            $verifyPdf = new Fpdi();
            $pageCount = $verifyPdf->setSourceFile($extractedPdfPath);
            $this->assertGreaterThanOrEqual(1, $pageCount, 'Generated PDF must have at least 1 page');
            $tpl = $verifyPdf->importPage(1);
            $size = $verifyPdf->getTemplateSize($tpl);
            $this->assertGreaterThan(0, $size['width']);
            $this->assertGreaterThan(0, $size['height']);

            @unlink($extractedPdfPath);
        }

        @rmdir($tempExtractDir);
    }

    /**
     * Requirement 7: MultiCell word-wrap stress test for long teks_kegiatan.
     */
    public function test_teks_kegiatan_multicell_handles_lengthy_text_without_clipping_or_failing(): void
    {
        // Very long text template to stress test TCPDF MultiCell
        $longText = 'Telah menyelesaikan Praktik Kerja Lapangan (PKL) / Magang Mandiri Berdampak di Lingkungan Kantor Wilayah Kementerian Hukum dan Hak Asasi Manusia Jawa Timur pada Divisi Administrasi, Divisi Pelayanan Hukum dan HAM, Divisi Pemasyarakatan, serta Divisi Keimigrasian terhitung mulai tanggal {periode}. Peserta telah menunjukkan dedikasi, integritas, kedisiplinan, serta etika profesional yang tinggi dalam melaksanakan tugas-tugas penunjang operasional organisasi kementerian.';
        
        Setting::updateOrCreate(
            ['key' => 'certificate_text_magang'],
            ['value' => $longText]
        );

        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Sebelas Maret',
            'campus_city'     => 'Surakarta',
            'study_program'   => 'Ilmu Komunikasi & Hubungan Masyarakat',
            'education_level' => 'S1',
            'start_date'      => '2026-06-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Panjang Banget Namanya S.Kom.|UNS-999|panjang@uns.ac.id',
            'letter_number'   => '077/UNS/2026',
            'letter_date'     => '2026-05-15',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $service = app(CertificateService::class);
        $result = $service->generateZip($submission, ['LONG-01']);

        $zipPath = Storage::disk('public')->path($result['zipStorePath']);
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertEquals(1, $zip->numFiles);
        $zip->close();
    }

    /**
     * Requirement 8: API Controller Endpoints Verification.
     */
    public function test_api_text_settings_endpoint_crud(): void
    {
        // 1. Get settings
        $getResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/certificate/settings');

        $getResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'prefix',
                    'pejabat',
                    'text_magang',
                    'text_penelitian',
                ],
            ]);

        // 2. Save text settings
        $savePayload = [
            'prefix'          => 'W.15-PAS.01.01-',
            'pejabat'         => 'Kepala Kantor Wilayah Kemenkumham Jatim',
            'text_magang'     => 'Magang PKL {periode}',
            'text_penelitian' => 'Penelitian Riset {periode}',
        ];

        $saveResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/text-settings', $savePayload);

        $saveResponse->assertOk()
            ->assertJson(['message' => 'Pengaturan teks berhasil disimpan']);

        $this->assertEquals('W.15-PAS.01.01-', Setting::where('key', 'certificate_prefix')->value('value'));
        $this->assertEquals('Kepala Kantor Wilayah Kemenkumham Jatim', Setting::where('key', 'certificate_pejabat')->value('value'));
    }

    public function test_api_save_fields_accepts_all_v2_field_identifiers(): void
    {
        $v2Fields = [
            [
                'id'         => 'nim',
                'label'      => 'NIM / NISN',
                'x'          => 15.5,
                'y'          => 40.2,
                'font_size'  => 14,
                'font_color' => '#112233',
                'width'      => 50.0,
                'text_align' => 'center',
            ],
            [
                'id'         => 'asal_instansi',
                'label'      => 'Asal Instansi',
                'x'          => 15.5,
                'y'          => 50.0,
                'font_size'  => 12,
                'font_color' => '#334455',
                'width'      => 60.0,
                'text_align' => 'center',
            ],
            [
                'id'         => 'teks_kegiatan',
                'label'      => 'Teks Kegiatan',
                'x'          => 10.0,
                'y'          => 60.0,
                'font_size'  => 11,
                'font_color' => '#556677',
                'width'      => 80.0,
                'text_align' => 'center',
            ],
            [
                'id'         => 'nama_pejabat',
                'label'      => 'Nama Pejabat',
                'x'          => 65.0,
                'y'          => 85.0,
                'font_size'  => 13,
                'font_color' => '#000000',
                'width'      => 30.0,
                'text_align' => 'center',
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/certificate/fields', [
                'fields' => $v2Fields,
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Posisi field berhasil disimpan']);

        $savedFields = json_decode(Setting::where('key', 'certificate_fields')->value('value'), true);
        $this->assertCount(4, $savedFields);
        $this->assertEquals('nim', $savedFields[0]['id']);
        $this->assertEquals('asal_instansi', $savedFields[1]['id']);
        $this->assertEquals('teks_kegiatan', $savedFields[2]['id']);
        $this->assertEquals('nama_pejabat', $savedFields[3]['id']);
    }

    public function test_api_download_generated_zip(): void
    {
        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Airlangga',
            'campus_city'     => 'Surabaya',
            'study_program'   => 'Sistem Informasi',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Download User|12345|d@test.com',
            'letter_number'   => '088/UNAIR/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        // Prior to generate: download returns 404
        $failResponse = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/submissions/{$submission->id}/certificate/download");
        $failResponse->assertNotFound();

        // Generate certificate
        $genResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/submissions/{$submission->id}/certificate", [
                'suffixes' => ['001'],
            ]);
        $genResponse->assertOk();

        // Download generated zip
        $downloadResponse = $this->actingAs($this->adminUser)
            ->get("/api/admin/submissions/{$submission->id}/certificate/download");

        $downloadResponse->assertOk();
        $this->assertEquals('application/zip', $downloadResponse->headers->get('content-type'));
    }

    public function test_duplicate_member_names_handling(): void
    {
        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Airlangga',
            'campus_city'     => 'Surabaya',
            'study_program'   => 'Sistem Informasi',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Ahmad Dani|NIM-01|ahmad1@test.com',
            'member_2'        => 'Ahmad Dani|NIM-02|ahmad2@test.com',
            'letter_number'   => '089/UNAIR/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, ['001', '002']);

        $this->assertCount(2, $members);
        $this->assertEquals('W.15-UM.01.01-001', $members[0]['nomor_sertifikat']);
        $this->assertEquals('W.15-UM.01.01-002', $members[1]['nomor_sertifikat']);

        // Generate zip
        $result = $service->generateZip($submission, ['001', '002']);
        $this->assertNotNull($result['zipStorePath']);
    }

    public function test_whitespace_in_suffixes_and_trimming_behavior(): void
    {
        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Brawijaya',
            'campus_city'     => 'Malang',
            'study_program'   => 'Informatika',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Budi|123|budi@test.com',
            'member_2'        => 'Siti|124|siti@test.com',
            'letter_number'   => '090/UB/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, ['  001  ', '002-A']);

        // Directly verifies concatenation
        $this->assertEquals('W.15-UM.01.01-  001  ', $members[0]['nomor_sertifikat']);
        $this->assertEquals('W.15-UM.01.01-002-A', $members[1]['nomor_sertifikat']);
    }

    public function test_missing_settings_fallbacks_gracefully(): void
    {
        // Delete all settings
        Setting::whereIn('key', [
            'certificate_prefix',
            'certificate_pejabat',
            'certificate_text_magang',
            'certificate_text_penelitian',
        ])->delete();

        $submission = Submission::create([
            'type'            => 'magang',
            'institution'     => 'Universitas Negeri Surabaya',
            'campus_city'     => 'Surabaya',
            'study_program'   => 'Pendidikan TI',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Test User|999|user@test.com',
            'letter_number'   => '091/UNESA/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $service = app(CertificateService::class);
        $members = $service->extractMembers($submission, ['001']);

        $this->assertCount(1, $members);
        // Default fallback prefix is W.15-UM.01.01-
        $this->assertEquals('W.15-UM.01.01-001', $members[0]['nomor_sertifikat']);
        $this->assertEquals('', $members[0]['nama_pejabat']);
        $this->assertEquals('', $members[0]['teks_kegiatan']);

        // PDF Generation still succeeds with empty values
        $result = $service->generateZip($submission, ['001']);
        $this->assertEquals(1, $result['memberCount']);
    }

    public function test_special_characters_in_member_names_and_institutions(): void
    {
        $submission = Submission::create([
            'type'            => 'penelitian',
            'institution'     => 'Institut Seni Indonesia "Yogyakarta" & Keraton',
            'campus_city'     => 'Yogyakarta',
            'study_program'   => 'Etnomusikologi / Seni & Budaya',
            'education_level' => 'S1',
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-08-31',
            'member_1'        => 'Kanjeng Raden Mas Dr. H. Soepomo, S.H. (Alm)|NIM/001/ISI|soepomo@test.com',
            'letter_number'   => '092/ISI/2026',
            'letter_date'     => '2026-06-01',
            'document_path'   => 'documents/test.pdf',
            'phone_number'    => '081234567890',
            'status'          => 'approved',
        ]);

        $service = app(CertificateService::class);
        $result = $service->generateZip($submission, ['KR-01']);

        $this->assertEquals(1, $result['memberCount']);
        $zipFile = Storage::disk('public')->path($result['zipStorePath']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipFile) === true);
        $this->assertEquals(1, $zip->numFiles);
        // Filename should be sanitized
        $this->assertMatchesRegularExpression('/^Sertifikat_[A-Za-z0-9_\-]+\.pdf$/', $zip->getNameIndex(0));
        $zip->close();
    }
}

