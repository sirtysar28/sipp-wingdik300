<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add super_admin & module-specific roles to users ──
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin_akademik','admin_kepribadian','admin_samapta','admin','instruktur','peserta') NOT NULL DEFAULT 'peserta'");

        // ── 2. Tabel Nilai Akademik (NPA) ──
        Schema::create('nilai_akademik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_didik_id')->constrained('peserta_didik')->onDelete('cascade');
            $table->foreignId('angkatan_id')->constrained('angkatan')->onDelete('cascade');
            // Subjek nilai (JSON: semua kolom SBS & latihan praktis)
            $table->json('detail_nilai')->nullable();
            $table->decimal('jumlah_nilai', 6, 2)->default(0);
            $table->decimal('npa', 5, 2)->default(0); // Nilai Prestasi Akademik (final)
            $table->integer('rank')->default(0);
            $table->foreignId('input_oleh')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->unique(['peserta_didik_id', 'angkatan_id']);
        });

        // ── 3. Tabel Nilai Samapta (NPS) ──
        Schema::create('nilai_samapta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_didik_id')->constrained('peserta_didik')->onDelete('cascade');
            $table->foreignId('angkatan_id')->constrained('angkatan')->onDelete('cascade');
            $table->integer('umur')->default(0);
            $table->decimal('tinggi_badan', 5, 2)->default(0);
            $table->decimal('berat_badan', 5, 2)->default(0);
            // GARJAS A
            $table->decimal('garjas_a_lari_p', 6, 2)->nullable();
            $table->decimal('garjas_a_lari_n', 6, 2)->nullable();
            $table->decimal('garjas_a_chin_up_p', 6, 2)->nullable();
            $table->decimal('garjas_a_chin_up_n', 6, 2)->nullable();
            $table->decimal('garjas_a_sit_up_p', 6, 2)->nullable();
            $table->decimal('garjas_a_sit_up_n', 6, 2)->nullable();
            $table->decimal('garjas_a_push_up_p', 6, 2)->nullable();
            $table->decimal('garjas_a_push_up_n', 6, 2)->nullable();
            $table->decimal('garjas_a_stl_run_p', 6, 2)->nullable();
            $table->decimal('garjas_a_stl_run_n', 6, 2)->nullable();
            // GARJAS B
            $table->decimal('garjas_b_lari_p', 6, 2)->nullable();
            $table->decimal('garjas_b_lari_n', 6, 2)->nullable();
            $table->decimal('garjas_b_chin_up_p', 6, 2)->nullable();
            $table->decimal('garjas_b_chin_up_n', 6, 2)->nullable();
            $table->decimal('garjas_b_sit_up_p', 6, 2)->nullable();
            $table->decimal('garjas_b_sit_up_n', 6, 2)->nullable();
            $table->decimal('garjas_b_push_up_p', 6, 2)->nullable();
            $table->decimal('garjas_b_push_up_n', 6, 2)->nullable();
            $table->decimal('garjas_b_stl_run_p', 6, 2)->nullable();
            $table->decimal('garjas_b_stl_run_n', 6, 2)->nullable();
            // Hasil
            $table->decimal('garjas_a_rata', 5, 2)->nullable();
            $table->decimal('garjas_b_rata', 5, 2)->nullable();
            $table->decimal('rata_rata', 5, 2)->nullable();
            $table->decimal('nilai_konversi', 5, 2)->nullable(); // NPS final
            $table->foreignId('input_oleh')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->unique(['peserta_didik_id', 'angkatan_id']);
        });

        // ── 4. Tabel Kompilasi Nilai ──
        Schema::create('kompilasi_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_didik_id')->constrained('peserta_didik')->onDelete('cascade');
            $table->foreignId('angkatan_id')->constrained('angkatan')->onDelete('cascade');
            $table->foreignId('nilai_akademik_id')->nullable()->constrained('nilai_akademik')->onDelete('set null');
            $table->decimal('nilai_akademik', 5, 2)->default(0);
            $table->decimal('bobot_akademik', 5, 2)->default(70); // 70%
            $table->decimal('nilai_kepribadian', 5, 2)->default(0);
            $table->decimal('bobot_kepribadian', 5, 2)->default(20); // 20%
            $table->decimal('nilai_samapta', 5, 2)->default(0);
            $table->decimal('bobot_samapta', 5, 2)->default(10); // 10%
            $table->decimal('nilai_akhir', 5, 2)->default(0);
            $table->decimal('predikat_angka', 5, 2)->nullable();
            $table->string('predikat_huruf', 20)->nullable();
            $table->integer('rank')->default(0);
            $table->foreignId('input_oleh')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->unique(['peserta_didik_id', 'angkatan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kompilasi_nilai');
        Schema::dropIfExists('nilai_samapta');
        Schema::dropIfExists('nilai_akademik');

        // Revert user roles
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','instruktur','peserta') NOT NULL DEFAULT 'peserta'");
    }
};
