<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nilai_samapta', function (Blueprint $table) {
            $table->string('putaran_label')->nullable()->after('angkatan_id')->comment('Nama putaran, misalnya "Putaran 1", "Putaran 2"');
        });
    }

    public function down(): void
    {
        Schema::table('nilai_samapta', function (Blueprint $table) {
            $table->dropColumn('putaran_label');
        });
    }
};
