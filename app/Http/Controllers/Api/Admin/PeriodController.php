<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternshipPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodController extends Controller
{
    public function index(): JsonResponse
    {
        $periods = InternshipPeriod::latest()->get();

        return response()->json(['success' => true, 'data' => $periods]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'quota' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $period = InternshipPeriod::create([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'quota' => $validated['quota'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Periode magang berhasil ditambahkan.',
            'data' => $period,
        ], 201);
    }

    public function update(Request $request, InternshipPeriod $period): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'quota' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $period->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Periode magang berhasil diperbarui.',
            'data' => $period,
        ]);
    }

    public function destroy(InternshipPeriod $period): JsonResponse
    {
        $period->delete();

        return response()->json([
            'success' => true,
            'message' => 'Periode magang berhasil dihapus.',
        ]);
    }
}
