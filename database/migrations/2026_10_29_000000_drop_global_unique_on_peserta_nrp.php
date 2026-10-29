<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REVISI ATURAN UNIKENESS (Okt 2026, berlaku se-Wingdik 300):
 *
 *   ┌──────────────────────────────────────────────────────────────────┐
 *   │ 1. NOSIS → unik GLOBAL se-Wingdik 300. Nosis yang sama TIDAK     │
 *   │    boleh dipakai lagi di sekolah lain.                           │
 *   │      → unique index global pada kolom `nosis` TETAP dipertahankan│
 *   │        (dipasang oleh migration 2026_08_06_000000).              │
 *   │                                                                  │
 *   │ 2. NRP   → unik per SEKOLAH (Skadik) saja. NRP yang sama BOLEH   │
 *   │    dipakai lagi oleh peserta di sekolah lain dalam Wingdik 300.  │
 *   │      → unique index GLOBAL pada kolom `nrp` harus DIHAPUS karena │
 *   │        memblokir NRP yang sama antar sekolah.                    │
 *   └──────────────────────────────────────────────────────────────────┘
 *
 * Uniqueness NRP per sekolah TIDAK bisa dinyatakan sebagai index DB biasa
 * (relasi peserta → angkatan → skadik menyeberang tabel), sehingga
 * ditegakkan di level aplikasi (validasi PesertaController + PesertaImport),
 * lihat helper PesertaDidik::nrpUsedInSkadik().
 */
return new class extends Migration
{
    /**
     * Daftar nama index unique global pada kolom nrp yang mungkin terpasang
     * (nama bervariasi antara versi schema / DB driver).
     */
    private function nrpUniqueIndexes(): array
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('peserta_didik')");
            return collect($rows)
                ->filter(fn($r) => (int) $r->unique === 1)
                ->pluck('name')
                ->intersect(['peserta_didik_nrp_unique', 'peserta_didik_nrp_unique_2'])
                ->values()
                ->all();
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                "SELECT indexname AS name FROM pg_indexes
                 WHERE tablename = 'peserta_didik'
                   AND indexname IN ('peserta_didik_nrp_unique', 'peserta_didik_nrp_unique_2')"
            );
            return array_column($rows, 'name');
        }

        // MySQL / MariaDB (default produksi)
        $rows = DB::select("SHOW INDEX FROM peserta_didik WHERE Column_name = 'nrp'");
        $names = collect($rows)
            ->groupBy('Key_name')
            ->filter(fn($group) => $group->count() === 1) // index single-column saja
            ->keys()
            ->intersect(['peserta_didik_nrp_unique', 'peserta_didik_nrp_unique_2'])
            ->values()
            ->all();

        return $names;
    }

    public function up(): void
    {
        foreach ($this->nrpUniqueIndexes() as $indexName) {
            Schema::table('peserta_didik', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }

    public function down(): void
    {
        // Rollback: kembalikan unique global pada nrp. Bila sudah ada data
        // NRP ganda lintas sekolah, hapus dulu duplikatnya secara manual
        // sebelum menjalankan rollback ini.
        Schema::table('peserta_didik', function (Blueprint $table) {
            $table->unique('nrp', 'peserta_didik_nrp_unique');
        });
    }
};
