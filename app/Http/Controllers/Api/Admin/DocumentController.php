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

    // Jenjang yang diklasifikasikan sebagai "mahasiswa"
    private const JENJANG_MAHASISWA = ['D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    /**
     * GET /api/admin/submissions/{submission}/generate-template
     * Copy template DOCX, isi semua placeholder, lalu download.
     */
    public function generateTemplate(Submission $submission): BinaryFileResponse
    {
        $templatePath = storage_path(self::TEMPLATE_MAGANG);

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

        // 2. Buka file DOCX (ZIP), baca & modifikasi document.xml
        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            abort(500, 'Gagal membuka file template DOCX.');
        }

        $xmlContent = $zip->getFromName('word/document.xml');
        $data       = $this->buildData($submission);
        $xmlContent = $this->fillPlaceholders($xmlContent, $data);

        $zip->addFromString('word/document.xml', $xmlContent);
        $zip->close();

        // 3. Tentukan nama file output
        $namaKetua = $this->parseNama($submission->member_1);
        $fileName  = 'Surat_Izin_Magang_'
            . Str::slug($namaKetua, '_')
            . '_' . now()->format('Y-m-d')
            . '.docx';

        // 4. Return file sebagai download, hapus temp setelah selesai
        return response()
            ->download($tempPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
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

        // Label NIM vs NISN berdasarkan jenjang pendidikan
        $labelNim = in_array($edLevel, ['SMA', 'SMK']) ? 'NISN' : 'NIM';

        // [2] Nama ketua (ucwords) + ", Dkk." jika lebih dari 1 anggota
        $namaKetua    = $members[0]['nama'] ?? '';
        $namaKetuaDkk = count($members) > 1
            ? $namaKetua . ', Dkk.'
            : $namaKetua;

        // [8] Format jenjang:
        //   Mahasiswa (D2/D3/D4/S1/S2/S3) → "mahasiswa S1 Informatika"  (pakai study_program)
        //   Siswa (SMA/SMK)               → "siswa SMK Muhammadiyah Sidoarjo" (pakai institution)
        $isMahasiswa    = in_array($edLevel, self::JENJANG_MAHASISWA);
        $prefix         = $isMahasiswa ? 'mahasiswa' : 'siswa';
        if ($isMahasiswa) {
            $studyProgram   = $this->toTitleCase($submission->study_program ?? '');
            $jenjangJurusan = trim($prefix . ' ' . $edLevel . ' ' . $studyProgram);
        } else {
            // SMA / SMK: tampilkan nama sekolah bukan program studi
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
        // Helper: escape nilai untuk konteks XML
        $e = static fn(string $v): string =>
            htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // ── Placeholder UTUH — ada dalam satu <w:t> ──────────────────────────

        // [1] Tanggal pembuatan surat (hari ini saat generate)
        $xml = str_replace('[....1]', $e($data['tgl_surat']), $xml);

        // [2] Nama Ketua + Dkk — template: "a.n.[.....2]" → tambah spasi di depan
        $xml = str_replace('[.....2]', ' ' . $e($data['nama_ketua_dkk']), $xml);

        // [3] Jabatan pejabat pengirim surat — tampilkan sebagai placeholder manual
        //     Muncul di baris "Yth." dan di badan surat "sehubungan dengan surat"
        $xml = str_replace('[.....3]', '[Nama Pejabat Pengirim Surat]', $xml);

        // Bagian Atas Tanda Tangan (Jabatan Pejabat)
        // Hardcode sesuai permintaan user:
        $xml = str_replace('[jabatan_pejabat]', 'Kepala Bagian Tata Usaha dan Umum', $xml);
        
        // Perbaiki spasi ganda pada "a.n.  Kepala Kantor Wilayah" menjadi spasi tunggal agar sejajar
        $xml = str_replace('a.n.  Kepala Kantor Wilayah,', 'a.n. Kepala Kantor Wilayah,', $xml);

        // Dinamis Nama Pejabat
        $xml = str_replace('[nama_pejabat]', $e($data['nama_pejabat']), $xml);

        // [5] Kota pengirim surat — tambah prefix "di" sesuai format surat resmi
        $xml = str_replace('[....5]', 'di ' . $e($data['kota_pengirim']), $xml);

        // [6] Nomor surat permohonan
        // Di raw XML menggunakan Unicode ellipsis U+2026 (…) bukan titik ASCII
        $ellipsis = "\xE2\x80\xA6"; // UTF-8 encoding U+2026
        $xml = preg_replace(
            '/\[[\.' . preg_quote($ellipsis, '/') . ']+6\]/u',
            $e($data['nomor_surat']),
            $xml
        );

        // [9] Daftar anggota — ganti seluruh <w:p> yang mengandung placeholder
        //     dengan Word XML table berkotak (2 kolom: label | nilai)
        $xml = preg_replace(
            '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*\[\.+9\](?:(?!<\/w:p>).)*<\/w:p>/s',
            $data['members_table_xml'],
            $xml
        );

        // [10] Periode magang — dalam kalimat "terhitung mulai tanggal [....10],"
        $xml = str_replace('[....10]', $e($data['periode_magang']), $xml);

        // ── Placeholder TERPECAH — dibagi ke beberapa <w:r> oleh Word ─────────

        // [4] Nama Instansi
        // XML: <w:t>[</w:t> → proofErr → <w:t>…..</w:t> → proofErr → <w:t>4]</w:t>
        $xml = preg_replace(
            '/<w:t>\[<\/w:t><\/w:r>(?:(?!<\/w:p>).)*?<w:t[^>]*>4\]<\/w:t>/s',
            '<w:t xml:space="preserve">' . $e($data['nama_instansi']) . '</w:t>',
            $xml
        );

        // [7] Tanggal surat permohonan
        // XML: <w:t>tanggal [</w:t> → dots → <w:t>7</w:t> → <w:t>] ,</w:t>
        $xml = preg_replace(
            '/tanggal \[<\/w:t><\/w:r>(?:(?!<\/w:p>).)*?<w:t[^>]*>\] ,<\/w:t>/s',
            'tanggal ' . $e($data['tgl_surat_permohonan']) . ', </w:t>',
            $xml
        );

        // [8] Jenjang / jurusan
        // XML: <w:t>...menerima [....8</w:t> → proofErr → <w:t>]  dengan</w:t>
        $xml = str_replace(
            '[....8</w:t>',
            $e($data['jenjang_jurusan']) . '</w:t>',
            $xml
        );
        $xml = str_replace(
            '<w:t>]  dengan</w:t>',
            '<w:t xml:space="preserve"> dengan</w:t>',
            $xml
        );

        return $xml;
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
        $tbl .= '<w:gridCol w:w="1700"/>'; // kolom label: ~3cm
        $tbl .= '<w:gridCol w:w="3800"/>'; // kolom nilai: ~7cm
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
            . $cell($col1, 1700)
            . $cell($col2, 3800)
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
