<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    /**
     * POST /api/submit
     * Menerima pengiriman formulir Magang atau Penelitian.
     */
    public function store(StoreSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Penelitian tidak punya posisi -> paksa null walau ada input nyasar
        if ($validated['type'] === 'penelitian') {
            $validated['position_id'] = null;
        }

        // Simpan file ke disk privat (read-only publik, tanpa eksekusi script)
        // Disk "submissions" didefinisikan di config/filesystems.php
        $fileName = Str::uuid() . '.zip';
        $path = $request->file('document')->storeAs(
            'submissions',
            $fileName,
            'submissions'
        );

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
