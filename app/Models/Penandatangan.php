<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Penandatangan extends Model {
    protected $table = 'penandatangan';
    protected $fillable = ['nama', 'nrp', 'pangkat', 'jabatan', 'jenis', 'skadik_id', 'lemdik_id', 'aktif'];

    public function scopeAktif($q) {
        return $q->where('aktif', true);
    }

    public function scopeJenis($q, $jenis) {
        return $q->where('jenis', $jenis);
    }

    public function skadik() {
        return $this->belongsTo(Skadik::class);
    }

    public function lemdik() {
        return $this->belongsTo(Lemdik::class);
    }

    /** Label cakupan singkat utk tampilan tabel. */
    public function getCakupanLabelAttribute(): string
    {
        if ($this->skadik_id)     return $this->skadik?->nama_singkat ?? $this->skadik?->nama ?? 'Sekolah';
        if ($this->lemdik_id)     return ($this->lemdik?->singkat ?? $this->lemdik?->nama ?? 'Skadron') . ' (Skadron)';
        return 'Global (Semua Sekolah)';
    }

    /**
     * Kandidat aktif berdasarkan jenis pada satu cakupan.
     * $cakupan: 'sekolah' (pakai $skadikId) | 'skadron' (pakai $lemdikId) | 'global'
     */
    private static function kandidat(string $jenis, string $cakupan, ?int $skadikId = null, ?int $lemdikId = null)
    {
        $q = self::aktif()->jenis($jenis);
        if ($cakupan === 'sekolah') return $q->where('skadik_id', $skadikId)->first();
        if ($cakupan === 'skadron') return $q->whereNull('skadik_id')->where('lemdik_id', $lemdikId)->first();
        return $q->whereNull('skadik_id')->whereNull('lemdik_id')->first();
    }

    /**
     * Ambil penandatangan aktif dengan urutan CASCADE (Revisi 22 Sept 2026):
     *   1. SEKOLAH spesifik  : jenis + skadik_id                (paling spesifik, menang)
     *   2. SKADRON (lemdik)  : jenis utk semua sekolah under skadron 30x
     *                          → cukup 1 baris Danskadik per skadron (4 baris utk 4 danskadik)
     *   3. GLOBAL            : jenis utk semua sekolah (mis. Kasibinjas)
     *   4. Jika masih kosong → ulangi cascade utk jenis 'umum'.
     */
    public static function getPenandatangan($jenis = 'umum', $skadikId = null)
    {
        $lemdikId = $skadikId ? Skadik::where('id', $skadikId)->value('lemdik_id') : null;

        // 1) Khusus sekolah
        if ($skadikId) {
            $found = self::kandidat($jenis, 'sekolah', $skadikId);
            if ($found) return $found;
        }

        // 2) Level skadron — Danskadik 30x utk semua sekolah under skadik 30x
        if ($lemdikId) {
            $found = self::kandidat($jenis, 'skadron', null, $lemdikId);
            if ($found) return $found;
        }

        // 3) Global — semua sekolah (Kasibinjas)
        $found = self::kandidat($jenis, 'global');
        if ($found) return $found;

        // 4) Fallback cascade ke jenis 'umum'
        if ($jenis !== 'umum') {
            return self::getPenandatangan('umum', $skadikId);
        }

        return null;
    }
}
