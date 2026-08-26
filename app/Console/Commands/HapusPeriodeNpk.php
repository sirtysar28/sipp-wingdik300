<?php

namespace App\Console\Commands;

use App\Models\{PeriodeNilai, NilaiKepribadian, DetailKepribadian};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hard delete periode NPK (kepribadian) beserta seluruh isinya.
 *
 * Revisi 26 Agustus 2026: periode uji coba (mis. "Periode 1" & "Periode 6")
 * masih tampil di Report NPK — https://sipp-wingdik300.my.id/report/npk.
 * Command ini menghapus permanen:
 *   detail_kepribadian → nilai_kepribadian → periode_nilai.
 *
 * Contoh pemakaian (di server produksi):
 *   php artisan sipp:hapus-periode-npk 1 6 --force
 *   php artisan sipp:hapus-periode-npk --label="Periode 1" --angkatan=3 --force
 */
class HapusPeriodeNpk extends Command
{
    protected $signature = 'sipp:hapus-periode-npk
                            {ids?* : ID periode_nilai (bisa lebih dari satu, dipisah spasi)}
                            {--label= : Alternatif: hapus berdasarkan label periode}
                            {--angkatan= : Filter angkatan_id saat memakai --label}
                            {--force : Jalankan tanpa konfirmasi interaktif}';

    protected $description = 'Hard delete periode NPK + seluruh data nilai kepribadian & detailnya (permanen)';

    public function handle(): int
    {
        $ids = collect($this->argument('ids'))->filter(fn($v) => $v !== '')->map(fn($v) => (int) $v);

        // Mode --label: cari ID berdasarkan label (opsional + angkatan)
        if ($this->option('label')) {
            $q = PeriodeNilai::where('label', 'like', trim((string) $this->option('label')));
            if ($this->option('angkatan')) {
                $q->where('angkatan_id', (int) $this->option('angkatan'));
            }
            $found = $q->pluck('id');
            if ($found->isEmpty()) {
                $this->error('Tidak ada periode dengan label tersebut.');
                return self::FAILURE;
            }
            $ids = $ids->merge($found)->unique();
        }

        $ids = $ids->unique()->values();

        if ($ids->isEmpty()) {
            $this->error('Berikan minimal satu ID periode, atau gunakan --label="Nama Periode".');
            return self::FAILURE;
        }

        $periodes = PeriodeNilai::whereIn('id', $ids)->orderBy('id')->get();

        if ($periodes->isEmpty()) {
            $this->error('Periode tidak ditemukan. Daftar periode yang ada:');
            $this->table(
                ['ID', 'Angkatan', 'Label', 'Mulai', 'Selesai', 'Aktif', 'Jumlah Nilai'],
                PeriodeNilai::orderBy('id')
                    ->withCount('nilaiKepribadian as jumlah_nilai')
                    ->get()
                    ->map(fn($p) => [
                        $p->id, $p->angkatan_id, $p->label,
                        $p->tanggal_mulai?->format('Y-m-d'), $p->tanggal_selesai?->format('Y-m-d'),
                        $p->aktif ? 'YA' : '-', $p->jumlah_nilai,
                    ])->all()
            );
            return self::FAILURE;
        }

        $this->warn('Periode berikut akan dihapus PERMANEN berserta seluruh datanya:');
        $this->table(
            ['ID', 'Angkatan', 'Label', 'Mulai', 'Selesai', 'Aktif', 'Jumlah Nilai'],
            $periodes->map(fn($p) => [
                $p->id, $p->angkatan_id, $p->label,
                $p->tanggal_mulai?->format('Y-m-d'), $p->tanggal_selesai?->format('Y-m-d'),
                $p->aktif ? 'YA' : '-',
                NilaiKepribadian::where('periode_nilai_id', $p->id)->count(),
            ])->all()
        );

        if (!$this->option('force') && !$this->confirm('Yakin ingin melanjutkan hard delete? TIDAK bisa dibatalkan')) {
            $this->info('Dibatalkan.');
            return self::SUCCESS;
        }

        foreach ($periodes as $periode) {
            DB::transaction(function () use ($periode) {
                $nilaiIds = NilaiKepribadian::where('periode_nilai_id', $periode->id)->pluck('id');
                $detail   = DetailKepribadian::whereIn('nilai_kepribadian_id', $nilaiIds)->delete();
                $nilai    = NilaiKepribadian::where('periode_nilai_id', $periode->id)->delete();

                // Bila periode aktif dihapus, aktifkan periode terakhir yg tersisa
                if ($periode->aktif) {
                    $terakhir = PeriodeNilai::where('angkatan_id', $periode->angkatan_id)
                        ->where('id', '!=', $periode->id)
                        ->orderByDesc('tanggal_mulai')->first();
                    if ($terakhir) $terakhir->update(['aktif' => true]);
                }

                $periode->delete();
                $this->info("✔ [{$periode->id}] \"{$periode->label}\" — {$nilai} nilai & {$detail} detail dihapus.");
            });
        }

        $this->newLine();
        $this->info('Selesai. Periode terhapus tidak akan tampil lagi di Report NPK.');
        return self::SUCCESS;
    }
}
