<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    protected $table = 'smtp_settings';

    protected $fillable = [
        'mailer',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'port'      => 'integer',
    ];

    /**
     * Ambil baris konfigurasi SMTP aktif (single row).
     */
    public static function active(): ?self
    {
        return self::where('is_active', true)->latest('id')->first();
    }
}
