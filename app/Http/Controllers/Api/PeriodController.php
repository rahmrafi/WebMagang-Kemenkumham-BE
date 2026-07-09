<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternshipPeriod;
use Illuminate\Http\JsonResponse;

class PeriodController extends Controller
{
    public function index(): JsonResponse
    {
        $periods = InternshipPeriod::where('status', 'active')
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(function ($period) {
                return [
                    'id' => $period->id,
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d'),
                    'quota' => $period->quota,
                    'remaining_quota' => max(0, $period->quota - $period->used_quota),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }
}
