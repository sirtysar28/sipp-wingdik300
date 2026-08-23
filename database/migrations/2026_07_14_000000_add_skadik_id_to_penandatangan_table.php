<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penandatangan', function (Blueprint $table) {
            // Kolom skadik_id: nullable, 1 orang bisa ttd di banyak sekolah
            $table->foreignId('skadik_id')->nullable()->after('jenis')
                ->constrained('skadik')->nullOnDelete();

            // Unique: 1 kombinasi jenis + skadik_id hanya boleh 1 penandatangan aktif
            // Tapi karena bisa ada nonaktif, kita batasi di level app (controller).
        });
    }

    public function down(): void
    {
        Schema::table('penandatangan', function (Blueprint $table) {
            $table->dropForeign(['skadik_id']);
            $table->dropColumn('skadik_id');
        });
    }
};
