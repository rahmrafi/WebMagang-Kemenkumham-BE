<?php

namespace App\Services;

use App\Models\InternshipPeriod;
use App\Models\Submission;
use App\Models\SubmissionMember;
use App\Support\MemberParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmissionRegistrationService
{
    /**
     * Handle registration of internship or research submission.
     *
     * @param array $validated
     * @param UploadedFile $document
     * @return Submission
     * @throws ValidationException|Throwable
     */
    public function register(array $validated, UploadedFile $document): Submission
    {
        $uploadedPath = $this->uploadDocument($document);

        try {
            return DB::transaction(function () use ($validated, $uploadedPath) {
                if (($validated['type'] ?? null) === 'penelitian') {
                    $validated['period_id'] = null;
                } else {
                    $this->verifyAndLockQuota($validated);
                }

                $this->verifyDuplicateRegistration($validated);

                $submission = $this->createSubmissionRecord($validated, $uploadedPath);
                $this->createSubmissionMembers($submission->id, $validated);

                return $submission;
            });
        } catch (Throwable $e) {
            $this->cleanupFile($uploadedPath);
            throw $e;
        }
    }

    /**
     * Upload submission file to storage.
     */
    private function uploadDocument(UploadedFile $document): string
    {
        $fileName = Str::uuid() . '.zip';
        return $document->storeAs('', $fileName, 'submissions');
    }

    /**
     * Remove stored file in case of transaction failure.
     */
    private function cleanupFile(string $uploadedPath): void
    {
        if (Storage::disk('submissions')->exists($uploadedPath)) {
            Storage::disk('submissions')->delete($uploadedPath);
        }
    }

    /**
     * Lock internship period row and verify available quota.
     *
     * @throws ValidationException
     */
    private function verifyAndLockQuota(array $validated): void
    {
        $periodId = $validated['period_id'] ?? null;
        $period = InternshipPeriod::lockForUpdate()->find($periodId);

        if (!$period || $period->status !== 'active') {
            throw ValidationException::withMessages([
                'period_id' => ['Periode magang tidak valid atau sudah tidak aktif.'],
            ]);
        }

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

    /**
     * Check if leader has an active pending/approved registration.
     *
     * @throws ValidationException
     */
    private function verifyDuplicateRegistration(array $validated): void
    {
        $leaderData = MemberParser::parse($validated['member_1'] ?? '');
        $ketuaNim   = $leaderData['nim'] ?? '';
        $ketuaEmail = $leaderData['email'] ?? '';

        if ($ketuaNim !== '' && $ketuaEmail !== '') {
            $alreadyRegistered = Submission::whereIn('status', ['pending', 'approved'])
                ->whereHas('members', function ($q) use ($ketuaNim, $ketuaEmail) {
                    $q->where('nim', $ketuaNim)
                      ->where('email', $ketuaEmail);
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
    }

    /**
     * Persist submission record.
     */
    private function createSubmissionRecord(array $validated, string $uploadedPath): Submission
    {
        return Submission::create([
            'type'            => $validated['type'],
            'period_id'       => $validated['period_id'] ?? null,
            'institution'     => $validated['institution'],
            'campus_city'     => $validated['campus_city'],
            'study_program'   => $validated['study_program'] ?? null,
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
    }

    /**
     * Persist parsed member records into submission_members table.
     */
    private function createSubmissionMembers(int $submissionId, array $validated): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $raw = $validated['member_' . $i] ?? null;
            if (empty($raw)) continue;

            $parsed = MemberParser::parse($raw);
            if (!$parsed) continue;

            $nama  = $parsed['nama'];
            $nim   = $parsed['nim'] ?: null;
            $email = filter_var($parsed['email'], FILTER_VALIDATE_EMAIL) ? $parsed['email'] : null;

            SubmissionMember::create([
                'submission_id' => $submissionId,
                'nama'          => $nama,
                'nim'           => $nim ?: null,
                'email'         => $email ?: null,
                'is_leader'     => ($i === 1),
                'urutan'        => $i,
            ]);
        }
    }
}
