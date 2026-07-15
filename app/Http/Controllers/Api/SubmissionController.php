<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use App\Models\SubmissionMessage;
use App\Models\InternshipPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Upload file SEBELUM transaksi — operasi I/O tidak boleh di dalam transaksi DB
        // agar koneksi DB tidak tertahan terlalu lama saat upload berlangsung.
        if (!$request->hasFile('document')) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas dokumen wajib dilampirkan.',
            ], 422);
        }

        $fileName = Str::uuid() . '.zip';
        $uploadedPath = $request->file('document')->storeAs('', $fileName, 'submissions');

        try {
            $submission = DB::transaction(function () use ($validated, $uploadedPath) {
                if ($validated['type'] === 'penelitian') {
                    $validated['period_id'] = null;
                } else {
                    // lockForUpdate(): kunci baris periode di DB selama transaksi ini berjalan.
                    // Jika ada request lain yang coba lock periode yang sama secara bersamaan,
                    // mereka akan MENUNGGU sampai transaksi ini COMMIT atau ROLLBACK.
                    // Ini mencegah dua user lolos cek kuota di saat yang sama (race condition).
                    $period = InternshipPeriod::lockForUpdate()->find($validated['period_id']);

                    if (!$period || $period->status !== 'active') {
                        throw ValidationException::withMessages([
                            'period_id' => ['Periode magang tidak valid atau sudah tidak aktif.'],
                        ]);
                    }

                    // Hitung kuota yang sudah terpakai — dibaca SETELAH lock dipegang,
                    // sehingga nilainya selalu fresh dan tidak stale dari cache/read sebelumnya.
                    $usedQuota = $period->used_quota;

                    $requestedQuota = 1;
                    for ($i = 2; $i <= 10; $i++) {
                        if (!empty($validated["member_$i"])) {
                            $requestedQuota += 1;
                        }
                    }

                    if (($usedQuota + $requestedQuota) > $period->quota) {
                        throw ValidationException::withMessages([
                            'period_id' => [
                                'Maaf, kuota untuk periode ini tidak mencukupi untuk jumlah pendaftar '
                                . '(' . $requestedQuota . ' orang). Sisa kuota: '
                                . max(0, $period->quota - $usedQuota),
                            ],
                        ]);
                    }
                }

                // ── Cek Duplikasi Pendaftaran ──────────────────────────────────────────────
                // member_1 disimpan dalam format "nama|nim|email".
                // Kita ekstrak NIM (index 1) dan email (index 2) milik ketua,
                // lalu cari apakah kombinasi NIM+email tersebut sudah ada di submission aktif
                // (status pending atau approved) di kolom member manapun (1–10).
                // Submission yang sudah rejected diizinkan daftar ulang.
                $member1Parts = explode('|', (string) ($validated['member_1'] ?? ''));
                $ketuaNim     = trim($member1Parts[1] ?? '');
                $ketuaEmail   = trim($member1Parts[2] ?? '');

                if ($ketuaNim !== '' && $ketuaEmail !== '') {
                    $alreadyRegistered = Submission::whereIn('status', ['pending', 'approved'])
                        ->where(function ($query) use ($ketuaNim, $ketuaEmail) {
                            for ($i = 1; $i <= 10; $i++) {
                                $query->orWhere(function ($q) use ($i, $ketuaNim, $ketuaEmail) {
                                    // Cari exact substring NIM dan email dalam kolom member_X
                                    $q->where("member_$i", 'LIKE', '%' . $ketuaNim . '%')
                                      ->where("member_$i", 'LIKE', '%' . $ketuaEmail . '%');
                                });
                            }
                        })
                        ->exists();

                    if ($alreadyRegistered) {
                        throw ValidationException::withMessages([
                            'member_1' => [
                                'Anda sudah memiliki pendaftaran aktif yang sedang diproses. '
                                . 'Silakan cek status pendaftaran Anda di halaman Status Pendaftaran. '
                                . 'Pendaftaran baru hanya bisa dilakukan setelah pendaftaran sebelumnya ditolak.',
                            ],
                        ]);
                    }
                }
                // ──────────────────────────────────────────────────────────────────────────

                return Submission::create([
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
                    'document_path'   => $uploadedPath,
                    'phone_number'    => $validated['phone_number'],
                    'status'          => 'pending',
                ]);
            });
        } catch (ValidationException $e) {
            // Kuota habis atau periode tidak valid — hapus file yang sudah ter-upload
            Storage::disk('submissions')->delete($uploadedPath);
            throw $e;
        } catch (\Throwable $e) {
            // Error tak terduga — hapus file agar tidak jadi orphan di storage
            Storage::disk('submissions')->delete($uploadedPath);
            throw $e;
        }

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

        // Hanya ketua kelompok / pendaftar individu (member_1) yang diizinkan mengakses
        $submission = Submission::where('member_1', 'LIKE', '%' . $email . '%')
            ->where('member_1', 'LIKE', '%' . $nim . '%')
            ->latest()
            ->first();

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
        $member1 = (string) $submission->getAttribute("member_1");
        
        if ($member1 && str_contains($member1, $email) && str_contains($member1, $nim)) {
            return true;
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
