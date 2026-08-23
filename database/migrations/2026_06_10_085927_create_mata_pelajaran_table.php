<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skadik_id')->constrained('skadik')->cascadeOnDelete();
            $table->string('nama');
            $table->string('kode')->nullable();
            $table->integer('jp')->default(0);       // Jam Pelajaran
            $table->integer('bobot')->default(0);     // Bobot penilaian
            $table->integer('urutan')->default(0);    // Urutan tampil
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran');
    }
};
