<?php
namespace App\Services;

use App\Models\{Angkatan, PesertaDidik, NilaiAkademik, NilaiKepribadian, NilaiSamapta, KompilasiNilai};
use Illuminate\Support\Collection;

/**
 * NppCalculator
 * -----------------------------------------------------------------------------
 * Menghitung NPP (Nilai Prestasi Pendidikan) SECARA LANGSUNG (live) dari sumber
 * data asli (nilai_akademik, nilai_kepribadian, nilai_samapta) untuk SEMUA
 * peserta pada suatu angkatan — bukan hanya peserta yang sudah ada record-nya
 * di tabel kompilasi_nilai.
 *
 * Ini menyelesaikan beberapa issue sekaligus:
 *  - Dashboard: ranking 4-10 selalu lengkap & benar (tidak bergantung rank lama)
 *  - Leaderboard: urutan berdasarkan NPP asli, bukan NPK / rank stale
 *  - Cetak Laporan / Report NPP: menampilkan SEMUA peserta angkatan (bulk),
 *    termasuk yang belum sempat diproses di menu Kompilasi.
 */
class NppCalculator
{
    /** Bobot default jika belum pernah ada kompilasi */
    public const BOBOT_AKADEMIK_DEFAULT = 70;
    public const BOBOT_KEPRIBADIAN_DEFAULT = 20;
    public const BOBOT_SAMAPTA_DEFAULT = 10;

    /**
     * Bangun peringkat NPP live untuk satu angkatan.
     *
     * @return \Illuminate\Support\Collection  kumpulan object dengan property:
     *   peserta, peserta_didik_id, nilai_akademik, nilai_kepribadian,
     *   nilai_samapta, bobot_akademik, bobot_kepribadian, bobot_samapta,
     *   nilai_akhir (NPP), rank, predikat_huruf, predikat_angka
     */
    public static function forAngkatan(?int $angkatanId): Collection
    {
        if (!$angkatanId) return collect();

        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)
            ->orderBy('nama')->get();

        if ($pesertaList->isEmpty()) return collect();

        $ids = $pesertaList->pluck('id');

        // Bobot diambil dari record kompilasi yang sudah ada (konsisten dengan
        // yg dipilih user di menu Proses Kompilasi), fallback ke default.
        $existing = KompilasiNilai::where('angkatan_id', $angkatanId)->first();
        $bA = $existing->bobot_akademik    ?? self::BOBOT_AKADEMIK_DEFAULT;
        $bK = $existing->bobot_kepribadian ?? self::BOBOT_KEPRIBADIAN_DEFAULT;
        $bS = $existing->bobot_samapta     ?? self::BOBOT_SAMAPTA_DEFAULT;

        // Muat nilai sumber sekali saja (eager) — efisien.
        $akademikMap = NilaiAkademik::where('angkatan_id', $angkatanId)
            ->whereIn('peserta_didik_id', $ids)->get()->keyBy('peserta_didik_id');

        $samaptaMap = NilaiSamapta::where('angkatan_id', $angkatanId)
            ->whereIn('peserta_didik_id', $ids)->get()->keyBy('peserta_didik_id');

        // Rata-rata nilai kepribadian semua periode per peserta.
        $kepribadianAvgs = NilaiKepribadian::whereIn('peserta_didik_id', $ids)
            ->selectRaw('peserta_didik_id, AVG(nilai_akhir) as avg_nilai')
            ->groupBy('peserta_didik_id')
            ->pluck('avg_nilai', 'peserta_didik_id');

        $rows = [];
        foreach ($pesertaList as $p) {
            $na = $akademikMap->get($p->id);
            $ns = $samaptaMap->get($p->id);

            $npa = $na ? round($na->npa, 2) : 0;
            $npk = $kepribadianAvgs->has($p->id) ? round($kepribadianAvgs[$p->id], 2) : 0;
            // Revisi 18 September 2026: NPP memakai NILAI KONVERSI NPS
            // (bukan nilai akhir). Fallback ke nilai_akhir hanya untuk data
            // lama yang belum punya nilai konversi.
            $nps = $ns ? round($ns->nilai_konversi ?? $ns->nilai_akhir ?? 0, 2) : 0;
            $npp = round(($npa * $bA / 100) + ($npk * $bK / 100) + ($nps * $bS / 100), 2);

            $predikat = KompilasiNilai::getPredikat($npp);

            $rows[] = (object) [
                'peserta'            => $p,
                'peserta_didik_id'   => $p->id,
                'nilai_akademik'     => $npa,
                'nilai_kepribadian'  => $npk,
                'nilai_samapta'      => $nps,
                'bobot_akademik'     => $bA,
                'bobot_kepribadian'  => $bK,
                'bobot_samapta'      => $bS,
                'nilai_akhir'        => $npp,
                'predikat_huruf'     => $predikat['huruf'],
                'predikat_angka'     => $predikat['angka'],
            ];
        }

        // Urutkan by NPP desc, lalu tentukan rank (dense, anti-gap).
        usort($rows, fn($a, $b) => $b->nilai_akhir <=> $a->nilai_akhir);
        foreach ($rows as $i => $r) {
            $r->rank = $i + 1;
        }

        return collect($rows);
    }

    /**
     * Hitung NPP untuk satu peserta pada satu angkatan.
     */
    public static function forPeserta(?int $angkatanId, ?int $pesertaId): ?object
    {
        if (!$angkatanId || !$pesertaId) return null;
        return self::forAngkatan($angkatanId)->firstWhere('peserta_didik_id', $pesertaId);
    }
}
