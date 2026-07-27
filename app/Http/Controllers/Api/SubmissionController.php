<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use App\Models\SubmissionMessage;
use App\Services\SubmissionRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function store(
        StoreSubmissionRequest $request,
        SubmissionRegistrationService $registrationService
    ): JsonResponse {
        $submission = $registrationService->register(
            $request->validated(),
            $request->file('document')
        );

        \Illuminate\Support\Facades\Cache::forget('public_periods');

        return response()->json([
            'success' => true,
            'message' => 'Permohonan berhasil dikirim.',
            'data' => [
                'id'     => $submission->id,
                'type'   => $submission->type,
                'status' => $submission->status,
            ],
        ], 201);
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $email = $request->query('email');
        $nim = $request->query('nim');

        if (!$email || !$nim) {
            return response()->json([
                'success' => false,
                'message' => 'Email dan NIM wajib diisi.',
            ], 400);
        }

        // Cari via tabel relasi baru — semua data sudah termigrasikan ke submission_members
        $submissionId = \App\Models\SubmissionMember::where('email', $email)
            ->where('nim', $nim)
            ->where('is_leader', true)
            ->value('submission_id');

        $submission = $submissionId ? Submission::find($submissionId) : null;

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $submission->id,
                'type' => $submission->type,
                'status' => $submission->status,
                'member_1' => $submission->member_1,
                'created_at' => $submission->created_at,
                'document_downloaded_at' => $submission->document_downloaded_at,
                'discussion_started_at' => $submission->discussion_started_at,
                'permit_file_name' => $submission->status === 'approved' ? $submission->permit_file_name : null,
                'rejection_note' => $submission->status === 'rejected' ? $submission->rejection_note : null,
                'effective_stage' => $this->effectiveStage($submission),
            ]
        ]);
    }

    public function messages(Request $request, Submission $submission): JsonResponse
    {
        $this->assertApplicantCanAccess($request, $submission);
        $this->assertDiscussionIsOpen($submission);

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
        $this->assertApplicantCanAccess($request, $submission);
        $this->assertDiscussionIsOpen($submission);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = SubmissionMessage::create([
            'submission_id' => $submission->id,
            'sender_type' => 'applicant',
            'sender_name' => $this->applicantName($submission),
            'message' => $validated['message'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message,
        ], 201);
    }

    public function downloadPermit(Request $request, Submission $submission): StreamedResponse
    {
        $this->assertApplicantCanAccess($request, $submission);

        if ($submission->status !== 'approved' || !$submission->permit_file_path) {
            abort(403, 'Surat izin hanya tersedia setelah pendaftaran diterima.');
        }

        if (!Storage::disk('permits')->exists($submission->permit_file_path)) {
            abort(404, 'File izin tidak ditemukan di server.');
        }

        return Storage::disk('permits')->download(
            $submission->permit_file_path,
            $submission->permit_file_name ?: basename($submission->permit_file_path)
        );
    }

    private function assertApplicantCanAccess(Request $request, Submission $submission): void
    {
        $email = (string) ($request->query('email') ?? $request->input('email'));
        $nim = (string) ($request->query('nim') ?? $request->input('nim'));

        if (!$email || !$nim || !$this->matchesApplicant($submission, $email, $nim)) {
            abort(403, 'Email dan NIM tidak cocok dengan data pendaftaran.');
        }
    }

    private function assertDiscussionIsOpen(Submission $submission): void
    {
        if (!$submission->discussion_started_at) {
            abort(403, 'Forum diskusi belum aktif untuk pendaftaran ini.');
        }
    }

    private function matchesApplicant(Submission $submission, string $email, string $nim): bool
    {
        // Cek via tabel submission_members — semua data sudah termigrasikan
        return \App\Models\SubmissionMember::where('submission_id', $submission->id)
            ->where('email', $email)
            ->where('nim', $nim)
            ->exists();
    }

    private function applicantName(Submission $submission): string
    {
        $parts = explode('|', (string) $submission->member_1);
        return trim($parts[0] ?? '') ?: 'Pendaftar';
    }

    /** Delay minimum (menit) setelah submit sebelum status berubah ke 'verification'. */
    private const VERIFICATION_DELAY_MINUTES = 5; // TODO: konfirmasi ke tim kenapa 5 menit — apakah ada SLA proses?

    private function effectiveStage(Submission $submission): string
    {
        if ($submission->status === 'approved' || $submission->status === 'rejected') {
            return 'announcement';
        }

        if ($submission->discussion_started_at) {
            return 'discussion';
        }

        if ($submission->document_downloaded_at) {
            return 'document_review';
        }

        if ($submission->created_at && $submission->created_at->diffInMinutes(now()) >= self::VERIFICATION_DELAY_MINUTES) {
            return 'verification';
        }

        return 'submitted';
    }
}
