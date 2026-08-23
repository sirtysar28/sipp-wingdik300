<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tabel master aspek kepribadian (bisa edit/tambah)
        Schema::create('aspek_kepribadian', function (Blueprint $table) {
            $table->id();
            $table->integer('nomor')->default(0);
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Tabel penilaian kepribadian per peserta per periode
        Schema::create('nilai_kepribadian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_didik_id')->constrained('peserta_didik')->onDelete('cascade');
            $table->foreignId('periode_nilai_id')->constrained('periode_nilai')->onDelete('cascade');
            $table->decimal('nilai_akhir', 5, 2)->default(75);
            $table->text('penjelasan')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->foreignId('input_oleh')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->unique(['peserta_didik_id', 'periode_nilai_id']);
        });

        // Tabel detail nilai per aspek
        Schema::create('detail_kepribadian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nilai_kepribadian_id')->constrained('nilai_kepribadian')->onDelete('cascade');
            $table->foreignId('aspek_kepribadian_id')->constrained('aspek_kepribadian')->onDelete('cascade');
            $table->enum('kriteria', ['KS', 'K', 'C', 'B', 'BS'])->default('C');
            $table->decimal('poin', 4, 2)->default(0); // -0.5, -0.25, 0, +0.25, +0.5
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_kepribadian');
        Schema::dropIfExists('nilai_kepribadian');
        Schema::dropIfExists('aspek_kepribadian');
    }
};
