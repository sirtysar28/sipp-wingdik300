<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mendukung satu user mengelola >1 Sekolah/Skadik.
     * Contoh kasus: admin samapta Binjaswingdiktek@gmail.com mengelola 4 skadik (301-304).
     *
     * Kolom users.skadik_id tetap dipertahankan (kompatibilitas mundur & sebagai
     * default single-assignment); pivot ini menjadi sumber kebenaran untuk akses
     * multi-skadik.
     */
    public function up(): void
    {
        Schema::create('user_skadik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skadik_id')->constrained('skadik')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'skadik_id']);
        });

        // Migrasi data existing: setiap user yg punya skadik_id otomatis dapat
        // akses ke skadik tersebut via pivot.
        DB::table('users')
            ->whereNotNull('skadik_id')
            ->orderBy('id')
            ->chunk(200, function ($users) {
                $rows = [];
                $now = now();
                foreach ($users as $u) {
                    $rows[] = [
                        'user_id'   => $u->id,
                        'skadik_id' => $u->skadik_id,
                        'created_at'=> $now,
                        'updated_at'=> $now,
                    ];
                }
                DB::table('user_skadik')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_skadik');
    }
};
