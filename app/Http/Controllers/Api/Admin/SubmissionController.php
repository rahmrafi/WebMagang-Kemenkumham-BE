<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    /**
     * GET /api/admin/submissions
     * Daftar semua permohonan dengan filter type & status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Submission::query()->with('period:id,start_date,end_date');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $submissions = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $submissions,
        ]);
    }

    /**
     * PATCH /api/admin/submissions/{id}/status
     * Mengubah status permohonan menjadi approved/rejected.
     */
    public function updateStatus(Request $request, Submission $submission): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $submission->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Status permohonan berhasil diperbarui.',
            'data' => $submission,
        ]);
    }

    /**
     * PATCH /api/admin/submissions/{id}/dates
     * Mengubah tanggal magang/penelitian untuk pendaftar spesifik (custom).
     */
    public function updateDates(Request $request, Submission $submission): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $submission->update([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tanggal kegiatan berhasil diperbarui.',
            'data' => $submission,
        ]);
    }

    /**
     * GET /api/admin/submissions/{id}/download
     * Mengunduh file ZIP dokumen permohonan.
     */
    public function download(Submission $submission): StreamedResponse
    {
        $documentPath = $this->resolveDocumentPath($submission);

        $member1Parts = explode('|', $submission->member_1);
        $namaKetua = $member1Parts[0] ?? 'ketua';
        
        $namaKetuaClean = Str::slug($namaKetua, '_') ?: 'ketua';
        $kampusClean = Str::slug($submission->institution, '_') ?: 'kampus';
        
        $downloadName = "permohonan_{$namaKetuaClean}_{$kampusClean}.zip";

        return Storage::disk('submissions')->download(
            $documentPath,
            $downloadName
        );
    }

    private function resolveDocumentPath(Submission $submission): string
    {
        $storedPath = trim((string) $submission->document_path);
        $fileName = basename($storedPath);

        $candidates = array_unique(array_filter([
            $storedPath,
            $fileName,
            "submissions/{$fileName}",
        ]));

        foreach ($candidates as $path) {
            if (Storage::disk('submissions')->exists($path)) {
                return $path;
            }
        }

        abort(404, 'Berkas ZIP tidak ditemukan di storage server.');
    }
}
