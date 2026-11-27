<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    // ASUMSI sementara: lembur dihitung bila pulang minimal 30 menit
    // setelah jam akhir shift. Tanyakan ke klien.
    public const MIN_OVERTIME_MINUTES = 30;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_outside_area' => 'boolean',
            'check_out_outside_area' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class)->oldest();
    }

    /**
     * Baris absen yang sedang berjalan untuk operator:
     * 1) yang sudah masuk tapi belum pulang (maks. 16 jam terakhir), atau
     * 2) baris untuk hari ini.
     */
    public static function currentFor(User $user): ?self
    {
        $open = static::where('user_id', $user->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->where('check_in_at', '>=', now()->subHours(16))
            ->latest('check_in_at')
            ->first();

        return $open ?? static::where('user_id', $user->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();
    }

    public function isCorrected(): bool
    {
        return $this->check_in_source === 'monitoring'
            || $this->check_out_source === 'monitoring';
    }

    public function statusLabel(): string
    {
        if ($this->status !== 'hadir') {
            return ucfirst($this->status);
        }

        if (! $this->check_in_at || ! $this->check_out_at) {
            return 'Belum lengkap';
        }

        return $this->isCorrected() ? 'Dikoreksi' : 'Lengkap';
    }

    /**
     * Waktu akhir shift pada hari kerja ini (lembur dihitung mulai dari sini).
     */
    public function shiftEnd(): ?Carbon
    {
        $shift = $this->user?->shift;

        if (! $shift) {
            return null;
        }

        $tanggal = $this->work_date->toDateString();
        $mulai = Carbon::parse("{$tanggal} {$shift->start_time}");
        $akhir = Carbon::parse("{$tanggal} {$shift->end_time}");

        if ($akhir->lte($mulai)) {
            $akhir->addDay(); // shift yang melewati tengah malam
        }

        return $akhir;
    }

    public function overtimeMinutes(): int
    {
        $akhirShift = $this->shiftEnd();

        if (! $this->check_out_at || ! $akhirShift) {
            return 0;
        }

        $menit = intdiv($this->check_out_at->timestamp - $akhirShift->timestamp, 60);

        if ($menit < self::MIN_OVERTIME_MINUTES) {
            return 0;
        }

        $sistem = $this->user->workSystem;

        if ($sistem) {
            $maks = max(0, ($sistem->max_total_hours - $sistem->normal_hours) * 60);
            $menit = min($menit, $maks);
        }

        return $menit;
    }
}