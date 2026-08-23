<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel penyimpanan konfigurasi SMTP (dikelola Super Admin).
     * Konfigurasi ini dipakai runtime saat mengirim email
     * (contoh: link reset password lupa password).
     */
    public function up(): void
    {
        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mailer')->default('smtp');          // smtp (default)
            $table->string('host');                              // smtp.gmail.com dll.
            $table->unsignedSmallInteger('port')->default(587);  // 587 / 465 / 25
            $table->string('encryption', 10)->default('tls');    // tls / ssl / none
            $table->string('username');                          // email aktif pengirim
            $table->text('password');                            // app password / password SMTP
            $table->string('from_address');                      // alamat From: (biasanya = username)
            $table->string('from_name')->default('SIPP');
            $table->boolean('is_active')->default(true);         // aktif/nonaktif kirim email
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_settings');
    }
};
