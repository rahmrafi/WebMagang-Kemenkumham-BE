<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DocumentController extends Controller
{
    private const TEMPLATE_MAGANG = 'template/magang/Format_Surat_Izin_Magang.docx';
    private const TEMPLATE_PENELITIAN = 'template/peneltian/Format_Surat_Izin_peneltian.docx';

    // Jenjang yang diklasifikasikan sebagai "mahasiswa"
    private const JENJANG_MAHASISWA = ['D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    /**
     * GET /api/admin/submissions/{submission}/generate-template
     * Copy template DOCX, isi semua placeholder, lalu download.
     */
    public function generateTemplate(Submission $submission): BinaryFileResponse
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

        // 2. Pastikan ekstensi ZipArchive tersedia di runtime.
        if (!class_exists(ZipArchive::class)) {
            @unlink($tempPath);
            abort(500, 'PHP extension ZipArchive tidak terpasang atau belum diaktifkan. Aktifkan ekstensi php_zip di php.ini.');
        }

        // 3. Buka file DOCX (ZIP), baca & modifikasi document.xml
        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            abort(500, 'Gagal membuka file template DOCX.');
        }

        $xmlContent = $zip->getFromName('word/document.xml');
        $data       = $this->buildData($submission);
        $xmlContent = $this->fillPlaceholders($xmlContent, $data);

        // Jangan kirim dokumen setengah jadi apabila format template berubah lagi.
        // strip_tags menyatukan placeholder yang mungkin dipecah ke beberapa run Word.
        $visibleText = strip_tags($xmlContent);
        if (preg_match('/\[(?:\.|\x{2026})+\d+\]/u', $visibleText, $unresolved)) {
            $zip->close();
            @unlink($tempPath);
            abort(500, 'Template masih memiliki placeholder yang tidak dikenali: ' . $unresolved[0]);
        }

        // Hapus entry lama terlebih dahulu agar tidak ada dua document.xml di arsip DOCX.
        $zip->deleteName('word/document.xml');
        if (!$zip->addFromString('word/document.xml', $xmlContent)) {
            $zip->close();
            @unlink($tempPath);
            abort(500, 'Gagal menulis hasil generate ke file DOCX.');
        }
        $zip->close();

        // 3. Tentukan nama file output
        $namaKetua = $this->parseNama($submission->member_1);
        $jenisSurat = $isPenelitian ? 'Penelitian' : 'Magang';
        $fileName  = 'Surat_Izin_' . $jenisSurat . '_'
            . Str::slug($namaKetua, '_')
            . '_' . now()->format('Y-m-d')
            . '.docx';

        // 4. Return file sebagai download, hapus temp setelah selesai
        return response()
            ->download($tempPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Bangun array data dari submission untuk mengisi placeholder surat.
     */
    private function buildData(Submission $submission): array
    {
        // Parse semua member dari format "nama|nim|email"
        $members = [];
        for ($i = 1; $i <= 10; $i++) {
            $parsed = $this->parseMember($submission->{"member_$i"});
            if ($parsed) {
                $members[] = $parsed;
            }
        }

        $edLevel = $submission->education_level ?? '';

        // Label NIM vs NISN berdasarkan jenjang pendidikan atau tipe submission
        if ($submission->type === 'penelitian') {
            $labelNim = 'Nomor Identitas';
        } else {
            $labelNim = match (true) {
                in_array($edLevel, ['SMA', 'SMK']) => 'NISN',
                in_array($edLevel, self::JENJANG_MAHASISWA) => 'NIM',
                default => 'Nomor Identitas',
            };
        }

        // [2] Nama ketua (ucwords) + ", Dkk." jika lebih dari 1 anggota
        $namaKetua    = $members[0]['nama'] ?? '';
        $namaKetuaDkk = count($members) > 1
            ? $namaKetua . ', Dkk.'
            : $namaKetua;

        // [8] Format jenjang:
        //   Mahasiswa (D2/D3/D4/S1/S2/S3) → "mahasiswa S1 Informatika"  (pakai study_program)
        //   Siswa (SMA/SMK)               → "siswa SMK Muhammadiyah Sidoarjo" (pakai institution)
        $isMahasiswa    = in_array($edLevel, self::JENJANG_MAHASISWA);
        $isSiswa        = in_array($edLevel, ['SMA', 'SMK']);
        $prefix         = $isMahasiswa ? 'mahasiswa' : ($isSiswa ? 'siswa' : 'peserta');
        if ($isMahasiswa) {
            $studyProgram   = $this->toTitleCase($submission->study_program ?? '');
            $jenjangJurusan = trim($prefix . ' ' . $edLevel . ' ' . $studyProgram);
        } else {
            // SMA/SMK dan peserta umum: tampilkan nama institusi bukan program studi.
            $namaSekolah    = $this->toTitleCase($submission->institution ?? '');
            $jenjangJurusan = trim($prefix . ' ' . $edLevel . ' ' . $namaSekolah);
        }

        // [9] Pre-generate Word XML table untuk daftar anggota
        $membersTableXml = $this->buildMembersTableXml($members, $labelNim);

        // [10] Format periode: "6 Juli – 28 Agustus 2026"
        $tglMulai = Carbon::parse($submission->start_date)->locale('id')->isoFormat('D MMMM');
        $tglAkhir = Carbon::parse($submission->end_date)->locale('id')->isoFormat('D MMMM YYYY');
        $periode  = $tglMulai . ' – ' . $tglAkhir; // en-dash (–)

        // Fetch settings for pejabat
        $settings = Setting::where('key', 'pejabat_name')->pluck('value', 'key');
        $pejabatName = $settings['pejabat_name'] ?? 'R. Prasetyo Wibowo';

        return [
            'tgl_surat'            => Carbon::now()->locale('id')->isoFormat('D MMMM YYYY'),
            'nama_ketua_dkk'       => $namaKetuaDkk,
            'nama_instansi'        => $this->toTitleCase($submission->institution ?? ''),
            'kota_pengirim'        => $this->toTitleCase($submission->campus_city   ?? ''),
            'nomor_surat'          => $submission->letter_number ?? '',
            'tgl_surat_permohonan' => Carbon::parse($submission->letter_date)->locale('id')->isoFormat('D MMMM YYYY'),
            'jenjang_jurusan'      => $jenjangJurusan,
            'members_table_xml'    => $membersTableXml,  // raw XML, injected langsung
            'periode_magang'       => $periode,
            'nama_pejabat'         => $pejabatName,
            'type'                 => $submission->type,
            'research_title'       => $submission->research_title,
        ];
    }

    /**
     * Ganti semua placeholder di XML document dengan nilai nyata.
     *
     * Catatan teknis:
     * - Placeholder UTUH: ada dalam satu <w:t> → str_replace langsung.
     * - Placeholder TERPECAH: dibagi ke beberapa <w:r> oleh Word spellcheck/proofErr
     *   → pakai preg_replace dengan flag /s untuk span multi-baris XML.
     */
    private function fillPlaceholders(string $xml, array $data): string
    {
        if ($data['type'] === 'penelitian') {
            return $this->fillPenelitianPlaceholders($xml, $data);
        }
        return $this->fillMagangPlaceholders($xml, $data);
    }

    private function fillMagangPlaceholders(string $xml, array $data): string
    {
        // Helper: escape nilai untuk konteks XML
        $e = static fn(string $v): string =>
            htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // Jumlah titik pada template pernah berubah dan sebagian memakai elipsis Unicode.
        // Cocokkan keduanya agar generator tidak bergantung pada jumlah titik tertentu.
        $xml = $this->replaceNumberedPlaceholder($xml, 1, $e($data['tgl_surat']));
        $xml = $this->replaceNumberedPlaceholder($xml, 2, ' ' . $e($data['nama_ketua_dkk']));
        $xml = $this->replaceNumberedPlaceholder($xml, 3, '[Nama Pejabat Pengirim Surat]');
        $xml = $this->replaceNumberedPlaceholder($xml, 4, $e($data['nama_instansi']));
        $xml = $this->replaceNumberedPlaceholder($xml, 5, 'di ' . $e($data['kota_pengirim']));
        $xml = $this->replaceNumberedPlaceholder($xml, 6, $e($data['nomor_surat']));
        $xml = $this->replaceNumberedPlaceholder($xml, 7, $e($data['tgl_surat_permohonan']));
        $xml = $this->replaceNumberedPlaceholder($xml, 8, $e($data['jenjang_jurusan']));
        $xml = $this->replaceNumberedPlaceholder($xml, 10, $e($data['periode_magang']));

        // Bagian Atas Tanda Tangan (Jabatan Pejabat)
        // Hardcode sesuai permintaan user:
        $xml = str_replace('[jabatan_pejabat]', 'Kepala Bagian Tata Usaha dan Umum', $xml);
        
        // Perbaiki spasi ganda pada "a.n.  Kepala Kantor Wilayah" menjadi spasi tunggal agar sejajar
        $xml = str_replace('a.n.  Kepala Kantor Wilayah,', 'a.n. Kepala Kantor Wilayah,', $xml);

        // Dinamis Nama Pejabat
        $xml = str_replace('[nama_pejabat]', $e($data['nama_pejabat']), $xml);

        // [9] Daftar anggota — ganti seluruh <w:p> yang mengandung placeholder
        //     dengan Word XML table berkotak (2 kolom: label | nilai)
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

        // 8. [......8] -> Table members
        $xml = preg_replace(
            '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*\[(?:\.|\x{2026})+8\](?:(?!<\/w:p>).)*<\/w:p>/su',
            $data['members_table_xml'],
            $xml
        );

        $xml = $this->replaceNumberedPlaceholder($xml, 9, $e($data['research_title'] ?? ''));

        // Signature adjustments
        $xml = str_replace('Kepala Bagian Umum dan Tata Usaha', 'Kepala Bagian Tata Usaha dan Umum', $xml);
        $xml = str_replace('Meirina Saeksi', $e($data['nama_pejabat']), $xml);

        return $xml;
    }

    /**
     * Ganti placeholder angka dengan jumlah titik bebas, termasuk elipsis Unicode.
     */
    private function replaceNumberedPlaceholder(string $xml, int $number, string $replacement): string
    {
        // Allow XML tags (<...>) and whitespaces between characters
        $t = '(?:<[^>]+>|\s+)*';
        // Dot or Unicode ellipsis
        $dot = '(?:\.|\x{2026})';
        
        $pattern = '/\[' . $t . '(?:' . $dot . $t . ')+' . $number . $t . '\]/su';

        return preg_replace_callback($pattern, static fn() => $replacement, $xml);
    }

    /**
     * Generate Word XML untuk tabel daftar anggota (2 kolom berkotak).
     * Setiap anggota = 2 baris: baris Nama + baris NIM/NISN.
     */
    private function buildMembersTableXml(array $members, string $labelNim): string
    {
        // Border none = tabel invisible (layout rapi, garis tidak muncul)
        $border = fn(string $side) =>
            '<w:' . $side . ' w:val="none" w:sz="0" w:space="0" w:color="auto"/>';

        $tbl  = '<w:tbl>';
        $tbl .= '<w:tblPr>';
        $tbl .= '<w:tblW w:w="0" w:type="auto"/>';
        $tbl .= '<w:tblInd w:w="360" w:type="dxa"/>'; // indent sejajar body text
        $tbl .= '<w:tblBorders>';
        $tbl .= $border('top') . $border('left') . $border('bottom')
              . $border('right') . $border('insideH') . $border('insideV');
        $tbl .= '</w:tblBorders>';
        $tbl .= '</w:tblPr>';
        $tbl .= '<w:tblGrid>';
        $tbl .= '<w:gridCol w:w="2400"/>'; // kolom label: lebar ditambah agar "Nomor Identitas" tidak wrap
        $tbl .= '<w:gridCol w:w="3100"/>'; // kolom nilai
        $tbl .= '</w:tblGrid>';

        foreach ($members as $idx => $member) {
            $no = $idx + 1;

            // Baris 1: nomor + Nama
            $tbl .= $this->buildTableRow(
                $no . '.  Nama',
                ': ' . htmlspecialchars($member['nama'], ENT_XML1 | ENT_QUOTES, 'UTF-8')
            );

            // Baris 2: NIM / NISN
            $tbl .= $this->buildTableRow(
                '     ' . $labelNim,
                ': ' . htmlspecialchars($member['nim'], ENT_XML1 | ENT_QUOTES, 'UTF-8')
            );
        }

        $tbl .= '</w:tbl>';
        return $tbl;
    }

    /**
     * Buat satu baris tabel Word XML dengan 2 kolom.
     */
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

    /**
     * Normalisasi teks bebas: lowercase semua lalu ucwords tiap kata.
     * Berlaku untuk: nama instansi, kota, jurusan, dll.
     */
    private function toTitleCase(?string $value): string
    {
        return ucwords(strtolower(trim($value ?? '')));
    }

    /**
     * Parse string pipe-separated "nama|nim|email" menjadi array.
     * Nama di-ucwords untuk konsistensi huruf kapital.
     */
    private function parseMember(?string $memberStr): ?array
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
            'nama'  => ucwords(strtolower($nama)), // normalisasi huruf kapital
            'nim'   => trim($parts[1] ?? ''),
            'email' => trim($parts[2] ?? ''),
        ];
    }

    /**
     * Ambil hanya bagian nama dari string "nama|nim|email".
     */
    private function parseNama(?string $memberStr): string
    {
        $parts = explode('|', (string) $memberStr);
        $nama  = trim($parts[0] ?? '');
        return $nama !== '' ? ucwords(strtolower($nama)) : 'pendaftar';
    }
}
