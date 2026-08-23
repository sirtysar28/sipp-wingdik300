<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Rename sekolah -> lemdik
        Schema::rename('sekolah', 'lemdik');

        // 2. Add optional wingdik field to lemdik
        Schema::table('lemdik', function (Blueprint $table) {
            $table->string('wingdik')->nullable()->after('alamat');
        });

        // 3. Rename wingdik -> skadik
        Schema::rename('wingdik', 'skadik');

        // 4. Rename column sekolah_id -> lemdik_id on skadik
        Schema::table('skadik', function (Blueprint $table) {
            $table->renameColumn('sekolah_id', 'lemdik_id');
        });

        // 5. Rename column wingdik_id -> skadik_id on angkatan
        Schema::table('angkatan', function (Blueprint $table) {
            $table->renameColumn('wingdik_id', 'skadik_id');
        });
    }

    public function down(): void
    {
        Schema::table('angkatan', function (Blueprint $table) {
            $table->renameColumn('skadik_id', 'wingdik_id');
        });

        Schema::table('skadik', function (Blueprint $table) {
            $table->renameColumn('lemdik_id', 'sekolah_id');
        });

        Schema::rename('skadik', 'wingdik');

        Schema::table('lemdik', function (Blueprint $table) {
            $table->dropColumn('wingdik');
        });

        Schema::rename('lemdik', 'sekolah');
    }
};
