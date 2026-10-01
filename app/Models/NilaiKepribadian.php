<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NilaiKepribadian extends Model {
    protected $table    = 'nilai_kepribadian';
    protected $fillable = ['peserta_didik_id','periode_nilai_id','nilai_akhir','penjelasan','rekomendasi','input_oleh'];
    protected $casts    = ['nilai_akhir' => 'float'];

    public function peserta()  { return $this->belongsTo(PesertaDidik::class, 'peserta_didik_id'); }
    public function periode()  { return $this->belongsTo(PeriodeNilai::class, 'periode_nilai_id'); }
    public function detail()   { return $this->hasMany(DetailKepribadian::class); }
    public function inputOleh(){ return $this->belongsTo(User::class, 'input_oleh'); }

    // Konstanta poin kriteria
    public static function poinKriteria(string $kriteria): float {
        return match($kriteria) {
            'BS' =>  0.5,
            'B'  =>  0.25,
            'C'  =>  0.0,
            'K'  => -0.25,
            'KS' => -0.5,
            default => 0.0,
        };
    }

    /**
     * Revisi 30 September 2026 — PERIODE NPK TERAKHIR utk satu angkatan.
     * Dipakai sebagai keterangan "Sumber NPK" saat NPP memakai mode
     * "Periode Terakhir" (default).
     */
    public static function periodeTerakhir(?int $angkatanId): ?PeriodeNilai
    {
        if (!$angkatanId) return null;
        return PeriodeNilai::where('angkatan_id', $angkatanId)
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Revisi 30 September 2026 — NPK UNTUK NPP: AMBIL PERIODE TERAKHIR.
     * Sebelumnya NPP memakai rata-rata semua periode; kini NPP memakai
     * nilai kepribadian dari PERIODE TERBARU/TERAKHIR (sesuai permintaan).
     *
     * - $periodeId null → record dari periode TERAKHIR milik peserta
     *   (peserta tanpa nilai di periode terakhir angkatan otomatis memakai
     *   periode terakhir MILIKNYA).
     * - $periodeId diisi → record dari periode tsb.
     *
     * @return array map: peserta_didik_id => nilai NPK (float)
     */
    public static function npkUntukNppPerPeserta($pesertaIds, $periodeId = null, ?int $angkatanId = null): array
    {
        if (empty($pesertaIds)) return [];

        $q = self::join('periode_nilai', 'periode_nilai.id', '=', 'nilai_kepribadian.periode_nilai_id')
            ->whereIn('nilai_kepribadian.peserta_didik_id', $pesertaIds)
            ->select(
                'nilai_kepribadian.peserta_didik_id',
                'nilai_kepribadian.nilai_akhir',
                'nilai_kepribadian.periode_nilai_id',
                'periode_nilai.tanggal_mulai'
            );
        if ($angkatanId) $q->where('periode_nilai.angkatan_id', $angkatanId);
        if ($periodeId)  $q->where('nilai_kepribadian.periode_nilai_id', $periodeId);

        $pilihan = [];
        foreach ($q->get() as $r) {
            $pid = $r->peserta_didik_id;
            if ($periodeId) { $pilihan[$pid] = $r; continue; } // periode spesifik: pasti satu record
            $c = $pilihan[$pid] ?? null;
            // Periode paling baru menang (tanggal_mulai desc, lalu id periode desc).
            $lebihBaru = !$c
                || [$r->tanggal_mulai, (int) $r->periode_nilai_id]
                   > [$c->tanggal_mulai, (int) $c->periode_nilai_id];
            if ($lebihBaru) $pilihan[$pid] = $r;
        }

        $hasil = [];
        foreach ($pilihan as $pid => $r) {
            $hasil[$pid] = round((float) ($r->nilai_akhir ?? 0), 2);
        }
        return $hasil;
    }

    /** NPK untuk NPP satu peserta — periode terakhir / periode pilihan. */
    public static function npkUntukNpp($pesertaId, $periodeId = null, ?int $angkatanId = null): float
    {
        if (!$pesertaId) return 0.0;
        $map = self::npkUntukNppPerPeserta([$pesertaId], $periodeId, $angkatanId);
        return $map[(int) $pesertaId] ?? 0.0;
    }

    // Hitung ulang nilai akhir dari detail
    public function hitungNilaiAkhir(): float {
        $totalPoin = $this->detail->sum('poin');
        return round(75 + $totalPoin, 2);
    }
}
