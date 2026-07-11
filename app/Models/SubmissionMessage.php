<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'sender_type',
        'sender_name',
        'message',
        'admin_read_at',
    ];

    protected $casts = [
        'admin_read_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
