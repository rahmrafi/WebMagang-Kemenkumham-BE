<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternshipPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_name',
        'status',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'position_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
