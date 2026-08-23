<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_nps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('angkatan_id')->constrained('angkatan')->onDelete('cascade');
            $table->string('label'); // "Putaran 1", "Putaran 2", dll
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['angkatan_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_nps');
    }
};
