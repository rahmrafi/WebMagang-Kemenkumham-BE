<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'position_id',
        'institution',
        'study_program',
        'research_title',
        'start_date',
        'end_date',
        'member_1',
        'member_2',
        'member_3',
        'letter_number',
        'document_path',
        'phone_number',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(InternshipPosition::class, 'position_id');
    }
}
