<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternshipPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    /**
     * GET /api/admin/positions
     */
    public function index(): JsonResponse
    {
        $positions = InternshipPosition::latest()->get();

        return response()->json(['success' => true, 'data' => $positions]);
    }

    /**
     * POST /api/admin/positions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position_name' => ['required', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $position = InternshipPosition::create([
            'position_name' => $validated['position_name'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Posisi magang berhasil ditambahkan.',
            'data' => $position,
        ], 201);
    }

    /**
     * PATCH /api/admin/positions/{position}
     */
    public function update(Request $request, InternshipPosition $position): JsonResponse
    {
        $validated = $request->validate([
            'position_name' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $position->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Posisi magang berhasil diperbarui.',
            'data' => $position,
        ]);
    }

    /**
     * DELETE /api/admin/positions/{position}
     */
    public function destroy(InternshipPosition $position): JsonResponse
    {
        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Posisi magang berhasil dihapus.',
        ]);
    }
}
