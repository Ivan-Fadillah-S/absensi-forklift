<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class Ai
{
    /**
     * Kirim satu pertanyaan ke Gemini (paket gratis). Kalau model sibuk, coba ulang
     * lalu pindah ke model cadangan.
     */
    public static function tanya(string $system, string $pesan, int $maxToken = 2048, bool $json = false): string
    {
        $key = config('services.gemini.key');

        if (! $key) {
            throw new RuntimeException('API key AI belum diisi. Isi GEMINI_API_KEY di file .env, lalu jalankan: php artisan config:clear');
        }

        @set_time_limit(90);

        $daftarModel = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('services.gemini.model'))
        )));

        $konfigurasi = ['maxOutputTokens' => $maxToken];
        if ($json) {
            $konfigurasi['responseMimeType'] = 'application/json';
        }

        $galatTerakhir = 'Layanan AI sedang sibuk. Coba lagi sebentar.';

        foreach ($daftarModel as $model) {
            for ($percobaan = 1; $percobaan <= 2; $percobaan++) {
                try {
                    $res = Http::withHeaders(['x-goog-api-key' => $key])
                        ->withOptions(['force_ip_resolve' => 'v4'])
                        ->connectTimeout(10)
                        ->timeout(30)
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                            'systemInstruction' => ['parts' => [['text' => $system]]],
                            'contents'          => [['role' => 'user', 'parts' => [['text' => $pesan]]]],
                            'generationConfig'  => $konfigurasi,
                        ]);
                } catch (\Throwable $e) {
                    throw new RuntimeException('Tidak bisa menghubungi layanan AI: ' . mb_substr($e->getMessage(), 0, 160));
                }

                if ($res->successful()) {
                    $teks = collect($res->json('candidates.0.content.parts', []))
                        ->pluck('text')->filter()->implode("\n");

                    if (trim($teks) !== '') {
                        return trim($teks);
                    }

                    $galatTerakhir = 'AI tidak memberikan jawaban. Coba ulangi dengan kalimat yang berbeda.';
                    break; // pindah model
                }

                // Sibuk atau kuota habis: coba lagi, lalu pindah model
                if (in_array($res->status(), [429, 500, 503, 504], true)) {
                    $galatTerakhir = $res->status() === 429
                        ? 'Kuota gratis AI sedang habis. Tunggu sebentar lalu coba lagi.'
                        : 'Layanan AI sedang sibuk. Coba lagi sebentar.';

                    if ($percobaan < 2) {
                        sleep(2);
                    }
                    continue;
                }

                // Galat lain (key salah, nama model salah, dll): langsung laporkan
                $detail = $res->json('error.message') ?? ('kode ' . $res->status());
                throw new RuntimeException("Layanan AI menolak permintaan ({$model}): {$detail}");
            }
        }

        throw new RuntimeException($galatTerakhir);
    }
}