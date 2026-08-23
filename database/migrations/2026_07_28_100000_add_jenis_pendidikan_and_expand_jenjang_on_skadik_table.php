<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Menambah kolom `jenis_pendidikan` (radio) pada tabel skadik:
 *     dikbangspes, dikcabpa, dikjurbata, dikjurPNS, dikmatuklih
 * - Memperluas kolom `jenjang` dari 2 opsi (perwira, tamtama_bintara)
 *   menjadi 4 opsi: perwira, bintara, tamtama, pns.
 *   Aturan starting NPK: perwira = 80, selainnya = 70.
 *
 * Catatan urutan langkah (PENTING agar tidak terjadi "Data truncated"):
 *   1. Perluas enum ke SUPERSET (old + new) supaya nilai lama tetap valid
 *      dan nilai baru (tamtama, bintara, pns) juga valid.
 *   2. Migrasi data: tamtama_bintara -> tamtama.
 *   3. Susutkan enum ke 4 opsi final (buang tamtama_bintara).
 *   4. Tambah kolom jenis_pendidikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Perluas enum ke superset (aman: semua nilai lama & baru valid)
        DB::statement(
            "ALTER TABLE skadik MODIFY COLUMN jenjang ".
            "ENUM('perwira','bintara','tamtama','pns','tamtama_bintara') NOT NULL DEFAULT 'perwira'"
        );

        // 2. Migrasi data lama: gabungan 'tamtama_bintara' -> 'tamtama'
        DB::table('skadik')
            ->where('jenjang', 'tamtama_bintara')
            ->update(['jenjang' => 'tamtama']);

        // 3. Susutkan enum ke 4 opsi final (buang 'tamtama_bintara')
        DB::statement(
            "ALTER TABLE skadik MODIFY COLUMN jenjang ".
            "ENUM('perwira','bintara','tamtama','pns') NOT NULL DEFAULT 'perwira'"
        );

        // 4. Tambah kolom jenis_pendidikan
        Schema::table('skadik', function (Blueprint $table) {
            $table->enum('jenis_pendidikan', [
                'dikbangspes',
                'dikcabpa',
                'dikjurbata',
                'dikjurPNS',
                'dikmatuklih',
            ])->default('dikbangspes')->after('jenjang');
        });
    }

    public function down(): void
    {
        // 1. Hapus kolom jenis_pendidikan
        Schema::table('skadik', function (Blueprint $table) {
            $table->dropColumn('jenis_pendidikan');
        });

        // 2. Perluas enum ke superset (supaya bintara/tamtama/pns tetap valid
        //    selama proses migrasi data balik)
        DB::statement(
            "ALTER TABLE skadik MODIFY COLUMN jenjang ".
            "ENUM('perwira','bintara','tamtama','pns','tamtama_bintara') NOT NULL DEFAULT 'perwira'"
        );

        // 3. Migrasi data balik: bintara/tamtama/pns -> tamtama_bintara
        DB::table('skadik')
            ->whereIn('jenjang', ['bintara', 'tamtama', 'pns'])
            ->update(['jenjang' => 'tamtama_bintara']);

        // 4. Kembalikan enum ke 2 opsi awal
        DB::statement(
            "ALTER TABLE skadik MODIFY COLUMN jenjang ".
            "ENUM('perwira','tamtama_bintara') NOT NULL DEFAULT 'perwira'"
        );
    }
};
