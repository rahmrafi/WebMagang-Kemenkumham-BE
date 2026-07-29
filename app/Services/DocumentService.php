<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Str;
use ZipArchive;

class DocumentService
{
    private const TEMPLATE_MAGANG     = 'template/magang/Format_Surat_Izin_Magang.docx';
    private const TEMPLATE_PENELITIAN = 'template/peneltian/Format_Surat_Izin_peneltian.docx';
    private const JENJANG_MAHASISWA   = ['D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    /**
     * Generate filled DOCX from template for a given submission.
     * Returns the path to the temp file; caller is responsible for sending & cleanup.
     */
    public function generateDocx(Submission $submission): array
    {
        $isPenelitian = $submission->type === 'penelitian';
        $templatePath = storage_path($isPenelitian ? self::TEMPLATE_PENELITIAN : self::TEMPLATE_MAGANG);

        if (!file_exists($templatePath)) {
            abort(404, 'File template surat tidak ditemukan di server.');
        }

        // 1. Copy template ke direktori temp
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . Str::uuid() . '.docx';
        copy($templatePath, $tempPath);

        // 2. Pastikan ekstensi ZipArchive tersedia
        if (!class_exists(ZipArchive::class)) {
            @unlink($tempPath);
            abort(500, 'PHP extension ZipArchive tidak terpasang atau belum diaktifkan.');
        }

        // 3. Buka DOCX (ZIP), baca & modifikasi document.xml
        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            abort(500, 'Gagal membuka file template DOCX.');
        }

        $xmlContent = $zip->getFromName('word/document.xml');
        $data       = $this->buildData($submission);
        $xmlContent = $this->fillPlaceholders($xmlContent, $data);

        // Validasi tidak ada placeholder yang tersisa
        $visibleText = strip_tags($xmlContent);
        if (preg_match('/\[(?:\.|\x{2026})+\d+\]/u', $visibleText, $unresolved)) {
            $zip->close();
            @unlink($tempPath);
            abort(500, 'Template masih memiliki placeholder yang tidak dikenali: ' . $unresolved[0]);
        }

        $zip->deleteName('word/document.xml');
        if (!$zip->addFromString('word/document.xml', $xmlContent)) {
            $zip->close();
            @unlink($tempPath);
            abort(500, 'Gagal menulis hasil generate ke file DOCX.');
        }
        $zip->close();

        // 4. Tentukan nama file output
        $namaKetua  = \App\Support\MemberParser::parseName($submission->member_1);
        $jenisSurat = $isPenelitian ? 'Penelitian' : 'Magang';
        $fileName   = 'Surat_Izin_' . $jenisSurat . '_'
            . Str::slug($namaKetua, '_')
            . '_' . now()->format('Y-m-d')
            . '.docx';

        return [
            'tempPath' => $tempPath,
            'fileName' => $fileName,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function buildData(Submission $submission): array
    {
        $members = [];
        for ($i = 1; $i <= 10; $i++) {
            $parsed = \App\Support\MemberParser::parse($submission->{"member_$i"});
            if ($parsed) {
                $members[] = $parsed;
            }
        }

        $edLevel = $submission->education_level ?? '';

        $labelNim = $submission->type === 'penelitian'
            ? 'Nomor Identitas'
            : match (true) {
                in_array($edLevel, ['SMA', 'SMK'])         => 'NISN',
                in_array($edLevel, self::JENJANG_MAHASISWA) => 'NIM',
                default                                     => 'Nomor Identitas',
            };

        $namaKetua    = $members[0]['nama'] ?? '';
        $namaKetuaDkk = count($members) > 1 ? $namaKetua . ', Dkk.' : $namaKetua;

        $isMahasiswa = in_array($edLevel, self::JENJANG_MAHASISWA);
        $isSiswa     = in_array($edLevel, ['SMA', 'SMK']);
        $prefix      = $isMahasiswa ? 'mahasiswa' : ($isSiswa ? 'siswa' : 'peserta');

        if ($isMahasiswa) {
            $studyProgram   = $this->toTitleCase($submission->study_program ?? '');
            $jenjangJurusan = trim($prefix . ' ' . $edLevel . ' ' . $studyProgram);
        } else {
            $namaSekolah = $this->toTitleCase($submission->institution ?? '');

            if ($edLevel === 'Umum/Profesional/Dosen') {
                $jenjangJurusan = trim($prefix . ' ' . $namaSekolah);
            } elseif ($edLevel !== '' && stripos(trim($namaSekolah), $edLevel) === 0) {
                $jenjangJurusan = trim($prefix . ' ' . $namaSekolah);
            } else {
                $jenjangJurusan = trim($prefix . ' ' . $edLevel . ' ' . $namaSekolah);
            }

            $studyProgram = trim($submission->study_program ?? '');
            if ($studyProgram !== '') {
                if (stripos($studyProgram, 'jurusan') === 0) {
                    $jenjangJurusan .= ' ' . $this->toTitleCase($studyProgram);
                } else {
                    $jenjangJurusan .= ' jurusan ' . $this->toTitleCase($studyProgram);
                }
            }
        }

        $membersTableXml = $this->buildMembersTableXml($members, $labelNim);

        $tglMulai = Carbon::parse($submission->start_date)->locale('id')->isoFormat('D MMMM');
        $tglAkhir = Carbon::parse($submission->end_date)->locale('id')->isoFormat('D MMMM YYYY');
        $periode  = $tglMulai . ' – ' . $tglAkhir;

        $settings    = Setting::where('key', 'pejabat_name')->pluck('value', 'key');
        $pejabatName = $settings['pejabat_name'] ?? 'R. Prasetyo Wibowo';

        return [
            'tgl_surat'            => Carbon::now()->locale('id')->isoFormat('D MMMM YYYY'),
            'nama_ketua_dkk'       => $namaKetuaDkk,
            'nama_instansi'        => $this->toTitleCase($submission->institution ?? ''),
            'kota_pengirim'        => $this->toTitleCase($submission->campus_city ?? ''),
            'nomor_surat'          => $submission->letter_number ?? '',
            'tgl_surat_permohonan' => Carbon::parse($submission->letter_date)->locale('id')->isoFormat('D MMMM YYYY'),
            'jenjang_jurusan'      => $jenjangJurusan,
            'members_table_xml'    => $membersTableXml,
            'periode_magang'       => $periode,
            'nama_pejabat'         => $pejabatName,
            'type'                 => $submission->type,
            'research_title'       => $submission->research_title,
        ];
    }

    private function fillPlaceholders(string $xml, array $data): string
    {
        if ($data['type'] === 'penelitian') {
            return $this->fillPenelitianPlaceholders($xml, $data);
        }
        return $this->fillMagangPlaceholders($xml, $data);
    }

    private function fillMagangPlaceholders(string $xml, array $data): string
    {
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = $this->replaceNumberedPlaceholder($xml, 1, $e($data['tgl_surat']));
        $xml = $this->replaceNumberedPlaceholder($xml, 2, ' ' . $e($data['nama_ketua_dkk']));
        $xml = $this->replaceNumberedPlaceholder($xml, 3, '[Nama Pejabat Pengirim Surat]');
        $xml = $this->replaceNumberedPlaceholder($xml, 4, $e($data['nama_instansi']));
        $xml = $this->replaceNumberedPlaceholder($xml, 5, 'di ' . $e($data['kota_pengirim']));
        $xml = $this->replaceNumberedPlaceholder($xml, 6, $e($data['nomor_surat']));
        $xml = $this->replaceNumberedPlaceholder($xml, 7, $e($data['tgl_surat_permohonan']));
        $xml = $this->replaceNumberedPlaceholder($xml, 8, $e($data['jenjang_jurusan']));
        $xml = $this->replaceNumberedPlaceholder($xml, 10, $e($data['periode_magang']));

        $xml = str_replace('[jabatan_pejabat]', 'Kepala Bagian Tata Usaha dan Umum', $xml);
        $xml = str_replace('a.n.  Kepala Kantor Wilayah,', 'a.n. Kepala Kantor Wilayah,', $xml);
        $xml = $this->replaceFlexible($xml, '[nama_pejabat]', $e($data['nama_pejabat']));
        $xml = $this->replaceFlexible($xml, 'R. Prasetyo Wibowo', $e($data['nama_pejabat']));
        $xml = $this->replaceFlexible($xml, 'Meirina Saeksi', $e($data['nama_pejabat']));

        $xml = preg_replace(
            '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*\[(?:\.|\x{2026})+9\](?:(?!<\/w:p>).)*<\/w:p>/su',
            $data['members_table_xml'],
            $xml
        );

        return $xml;
    }

    private function fillPenelitianPlaceholders(string $xml, array $data): string
    {
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = $this->replaceNumberedPlaceholder($xml, 1, $e($data['tgl_surat']));
        $xml = $this->replaceNumberedPlaceholder($xml, 2, $e($data['nama_ketua_dkk']));
        $xml = $this->replaceNumberedPlaceholder($xml, 3, '[Nama Pejabat Pengirim Surat]');
        $xml = $this->replaceNumberedPlaceholder($xml, 4, $e($data['nama_instansi']));
        $xml = $this->replaceNumberedPlaceholder($xml, 5, $e($data['kota_pengirim']));
        $xml = $this->replaceNumberedPlaceholder($xml, 6, $e($data['nomor_surat']));
        $xml = $this->replaceNumberedPlaceholder($xml, 7, $e($data['tgl_surat_permohonan']));

        $xml = preg_replace(
            '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*\[(?:\.|\x{2026})+8\](?:(?!<\/w:p>).)*<\/w:p>/su',
            $data['members_table_xml'],
            $xml
        );

        $xml = $this->replaceNumberedPlaceholder($xml, 9, $e($data['research_title'] ?? ''));

        $xml = str_replace('Kepala Bagian Umum dan Tata Usaha', 'Kepala Bagian Tata Usaha dan Umum', $xml);
        $xml = $this->replaceFlexible($xml, 'Meirina Saeksi', $e($data['nama_pejabat']));
        $xml = $this->replaceFlexible($xml, 'R. Prasetyo Wibowo', $e($data['nama_pejabat']));
        $xml = $this->replaceFlexible($xml, '[nama_pejabat]', $e($data['nama_pejabat']));

        return $xml;
    }

    private function replaceNumberedPlaceholder(string $xml, int $number, string $replacement): string
    {
        $t   = '(?:<[^>]+>|\s+)*';
        $dot = '(?:\.|\x{2026})';

        $pattern = '/\[' . $t . '(?:' . $dot . $t . ')+' . $number . $t . '\]/su';

        return preg_replace_callback($pattern, static fn() => $replacement, $xml);
    }

    private function replaceFlexible(string $xml, string $search, string $replacement): string
    {
        $t = '(?:<[^>]+>)*'; // Only match XML tags, avoid matching excessive whitespace if not needed, but Word puts tags anywhere.
        
        $chars = mb_str_split($search);
        $patternParts = [];
        foreach ($chars as $char) {
            if ($char === ' ') {
                $patternParts[] = '(?:<[^>]+>|\s+)+';
            } else {
                $patternParts[] = preg_quote($char, '/');
            }
        }
        $pattern = '/' . implode($t, $patternParts) . '/sui';

        return preg_replace($pattern, $replacement, $xml) ?? $xml;
    }

    private function buildMembersTableXml(array $members, string $labelNim): string
    {
        $border = fn(string $side) =>
            '<w:' . $side . ' w:val="none" w:sz="0" w:space="0" w:color="auto"/>';

        $tbl  = '<w:tbl>';
        $tbl .= '<w:tblPr>';
        $tbl .= '<w:tblW w:w="0" w:type="auto"/>';
        $tbl .= '<w:tblInd w:w="360" w:type="dxa"/>';
        $tbl .= '<w:tblBorders>';
        $tbl .= $border('top') . $border('left') . $border('bottom')
              . $border('right') . $border('insideH') . $border('insideV');
        $tbl .= '</w:tblBorders>';
        $tbl .= '</w:tblPr>';
        $tbl .= '<w:tblGrid>';
        $tbl .= '<w:gridCol w:w="2400"/>';
        $tbl .= '<w:gridCol w:w="3100"/>';
        $tbl .= '</w:tblGrid>';

        foreach ($members as $idx => $member) {
            $no   = $idx + 1;
            $tbl .= $this->buildTableRow($no . '.  Nama', ': ' . htmlspecialchars($member['nama'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            $tbl .= $this->buildTableRow('     ' . $labelNim, ': ' . htmlspecialchars($member['nim'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        $tbl .= '</w:tbl>';
        return $tbl;
    }

    private function buildTableRow(string $col1, string $col2): string
    {
        $cell = static fn(string $text, int $width): string =>
            '<w:tc>'
            . '<w:tcPr>'
            . '<w:tcW w:w="' . $width . '" w:type="dxa"/>'
            . '<w:tcMar>'
            . '<w:left w:w="108" w:type="dxa"/>'
            . '<w:right w:w="108" w:type="dxa"/>'
            . '</w:tcMar>'
            . '</w:tcPr>'
            . '<w:p>'
            . '<w:r>'
            . '<w:rPr><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>'
            . '<w:t xml:space="preserve">' . $text . '</w:t>'
            . '</w:r>'
            . '</w:p>'
            . '</w:tc>';

        return '<w:tr>'
            . $cell($col1, 2400)
            . $cell($col2, 3100)
            . '</w:tr>';
    }

    public function toTitleCase(?string $value): string
    {
        $val = ucwords(strtolower(trim($value ?? '')));
        $val = preg_replace('/\bSmk\b/i', 'SMK', $val);
        $val = preg_replace('/\bSma\b/i', 'SMA', $val);
        $val = preg_replace('/\bSmp\b/i', 'SMP', $val);
        $val = preg_replace('/\bSd\b/i', 'SD', $val);
        $val = preg_replace('/\bPt\b/i', 'PT', $val);
        $val = preg_replace('/\bCv\b/i', 'CV', $val);
        return $val;
    }

    public function parseMember(?string $memberStr): ?array
    {
        if (!$memberStr || trim($memberStr) === '') {
            return null;
        }
        $parts = explode('|', $memberStr);
        $nama  = trim($parts[0] ?? '');
        if ($nama === '') {
            return null;
        }
        return [
            'nama'  => ucwords(strtolower($nama)),
            'nim'   => trim($parts[1] ?? ''),
            'email' => trim($parts[2] ?? ''),
        ];
    }

    public function parseNama(?string $memberStr): string
    {
        $parts = explode('|', (string) $memberStr);
        $nama  = trim($parts[0] ?? '');
        return $nama !== '' ? ucwords(strtolower($nama)) : 'pendaftar';
    }
}
