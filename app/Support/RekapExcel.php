<?php

namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapExcel
{
    public static function buat(array $baris, Carbon $mulai, Carbon $akhir, string $site): Spreadsheet
    {
        $buku = new Spreadsheet();

        $rekap = $buku->getActiveSheet();
        $rekap->setTitle('Rekap');
        self::isiRekap($rekap, $baris, $mulai, $akhir, $site);

        $harian = $buku->createSheet();
        $harian->setTitle('Harian');
        self::isiHarian($harian, $baris);

        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    private static function gayaJudul(Worksheet $s, string $rentang): void
    {
        $gaya = $s->getStyle($rentang);
        $gaya->getFont()->setBold(true);
        $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5B800');
        $gaya->getAlignment()->setWrapText(true)->setVertical('center');
    }

    private static function isiRekap(Worksheet $s, array $baris, Carbon $mulai, Carbon $akhir, string $site): void
    {
        $tgl = fn (Carbon $c) => $c->copy()->locale('id')->translatedFormat('j F Y');

        $s->setCellValue('A1', 'Rekap Absensi Operator Forklift');
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $s->setCellValue('A2', 'Periode: ' . $tgl($mulai) . ' – ' . $tgl($akhir));
        $s->setCellValue('A3', $site);

        $belumFinal = count(array_filter($baris, fn ($b) => $b['belum'] > 0));
        if ($belumFinal > 0) {
            $s->setCellValue('A4', "REKAP BELUM FINAL: {$belumFinal} operator masih punya lupa absen yang belum dikoreksi.");
            $s->getStyle('A4')->getFont()->setBold(true)->getColor()->setRGB('B45309');
        }

        $judul = [
            'No', 'Operator', 'Shift', 'Hadir', 'Jam per hari', 'Jam normal', 'Lembur (jam)', 'Total jam',
            'Cuti', 'Izin', 'Sakit', 'Lupa absen (dikoreksi)', 'Lupa absen (belum dikoreksi)', 'Keterangan',
        ];
        foreach ($judul as $i => $teks) {
            $s->setCellValue(chr(65 + $i) . '6', $teks);
        }
        self::gayaJudul($s, 'A6:N6');

        $r = 7;
        foreach ($baris as $i => $b) {
            $s->setCellValue("A{$r}", $i + 1);
            $s->setCellValue("B{$r}", $b['user']->name);
            $s->setCellValue("C{$r}", $b['user']->shift?->name ?? '');
            $s->setCellValue("D{$r}", $b['hadir']);
            $s->setCellValue("E{$r}", $b['jamPerHari']);
            $s->setCellValue("F{$r}", "=D{$r}*E{$r}");
            $s->setCellValue("G{$r}", $b['lemburJam']);
            $s->setCellValue("H{$r}", "=F{$r}+G{$r}");
            $s->setCellValue("I{$r}", $b['cuti']);
            $s->setCellValue("J{$r}", $b['izin']);
            $s->setCellValue("K{$r}", $b['sakit']);
            $s->setCellValue("L{$r}", $b['dikoreksi']);
            $s->setCellValue("M{$r}", $b['belum']);

            if ($b['belum'] > 0) {
                $tanggal = array_map(fn ($t) => Carbon::parse($t)->format('d/m'), $b['tanggalBelum']);
                $s->setCellValue("N{$r}", 'Perlu dikoreksi: ' . implode(', ', $tanggal));
            }

            $r++;
        }

        $akhirData = $r - 1;

        $s->setCellValue("B{$r}", 'Total');
        foreach (['D', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'] as $k) {
            $s->setCellValue("{$k}{$r}", "=SUM({$k}7:{$k}{$akhirData})");
        }
        $s->getStyle("A{$r}:N{$r}")->getFont()->setBold(true);
        $s->getStyle("G7:H{$r}")->getNumberFormat()->setFormatCode('0.00');

        // Kolom tanda tangan, menggantikan lembar manual
        $r += 3;
        $s->setCellValue("B{$r}", 'Dibuat oleh (Monitoring)');
        $s->setCellValue("H{$r}", 'Diperiksa oleh (Supervisor)');
        $r += 4;
        $s->setCellValue("B{$r}", '(............................)');
        $s->setCellValue("H{$r}", '(............................)');

        $s->freezePane('C7');
        $s->getColumnDimension('A')->setWidth(6);
        foreach (range('B', 'N') as $k) {
            $s->getColumnDimension($k)->setAutoSize(true);
        }
    }

    private static function isiHarian(Worksheet $s, array $baris): void
    {
        $judul = ['Tanggal', 'Operator', 'Shift', 'Masuk', 'Pulang', 'Lembur mulai', 'Lembur selesai', 'Lembur (jam)', 'Keterangan'];
        foreach ($judul as $i => $teks) {
            $s->setCellValue(chr(65 + $i) . '1', $teks);
        }
        self::gayaJudul($s, 'A1:I1');

        $r = 2;
        foreach ($baris as $b) {
            foreach ($b['harian'] as $a) {
                $d = Rekap::detailHari($a);

                $s->setCellValue("A{$r}", Date::PHPToExcel($a->work_date));
                $s->getStyle("A{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
                $s->setCellValue("B{$r}", $b['user']->name);
                $s->setCellValue("C{$r}", $b['user']->shift?->name ?? '');
                $s->setCellValue("D{$r}", $d['masuk'] ?? '');
                $s->setCellValue("E{$r}", $d['pulang'] ?? '');
                $s->setCellValue("F{$r}", $d['lemburMulai'] ?? '');
                $s->setCellValue("G{$r}", $d['lemburSelesai'] ?? '');
                $s->setCellValue("H{$r}", round($d['lemburMenit'] / 60, 2));
                $s->setCellValue("I{$r}", $d['keterangan']);
                $r++;
            }
        }

        $s->getStyle('H2:H' . max(2, $r - 1))->getNumberFormat()->setFormatCode('0.00');
        $s->freezePane('A2');
        $s->setAutoFilter('A1:I' . max(2, $r - 1));
        foreach (range('A', 'I') as $k) {
            $s->getColumnDimension($k)->setAutoSize(true);
        }
    }
}