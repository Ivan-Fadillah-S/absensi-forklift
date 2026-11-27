<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Support\DailyStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MonitoringController extends Controller
{
    public const ALASAN = [
        'Operator lupa absen',
        'Kendala HP atau aplikasi',
        'Kendala lokasi atau GPS',
        'Koreksi oleh supervisor',
        'Cuti, izin, atau sakit sesuai pemberitahuan operator',
        'Lainnya',
    ];

    public function dashboard(Request $request)
    {
        $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'filter'  => ['nullable', 'in:lengkap,lupa,belum,izin,luar'],
        ]);

        $tanggal = $request->filled('tanggal')
            ? Carbon::createFromFormat('Y-m-d', $request->query('tanggal'))->startOfDay()
            : now()->startOfDay();

        $operators = User::where('role', 'operator')
            ->where('is_active', true)
            ->with(['shift', 'workSystem'])
            ->orderBy('username')
            ->get();

        $absen = Attendance::whereDate('work_date', $tanggal->toDateString())
            ->get()
            ->keyBy('user_id');

        $baris = $operators->map(function (User $op) use ($absen, $tanggal) {
            $a = $absen->get($op->id);
            [$kode, $label] = DailyStatus::resolve($op, $a, $tanggal);

            return [
                'user'  => $op,
                'a'     => $a,
                'kode'  => $kode,
                'label' => $label,
                'luar'  => (bool) ($a?->check_in_outside_area || $a?->check_out_outside_area),
                'jarak' => $a?->check_in_distance_m ?? $a?->check_out_distance_m,
            ];
        });

        $hitung = [
            'lengkap' => $baris->where('kode', 'lengkap')->count(),
            'lupa'    => $baris->whereIn('kode', ['lupa_masuk', 'lupa_pulang'])->count(),
            'belum'   => $baris->where('kode', 'belum')->count(),
            'izin'    => $baris->where('kode', 'izin')->count(),
            'luar'    => $baris->where('luar', true)->count(),
        ];

        $filter = $request->query('filter');

        $tampil = match ($filter) {
            'lengkap' => $baris->where('kode', 'lengkap'),
            'lupa'    => $baris->whereIn('kode', ['lupa_masuk', 'lupa_pulang']),
            'belum'   => $baris->where('kode', 'belum'),
            'izin'    => $baris->where('kode', 'izin'),
            'luar'    => $baris->where('luar', true),
            default   => $baris,
        };

        return view('monitoring.dashboard', [
            'tanggal'  => $tanggal,
            'prev'     => $tanggal->copy()->subDay()->format('Y-m-d'),
            'next'     => $tanggal->copy()->addDay()->format('Y-m-d'),
            'bisaNext' => $tanggal->lt(now()->startOfDay()),
            'hitung'   => $hitung,
            'filter'   => $filter,
            'tampil'   => $tampil->values(),
            'total'    => $baris->count(),
        ]);
    }

    public function show(Request $request, User $operator, string $tanggal)
    {
        abort_unless($operator->role === 'operator', 404);

        $tgl = $this->parseTanggal($tanggal);
        $operator->load(['shift', 'workSystem', 'site']);

        $attendance = Attendance::where('user_id', $operator->id)
            ->whereDate('work_date', $tgl->toDateString())
            ->with('corrections.corrector')
            ->first();

        [$kode, $label] = DailyStatus::resolve($operator, $attendance, $tgl);

        $tab = $request->query('bukti');
        if (! in_array($tab, ['masuk', 'pulang'], true)) {
            $tab = $attendance?->check_in_at ? 'masuk' : 'pulang';
        }

        return view('monitoring.attendance', [
            'operator'   => $operator,
            'tgl'        => $tgl,
            'attendance' => $attendance,
            'kode'       => $kode,
            'label'      => $label,
            'jendela'    => DailyStatus::shiftWindow($operator, $tgl),
            'riwayat'    => $this->susunRiwayat($attendance),
            'tab'        => $tab,
            'bukti'      => $this->susunBukti($attendance, $tab),
            'alasan'     => self::ALASAN,
        ]);
    }

    public function update(Request $request, User $operator, string $tanggal)
    {
        abort_unless($operator->role === 'operator', 404);

        $tgl = $this->parseTanggal($tanggal);
        $tanggalStr = $tgl->toDateString();
        $operator->load('shift');

        $data = $request->validate([
            'status'    => ['required', Rule::in(['hadir', 'cuti', 'izin', 'sakit'])],
            'check_in'  => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'reason'    => ['required', Rule::in(self::ALASAN)],
            'note'      => [
                'nullable', 'string', 'max:200',
                Rule::requiredIf(fn () => $request->input('reason') === 'Lainnya'
                    || in_array($request->input('status'), ['cuti', 'izin', 'sakit'], true)),
            ],
        ], [
            'status.required'      => 'Pilih status hari itu.',
            'check_in.date_format' => 'Format jam masuk tidak valid.',
            'check_out.date_format' => 'Format jam pulang tidak valid.',
            'reason.required'      => 'Alasan koreksi wajib dipilih.',
            'reason.in'            => 'Alasan koreksi tidak dikenal.',
            'note.required'        => 'Catatan wajib diisi untuk alasan "Lainnya" dan untuk status cuti, izin, atau sakit.',
        ]);

        $attendance = Attendance::where('user_id', $operator->id)
            ->whereDate('work_date', $tanggalStr)
            ->first();
        $baru = $attendance === null;
        $status = $data['status'];

        if ($status !== 'hadir') {
            if ($data['check_in'] || $data['check_out']) {
                return back()->withInput()->withErrors([
                    'status' => 'Kosongkan jam masuk dan pulang untuk status cuti, izin, atau sakit.',
                ]);
            }

            if ($attendance?->check_in_at || $attendance?->check_out_at) {
                return back()->withInput()->withErrors([
                    'status' => 'Operator sudah punya jam absen pada hari ini, jadi tidak bisa diubah menjadi cuti, izin, atau sakit.',
                ]);
            }
        } elseif (! $data['check_in'] && ! $data['check_out']
            && ! $attendance?->check_in_at && ! $attendance?->check_out_at) {
            return back()->withInput()->withErrors([
                'check_in' => 'Isi minimal satu jam (masuk atau pulang).',
            ]);
        }

        $masuk = $data['check_in'] ? Carbon::parse("{$tanggalStr} {$data['check_in']}") : null;
        $pulang = $data['check_out'] ? Carbon::parse("{$tanggalStr} {$data['check_out']}") : null;

        // Pulang yang lebih awal dari acuan berarti sudah lewat tengah malam
        if ($pulang) {
            $jendela = DailyStatus::shiftWindow($operator, $tgl);
            $acuan = $masuk ?? $attendance?->check_in_at ?? ($jendela[0] ?? null);

            if ($acuan && $pulang->lte($acuan)) {
                $pulang->addDay();
            }
        }

        if ($masuk && $pulang && abs($pulang->timestamp - $masuk->timestamp) > 16 * 3600) {
            return back()->withInput()->withErrors([
                'check_out' => 'Selisih jam masuk dan pulang lebih dari 16 jam. Periksa lagi jamnya.',
            ]);
        }

        $attendance ??= (new Attendance())->forceFill([
            'user_id'   => $operator->id,
            'work_date' => $tanggalStr,
        ]);

        $ubah = []; // kolom => [nilai lama, nilai baru]

        if ($masuk && $attendance->check_in_at?->format('Y-m-d H:i') !== $masuk->format('Y-m-d H:i')) {
            $ubah['check_in_at'] = [$attendance->check_in_at?->format('H.i'), $masuk->format('H.i')];
            $attendance->forceFill(['check_in_at' => $masuk, 'check_in_source' => 'monitoring']);
        }

        if ($pulang && $attendance->check_out_at?->format('Y-m-d H:i') !== $pulang->format('Y-m-d H:i')) {
            $ubah['check_out_at'] = [$attendance->check_out_at?->format('H.i'), $pulang->format('H.i')];
            $attendance->forceFill(['check_out_at' => $pulang, 'check_out_source' => 'monitoring']);
        }

        $statusLama = $baru ? null : $attendance->status;
        if ($statusLama !== $status && ! ($baru && $status === 'hadir')) {
            $ubah['status'] = [$statusLama, $status];
        }
        $attendance->status = $status;

        if ($status !== 'hadir' && ! empty($data['note'])) {
            $attendance->note = $data['note'];
        }

        if (empty($ubah)) {
            return back()->withInput()->withErrors(['reason' => 'Tidak ada perubahan untuk disimpan.']);
        }

        $alasan = $data['reason'] . (! empty($data['note']) ? ' — ' . $data['note'] : '');
        $penyimpan = $request->user();

        DB::transaction(function () use ($attendance, $ubah, $penyimpan, $alasan) {
            $attendance->save();

            foreach ($ubah as $kolom => [$lama, $sesudah]) {
                AttendanceCorrection::forceCreate([
                    'attendance_id' => $attendance->id,
                    'corrected_by'  => $penyimpan->id,
                    'field'         => $kolom,
                    'old_value'     => $lama,
                    'new_value'     => $sesudah,
                    'reason'        => mb_substr($alasan, 0, 255),
                ]);
            }
        });

        return redirect()
            ->route('monitoring.attendance.show', ['operator' => $operator, 'tanggal' => $tanggalStr])
            ->with('status', 'Koreksi tersimpan dan tercatat di riwayat perubahan.');
    }

    private function parseTanggal(string $tanggal): Carbon
    {
        abort_unless(Carbon::canBeCreatedFromFormat($tanggal, 'Y-m-d'), 404);

        return Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay();
    }

    /**
     * Gabungkan catatan asli operator dan koreksi monitoring, terbaru di atas.
     */
    private function susunRiwayat(?Attendance $a): array
    {
        if (! $a) {
            return [];
        }

        $item = [];

        foreach (['check_in' => 'masuk', 'check_out' => 'pulang'] as $prefix => $nama) {
            $waktu = $a->{"{$prefix}_at"};

            if ($waktu && $a->{"{$prefix}_source"} === 'operator') {
                $item[] = [
                    'judul'   => 'Absen ' . $nama . ' ' . $waktu->format('H.i'),
                    'oleh'    => 'Dicatat operator · ' . $waktu->copy()->locale('id')->translatedFormat('j M Y, H.i'),
                    'waktu'   => $waktu,
                    'koreksi' => false,
                ];
            }
        }

        foreach ($a->corrections as $c) {
            $label = AttendanceCorrection::FIELD_LABELS[$c->field] ?? $c->field;
            $nilai = fn ($v) => $v === null ? 'kosong' : ($c->field === 'status' ? ucfirst($v) : $v);

            $item[] = [
                'judul'   => "{$label} " . $nilai($c->old_value) . ' → ' . $nilai($c->new_value),
                'oleh'    => ($c->corrector?->name ?? 'Monitoring') . ' · '
                    . $c->created_at->copy()->locale('id')->translatedFormat('j M Y, H.i') . ' · ' . $c->reason,
                'waktu'   => $c->created_at,
                'koreksi' => true,
            ];
        }

        usort($item, fn ($x, $y) => $y['waktu']->timestamp <=> $x['waktu']->timestamp);

        return $item;
    }

    /**
     * Data bukti (foto, lokasi) untuk tab Masuk atau Pulang.
     */
    private function susunBukti(?Attendance $a, string $tab): ?array
    {
        if (! $a) {
            return null;
        }

        $p = $tab === 'masuk' ? 'check_in' : 'check_out';
        $waktu = $a->{"{$p}_at"};

        if (! $waktu) {
            return null;
        }

        return [
            'waktu'    => $waktu,
            'source'   => $a->{"{$p}_source"},
            'photo'    => $a->{"{$p}_photo"},
            'lat'      => $a->{"{$p}_lat"},
            'lng'      => $a->{"{$p}_lng"},
            'accuracy' => $a->{"{$p}_accuracy_m"},
            'distance' => $a->{"{$p}_distance_m"},
            'outside'  => (bool) $a->{"{$p}_outside_area"},
        ];
    }
}