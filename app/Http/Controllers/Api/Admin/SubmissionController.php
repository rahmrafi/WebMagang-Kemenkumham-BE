<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Exports\SubmissionsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Events\SubmissionUpdated;
use App\Events\MessageSent;

class SubmissionController extends Controller
{
    /**
     * GET /api/admin/submissions/export
     * Export all submissions to Excel
     */
    public function export()
    {
        return Excel::download(new SubmissionsExport, 'Data_Pendaftar_Magang_Penelitian_' . date('Ymd_His') . '.xlsx');
    }

    /**
     * GET /api/admin/submissions/{submission}/export
     * Export a single submission to Excel
     */
    public function exportSingle(Submission $submission)
    {
        return Excel::download(new SubmissionsExport($submission->id), 'Data_Pendaftar_' . $submission->id . '_' . date('Ymd_His') . '.xlsx');
    }
    /**
     * GET /api/admin/submissions
     * Daftar semua permohonan dengan filter type & status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Submission::query()
            ->with([
                'period:id,start_date,end_date',
            ]);

        $canTrackUnreadMessages = $this->canTrackUnreadMessages();
        if ($canTrackUnreadMessages) {
            $query->withCount([
                'messages as unread_admin_messages_count' => fn ($query) => $query
                    ->where('sender_type', 'applicant')
                    ->whereNull('admin_read_at'),
            ]);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $submissions = $query->latest()->get();

        $latestMessages = SubmissionMessage::query()
            ->whereIn('submission_id', $submissions->pluck('id'))
            ->latest()
            ->get(['id', 'submission_id', 'sender_type', 'created_at'])
            ->unique('submission_id')
            ->keyBy('submission_id');

        $submissions->each(function (Submission $submission) use ($latestMessages) {
            $submission->setAttribute('latest_message', $latestMessages->get($submission->id));
        });

        if (!$canTrackUnreadMessages) {
            $submissions->each(fn (Submission $submission) => $submission->setAttribute('unread_admin_messages_count', 0));
        }

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

        if ($validated['status'] === 'approved' && !$submission->permit_file_path) {
            return response()->json([
                'success' => false,
                'message' => 'File izin belum diunggah. Silakan upload surat izin magang/penelitian terlebih dahulu sebelum menerima peserta.',
            ], 422);
        }

        $submission->update(['status' => $validated['status']]);

        broadcast(new SubmissionUpdated($submission->id));

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

        if (!$submission->document_downloaded_at) {
            $submission->update(['document_downloaded_at' => now()]);
        }

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

    public function uploadPermit(Request $request, Submission $submission): JsonResponse
    {
        $validated = $request->validate([
            'permit_file' => ['required', 'file', 'mimes:pdf,docx', 'max:10240'],
            'replace' => ['nullable', 'boolean'],
        ]);

        if ($submission->permit_file_path && !$request->boolean('replace')) {
            return response()->json([
                'success' => false,
                'message' => 'File izin sudah tersedia. Upload ulang akan mengganti file sebelumnya.',
                'requires_confirmation' => true,
            ], 409);
        }

        if ($submission->permit_file_path && Storage::disk('permits')->exists($submission->permit_file_path)) {
            Storage::disk('permits')->delete($submission->permit_file_path);
        }

        $file = $validated['permit_file'];
        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs('', Str::uuid() . '.' . $extension, 'permits');

        $submission->update([
            'permit_file_path' => $path,
            'permit_file_name' => $file->getClientOriginalName(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File izin berhasil diunggah.',
            'data' => $submission->fresh(),
        ]);
    }

    public function startDiscussion(Submission $submission): JsonResponse
    {
        if (!$submission->document_downloaded_at) {
            return response()->json([
                'success' => false,
                'message' => 'Forum diskusi belum aktif. Silakan unduh dan review berkas pendukung terlebih dahulu sebelum memulai diskusi dengan pendaftar.',
            ], 422);
        }

        if (!$submission->discussion_started_at) {
            $submission->forceFill(['discussion_started_at' => now()])->save();
            broadcast(new SubmissionUpdated($submission->id));
        }

        return response()->json([
            'success' => true,
            'message' => 'Forum diskusi berhasil diaktifkan.',
            'data' => $submission->fresh(),
        ]);
    }

    public function messages(Request $request, Submission $submission): JsonResponse
    {
        if ($request->boolean('mark_read', true) && $this->canTrackUnreadMessages()) {
            $submission->messages()
                ->where('sender_type', 'applicant')
                ->whereNull('admin_read_at')
                ->update(['admin_read_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'data' => $submission->messages()
                ->oldest()
                ->when($request->filled('since') && is_numeric($request->query('since')), function ($query) use ($request) {
                    $query->where('id', '>', (int) $request->query('since'));
                })
                ->get(['id', 'sender_type', 'sender_name', 'message', 'created_at']),
        ]);
    }

    public function sendMessage(Request $request, Submission $submission): JsonResponse
    {
        if (!$submission->discussion_started_at) {
            return response()->json([
                'success' => false,
                'message' => 'Forum diskusi belum aktif untuk pendaftaran ini.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = SubmissionMessage::create([
            'submission_id' => $submission->id,
            'sender_type' => 'admin',
            'sender_name' => 'Admin Kementerian Hukum',
            'message' => $validated['message'],
        ]);

        broadcast(new MessageSent($submission->id, $message));

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message,
        ], 201);
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

    private function canTrackUnreadMessages(): bool
    {
        return Schema::hasTable('submission_messages')
            && Schema::hasColumn('submission_messages', 'admin_read_at');
    }
}
