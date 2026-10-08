<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakCorrect extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correct_id',
        'break_in',
        'break_out',
    ];

    public function attendanceCorrect(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrect::class);
    }
}