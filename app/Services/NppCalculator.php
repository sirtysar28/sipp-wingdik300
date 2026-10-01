<?php
namespace App\Services;

use App\Models\{Angkatan, PesertaDidik, NilaiAkademik, NilaiKepribadian, NilaiSamapta, KompilasiNilai, PeriodeNilai};
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
 *
 * Revisi 30 September 2026 — SUMBER NPS & NPK BISA DIPILIH:
 *  - NPS diambil dari NILAI KONVERSI putaran TERAKHIR (default), atau
 *    putaran tertentu pilihan user (param $npsPutaran).
 *  - NPK diambil dari nilai periode TERAKHIR (default), atau periode
 *    tertentu pilihan user (param $npkPeriodeId).
 */
class NppCalculator
{
    /** Bobot default jika belum pernah ada kompilasi */
    public const BOBOT_AKADEMIK_DEFAULT = 70;
    public const BOBOT_KEPRIBADIAN_DEFAULT = 20;
    public const BOBOT_SAMAPTA_DEFAULT = 10;

    /** Nilai param "sumber" = otomatis ambil yang TERAKHIR. */
    public const SUMBER_TERAKHIR = 'terakhir';

    /**
     * Normalisasi input sumber dari request: null/''/'terakhir' → null
     * (artinya pakai yang terakhir), selain itu → nilai pilihan user.
     */
    public static function normalizeSumber($value): ?string
    {
        $v = trim((string) $value);
        return ($v === '' || strcasecmp($v, self::SUMBER_TERAKHIR) === 0) ? null : $v;
    }

    /**
     * Bangun peringkat NPP live untuk satu angkatan.
     *
     * @param int|null      $angkatanId
     * @param string|null   $npsPutaran   null = putaran TERAKHIR; selain itu label putaran pilihan
     * @param int|string|null $npkPeriode null = periode TERAKHIR; selain itu id periode_nilai
     *
     * @return \Illuminate\Support\Collection  kumpulan object dengan property:
     *   peserta, peserta_didik_id, nilai_akademik, nilai_kepribadian,
     *   nilai_samapta, bobot_akademik, bobot_kepribadian, bobot_samapta,
     *   nilai_akhir (NPP), rank, predikat_huruf, predikat_angka,
     *   sumber_nps (label putaran), sumber_npk (label periode)
     */
    public static function forAngkatan(?int $angkatanId, ?string $npsPutaran = null, $npkPeriode = null): Collection
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

        // Revisi 30 September 2026: NPP memakai NILAI KONVERSI NPS dari
        // PUTARAN TERAKHIR (default) atau putaran pilihan user. Fallback ke
        // nilai_akhir hanya untuk data lama yang belum punya nilai konversi
        // (ditangani di npsUntukNppPerPeserta).
        $npsPutaran = self::normalizeSumber($npsPutaran);
        $samaptaMap = NilaiSamapta::npsUntukNppPerPeserta($angkatanId, $ids, $npsPutaran);

        // Revisi 30 September 2026: NPK diambil dari PERIODE TERAKHIR
        // (default) atau periode pilihan user — bukan rata-rata semua periode.
        $npkPeriodeId = self::normalizeSumber($npkPeriode);
        $npkPeriodeId = $npkPeriodeId !== null ? (int) $npkPeriodeId : null;
        $kepribadianMap = NilaiKepribadian::npkUntukNppPerPeserta($ids, $npkPeriodeId, $angkatanId);

        // Label sumber (untuk keterangan di halaman NPP / cetak laporan).
        $sumberNps = $npsPutaran
            ?? NilaiSamapta::putaranTerakhirLabel($angkatanId)
            ?? NilaiSamapta::PUTARAN_DEFAULT;
        $sumberNpkObj = $npkPeriodeId
            ? PeriodeNilai::find($npkPeriodeId)
            : NilaiKepribadian::periodeTerakhir($angkatanId);
        $sumberNpk = $sumberNpkObj?->label ?? '-';

        $rows = [];
        foreach ($pesertaList as $p) {
            $na = $akademikMap->get($p->id);

            $npa = $na ? round($na->npa, 2) : 0;
            $npk = $kepribadianMap[$p->id] ?? 0;
            $nps = $samaptaMap[$p->id] ?? 0;
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
                'sumber_nps'         => $sumberNps,
                'sumber_npk'         => $sumberNpk,
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
    public static function forPeserta(?int $angkatanId, ?int $pesertaId, ?string $npsPutaran = null, $npkPeriode = null): ?object
    {
        if (!$angkatanId || !$pesertaId) return null;
        return self::forAngkatan($angkatanId, $npsPutaran, $npkPeriode)
            ->firstWhere('peserta_didik_id', $pesertaId);
    }
}
