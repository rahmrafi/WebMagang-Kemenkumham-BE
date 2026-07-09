<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use App\Models\InternshipPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['type'] === 'penelitian') {
            $validated['period_id'] = null;
        } else {
            $period = InternshipPeriod::with(['submissions' => function ($query) {
                $query->whereIn('status', ['pending', 'approved']);
            }])->find($validated['period_id']);

            if (!$period || $period->status !== 'active') {
                throw ValidationException::withMessages([
                    'period_id' => ['Periode magang tidak valid atau sudah tidak aktif.'],
                ]);
            }

            $usedQuota = 0;
            foreach ($period->submissions as $sub) {
                $usedQuota += 1;
                for ($i = 2; $i <= 10; $i++) {
                    if ($sub->{"member_$i"}) $usedQuota += 1;
                }
            }

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
            'type' => $validated['type'],
            'period_id' => $validated['period_id'] ?? null,
            'institution' => $validated['institution'],
            'study_program' => $validated['study_program'],
            'research_title' => $validated['research_title'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'member_1' => $validated['member_1'],
            'member_2' => $validated['member_2'] ?? null,
            'member_3' => $validated['member_3'] ?? null,
            'member_4' => $validated['member_4'] ?? null,
            'member_5' => $validated['member_5'] ?? null,
            'member_6' => $validated['member_6'] ?? null,
            'member_7' => $validated['member_7'] ?? null,
            'member_8' => $validated['member_8'] ?? null,
            'member_9' => $validated['member_9'] ?? null,
            'member_10' => $validated['member_10'] ?? null,
            'letter_number' => $validated['letter_number'],
            'document_path' => $path,
            'phone_number' => $validated['phone_number'],
            'status' => 'pending',
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
}
