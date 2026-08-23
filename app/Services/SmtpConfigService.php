<?php

namespace App\Services;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Config;

class SmtpConfigService
{
    /**
     * Terapkan konfigurasi SMTP dari database ke runtime config Laravel.
     * Dipanggil sebelum Mail::send()/Notification dikirim, sehingga email
     * memakai kredensial yang diisi Super Admin di menu Pengaturan SMTP —
     * tanpa perlu mengubah file .env.
     *
     * @return bool true jika konfigurasi aktif ditemukan & diterapkan
     */
    public static function apply(): bool
    {
        $setting = SmtpSetting::active();

        if (!$setting) {
            return false;
        }

        Config::set('mail.default', $setting->mailer ?: 'smtp');
        Config::set('mail.mailers.smtp.host', $setting->host);
        Config::set('mail.mailers.smtp.port', $setting->port);
        Config::set('mail.mailers.smtp.username', $setting->username);
        Config::set('mail.mailers.smtp.password', $setting->password);
        Config::set(
            'mail.mailers.smtp.encryption',
            in_array($setting->encryption, ['tls', 'ssl', null], true) ? $setting->encryption : 'tls'
        );
        Config::set('mail.from.address', $setting->from_address ?: $setting->username);
        Config::set('mail.from.name', $setting->from_name ?: 'SIPP');

        return true;
    }

    /**
     * Cek apakah SMTP sudah dikonfigurasi & aktif (untuk validasi
     * sebelum mengirim link reset password).
     */
    public static function isConfigured(): bool
    {
        $setting = SmtpSetting::active();
        return $setting
            && $setting->host
            && $setting->username
            && $setting->from_address;
    }
}
