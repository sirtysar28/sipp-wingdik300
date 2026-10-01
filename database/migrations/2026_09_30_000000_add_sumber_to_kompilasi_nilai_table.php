<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 30 September 2026 — NPP kini mengambil:
 *   - NPS dari PUTARAN TERAKHIR (bukan rata-rata semua putaran)
 *   - NPK dari PERIODE TERAKHIR (bukan rata-rata semua periode)
 * dan sumbernya bisa DIPILIH user di halaman NPP / Proses Kompilasi.
 * Kolom ini mencatat sumber yang dipakai saat kompilasi diproses agar
 * hasil NPP mudah diaudit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kompilasi_nilai', function (Blueprint $table) {
            if (!Schema::hasColumn('kompilasi_nilai', 'sumber_nps')) {
                $table->string('sumber_nps', 100)->nullable()->after('bobot_samapta')
                    ->comment('Label putaran NPS yg dipakai (mis. "Putaran 2")');
            }
            if (!Schema::hasColumn('kompilasi_nilai', 'sumber_npk')) {
                $table->string('sumber_npk', 100)->nullable()->after('sumber_nps')
                    ->comment('Label periode NPK yg dipakai (mis. "Periode 3")');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kompilasi_nilai', function (Blueprint $table) {
            if (Schema::hasColumn('kompilasi_nilai', 'sumber_nps')) {
                $table->dropColumn('sumber_nps');
            }
            if (Schema::hasColumn('kompilasi_nilai', 'sumber_npk')) {
                $table->dropColumn('sumber_npk');
            }
        });
    }
};
