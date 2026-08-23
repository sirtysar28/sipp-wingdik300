<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('skadik', function (Blueprint $table) {
            $table->string('kepala_sekolah')->nullable()->after('keterangan');
            $table->string('pangkat_kepala')->nullable()->after('kepala_sekolah');
            $table->string('nrp_kepala')->nullable()->after('pangkat_kepala');
        });
    }

    public function down(): void
    {
        Schema::table('skadik', function (Blueprint $table) {
            $table->dropColumn(['kepala_sekolah', 'pangkat_kepala', 'nrp_kepala']);
        });
    }
};
