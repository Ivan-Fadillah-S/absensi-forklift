<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetEntry extends Model
{
    public const JOB_TYPES = ['Bongkar muat', 'Pindah material', 'Loading truk', 'Lainnya'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function durationMinutes(): int
    {
        return intdiv(strtotime($this->end_time) - strtotime($this->start_time), 60);
    }
}