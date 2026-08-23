<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->text('alamat')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('wingdik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->onDelete('cascade');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->timestamps();
        });

        Schema::create('angkatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wingdik_id')->constrained('wingdik')->onDelete('cascade');
            $table->string('jurusan');
            $table->string('nomor_angkatan');
            $table->year('tahun_masuk');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('peserta_didik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('angkatan_id')->constrained('angkatan')->onDelete('cascade');
            $table->string('nama');
            $table->string('nrp')->unique();
            $table->string('pangkat');
            $table->string('nosis');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('periode_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('angkatan_id')->constrained('angkatan')->onDelete('cascade');
            $table->string('label'); // "Periode 1", "Mei I 2026"
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_didik_id')->constrained('peserta_didik')->onDelete('cascade');
            $table->foreignId('periode_nilai_id')->constrained('periode_nilai')->onDelete('cascade');
            $table->decimal('akademik', 5, 2)->default(0);
            $table->decimal('fisik', 5, 2)->default(0);
            $table->decimal('sikap', 5, 2)->default(0);
            $table->decimal('kepemimpinan', 5, 2)->default(0);
            $table->decimal('total', 5, 2)->storedAs('(akademik + fisik + sikap + kepemimpinan) / 4');
            $table->foreignId('input_oleh')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->unique(['peserta_didik_id', 'periode_nilai_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai');
        Schema::dropIfExists('periode_nilai');
        Schema::dropIfExists('peserta_didik');
        Schema::dropIfExists('angkatan');
        Schema::dropIfExists('wingdik');
        Schema::dropIfExists('sekolah');
    }
};
