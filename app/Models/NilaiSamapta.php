<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NilaiSamapta extends Model {
    protected $table = 'nilai_samapta';

    protected $fillable = [
        'peserta_didik_id', 'angkatan_id', 'putaran_label',
        // ── NPS sederhana (5 field manual, TANPA rumus) ──
        'jarak_lari',      // Jarak Lari (meter)
        'nilai_lari',      // Nilai Lari (Garjas A)
        'garjas_b_nilai',  // Garjas B
        'nilai_akhir',     // Nilai Akhir
        'nilai_konversi',  // Nilai Konversi
        // ── kolom lama (dipertahankan utk data historis, tdk dipakai input baru) ──
        'umur', 'tinggi_badan', 'berat_badan',
        'garjas_a_putaran', 'garjas_a_lebih', 'garjas_a_waktu', 'garjas_a_tsc',
        'garjas_b_chin_up_jml', 'garjas_b_chin_up_tsc',
        'garjas_b_sit_up_jml',  'garjas_b_sit_up_tsc',
        'garjas_b_push_up_jml', 'garjas_b_push_up_tsc',
        'garjas_b_stl_run_wkt', 'garjas_b_stl_run_tsc',
        'rata_b', 'rata_ab',
        'input_oleh',
    ];

    /** Label putaran default bila user tidak mengisi. */
    public const PUTARAN_DEFAULT = 'Putaran 1';

    protected $casts = [
        'jarak_lari'     => 'float',
        'nilai_lari'     => 'float',
        'garjas_b_nilai' => 'float',
        'nilai_konversi' => 'float',
        'umur'                  => 'integer',
        'tinggi_badan'          => 'float',
        'berat_badan'           => 'float',
        'garjas_a_putaran'      => 'integer',
        'garjas_a_lebih'        => 'integer',
        'garjas_a_waktu'        => 'float',
        'garjas_a_tsc'          => 'float',
        'garjas_b_chin_up_jml'  => 'float',
        'garjas_b_chin_up_tsc'  => 'float',
        'garjas_b_sit_up_jml'   => 'float',
        'garjas_b_sit_up_tsc'   => 'float',
        'garjas_b_push_up_jml'  => 'float',
        'garjas_b_push_up_tsc'  => 'float',
        'garjas_b_stl_run_wkt'  => 'float',
        'garjas_b_stl_run_tsc'  => 'float',
        'rata_b'                => 'float',
        'rata_ab'               => 'float',
        'nilai_akhir'           => 'float',
    ];

    protected $appends = ['predikat'];

    /**
     * Hitung Waktu Garjas A = putaran × 230 + lebih
     */
    public static function hitungWaktu($putaran, $lebih) {
        return ($putaran * 230) + $lebih;
    }

    /**
     * Hitung Rata-rata B = (TSC ChinUp + TSC SitUp + TSC PushUp + TSC STLRun) / 4
     */
    public static function hitungRataB($tscChinUp, $tscSitUp, $tscPushUp, $tscStlRun) {
        $sum = $tscChinUp + $tscSitUp + $tscPushUp + $tscStlRun;
        return $sum / 4;
    }

    /**
     * Hitung Rata-rata AB = (TSC Garjas A + Rata-rata B) / 2
     */
    public static function hitungRataAB($tscA, $rataB) {
        return ($tscA + $rataB) / 2;
    }

    /**
     * ── TABEL KONVERSI RATA-RATA AB → NILAI PRESTASI (NPS) ────────────────
     * Sumber: Sheet "DATA AWAL" GQ:GR pada file resmi Mabes
     *         "Garjas I Sesarcab Tek A-39 TA 2026.xlsx".
     *
     * Rumus di Excel:  CN12 = VLOOKUP(CM12, $GQ$10:$GR$110, 2, FALSE)
     *                  CM12 = ROUND(W12,0)   ← W12 = rata-rata AB
     *                  X12  = SUM(CN12)       ← nilai konversi = NPS
     *
     * Key   = round(rata-rata AB)  (integer 1..100)
     * Value = nilai konversi (skor prestasi samapta).
     */
    public const KONVERSI_GARJAS = [
        1 => 23.0,    2 => 23.78,   3 => 24.56,   4 => 25.33,   5 => 26.11,
        6 => 26.89,   7 => 27.67,   8 => 28.44,   9 => 29.22,  10 => 30.0,
       11 => 30.78,  12 => 31.56,  13 => 32.33,  14 => 33.11,  15 => 33.89,
       16 => 34.67,  17 => 35.44,  18 => 36.22,  19 => 37.0,   20 => 37.78,
       21 => 38.56,  22 => 39.33,  23 => 40.11,  24 => 40.89,  25 => 41.67,
       26 => 42.44,  27 => 43.22,  28 => 44.0,   29 => 44.78,  30 => 45.56,
       31 => 46.33,  32 => 47.11,  33 => 47.89,  34 => 48.67,  35 => 49.44,
       36 => 50.22,  37 => 51.0,   38 => 51.78,  39 => 52.56,  40 => 53.33,
       41 => 54.11,  42 => 54.89,  43 => 55.67,  44 => 56.44,  45 => 57.22,
       46 => 58.0,   47 => 58.78,  48 => 59.56,  49 => 60.33,  50 => 61.41,
       51 => 61.89,  52 => 62.67,  53 => 63.44,  54 => 64.22,  55 => 65.0,
       56 => 65.78,  57 => 66.56,  58 => 67.33,  59 => 68.11,  60 => 68.89,
       61 => 69.67,  62 => 70.44,  63 => 71.22,  64 => 72.0,   65 => 72.78,
       66 => 73.56,  67 => 74.33,  68 => 75.11,  69 => 75.89,  70 => 76.67,
       71 => 77.44,  72 => 78.22,  73 => 79.0,   74 => 79.78,  75 => 80.56,
       76 => 81.33,  77 => 82.11,  78 => 82.89,  79 => 83.67,  80 => 84.44,
       81 => 85.22,  82 => 85.78,  83 => 86.0,   84 => 87.55,  85 => 88.33,
       86 => 89.11,  87 => 89.89,  88 => 90.67,  89 => 91.44,  90 => 92.22,
       91 => 93.0,   92 => 93.78,  93 => 94.56,  94 => 95.33,  95 => 96.11,
       96 => 96.89,  97 => 97.67,  98 => 98.44,  99 => 99.22, 100 => 100.0,
    ];

    /**
     * Hitung NILAI KONVERSI (NPS) dari rata-rata AB.
     * Mengikuti rumus Mabes: VLOOKUP(ROUND(rataAB,0), tabelGQ:GR, 2, FALSE).
     * - rataAB dibulatkan ke integer terdekat (round half up, sama seperti Excel).
     * - key > 100 di-clamp ke 100; key < 1 (belum ada data) → 0.
     */
    public static function hitungNilaiKonversi($rataAB): float
    {
        $key = (int) round((float) $rataAB);          // ROUND(W12,0)
        if ($key < 1) return 0.0;                      // belum ada nilai
        if ($key > 100) return 100.0;                  // nilai sempurna
        return (float) self::KONVERSI_GARJAS[$key];
    }

    /**
     * Hitung Nilai Akhir NPS.
     * NOTE: NPS = NILAI KONVERSI (bukan rata-rata aritmatika).
     *       Disamakan dengan rumus resmi Mabes (cell X12 pada DATA AWAL).
     */
    public static function hitungNilaiAkhir($rataB, $rataAB) {
        return self::hitungNilaiKonversi($rataAB);
    }

    /**
     * Hitung semua dari input mentah
     */
    public static function hitungSemua($data) {
        $putaran = (int)($data['garjas_a_putaran'] ?? 0);
        $lebih   = (int)($data['garjas_a_lebih'] ?? 0);
        $waktu   = self::hitungWaktu($putaran, $lebih);

        $tscA            = (float)($data['garjas_a_tsc'] ?? 0);
        $tscChinUp       = (float)($data['garjas_b_chin_up_tsc'] ?? 0);
        $tscSitUp        = (float)($data['garjas_b_sit_up_tsc'] ?? 0);
        $tscPushUp       = (float)($data['garjas_b_push_up_tsc'] ?? 0);
        $tscStlRun       = (float)($data['garjas_b_stl_run_tsc'] ?? 0);

        $rataB = self::hitungRataB($tscChinUp, $tscSitUp, $tscPushUp, $tscStlRun);
        $rataAB = self::hitungRataAB($tscA, $rataB);
        $nilaiAkhir = self::hitungNilaiAkhir($rataB, $rataAB);

        return [
            'garjas_a_waktu' => $waktu,
            'rata_b'          => round($rataB, 2),
            'rata_ab'         => round($rataAB, 2),
            'nilai_akhir'     => round($nilaiAkhir, 2),
        ];
    }

    /**
     * Kategori kriteria (Baik Sekali / Baik / Cukup / Kurang).
     * Revisi 26 Agustus 2026: dihitung dari NILAI KONVERSI
     * (bukan nilai akhir) sesuai permintaan WingDik.
     */
    public function getPredikatAttribute() {
        $v = $this->nilai_konversi;
        if ($v >= 85) return ['label' => 'Baik Sekali', 'color' => '#059669', 'icon' => '⭐'];
        if ($v >= 75) return ['label' => 'Baik', 'color' => '#2563eb', 'icon' => '✅'];
        if ($v >= 65) return ['label' => 'Cukup', 'color' => '#d97706', 'icon' => '⚠️'];
        if ($v !== null && $v > 0) return ['label' => 'Kurang', 'color' => '#dc2626', 'icon' => '❌'];
        return ['label' => '-', 'color' => '#aaa', 'icon' => ''];
    }

    public function peserta()  { return $this->belongsTo(PesertaDidik::class, 'peserta_didik_id'); }
    public function angkatan() { return $this->belongsTo(Angkatan::class); }
    public function inputOleh(){ return $this->belongsTo(User::class, 'input_oleh'); }

    /**
     * Get unique putaran labels for an angkatan (urut ascending).
     * Selalu menyertakan label default agar dropdown tidak kosong saat belum ada data.
     */
    public static function getPutaranList(int $angkatanId): array
    {
        // 1) Ambil label dari tabel periode_nps (yang sudah terdaftar via Tambah Putaran)
        $periodeLabels = \App\Models\PeriodeNps::where('angkatan_id', $angkatanId)
            ->orderBy('tanggal_mulai')
            ->pluck('label')
            ->all();

        // 2) Ambil label dari data nilai_samapta yang sudah ada
        $dataLabels = self::where('angkatan_id', $angkatanId)
            ->whereNotNull('putaran_label')
            ->distinct()
            ->orderBy('putaran_label')
            ->pluck('putaran_label')
            ->all();

        // 3) Merge & unik, pertahankan urutan dari periode_nps dulu
        $labels = array_values(array_unique(array_merge($periodeLabels, $dataLabels)));

        if (!in_array(self::PUTARAN_DEFAULT, $labels, true)) {
            array_unshift($labels, self::PUTARAN_DEFAULT);
        }
        return $labels;
    }

    /**
     * Normalisasi label putaran: kosong → default. Menghindari NULL pada
     * unique key (peserta, angkatan, putaran_label) supaya upsert konsisten.
     */
    public static function normalizePutaran(?string $label): string
    {
        $label = trim((string) $label);
        return $label === '' ? self::PUTARAN_DEFAULT : $label;
    }
}
