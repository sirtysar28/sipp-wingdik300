<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penandatangan', function (Blueprint $table) {
            // Kolom lemdik_id: cakupan level SKADRON (mis. Skadik 301/302/303/304).
            // Dipakai utk Danskadik: cukup 1 baris per skadron yg berlaku utk
            // SEMUA sekolah (skadik) di bawah skadron tsb.
            // skadik_id = null + lemdik_id = null  → GLOBAL (semua sekolah, mis. Kasibinjas)
            // skadik_id = null + lemdik_id diisi   → SKADRON (semua sekolah under skadron)
            // skadik_id diisi                       → SEKOLAH spesifik
            $table->foreignId('lemdik_id')->nullable()->after('skadik_id')
                ->constrained('lemdik')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('penandatangan', function (Blueprint $table) {
            $table->dropForeign(['lemdik_id']);
            $table->dropColumn('lemdik_id');
        });
    }
};
