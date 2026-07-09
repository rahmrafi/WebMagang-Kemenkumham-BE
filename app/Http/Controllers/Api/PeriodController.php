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
            ->with(['submissions' => function ($query) {
                $query->whereIn('status', ['pending', 'approved']);
            }])
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(function ($period) {
                $usedQuota = 0;
                foreach ($period->submissions as $sub) {
                    $usedQuota += 1;
                    if ($sub->member_2) $usedQuota += 1;
                    if ($sub->member_3) $usedQuota += 1;
                }
                
                return [
                    'id' => $period->id,
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d'),
                    'quota' => $period->quota,
                    'remaining_quota' => max(0, $period->quota - $usedQuota),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }
}
