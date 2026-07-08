<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['type'] === 'penelitian') {
            $validated['position_id'] = null;
        }

        $fileName = Str::uuid() . '.zip';
        $path = $request->file('document')->storeAs('submissions', $fileName, 'submissions');

        $submission = Submission::create([
            'type' => $validated['type'],
            'position_id' => $validated['position_id'] ?? null,
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