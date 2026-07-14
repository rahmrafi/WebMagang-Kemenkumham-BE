<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use App\Models\SubmissionMessage;
use App\Models\InternshipPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['type'] === 'penelitian') {
            $validated['period_id'] = null;
        } else {
            $period = InternshipPeriod::find($validated['period_id']);

            if (!$period || $period->status !== 'active') {
                throw ValidationException::withMessages([
                    'period_id' => ['Periode magang tidak valid atau sudah tidak aktif.'],
                ]);
            }

            $usedQuota = $period->used_quota;

            $requestedQuota = 1;
            for ($i = 2; $i <= 10; $i++) {
                if (isset($validated["member_$i"]) && $validated["member_$i"]) {
                    $requestedQuota += 1;
                }
            }

            if (($usedQuota + $requestedQuota) > $period->quota) {
                throw ValidationException::withMessages([
                    'period_id' => ['Maaf, kuota untuk periode ini tidak mencukupi untuk jumlah pendaftar (' . $requestedQuota . ' orang). Sisa kuota: ' . max(0, $period->quota - $usedQuota)],
                ]);
            }
        }

        if (!$request->hasFile('document')) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas dokumen wajib dilampirkan.',
            ], 422);
        }

        $fileName = Str::uuid() . '.zip';
        $path = $request->file('document')->storeAs('', $fileName, 'submissions');

        $submission = Submission::create([
            'type'            => $validated['type'],
            'period_id'       => $validated['period_id'] ?? null,
            'institution'     => $validated['institution'],
            'campus_city'     => $validated['campus_city'],
            'study_program'   => $validated['study_program'],
            'education_level' => $validated['education_level'],
            'research_title'  => $validated['research_title'] ?? null,
            'start_date'      => $validated['start_date'],
            'end_date'        => $validated['end_date'],
            'member_1'        => $validated['member_1'],
            'member_2'        => $validated['member_2'] ?? null,
            'member_3'        => $validated['member_3'] ?? null,
            'member_4'        => $validated['member_4'] ?? null,
            'member_5'        => $validated['member_5'] ?? null,
            'member_6'        => $validated['member_6'] ?? null,
            'member_7'        => $validated['member_7'] ?? null,
            'member_8'        => $validated['member_8'] ?? null,
            'member_9'        => $validated['member_9'] ?? null,
            'member_10'       => $validated['member_10'] ?? null,
            'letter_number'   => $validated['letter_number'],
            'letter_date'     => $validated['letter_date'],
            'document_path'   => $path,
            'phone_number'    => $validated['phone_number'],
            'status'          => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan berhasil dikirim.',
            'data' => [
                'id' => $submission->id,
                'type' => $submission->type,
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

        $submission = Submission::where(function ($query) use ($email, $nim) {
            for ($i = 1; $i <= 10; $i++) {
                $query->orWhere(function ($q) use ($i, $email, $nim) {
                    $q->where("member_$i", 'LIKE', '%' . $email . '%')
                      ->where("member_$i", 'LIKE', '%' . $nim . '%');
                });
            }
        })->latest()->first();

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
        for ($i = 1; $i <= 10; $i++) {
            $member = (string) $submission->getAttribute("member_$i");
            if ($member && str_contains($member, $email) && str_contains($member, $nim)) {
                return true;
            }
        }

        return false;
    }

    private function applicantName(Submission $submission): string
    {
        $parts = explode('|', (string) $submission->member_1);
        return trim($parts[0] ?? '') ?: 'Pendaftar';
    }

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

        if ($submission->created_at && $submission->created_at->diffInMinutes(now()) >= 5) {
            return 'verification';
        }

        return 'submitted';
    }
}
