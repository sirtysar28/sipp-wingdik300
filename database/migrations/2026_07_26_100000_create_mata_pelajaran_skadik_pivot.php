<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mata Pelajaran ↔ Sekolah (Skadik): relasi MANY-TO-MANY.
 *
 * Sebelumnya 1 mata pelajaran hanya milik 1 sekolah (kolom
 * `mata_pelajaran.skadik_id`). Sekarang 1 mata pelajaran bisa dipetakan ke
 * beberapa sekolah melalui pivot `mata_pelajaran_skadik`.
 *
 * - Kolom `mata_pelajaran.skadik_id` DIPERTAHANKAN sebagai metadata "pemilik/
 *   pembuat" (aman, tidak perlu drop FK). Sumber kebenaran pemetaan adalah pivot.
 * - `urutan` & `aktif` per-sekolah disimpan di pivot, sehingga tiap sekolah
 *   bisa mengurutkan & mengaktifkan matpel secara mandiri.
 * - Backfill: setiap matpel lama otomatis dipetakan ke sekolah pemiliknya
 *   (urutan & aktif disalin) → data NPA lama (detail_nilai posisional) tetap
 *   konsisten karena urutan tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mata_pelajaran_skadik')) {
            Schema::create('mata_pelajaran_skadik', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
                $table->foreignId('skadik_id')->constrained('skadik')->cascadeOnDelete();
                $table->integer('urutan')->default(0)->comment('Urutan matpel di sekolah ini');
                $table->boolean('aktif')->default(true)->comment('Aktif untuk sekolah ini');
                $table->timestamps();

                $table->unique(['mata_pelajaran_id', 'skadik_id'], 'mapel_skadik_unique');
            });
        }

        // Backfill: tiap matpel lama → dipetakan ke skadik_id pemiliknya.
        if (Schema::hasColumn('mata_pelajaran', 'skadik_id')) {
            $existing = DB::table('mata_pelajaran')
                ->select('id', 'skadik_id', 'urutan', 'aktif')
                ->get();

            foreach ($existing as $m) {
                $already = DB::table('mata_pelajaran_skadik')
                    ->where('mata_pelajaran_id', $m->id)
                    ->where('skadik_id', $m->skadik_id)
                    ->exists();
                if ($already) continue;

                DB::table('mata_pelajaran_skadik')->insert([
                    'mata_pelajaran_id' => $m->id,
                    'skadik_id'         => $m->skadik_id,
                    'urutan'            => (int) ($m->urutan ?? 0),
                    'aktif'             => (bool) ($m->aktif ?? true),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran_skadik');
    }
};
