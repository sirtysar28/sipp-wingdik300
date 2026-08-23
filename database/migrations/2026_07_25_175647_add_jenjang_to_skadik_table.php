<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skadik', function (Blueprint $table) {
            $table->enum('jenjang', ['perwira', 'tamtama_bintara'])->default('perwira')->after('lemdik_id');
        });
    }

    public function down(): void
    {
        Schema::table('skadik', function (Blueprint $table) {
            $table->dropColumn('jenjang');
        });
    }
};
