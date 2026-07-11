<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'period_id',
        'institution',
        'study_program',
        'research_title',
        'start_date',
        'end_date',
        'member_1',
        'member_2',
        'member_3',
        'member_4',
        'member_5',
        'member_6',
        'member_7',
        'member_8',
        'member_9',
        'member_10',
        'letter_number',
        'document_path',
        'phone_number',
        'status',
        'document_downloaded_at',
        'discussion_started_at',
        'permit_file_path',
        'permit_file_name',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'document_downloaded_at' => 'datetime',
        'discussion_started_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(InternshipPeriod::class, 'period_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SubmissionMessage::class);
    }
}
