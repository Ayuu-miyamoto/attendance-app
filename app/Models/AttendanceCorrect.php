<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrect extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'clock_in',
        'clock_out',
        'memo',
        'status',
        'requested_at',
        'approved_at',
    ];

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function breakCorrects(): HasMany
    {
        return $this->hasMany(BreakCorrect::class);
    }
}