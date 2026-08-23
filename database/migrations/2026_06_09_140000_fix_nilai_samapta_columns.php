<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nilai_samapta', function (Blueprint $table) {
            // ── Drop old columns ──
            $oldCols = [
                'garjas_a_lari_p', 'garjas_a_lari_n',
                'garjas_a_chin_up_p', 'garjas_a_chin_up_n',
                'garjas_a_sit_up_p', 'garjas_a_sit_up_n',
                'garjas_a_push_up_p', 'garjas_a_push_up_n',
                'garjas_a_stl_run_p', 'garjas_a_stl_run_n',
                'garjas_b_lari_p', 'garjas_b_lari_n',
                'garjas_b_chin_up_p', 'garjas_b_chin_up_n',
                'garjas_b_sit_up_p', 'garjas_b_sit_up_n',
                'garjas_b_push_up_p', 'garjas_b_push_up_n',
                'garjas_b_stl_run_p', 'garjas_b_stl_run_n',
                'garjas_a_rata', 'garjas_b_rata',
                'rata_rata', 'nilai_konversi',
            ];
            foreach ($oldCols as $col) {
                if (Schema::hasColumn('nilai_samapta', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('nilai_samapta', function (Blueprint $table) {
            // ── GARJAS "A": Lari 12 Menit ──
            $table->integer('garjas_a_putaran')->nullable()->after('berat_badan');
            $table->integer('garjas_a_lebih')->nullable()->after('garjas_a_putaran');
            $table->decimal('garjas_a_waktu', 8, 2)->nullable()->after('garjas_a_lebih');   // putaran*230+lebih
            $table->decimal('garjas_a_tsc', 6, 2)->nullable()->after('garjas_a_waktu');         // TSC (manual/lookup)

            // ── GARJAS "B" ──
            // B1: Chin Up
            $table->decimal('garjas_b_chin_up_jml', 6, 2)->nullable()->after('garjas_a_tsc');
            $table->decimal('garjas_b_chin_up_tsc', 6, 2)->nullable()->after('garjas_b_chin_up_jml');
            // B2: Sit Up
            $table->decimal('garjas_b_sit_up_jml', 6, 2)->nullable()->after('garjas_b_chin_up_tsc');
            $table->decimal('garjas_b_sit_up_tsc', 6, 2)->nullable()->after('garjas_b_sit_up_jml');
            // B3: Push Up
            $table->decimal('garjas_b_push_up_jml', 6, 2)->nullable()->after('garjas_b_sit_up_tsc');
            $table->decimal('garjas_b_push_up_tsc', 6, 2)->nullable()->after('garjas_b_push_up_jml');
            // B4: STL Run
            $table->decimal('garjas_b_stl_run_wkt', 6, 2)->nullable()->after('garjas_b_push_up_tsc');
            $table->decimal('garjas_b_stl_run_tsc', 6, 2)->nullable()->after('garjas_b_stl_run_wkt');

            // ── RATA-RATA & NILAI AKHIR ──
            // Rata-rata "B" = (TSC ChinUp + TSC SitUp + TSC PushUp + TSC STLRun) / 4
            $table->decimal('rata_b', 6, 2)->nullable()->after('garjas_b_stl_run_tsc');
            // Rata-rata "AB" = (TSC Garjas A + Rata-rata B) / 2
            $table->decimal('rata_ab', 6, 2)->nullable()->after('rata_b');
            // Nilai Akhir NPS = (Rata-rata B + Rata-rata AB) / 2
            $table->decimal('nilai_akhir', 6, 2)->nullable()->after('rata_ab');
        });
    }

    public function down(): void
    {
        Schema::table('nilai_samapta', function (Blueprint $table) {
            $newCols = [
                'garjas_a_putaran', 'garjas_a_lebih', 'garjas_a_waktu', 'garjas_a_tsc',
                'garjas_b_chin_up_jml', 'garjas_b_chin_up_tsc',
                'garjas_b_sit_up_jml', 'garjas_b_sit_up_tsc',
                'garjas_b_push_up_jml', 'garjas_b_push_up_tsc',
                'garjas_b_stl_run_wkt', 'garjas_b_stl_run_tsc',
                'rata_b', 'rata_ab', 'nilai_akhir',
            ];
            foreach ($newCols as $col) {
                $table->dropColumn($col);
            }
        });
    }
};
