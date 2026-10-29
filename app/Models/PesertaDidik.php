<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PesertaDidik extends Model {
    use HasFactory;
    protected $table = 'peserta_didik';
    protected $fillable = ['angkatan_id', 'nama', 'nrp', 'pangkat', 'nosis', 'aktif'];

    public function angkatan()          { return $this->belongsTo(Angkatan::class); }
    public function nilai()             { return $this->hasMany(Nilai::class); }
    public function user()              { return $this->hasOne(User::class); }
    public function nilaiKepribadian()  { return $this->hasMany(NilaiKepribadian::class); }
    public function nilaiAkademik()     { return $this->hasOne(NilaiAkademik::class); }
    public function nilaiSamapta()      { return $this->hasOne(NilaiSamapta::class); }
    public function kompilasiNilai()    { return $this->hasOne(KompilasiNilai::class); }

    public function nilaiPeriode($periodeId) {
        return $this->nilai()->where('periode_nilai_id', $periodeId)->first();
    }

    /* ─────────────────────────────────────────────────────────────────────
     * ATURAN UNIKENESS FINAL (revisi 29 Okt 2026, berlaku se-Wingdik 300):
     *
     *   ┌──────────────────────────────────┬───────────┬───────────┐
     *   │ KONDISI                          │  NRP      │  NOSIS    │
     *   ├──────────────────────────────────┼───────────┼───────────┤
     *   │ 1 angkatan yang sama             │  ❌ dobel │  ❌ dobel │
     *   │ Sekolah sama, angkatan beda      │  ✅ boleh │  ✅ boleh*│
     *   │ (kasus TIDAK LULUS → mengulang)  │           │           │
     *   │ Sekolah lain (Wingdik 300)       │  ✅ boleh │  ❌ dobel │
     *   └──────────────────────────────────┴───────────┴───────────┘
     *   * Nosis di angkatan lain sekolah yang sama hanya boleh bila NRP
     *     juga sama (mengulang = orang yang sama). NRP beda + nosis sama
     *     dianggap salah input → ditolak.
     *
     * Karena NRP & Nosis kini bisa muncul ganda (kasus mengulang),
     * pencarian peserta berdasarkan NRP/Nosis WAJIB di-scope per
     * angkatan/sekolah — jangan pernah global.
     * ───────────────────────────────────────────────────────────────────── */

    /**
     * ID semua angkatan dalam satu Skadik (sekolah) —
     * basis scope pengecekan per sekolah.
     */
    public static function angkatanIdsInSkadik($skadikId): array
    {
        return Angkatan::where('skadik_id', $skadikId)->pluck('id')->all();
    }

    /**
     * Peserta lain yang memakai NRP sama dalam SATU ANGKATAN (duplikat →
     * tolak). NRP yang sama BOLEH dipakai di angkatan lain (mengulang di
     * sekolah yang sama) maupun di sekolah lain dalam Wingdik 300.
     */
    public static function nrpUsedInAngkatan(string $nrp, $angkatanId, ?int $ignorePesertaId = null): ?self
    {
        if ($nrp === '') {
            return null;
        }
        $q = static::where('angkatan_id', $angkatanId)->where('nrp', $nrp);
        if ($ignorePesertaId) {
            $q->where('id', '!=', $ignorePesertaId);
        }
        return $q->first();
    }

    /**
     * Apakah sebuah NOSIS boleh dipakai untuk peserta pada angkatan tertentu?
     *
     * Aturan:
     *  - Nosis unik se-Wingdik 300 → ditolak bila sudah dipakai SEKOLAH LAIN
     *  - Ditolak bila sudah dipakai peserta lain di ANGKATAN YANG SAMA
     *  - BOLEH sama di ANGKATAN LAIN, SEKOLAH YANG SAMA, dengan syarat NRP
     *    juga sama → kasus siswa TIDAK LULUS yang mengulang di sekolah yang
     *    sama (revisi 29 Okt 2026)
     *
     * Return null bila BOLEH, atau string pesan error bila TIDAK BOLEH.
     */
    public static function nosisConflict(string $nosis, $angkatanId, ?string $nrp, ?int $ignorePesertaId = null): ?string
    {
        if ($nosis === '') {
            return null;
        }

        $q = static::with('angkatan.skadik')->where('nosis', $nosis);
        if ($ignorePesertaId) {
            $q->where('id', '!=', $ignorePesertaId);
        }
        $rows = $q->get();
        if ($rows->isEmpty()) {
            return null;
        }

        $skadikId = Angkatan::find($angkatanId)?->skadik_id;
        $nrp      = trim((string) $nrp);

        foreach ($rows as $row) {
            $rowSkadikId = $row->angkatan?->skadik_id;

            // 1) Sudah dipakai peserta SEKOLAH LAIN → tolak (unik se-Wingdik 300)
            if ($rowSkadikId != $skadikId) {
                $sekolah = $row->angkatan?->skadik?->nama ?? 'sekolah lain';
                return "Nosis '{$nosis}' sudah dipakai peserta '{$row->nama}' di sekolah lain ({$sekolah}). Nosis yang sama tidak bisa dipakai lagi di sekolah lain dalam Wingdik 300.";
            }

            // 2) Sudah dipakai peserta lain di ANGKATAN YANG SAMA → tolak
            if ($row->angkatan_id == $angkatanId) {
                return "Nosis '{$nosis}' sudah dipakai peserta lain ('{$row->nama}') di angkatan ini.";
            }

            // 3) Sekolah sama, angkatan beda → hanya boleh bila NRP sama
            //    (kasus mengulang karena tidak lulus)
            if (trim((string) $row->nrp) !== $nrp) {
                return "Nosis '{$nosis}' sudah dipakai peserta '{$row->nama}' (NRP {$row->nrp}) di angkatan lain di sekolah ini. Nosis yang sama hanya boleh dipakai siswa yang MENGULANG dengan NRP yang sama — cek kembali NRP yang diinput.";
            }
        }

        // Semua pemakai nosis ini = dirinya sendiri di angkatan lain → mengulang, BOLEH
        return null;
    }

    /**
     * Cari peserta berdasarkan NRP dalam lingkup SATU SEKOLAH (Skadik).
     * NRP tidak lagi unik global, sehingga pencarian NRP global bisa salah
     * peserta (menabrak peserta sekolah lain) — selalu gunakan method ini.
     */
    public static function findByNrpInSkadik(string $nrp, $skadikId): ?self
    {
        $ids = static::angkatanIdsInSkadik($skadikId);
        if (empty($ids) || $nrp === '') {
            return null;
        }
        return static::whereIn('angkatan_id', $ids)->where('nrp', $nrp)->first();
    }
    public function getRataRataAttribute() {
        return $this->nilai->avg('total') ?? 0;
    }

    public function getNilaiKepribadianRataAttribute() {
        return $this->nilaiKepribadian->avg('nilai_akhir') ?? 0;
    }
}
