<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;
    protected $fillabel = [
        'user_id',
        'date',
        'start',
        'finish',
        'break_in',
        'break_out',
        'break2_in',
        'break2_out',
    ];

      protected $casts = [
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class);
    }

    public function stampcorrection(): HasMany
    {
        return $this->hasMany(StampCorrections::class);
    }
}
