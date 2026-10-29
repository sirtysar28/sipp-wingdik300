<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REVISI 29 Oktober 2026 — TAMBAHAN aturan kasus MENGULANG (tidak lulus):
 *
 * Siswa yang TIDAK LULUS mengulang di SEKOLAH (Skadik) yang sama dengan
 * ANGKATAN yang berbeda, tetap memakai NRP dan NOSIS yang sama. Artinya
 * nosis kini BISA muncul ganda antar angkatan di satu sekolah (record
 * peserta baru untuk riwayat angkatan baru).
 *
 *   ┌────────────────────────────────────┬─────────┬─────────┐
 *   │ KONDISI                            │  NRP    │  NOSIS  │
 *   ├────────────────────────────────────┼─────────┼─────────┤
 *   │ 1 angkatan yang sama               │ ❌ dobel│ ❌ dobel│
 *   │ Sekolah sama, angkatan beda        │ ✅ boleh│ ✅ boleh│
 *   │ (tidak lulus → mengulang, NRP sama)│         │         │
 *   │ Sekolah lain (Wingdik 300)         │ ✅ boleh│ ❌ dobel│
 *   └────────────────────────────────────┴─────────┴─────────┘
 *
 * Konsekuensinya unique index GLOBAL pada kolom `nosis` (dipasang oleh
 * migration 2026_08_06_000000) harus DIHAPUS — siswa mengulang akan
 * menyimpan nosis yang sama pada dua record berbeda.
 *
 * Aturan nosis (tolak antar sekolah / antar peserta, boleh untuk kasus
 * mengulang) ditegakkan di level aplikasi karena melibatkan relasi
 * peserta → angkatan → skadik yang tidak bisa dinyatakan sebagai index
 * DB biasa. Lihat PesertaDidik::nosisConflict().
 */
return new class extends Migration
{
    /**
     * Daftar nama index unique global pada kolom nosis yang mungkin terpasang.
     */
    private function nosisUniqueIndexes(): array
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('peserta_didik')");
            return collect($rows)
                ->filter(fn($r) => (int) $r->unique === 1)
                ->pluck('name')
                ->intersect(['peserta_didik_nosis_unique', 'peserta_didik_nosis_unique_2'])
                ->values()
                ->all();
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                "SELECT indexname AS name FROM pg_indexes
                 WHERE tablename = 'peserta_didik'
                   AND indexname IN ('peserta_didik_nosis_unique', 'peserta_didik_nosis_unique_2')"
            );
            return array_column($rows, 'name');
        }

        // MySQL / MariaDB (default produksi)
        $rows = DB::select("SHOW INDEX FROM peserta_didik WHERE Column_name = 'nosis'");
        return collect($rows)
            ->groupBy('Key_name')
            ->filter(fn($group) => $group->count() === 1) // index single-column saja
            ->keys()
            ->intersect(['peserta_didik_nosis_unique', 'peserta_didik_nosis_unique_2'])
            ->values()
            ->all();
    }

    public function up(): void
    {
        foreach ($this->nosisUniqueIndexes() as $indexName) {
            Schema::table('peserta_didik', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }

    public function down(): void
    {
        // Rollback: kembalikan unique global pada nosis. Bila sudah ada data
        // nosis ganda (siswa mengulang), hapus/ubah dulu duplikatnya secara
        // manual sebelum menjalankan rollback ini.
        Schema::table('peserta_didik', function (Blueprint $table) {
            $table->unique('nosis', 'peserta_didik_nosis_unique');
        });
    }
};
