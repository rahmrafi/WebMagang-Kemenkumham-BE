<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternshipPosition;
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
{
    /**
     * GET /api/positions
     * Mengambil daftar posisi magang berstatus aktif untuk dropdown form.
     */
    public function index(): JsonResponse
    {
        $positions = InternshipPosition::active()
            ->select('id', 'position_name')
            ->orderBy('position_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $positions,
        ]);
    }
}
