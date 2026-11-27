<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Ai;
use App\Support\Anomali;
use App\Support\DailyStatus;
use App\Support\Rekap;
use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;

class AiController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        [$akhirBulan, $mulai, $akhir] = Rekap::periode($request->query('bulan'));
        $temuan = Anomali::cari($mulai, $akhir);

        return view('monitoring.ai', [
            'temuan'       => $temuan,
            'tinggi'       => count(array_filter($temuan, fn ($t) => $t['tingkat'] === 'tinggi')),
            'sedang'       => count(array_filter($temuan, fn ($t) => $t['tingkat'] === 'sedang')),
            'operatorKena' => collect($temuan)->pluck('user.id')->unique()->count(),
            'bulan'        => $akhirBulan->format('Y-m'),
            'prev'         => $akhirBulan->copy()->subMonthNoOverflow()->format('Y-m'),
            'next'         => $akhirBulan->copy()->addMonthNoOverflow()->format('Y-m'),
            'bisaNext'     => $akhirBulan->lt(Rekap::periode(null)[0]),
            'labelPeriode' => $mulai->copy()->locale('id')->translatedFormat('j M') . ' – ' . $akhir->copy()->locale('id')->translatedFormat('j M Y'),
        ]);
    }

    public function koreksi(Request $request)
    {
        $data = $request->validate([
            'perintah' => ['required', 'string', 'min:8', 'max:400'],
        ], [
            'perintah.required' => 'Tulis perintah koreksi dulu.',
            'perintah.min'      => 'Perintah terlalu pendek.',
        ]);

        $operators = User::where('role', 'operator')
            ->where('is_active', true)
            ->with('shift')
            ->orderBy('username')
            ->get();

        $daftar = $operators
            ->map(fn ($u) => "- {$u->username} ({$u->name}), " . ($u->shift?->name ?? 'tanpa shift'))
            ->implode("\n");

        $aturan = <<<'TXT'
Kamu adalah asisten sistem absensi operator forklift. Tugasmu mengubah perintah koreksi dari petugas monitoring menjadi data terstruktur. Kamu hanya menyusun DRAF dan tidak menyimpan apa pun.

Balas HANYA dengan satu objek JSON, tanpa teks lain, tanpa penjelasan, dan tanpa tanda kutip balik.

Format:
{"operator": "username", "tanggal": "YYYY-MM-DD", "status": "hadir", "check_in": null, "check_out": "jam_shift", "alasan": "Operator lupa absen", "catatan": null, "error": null}

Aturan:
- "operator" harus salah satu username pada daftar operator.
- "status" salah satu dari: hadir, cuti, izin, sakit. Gunakan "hadir" kecuali petugas menyebut cuti, izin, atau sakit. Untuk cuti, izin, atau sakit: check_in dan check_out null, dan "catatan" wajib berisi alasannya.
- "check_in" dan "check_out" hanya diisi untuk jam yang disebut petugas, dalam format "HH:MM" 24 jam (contoh "jam 3 sore" menjadi "15:00"). Jika petugas menyebut "pakai jam shift" atau sejenisnya, isi dengan teks "jam_shift". Yang tidak disebut diisi null.
- Ubah "kemarin", "Jumat lalu", "tanggal 6", dan sejenisnya menjadi tanggal lengkap yang paling masuk akal. Tanggal tidak boleh di masa depan.
- "alasan" harus persis salah satu dari daftar alasan. Jika ragu, pilih "Lainnya" dan isi "catatan".
- Jika operator atau tanggal tidak bisa ditentukan, atau perintah bukan soal koreksi absensi, isi semua field lain dengan null dan isi "error" dengan satu kalimat pertanyaan klarifikasi dalam Bahasa Indonesia.
TXT;

        $hariIni = now();
        $system = $aturan
            . "\n\nHari ini: " . $hariIni->copy()->locale('id')->translatedFormat('l, j F Y') . ' (' . $hariIni->format('Y-m-d') . ')'
            . "\n\nDaftar alasan:\n- " . implode("\n- ", MonitoringController::ALASAN);

        try {
            $jawab = Ai::tanya($system, "Daftar operator:\n{$daftar}\n\nPerintah petugas:\n{$data['perintah']}", 2048, true);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['perintah' => $e->getMessage()]);
        }

        $json = $this->ambilJson($jawab);

        if (! $json) {
            return back()->withInput()->withErrors(['perintah' => 'Jawaban AI tidak bisa dibaca. Coba tulis perintah dengan lebih jelas.']);
        }

        if (! empty($json['error'])) {
            return back()->withInput()->with('ai_tanya', $json['error']);
        }

        // Semua keluaran AI diperiksa ulang oleh sistem (AI tidak dipercaya begitu saja)
        $operator = $operators->firstWhere('username', $json['operator'] ?? '');
        if (! $operator) {
            return back()->withInput()->withErrors(['perintah' => 'AI tidak menemukan operator yang dimaksud. Sebutkan nomor operatornya.']);
        }

        $tanggalAi = (string) ($json['tanggal'] ?? '');
        if (! Carbon::canBeCreatedFromFormat($tanggalAi, 'Y-m-d')) {
            return back()->withInput()->withErrors(['perintah' => 'AI tidak bisa menentukan tanggalnya. Sebutkan tanggal yang jelas.']);
        }

        $tgl = Carbon::createFromFormat('Y-m-d', $tanggalAi)->startOfDay();
        if ($tgl->gt(now()->startOfDay())) {
            return back()->withInput()->withErrors(['perintah' => 'Tanggal koreksi tidak boleh di masa depan.']);
        }

        $jendela = DailyStatus::shiftWindow($operator, $tgl);

        $jam = function ($nilai, int $indeks) use ($jendela) {
            if ($nilai === 'jam_shift') {
                return $jendela ? $jendela[$indeks]->format('H:i') : null;
            }

            return is_string($nilai) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $nilai) ? $nilai : null;
        };

        $masuk = $jam($json['check_in'] ?? null, 0);
        $pulang = $jam($json['check_out'] ?? null, 1);
        $status = in_array($json['status'] ?? '', ['hadir', 'cuti', 'izin', 'sakit'], true) ? $json['status'] : 'hadir';
        $alasan = in_array($json['alasan'] ?? '', MonitoringController::ALASAN, true) ? $json['alasan'] : 'Lainnya';
        $catatan = isset($json['catatan']) ? mb_substr((string) $json['catatan'], 0, 200) : null;

        if ($status === 'hadir' && ! $masuk && ! $pulang) {
            return back()->withInput()->withErrors(['perintah' => 'AI tidak menemukan jam yang harus diisi. Sebutkan jam masuk atau pulangnya.']);
        }

        if (($alasan === 'Lainnya' || $status !== 'hadir') && ! $catatan) {
            $catatan = 'Koreksi melalui asisten AI';
        }

        $isian = array_filter([
            'status'    => $status,
            'check_in'  => $masuk,
            'check_out' => $pulang,
            'reason'    => $alasan,
            'note'      => $catatan,
        ], fn ($v) => $v !== null);

        return redirect()
            ->route('monitoring.attendance.show', ['operator' => $operator, 'tanggal' => $tgl->format('Y-m-d')])
            ->withInput($isian)
            ->with('ai', 'Draf dari asisten AI. Periksa semua isian, lalu klik Simpan koreksi. Belum ada yang tersimpan.');
    }

    public function ringkas(Request $request)
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        [$akhirBulan, $mulai, $akhir] = Rekap::periode($request->input('bulan'));
        $temuan = Anomali::cari($mulai, $akhir);

        if (empty($temuan)) {
            return back()->with('ringkasan', 'Tidak ada temuan pada periode ini.');
        }

        $baris = collect($temuan)->take(40)
            ->map(fn ($t) => "- [{$t['tingkat']}] {$t['operator']}, {$t['tanggal']}: {$t['pesan']}")
            ->implode("\n");

        $system = 'Kamu asisten untuk petugas monitoring absensi operator forklift. '
            . 'Ringkas daftar temuan anomali yang diberikan dalam Bahasa Indonesia, maksimal 5 poin singkat. '
            . 'Sebut operator yang paling perlu diperiksa lebih dulu dan beri saran tindakan singkat. '
            . 'Ini hanya penanda untuk diperiksa, bukan bukti kecurangan: jangan menuduh dan gunakan bahasa "perlu diperiksa". '
            . 'Hanya gunakan data yang diberikan, jangan menambah fakta.';

        $label = $mulai->copy()->locale('id')->translatedFormat('j M') . ' sampai ' . $akhir->copy()->locale('id')->translatedFormat('j M Y');

        try {
            $teks = Ai::tanya($system, "Periode {$label}\n\nTemuan:\n{$baris}");
        } catch (RuntimeException $e) {
            return back()->withErrors(['ringkasan' => $e->getMessage()]);
        }

        return back()->with('ringkasan', $teks);
    }

    /**
     * Ambil objek JSON dari jawaban AI, walau ada teks di sekelilingnya.
     */
    private function ambilJson(string $teks): ?array
    {
        $awal = strpos($teks, '{');
        $akhir = strrpos($teks, '}');

        if ($awal === false || $akhir === false || $akhir < $awal) {
            return null;
        }

        $data = json_decode(substr($teks, $awal, $akhir - $awal + 1), true);

        return is_array($data) ? $data : null;
    }
}