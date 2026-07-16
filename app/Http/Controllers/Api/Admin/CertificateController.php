<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use ZipArchive;

class CertificateController extends Controller
{
    // ── Ambil settings sertifikat (template path + fields) ────────────────────
    public function getSettings(): JsonResponse
    {
        $templatePath = Setting::where('key', 'certificate_template_path')->value('value');
        $fieldsRaw    = Setting::where('key', 'certificate_fields')->value('value');
        $fields       = $fieldsRaw ? json_decode($fieldsRaw, true) : [];

        $templateUrl = null;
        if ($templatePath && Storage::disk('public')->exists($templatePath)) {
            $templateUrl = Storage::disk('public')->url($templatePath);
        }

        return response()->json([
            'data' => [
                'template_path' => $templatePath,
                'template_url'  => $templateUrl,
                'fields'        => $fields,
            ],
        ]);
    }

    // ── Upload file PDF template baru ─────────────────────────────────────────
    public function uploadTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'template' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        // Hapus template lama jika ada
        $oldPath = Setting::where('key', 'certificate_template_path')->value('value');
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('template')->store('certificates/template', 'public');

        Setting::updateOrCreate(
            ['key' => 'certificate_template_path'],
            ['value' => $path]
        );

        return response()->json([
            'message'      => 'Template berhasil diupload',
            'data'         => [
                'template_path' => $path,
                'template_url'  => Storage::disk('public')->url($path),
            ],
        ]);
    }

    // ── Proxy PDF template (agar tidak kena CORS) ─────────────────────────────
    public function previewTemplate()
    {
        $templatePath = Setting::where('key', 'certificate_template_path')->value('value');

        if (!$templatePath || !Storage::disk('public')->exists($templatePath)) {
            return response()->json(['message' => 'Template tidak ditemukan'], 404);
        }

        $fullPath = Storage::disk('public')->path($templatePath);
        $filename = basename($templatePath);

        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache',
        ]);
    }

    // ── Hapus template PDF ────────────────────────────────────────────────────
    public function deleteTemplate(): JsonResponse
    {
        $templatePath = Setting::where('key', 'certificate_template_path')->value('value');

        if ($templatePath && Storage::disk('public')->exists($templatePath)) {
            Storage::disk('public')->delete($templatePath);
        }

        Setting::where('key', 'certificate_template_path')->update(['value' => null]);

        return response()->json(['message' => 'Template berhasil dihapus']);
    }

    // ── Simpan posisi field (JSON koordinat kotak) ────────────────────────────
    public function saveFields(Request $request): JsonResponse
    {
        $request->validate([
            'fields'             => ['required', 'array'],
            'fields.*.id'        => ['required', 'string'],
            'fields.*.label'     => ['required', 'string'],
            'fields.*.x'         => ['required', 'numeric', 'min:0', 'max:100'],
            'fields.*.y'         => ['required', 'numeric', 'min:0', 'max:100'],
            'fields.*.font_size' => ['required', 'integer', 'min:6', 'max:72'],
            'fields.*.font_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fields.*.width' => ['sometimes', 'numeric', 'min:5', 'max:100'],
            'fields.*.text_align' => ['sometimes', 'in:left,center,right'],
            'fields.*.font_family' => ['sometimes', 'in:helvetica,times,georgia,montserrat,poppins,playfair,dancing-script,great-vibes'],
            'fields.*.font_weight' => ['sometimes', 'integer', 'in:200,300,400,500,600,700,800'],
            'fields.*.font_style' => ['sometimes', 'in:normal,italic'],
            'fields.*.preview_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.*.preview_width' => ['sometimes', 'numeric', 'min:200', 'max:5000'],
        ]);

        // Konfigurasi lama tetap dapat disimpan. Admin juga bebas menggunakan
        // hanya field yang dibutuhkan tanpa harus menambahkan semua field.
        $fields = collect($request->fields)->map(function (array $field) {
            return [
                ...$field,
                'font_color' => $field['font_color'] ?? '#1a1a1a',
                'width' => $field['width'] ?? 40,
                'text_align' => $field['text_align'] ?? 'center',
                'font_family' => $field['font_family'] ?? 'helvetica',
                'font_weight' => $field['font_weight'] ?? 400,
                'font_style' => $field['font_style'] ?? 'normal',
                'preview_text' => $field['preview_text'] ?? $field['label'],
                'preview_width' => $field['preview_width'] ?? 1024,
            ];
        })->values()->all();

        Setting::updateOrCreate(
            ['key' => 'certificate_fields'],
            ['value' => json_encode($fields)]
        );

        return response()->json([
            'message' => 'Posisi field berhasil disimpan',
            'data'    => $fields,
        ]);
    }

    // ── Generate sertifikat (1 PDF per member) → ZIP ─────────────────────────
    public function generate(Submission $submission): JsonResponse
    {
        if ($submission->status !== 'approved') {
            return response()->json(['message' => 'Submission belum disetujui'], 422);
        }

        // Ambil template PDF
        $templatePath = Setting::where('key', 'certificate_template_path')->value('value');
        if (!$templatePath || !Storage::disk('public')->exists($templatePath)) {
            return response()->json(['message' => 'Template sertifikat belum diupload'], 422);
        }

        // Ambil fields konfigurasi
        $fieldsRaw = Setting::where('key', 'certificate_fields')->value('value');
        $fields    = $fieldsRaw ? json_decode($fieldsRaw, true) : [];
        if (empty($fields)) {
            return response()->json(['message' => 'Posisi field sertifikat belum dikonfigurasi'], 422);
        }

        $templateFullPath = Storage::disk('public')->path($templatePath);

        // Kumpulkan semua member yang tidak null
        $members = [];
        for ($i = 1; $i <= 10; $i++) {
            $memberRaw = $submission->{"member_{$i}"};
            if (!$memberRaw) continue;
            $parts = explode('|', $memberRaw);
            $members[] = [
                'nama'      => $parts[0] ?? 'Peserta',
                'nim'       => $parts[1] ?? '',
                'institusi' => $submission->institution,
                'prodi'     => $submission->study_program ?? '',
                'periode'   => $this->formatPeriode($submission->start_date, $submission->end_date),
                'nomor_sertifikat' => $this->generateNomorSertifikat($submission->id, $i),
                'tanggal_terbit'   => now()->locale('id')->isoFormat('D MMMM YYYY'),
            ];
        }

        if (empty($members)) {
            return response()->json(['message' => 'Tidak ada anggota yang terdaftar'], 422);
        }

        // Buat folder temp untuk PDF individual
        $tempDir = storage_path("app/temp/cert_{$submission->id}_" . time());
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

        $pdfFiles = [];

        foreach ($members as $index => $member) {
            $pdfPath = "{$tempDir}/Sertifikat_" . $this->sanitizeFilename($member['nama']) . ".pdf";

            $this->generatePdfForMember(
                templatePath: $templateFullPath,
                fields:       $fields,
                memberData:   $member,
                outputPath:   $pdfPath,
            );

            $pdfFiles[] = $pdfPath;
        }

        // Buat ZIP
        $zipFilename  = "Sertifikat_{$submission->id}_" . now()->format('Ymd_His') . ".zip";
        $zipStorePath = "certificates/generated/{$zipFilename}";
        $zipFullPath  = Storage::disk('public')->path($zipStorePath);

        if (!is_dir(dirname($zipFullPath))) {
            mkdir(dirname($zipFullPath), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Gagal membuat file ZIP'], 500);
        }

        foreach ($pdfFiles as $pdfFile) {
            $zip->addFile($pdfFile, basename($pdfFile));
        }
        $zip->close();

        // Hapus PDF temp
        foreach ($pdfFiles as $pdfFile) {
            @unlink($pdfFile);
        }
        @rmdir($tempDir);

        // Hapus ZIP lama jika ada
        if ($submission->certificate_zip_path && Storage::disk('public')->exists($submission->certificate_zip_path)) {
            Storage::disk('public')->delete($submission->certificate_zip_path);
        }

        // Simpan path ZIP ke submission
        $submission->update([
            'certificate_zip_path'      => $zipStorePath,
            'certificate_generated_at'  => now(),
        ]);

        return response()->json([
            'message'      => 'Sertifikat berhasil di-generate',
            'data'         => [
                'zip_url'              => Storage::disk('public')->url($zipStorePath),
                'zip_filename'         => $zipFilename,
                'member_count'         => count($members),
                'generated_at'         => now()->toISOString(),
            ],
        ]);
    }

    // ── Re-download ZIP yang sudah ada ────────────────────────────────────────
    public function download(Submission $submission)
    {
        if (!$submission->certificate_zip_path || !Storage::disk('public')->exists($submission->certificate_zip_path)) {
            return response()->json(['message' => 'File sertifikat tidak ditemukan. Silakan generate ulang.'], 404);
        }

        $fullPath = Storage::disk('public')->path($submission->certificate_zip_path);
        $filename = basename($submission->certificate_zip_path);

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    // ── Helper: generate 1 PDF dari template + data member ───────────────────
    private function generatePdfForMember(
        string $templatePath,
        array  $fields,
        array  $memberData,
        string $outputPath,
    ): void {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);

        // Import halaman pertama template
        $pageCount = $pdf->setSourceFile($templatePath);
        $tpl       = $pdf->importPage(1);
        $size      = $pdf->getTemplateSize($tpl);

        $pageW = $size['width'];
        $pageH = $size['height'];

        $pdf->AddPage($pageW > $pageH ? 'L' : 'P', [$pageW, $pageH]);
        $pdf->useTemplate($tpl, 0, 0, $pageW, $pageH);

        // Tulis setiap field di atas template
        foreach ($fields as $field) {
            $value = $memberData[$field['id']] ?? '';
            if ($value === '') continue;

            // Konversi persen → mm
            $xMm = ($field['x'] / 100) * $pageW;
            $yMm = ($field['y'] / 100) * $pageH;

            // Konversi px preview ke satuan halaman (mm/pt)
            $previewWidth = max(200, (float) ($field['preview_width'] ?? 1024));
            
            // Lebar halaman PDF dalam point (pt) -> 1 mm = 2.83465 pt
            $pageWPt = $pageW * 2.83465;

            // Rasio font size di browser terhadap lebar gambar preview
            $fontRatio = ($field['font_size'] ?? 12) / $previewWidth;
            
            // Ukuran font riil di PDF agar persis proporsinya dengan yang dilihat user
            $fontSize = max(5, (int) round($fontRatio * $pageWPt));

            // Di frontend, AutoFitText menggunakan lineHeight: 1.
            // Kita atur tinggi cell sama persis dengan tinggi font (1.0 * fontSize).
            $cellHeightMm = ($fontSize * 1.0) / 2.83465;

            // Parse warna hex → RGB
            $color = ltrim($field['font_color'] ?? '#000000', '#');
            $r = hexdec(substr($color, 0, 2));
            $g = hexdec(substr($color, 2, 2));
            $b = hexdec(substr($color, 4, 2));

            $fontMap = [
                'helvetica' => 'helvetica',
                'times' => 'times',
                'georgia' => 'times',
                'montserrat' => 'helvetica',
                'poppins' => 'poppins',
                'playfair' => 'times',
                'dancing-script' => 'dancing-script',
                'great-vibes' => 'great-vibes',
            ];
            $fontFamily = $field['font_family'] ?? 'helvetica';
            $font = $fontMap[$fontFamily] ?? 'helvetica';

            $fontStyle = '';
            if (($field['font_weight'] ?? 400) >= 600) $fontStyle .= 'B';
            if (($field['font_style'] ?? 'normal') === 'italic') $fontStyle .= 'I';

            // Sematkan font tulisan bersambung agar hasil PDF tidak kembali ke Times.
            $customFontFiles = [
                'great-vibes' => resource_path('fonts/GreatVibes-Regular.ttf'),
                'dancing-script' => resource_path('fonts/DancingScript-Variable.ttf'),
                'poppins' => resource_path('fonts/Poppins-Regular.ttf'),
            ];
            if (isset($customFontFiles[$fontFamily]) && is_file($customFontFiles[$fontFamily])) {
                $embeddedFont = \TCPDF_FONTS::addTTFfont(
                    $customFontFiles[$fontFamily],
                    'TrueTypeUnicode',
                    '',
                    96,
                );
                if ($embeddedFont !== false) {
                    $font = $embeddedFont;
                }
            }
            $widthMm = (($field['width'] ?? 40) / 100) * $pageW;
            $align = match ($field['text_align'] ?? 'left') {
                'center' => 'C',
                'right' => 'R',
                default => 'L',
            };

            // Pertahankan satu baris: kecilkan font sampai teks muat dalam area.
            $pdf->SetFont($font, $fontStyle, $fontSize);
            // 6pt setara dengan batas minimum preview sebesar 8px.
            while ($fontSize > 6 && $pdf->GetStringWidth($value) > $widthMm) {
                $fontSize--;
                $pdf->SetFont($font, $fontStyle, $fontSize);
            }
            $pdf->SetTextColor($r, $g, $b);
            $pdf->SetCellPadding(0);
            // Gambar teks dengan valign='M' untuk mencocokkan line-height browser.
            $pdf->SetXY($xMm, $yMm);
            $pdf->Cell($widthMm, $cellHeightMm, $value, 0, 0, $align, 0, '', 0, false, 'T', 'M');
        }

        $pdf->Output($outputPath, 'F');
    }

    // ── Helper: format tanggal periode ───────────────────────────────────────
    private function formatPeriode($startDate, $endDate): string
    {
        $start = \Carbon\Carbon::parse($startDate)->locale('id')->isoFormat('D MMMM YYYY');
        $end   = \Carbon\Carbon::parse($endDate)->locale('id')->isoFormat('D MMMM YYYY');
        return "{$start} – {$end}";
    }

    // ── Helper: generate nomor sertifikat ────────────────────────────────────
    private function generateNomorSertifikat(int $submissionId, int $memberIndex): string
    {
        $year   = now()->year;
        $no     = str_pad($submissionId, 4, '0', STR_PAD_LEFT);
        $member = str_pad($memberIndex, 2, '0', STR_PAD_LEFT);
        return "SERT/{$year}/{$no}/{$member}";
    }

    // ── Helper: bersihkan nama file ───────────────────────────────────────────
    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
    }
}
