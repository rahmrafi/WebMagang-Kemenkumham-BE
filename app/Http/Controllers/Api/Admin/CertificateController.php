<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Submission;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function __construct(private readonly CertificateService $certificateService) {}

    // ── Ambil settings sertifikat (template path + fields + text settings)
    public function getSettings(): JsonResponse
    {
        $templatePath   = Setting::where('key', 'certificate_template_path')->value('value');
        $fieldsRaw      = Setting::where('key', 'certificate_fields')->value('value');
        $fields         = $fieldsRaw ? json_decode($fieldsRaw, true) : [];
        $prefix         = Setting::where('key', 'certificate_prefix')->value('value') ?? '';
        $pejabat        = Setting::where('key', 'certificate_pejabat')->value('value') ?? '';
        $textMagang     = Setting::where('key', 'certificate_text_magang')->value('value') ?? '';
        $textPenelitian = Setting::where('key', 'certificate_text_penelitian')->value('value') ?? '';
        $formatNim      = Setting::where('key', 'certificate_format_nim')->value('value') ?? 'Nomor Induk Mahasiswa: {nim}';
        $formatInstansi = Setting::where('key', 'certificate_format_instansi')->value('value') ?? 'Asal Instansi: {instansi}';

        $templateUrl = null;
        if ($templatePath && Storage::disk('public')->exists($templatePath)) {
            $templateUrl = Storage::disk('public')->url($templatePath);
        }

        return response()->json([
            'data' => [
                'template_path'   => $templatePath,
                'template_url'    => $templateUrl,
                'fields'          => $fields,
                'prefix'          => $prefix,
                'pejabat'         => $pejabat,
                'text_magang'     => $textMagang,
                'text_penelitian' => $textPenelitian,
                'format_nim'      => $formatNim,
                'format_instansi' => $formatInstansi,
            ]
        ]);
    }

    // ── Simpan pengaturan teks sertifikat ─────────────────────────────────────
    public function saveTextSettings(Request $request): JsonResponse
    {
        $request->validate([
            'prefix'          => ['required', 'string'],
            'pejabat'         => ['required', 'string'],
            'text_magang'     => ['required', 'string'],
            'text_penelitian' => ['required', 'string'],
            'format_nim'      => ['sometimes', 'string', 'nullable'],
            'format_instansi' => ['sometimes', 'string', 'nullable'],
        ]);

        Setting::updateOrCreate(['key' => 'certificate_prefix'], ['value' => $request->prefix]);
        Setting::updateOrCreate(['key' => 'certificate_pejabat'], ['value' => $request->pejabat]);
        Setting::updateOrCreate(['key' => 'certificate_text_magang'], ['value' => $request->text_magang]);
        Setting::updateOrCreate(['key' => 'certificate_text_penelitian'], ['value' => $request->text_penelitian]);
        Setting::updateOrCreate(['key' => 'certificate_format_nim'], ['value' => $request->format_nim ?? 'Nomor Induk Mahasiswa: {nim}']);
        Setting::updateOrCreate(['key' => 'certificate_format_instansi'], ['value' => $request->format_instansi ?? 'Asal Instansi: {instansi}']);

        return response()->json([
            'message' => 'Pengaturan teks berhasil disimpan',
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

        try {
            Setting::updateOrCreate(
                ['key' => 'certificate_template_path'],
                ['value' => $path]
            );
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return response()->json([
            'message' => 'Template berhasil diupload',
            'data'    => [
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
            'fields'                 => ['required', 'array'],
            'fields.*.id'            => ['required', 'string'],
            'fields.*.label'         => ['required', 'string'],
            'fields.*.x'             => ['required', 'numeric', 'min:0', 'max:100'],
            'fields.*.y'             => ['required', 'numeric', 'min:0', 'max:100'],
            'fields.*.font_size'     => ['required', 'integer', 'min:6', 'max:72'],
            'fields.*.font_color'    => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fields.*.width'         => ['sometimes', 'numeric', 'min:5', 'max:100'],
            'fields.*.text_align'    => ['sometimes', 'in:left,center,right'],
            'fields.*.font_family'   => ['sometimes', 'in:helvetica,times,georgia,montserrat,poppins,playfair,dancing-script,great-vibes,niconne'],
            'fields.*.font_weight'   => ['sometimes', 'integer', 'in:200,300,400,500,600,700,800'],
            'fields.*.font_style'    => ['sometimes', 'in:normal,italic'],
            'fields.*.preview_text'  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'fields.*.preview_width' => ['sometimes', 'numeric', 'min:200', 'max:5000'],
        ]);

        $fields = collect($request->fields)->map(function (array $field) {
            return [
                ...$field,
                'font_color'   => $field['font_color'] ?? '#1a1a1a',
                'width'        => $field['width'] ?? 40,
                'text_align'   => $field['text_align'] ?? 'center',
                'font_family'  => $field['font_family'] ?? 'helvetica',
                'font_weight'  => $field['font_weight'] ?? 400,
                'font_style'   => $field['font_style'] ?? 'normal',
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
    public function generate(Request $request, Submission $submission): JsonResponse
    {
        $request->validate([
            'suffixes'   => ['required', 'array'],
            'suffixes.*' => ['required', 'string'],
        ]);

        if ($submission->status !== 'approved') {
            return response()->json(['message' => 'Submission belum disetujui'], 422);
        }

        $memberCount = 0;
        for ($i = 1; $i <= 10; $i++) {
            if (!empty($submission->{"member_{$i}"})) {
                $memberCount++;
            }
        }

        if (count($request->input('suffixes')) !== $memberCount) {
            return response()->json([
                'message' => "Jumlah suffix nomor surat (" . count($request->input('suffixes')) . ") tidak sesuai dengan jumlah anggota ({$memberCount})",
                'errors'  => [
                    'suffixes' => ["Jumlah suffix harus sama dengan jumlah anggota ({$memberCount})"],
                ],
            ], 422);
        }

        $result = $this->certificateService->generateZip($submission, $request->input('suffixes'));

        // Hapus ZIP lama jika ada
        if ($submission->certificate_zip_path && Storage::disk('public')->exists($submission->certificate_zip_path)) {
            Storage::disk('public')->delete($submission->certificate_zip_path);
        }

        // Simpan path ZIP ke submission
        $submission->update([
            'certificate_zip_path'        => $result['zipStorePath'],
            'certificate_generated_at'    => now(),
            'certificate_number_suffixes' => $request->input('suffixes'),
        ]);

        return response()->json([
            'message' => 'Sertifikat berhasil di-generate',
            'data'    => [
                'zip_url'      => $result['zipUrl'],
                'zip_filename' => $result['zipFilename'],
                'member_count' => $result['memberCount'],
                'generated_at' => now()->toISOString(),
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
}
