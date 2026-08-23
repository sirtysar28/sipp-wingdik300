<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Set existing NULL values first (in case any)
        DB::statement("UPDATE `angkatan` SET `jurusan` = '' WHERE `jurusan` IS NULL");

        Schema::table('angkatan', function (Blueprint $table) {
            $table->string('jurusan')->nullable()->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('angkatan', function (Blueprint $table) {
            $table->string('jurusan')->nullable(false)->change();
        });
    }
};
