<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionMember extends Model
{
    protected $fillable = [
        'submission_id',
        'nama',
        'nim',
        'email',
        'is_leader',
        'urutan',
    ];

    protected $casts = [
        'is_leader' => 'boolean',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
