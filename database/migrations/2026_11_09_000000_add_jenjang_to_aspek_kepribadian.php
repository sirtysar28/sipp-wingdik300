<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 9 November 2026 — PARAMETER NPK TAMBAHAN KHUSUS PERWIRA (11–15).
 *
 * Feedback Mas Ihwanul Alim (WA, 9/11/2026):
 * - Fitur tambah parameter kepribadian berlaku universal
 *   (tamtara/bintara/perwira) — sudah ada, tetap dipertahankan.
 * - Tambahan 5 parameter khusus SEKOLAH PERWIRA (nomor 11–15), sehingga
 *   NPK perwira total 15 item; tamtara/bintara/PNS tetap 10 item eksisting.
 *
 * Implementasi:
 * - Kolom `jenjang` pada master aspek_kepribadian:
 *     NULL      → universal (tamtara/bintara/perwira/PNS)
 *     'perwira' → hanya tampil di NPK golongan Perwira
 * - Seed 5 aspek baku khusus Perwira bila belum ada (idempotent).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('aspek_kepribadian', 'jenjang')) {
            Schema::table('aspek_kepribadian', function (Blueprint $table) {
                $table->string('jenjang', 20)->nullable()->default(null)->after('deskripsi');
            });
        }

        // 5 aspek tambahan khusus Perwira (feedback 9 Nov 2026)
        $perwira = [
            ['Kemampuan Mengenali Masalah',    'Pengenalan Masalah, Pemilihan Solusi, Keputusan Pemecahan Masalah.'],
            ['Kemampuan Menyatakan Pendapat',  'Kemantapan Pendapat, Penggunaan Kata, Kejelasan Pernyataan, Konsistensi Pendapat, Rasional Pendapat.'],
            ['Ketekunan',                      'Konsentrasi, Upaya Penyelesaian Tugas, Pemanfaatan Waktu yang Efektif dan Efisien.'],
            ['Kemampuan Mengorganisasikan',    'Keyakinan Tujuan, Membagi Tugas, Membangkitkan Kerja Sama, Identifikasi Kemampuan Orang Lain.'],
            ['Kemampuan Memutuskan',           'Kecepatan dan Ketepatan, Prioritas Penyelesaian, Mengatasi Ketidakpastian.'],
        ];

        $now = now();
        foreach ($perwira as [$nama, $deskripsi]) {
            $existing = DB::table('aspek_kepribadian')->where('nama', $nama)->first();
            if ($existing) {
                // Sudah ada (mis. dibuat manual via master data) → cukup andai jenjang
                DB::table('aspek_kepribadian')->where('id', $existing->id)
                    ->update(['jenjang' => 'perwira', 'updated_at' => $now]);
                continue;
            }
            DB::table('aspek_kepribadian')->insert([
                'nomor'      => DB::table('aspek_kepribadian')->max('nomor') + 1,
                'nama'       => $nama,
                'deskripsi'  => $deskripsi,
                'aktif'      => true,
                'jenjang'    => 'perwira',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Hapus aspek khusus perwira + kolom penanda jenjang
        DB::table('aspek_kepribadian')->where('jenjang', 'perwira')->delete();
        if (Schema::hasColumn('aspek_kepribadian', 'jenjang')) {
            Schema::table('aspek_kepribadian', function (Blueprint $table) {
                $table->dropColumn('jenjang');
            });
        }
    }
};
