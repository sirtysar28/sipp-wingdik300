<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_didik', function (Blueprint $table) {
            $table->string('nosis')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('peserta_didik', function (Blueprint $table) {
            $table->string('nosis')->nullable(false)->change();
        });
    }
};
