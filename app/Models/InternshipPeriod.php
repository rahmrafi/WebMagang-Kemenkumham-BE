<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternshipPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'quota',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'period_id');
    }

    public function getUsedQuotaAttribute(): int
    {
        return Submission::where('period_id', $this->id)
            ->whereIn('status', ['pending', 'approved'])
            ->selectRaw("SUM(
                1 + 
                (CASE WHEN member_2 IS NOT NULL AND member_2 != '' THEN 1 ELSE 0 END) +
                (CASE WHEN member_3 IS NOT NULL AND member_3 != '' THEN 1 ELSE 0 END) +
                (CASE WHEN member_4 IS NOT NULL AND member_4 != '' THEN 1 ELSE 0 END) +
                (CASE WHEN member_5 IS NOT NULL AND member_5 != '' THEN 1 ELSE 0 END) +
                (CASE WHEN member_6 IS NOT NULL AND member_6 != '' THEN 1 ELSE 0 END) +
                (CASE WHEN member_7 IS NOT NULL AND member_7 != '' THEN 1 ELSE 0 END) +
                (CASE WHEN member_8 IS NOT NULL AND member_8 != '' THEN 1 ELSE 0 END) +
                (CASE WHEN member_9 IS NOT NULL AND member_9 != '' THEN 1 ELSE 0 END) +
                (CASE WHEN member_10 IS NOT NULL AND member_10 != '' THEN 1 ELSE 0 END)
            ) as total")
            ->value('total') ?? 0;
    }
}
