<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternshipPosition;
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
{
    public function index(): JsonResponse
    {
        $positions = InternshipPosition::active()
            ->select('id', 'position_name')
            ->orderByRaw("CASE WHEN position_name LIKE 'Lain%' THEN 1 ELSE 0 END ASC, position_name ASC")
            ->get();

        return response()->json([
            'success' => true,
            'data' => $positions,
        ]);
    }
}