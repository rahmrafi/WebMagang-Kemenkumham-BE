<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $query = Submission::query()->with('position:id,position_name');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $submissions = $query->latest()->paginate(
            $request->integer('per_page', 15)
        );

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
     * GET /api/admin/submissions/{id}/download
     * Mengunduh file ZIP dokumen permohonan.
     */
    public function download(Submission $submission): StreamedResponse
    {
        abort_unless(
            Storage::disk('submissions')->exists($submission->document_path),
            404,
            'Berkas tidak ditemukan.'
        );

        $member1Parts = explode('|', $submission->member_1);
        $namaKetua = $member1Parts[0] ?? 'ketua';
        
        $namaKetuaClean = \Illuminate\Support\Str::slug($namaKetua, '_') ?: 'ketua';
        $kampusClean = \Illuminate\Support\Str::slug($submission->institution, '_') ?: 'kampus';
        
        $downloadName = "permohonan_{$namaKetuaClean}_{$kampusClean}.zip";

        return Storage::disk('submissions')->download(
            $submission->document_path,
            $downloadName
        );
    }
}
