<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use ZipArchive;

class CertificateService
{
    /**
     * Generate 1 PDF per member → ZIP archive.
     * Returns info about the created ZIP.
     */
    public function generateZip(Submission $submission): array
    {
        // Ambil template PDF
        $templatePath = Setting::where('key', 'certificate_template_path')->value('value');
        if (!$templatePath || !Storage::disk('public')->exists($templatePath)) {
            abort(422, 'Template sertifikat belum diupload');
        }

        // Ambil fields konfigurasi
        $fieldsRaw = Setting::where('key', 'certificate_fields')->value('value');
        $fields    = $fieldsRaw ? json_decode($fieldsRaw, true) : [];
        if (empty($fields)) {
            abort(422, 'Posisi field sertifikat belum dikonfigurasi');
        }

        $templateFullPath = Storage::disk('public')->path($templatePath);

        // Kumpulkan semua member
        $members = $this->extractMembers($submission);
        if (empty($members)) {
            abort(422, 'Tidak ada anggota yang terdaftar');
        }

        // Buat folder temp untuk PDF individual
        $tempDir = storage_path("app/temp/cert_{$submission->id}_" . time());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdfFiles = [];
        foreach ($members as $index => $member) {
            $pdfPath    = "{$tempDir}/Sertifikat_" . $this->sanitizeFilename($member['nama']) . ".pdf";
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
            abort(500, 'Gagal membuat file ZIP');
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

        return [
            'zipStorePath' => $zipStorePath,
            'zipFilename'  => $zipFilename,
            'memberCount'  => count($members),
            'zipUrl'       => Storage::disk('public')->url($zipStorePath),
        ];
    }

    /**
     * Extract members from submission's member_1..10 columns.
     */
    public function extractMembers(Submission $submission): array
    {
        $members = [];
        for ($i = 1; $i <= 10; $i++) {
            $parsed = \App\Support\MemberParser::parse($submission->{"member_{$i}"});
            if (!$parsed) continue;
            $members[] = [
                'nama'             => $parsed['nama'],
                'nim'              => $parsed['nim'],
                'institusi'        => $submission->institution,
                'prodi'            => $submission->study_program ?? '',
                'periode'          => $this->formatPeriode($submission->start_date, $submission->end_date),
                'nomor_sertifikat' => $this->generateNomorSertifikat($submission->id, $i),
                'tanggal_terbit'   => now()->locale('id')->isoFormat('D MMMM YYYY'),
            ];
        }
        return $members;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Faktor konversi milimeter ke point. 1 inch = 25.4mm = 72pt, maka 1mm = 72/25.4 ≈ 2.83465pt */
    private const MM_TO_PT_RATIO = 2.83465;

    private function generatePdfForMember(
        string $templatePath,
        array  $fields,
        array  $memberData,
        string $outputPath,
    ): void {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($templatePath);
        $tpl       = $pdf->importPage(1);
        $size      = $pdf->getTemplateSize($tpl);

        $pageW = $size['width'];
        $pageH = $size['height'];

        $pdf->AddPage($pageW > $pageH ? 'L' : 'P', [$pageW, $pageH]);
        $pdf->useTemplate($tpl, 0, 0, $pageW, $pageH);

        foreach ($fields as $field) {
            $value = $memberData[$field['id']] ?? '';
            if ($value === '') continue;

            $xMm          = ($field['x'] / 100) * $pageW;
            $yMm          = ($field['y'] / 100) * $pageH;
            $previewWidth = max(200, (float) ($field['preview_width'] ?? 1024));
            $pageWPt      = $pageW * self::MM_TO_PT_RATIO;
            $fontRatio    = ($field['font_size'] ?? 12) / $previewWidth;
            $fontSize     = max(5, (int) round($fontRatio * $pageWPt));
            $cellHeightMm = ($fontSize * 1.0) / self::MM_TO_PT_RATIO;

            $color = ltrim($field['font_color'] ?? '#000000', '#');
            $r     = hexdec(substr($color, 0, 2));
            $g     = hexdec(substr($color, 2, 2));
            $b     = hexdec(substr($color, 4, 2));

            $fontMap = [
                'helvetica'      => 'helvetica',
                'times'          => 'times',
                'georgia'        => 'times',
                'montserrat'     => 'helvetica',
                'poppins'        => 'poppins',
                'playfair'       => 'times',
                'dancing-script' => 'dancing-script',
                'great-vibes'    => 'great-vibes',
            ];
            $fontFamily = $field['font_family'] ?? 'helvetica';
            $font       = $fontMap[$fontFamily] ?? 'helvetica';

            $fontStyle = '';
            if (($field['font_weight'] ?? 400) >= 600) $fontStyle .= 'B';
            if (($field['font_style'] ?? 'normal') === 'italic') $fontStyle .= 'I';

            $customFontFiles = [
                'great-vibes'    => resource_path('fonts/GreatVibes-Regular.ttf'),
                'dancing-script' => resource_path('fonts/DancingScript-Variable.ttf'),
                'poppins'        => resource_path('fonts/Poppins-Regular.ttf'),
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
            $align   = match ($field['text_align'] ?? 'left') {
                'center' => 'C',
                'right'  => 'R',
                default  => 'L',
            };

            $pdf->SetFont($font, $fontStyle, $fontSize);
            while ($fontSize > 6 && $pdf->GetStringWidth($value) > $widthMm) {
                $fontSize--;
                $pdf->SetFont($font, $fontStyle, $fontSize);
            }
            $pdf->SetTextColor($r, $g, $b);
            $pdf->SetCellPadding(0);
            $pdf->SetXY($xMm, $yMm);
            $pdf->Cell($widthMm, $cellHeightMm, $value, 0, 0, $align, 0, '', 0, false, 'T', 'M');
        }

        $pdf->Output($outputPath, 'F');
    }

    private function formatPeriode($startDate, $endDate): string
    {
        $start = Carbon::parse($startDate)->locale('id')->isoFormat('D MMMM YYYY');
        $end   = Carbon::parse($endDate)->locale('id')->isoFormat('D MMMM YYYY');
        return "{$start} – {$end}";
    }

    private function generateNomorSertifikat(int $submissionId, int $memberIndex): string
    {
        $year   = now()->year;
        $no     = str_pad($submissionId, 4, '0', STR_PAD_LEFT);
        $member = str_pad($memberIndex, 2, '0', STR_PAD_LEFT);
        return "SERT/{$year}/{$no}/{$member}";
    }

    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
    }
}
