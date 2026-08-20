<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tutorial extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'video_type',
        'video_source',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'video_url',
    ];

    /**
     * Get the full video URL.
     */
    public function getVideoUrlAttribute(): ?string
    {
        if (empty($this->video_source)) {
            return null;
        }

        if ($this->video_type === 'file') {
            if (str_starts_with($this->video_source, 'http://') || str_starts_with($this->video_source, 'https://')) {
                return $this->video_source;
            }

            return url($this->video_source);
        }

        return $this->video_source;
    }
}
