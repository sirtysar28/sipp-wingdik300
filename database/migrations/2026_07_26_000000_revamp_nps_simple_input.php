<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revamp NPS (Nilai Prestasi Samapta) menjadi input sederhana 5 field manual
 * TANPA rumus/formula:
 *   1. jarak_lari       — Jarak Lari (meter)
 *   2. nilai_lari       — Nilai Lari (Garjas A)
 *   3. garjas_b_nilai   — Garjas B
 *   4. nilai_akhir      — Nilai Akhir  (sudah ada, kini diisi manual)
 *   5. nilai_konversi   — Nilai Konversi (re-add, pernah di-drop)
 *
 * Menambahkan `putaran_label` sebagai bagian dari unique key agar satu peserta
 * bisa punya beberapa nilai NPS (Putaran 1, Putaran 2, dst) — konsep mirip
 * "periode" di NPK tapi khusus NPS.
 *
 * Idempotent: aman dijalankan ulang bila sebagian step sempat gagal (MySQL DDL
 * auto-commit sehingga kolom bisa tertinggal walau migrasi gagal).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill NULL putaran_label → 'Putaran 1' supaya unique key bersih.
        DB::table('nilai_samapta')->whereNull('putaran_label')->update(['putaran_label' => 'Putaran 1']);

        // 2. Tambah 4 kolom baru (nilai_akhir sudah ada) — hanya jika belum ada.
        Schema::table('nilai_samapta', function (Blueprint $table) {
            if (!Schema::hasColumn('nilai_samapta', 'jarak_lari')) {
                $table->decimal('jarak_lari', 8, 2)->nullable()->after('putaran_label')->comment('Jarak Lari (meter) — input manual');
            }
            if (!Schema::hasColumn('nilai_samapta', 'nilai_lari')) {
                $table->decimal('nilai_lari', 6, 2)->nullable()->after('jarak_lari')->comment('Nilai Lari / Garjas A — input manual');
            }
            if (!Schema::hasColumn('nilai_samapta', 'garjas_b_nilai')) {
                $table->decimal('garjas_b_nilai', 6, 2)->nullable()->after('nilai_lari')->comment('Garjas B — input manual');
            }
            if (!Schema::hasColumn('nilai_samapta', 'nilai_konversi')) {
                $table->decimal('nilai_konversi', 6, 2)->nullable()->after('nilai_akhir')->comment('Nilai Konversi — input manual');
            }
        });

        // 3. Ganti unique key: (peserta, angkatan) → (peserta, angkatan, putaran_label).
        //    Penting: buat unique BARU dulu sebelum drop unique LAMA, karena
        //    unique lama dipakai oleh FK peserta_didik_id. Unique baru juga
        //    ber-index pada peserta_didik_id (leftmost) sehingga FK tetap terpenuhi.
        $this->createUniqueIfNotExists(
            'nilai_samapta',
            ['peserta_didik_id', 'angkatan_id', 'putaran_label'],
            'nilai_samapta_peserta_didik_id_angkatan_id_putaran_label_unique'
        );
        $this->dropIndexIfExists('nilai_samapta', 'nilai_samapta_peserta_didik_id_angkatan_id_unique');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('nilai_samapta', 'nilai_samapta_peserta_didik_id_angkatan_id_putaran_label_unique');
        Schema::table('nilai_samapta', function (Blueprint $table) {
            if (Schema::hasColumn('nilai_samapta', 'nilai_samapta_peserta_didik_id_angkatan_id_unique') === false) {
                // recreate old unique only if not present
            }
        });
        // Recreate old unique (best-effort).
        try {
            Schema::table('nilai_samapta', function (Blueprint $table) {
                $table->unique(['peserta_didik_id', 'angkatan_id'], 'nilai_samapta_peserta_didik_id_angkatan_id_unique');
            });
        } catch (\Throwable $e) { /* ignore if exists */ }

        Schema::table('nilai_samapta', function (Blueprint $table) {
            foreach (['jarak_lari', 'nilai_lari', 'garjas_b_nilai', 'nilai_konversi'] as $col) {
                if (Schema::hasColumn('nilai_samapta', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /** Drop a named index only if it exists (MySQL-safe). */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        }
    }

    /** Create a named unique index only if it does not exist. */
    private function createUniqueIfNotExists(string $table, array $columns, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                $t->unique($columns, $indexName);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
    }
};
