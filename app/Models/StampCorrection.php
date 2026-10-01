<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'memo',
        'approval_status',
        'requested_at',
        'approved_at',
    ];

    public function attendances(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
