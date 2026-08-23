<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Penandatangan extends Model {
    protected $table = 'penandatangan';
    protected $fillable = ['nama', 'nrp', 'pangkat', 'jabatan', 'jenis', 'skadik_id', 'aktif'];

    public function scopeAktif($q) {
        return $q->where('aktif', true);
    }

    public function scopeJenis($q, $jenis) {
        return $q->where('jenis', $jenis);
    }

    public function skadik() {
        return $this->belongsTo(Skadik::class);
    }

    /**
     * Ambil penandatangan aktif berdasarkan jenis DAN skadik (sekolah).
     * Jika tidak ketemu untuk skadik tersebut, fallback ke umum (skadik_id = null)
     * lalu fallback ke jenis 'umum'.
     */
    public static function getPenandatangan($jenis = 'umum', $skadikId = null)
    {
        // 1) Cari spesifik: jenis + skadik
        if ($skadikId) {
            $found = self::aktif()->jenis($jenis)->where('skadik_id', $skadikId)->first();
            if ($found) return $found;
        }

        // 2) Fallback: jenis tanpa skadik (global)
        $global = self::aktif()->jenis($jenis)->whereNull('skadik_id')->first();
        if ($global) return $global;

        // 3) Fallback ke 'umum' dengan skadik
        if ($jenis !== 'umum' && $skadikId) {
            $found = self::aktif()->jenis('umum')->where('skadik_id', $skadikId)->first();
            if ($found) return $found;
        }

        // 4) Fallback ke 'umum' global
        if ($jenis !== 'umum') {
            return self::aktif()->jenis('umum')->whereNull('skadik_id')->first();
        }

        return null;
    }
}
