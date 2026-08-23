<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MataPelajaran extends Model
{
    use HasFactory;

    protected $table = 'mata_pelajaran';
    protected $fillable = ['skadik_id', 'nama', 'kode', 'jp', 'bobot', 'harga_nilai', 'urutan', 'aktif'];

    protected $casts = [
        'jp'           => 'integer',
        'bobot'        => 'integer',
        'harga_nilai'  => 'integer',
        'urutan'       => 'integer',
        'aktif'        => 'boolean',
    ];

    public function skadik()
    {
        return $this->belongsTo(Skadik::class);
    }

    /**
     * Relasi many-to-many: mata pelajaran ↔ sekolah (Skadik).
     * Pivot `mata_pelajaran_skadik` menyimpan `urutan` & `aktif` per-sekolah.
     */
    public function skadiks()
    {
        return $this->belongsToMany(Skadik::class, 'mata_pelajaran_skadik')
            ->withPivot(['urutan', 'aktif'])
            ->withTimestamps();
    }

    /**
     * Ambil mata pelajaran yang dipetakan ke sebuah sekolah, urut per-pivot.
     *
     * @param  int      $skadikId
     * @param  bool     $onlyAktif  true = hanya pivot.aktif=true (untuk input/report NPA)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function forSkadik(int $skadikId, bool $onlyAktif = true)
    {
        $query = self::from('mata_pelajaran as mp')
            ->join('mata_pelajaran_skadik as piv', 'piv.mata_pelajaran_id', '=', 'mp.id')
            ->where('piv.skadik_id', $skadikId)
            ->orderBy('piv.urutan')
            ->orderBy('mp.id')
            ->select('mp.*', 'piv.urutan as pivot_urutan', 'piv.aktif as pivot_aktif');

        if ($onlyAktif) {
            $query->where('piv.aktif', true);
        }

        return $query->get()->each(function ($m) {
            // Ekspos pivot agar konsisten dgn akses $m->pivot->urutan
            $m->setRelation('pivot', (object) [
                'urutan' => $m->pivot_urutan ?? 0,
                'aktif'  => (bool) ($m->pivot_aktif ?? true),
            ]);
        });
    }

    /**
     * Hitung "harga nilai" default = JP × Bobot (kalau harga_nilai belum diisi manual)
     * Jika kolom harga_nilai sudah diisi manual (> 0), gunakan nilai tersebut
     */
    public function getHargaNilaiCalcAttribute(): int
    {
        return $this->harga_nilai > 0 ? $this->harga_nilai : ($this->jp * $this->bobot);
    }

    /**
     * Scope: hanya yang aktif
     */
    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    /**
     * Scope: urutkan berdasarkan urutan
     */
    public function scopeTerurut($query)
    {
        return $query->orderBy('urutan');
    }
}
