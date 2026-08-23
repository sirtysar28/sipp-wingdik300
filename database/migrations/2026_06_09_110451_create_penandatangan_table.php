<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penandatangan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('nrp', 30);
            $table->string('jabatan', 200);
            $table->string('jenis', 50)->default('umum'); // umum, akademik, kepribadian, samapta, kompilasi
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penandatangan');
    }
};
