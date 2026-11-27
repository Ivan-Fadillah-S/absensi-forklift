<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\TimesheetEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimesheetController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('shift');
        $attendance = Attendance::currentFor($user);
        $workDate = ($attendance?->work_date ?? now())->copy()->startOfDay();

        $entries = TimesheetEntry::where('user_id', $user->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->orderBy('start_time')
            ->get();

        return view('operator.timesheet', [
            'user'       => $user,
            'attendance' => $attendance,
            'workDate'   => $workDate,
            'entries'    => $entries,
            'totalMenit' => $entries->sum(fn ($e) => $e->durationMinutes()),
            'jenis'      => TimesheetEntry::JOB_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'job_type'   => ['required', Rule::in(TimesheetEntry::JOB_TYPES)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'note'       => ['nullable', 'string', 'max:255', Rule::requiredIf($request->job_type === 'Lainnya')],
        ], [
            'job_type.required'  => 'Pilih jenis pekerjaan dulu.',
            'job_type.in'        => 'Jenis pekerjaan tidak dikenal.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'end_time.required'  => 'Jam selesai wajib diisi.',
            'end_time.after'     => 'Jam selesai harus setelah jam mulai.',
            'note.required'      => 'Isi catatan untuk jenis "Lainnya".',
        ]);

        $user = $request->user();
        $workDate = (Attendance::currentFor($user)?->work_date ?? now())->toDateString();

        $mulai = Carbon::createFromFormat('H:i', $data['start_time'])->format('H:i:s');
        $selesai = Carbon::createFromFormat('H:i', $data['end_time'])->format('H:i:s');

        $bentrok = TimesheetEntry::where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->where('start_time', '<', $selesai)
            ->where('end_time', '>', $mulai)
            ->exists();

        if ($bentrok) {
            return back()
                ->withErrors(['start_time' => 'Jam bertumpuk dengan catatan lain.'])
                ->withInput();
        }

        TimesheetEntry::forceCreate([
            'user_id'    => $user->id,
            'work_date'  => $workDate,
            'job_type'   => $data['job_type'],
            'start_time' => $mulai,
            'end_time'   => $selesai,
            'note'       => $data['note'] ?? null,
            'source'     => 'operator',
        ]);

        return redirect()->route('operator.timesheet')->with('status', 'Catatan disimpan.');
    }

    public function destroy(Request $request, TimesheetEntry $entry)
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->delete();

        return back()->with('status', 'Catatan dihapus.');
    }

    public function setStatus(Request $request)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['cuti', 'izin', 'sakit'])],
            'note'   => ['required', 'string', 'max:255'],
        ], [
            'status.required' => 'Pilih cuti, izin, atau sakit.',
            'note.required'   => 'Isi alasan singkat.',
        ]);

        $user = $request->user();
        $hariIni = now()->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $hariIni)
            ->first();

        if ($attendance?->check_in_at) {
            return back()->withErrors([
                'status' => 'Anda sudah absen masuk hari ini, jadi tidak bisa ditandai cuti/izin/sakit.',
            ]);
        }

        $attendance ??= (new Attendance())->forceFill([
            'user_id'   => $user->id,
            'work_date' => $hariIni,
        ]);

        $attendance->forceFill([
            'status' => $data['status'],
            'note'   => $data['note'],
        ])->save();

        return back()->with('status', 'Keterangan ' . $data['status'] . ' tersimpan. Monitoring dapat melihat dan mengoreksinya.');
    }

    public function clearStatus(Request $request)
    {
        Attendance::where('user_id', $request->user()->id)
            ->whereDate('work_date', now()->toDateString())
            ->whereNull('check_in_at')
            ->whereIn('status', ['cuti', 'izin', 'sakit'])
            ->first()
            ?->delete();

        return back()->with('status', 'Keterangan dibatalkan.');
    }
}