<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, KompilasiNilai, NilaiAkademik, NilaiKepribadian, NilaiSamapta, PeriodeNilai, Skadik, Penandatangan};
use App\Services\NppCalculator;
use App\Services\ExportFile;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class ReportController extends Controller
{
    /**
     * Resolver filter Sekolah–Angkatan yang KONSISTEN (Revisi 2 September 2026).
     *
     * Masalah lama: saat user mengganti dropdown Sekolah lalu form ter-submit,
     * angkatan_id lama (milik sekolah lain) ikut terkirim sehingga report/
     * drop-down peserta menampilkan “sekolah-angkatan yang keliru”.
     *
     * Aturan:
     * 1. Jika URL membawa angkatan_id TANPA skadik_id (link langsung 📋 dari
     *    tabel NPA/NPK/NPS ke report individu), sekolah otomatis diambil dari
     *    angkatan tsb (selama masih dalam wewenang user).
     * 2. angkatan_id WAJIB milik sekolah terpilih. Jika tidak (stale), di-reset
     *    ke angkatan pertama sekolah terpilih.
     *
     * @return array{0: Collection, 1: mixed, 2: Collection, 3: mixed, 4: Angkatan|null}
     *         [allSkadik, skadikId, allAngkatan, angkatanId, angkatan]
     */
    private function resolveSekolahAngkatan(Request $request): array
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $angkatanId = $request->get('angkatan_id');
        if (!$request->filled('skadik_id') && $angkatanId) {
            $skadikAsal = Angkatan::find($angkatanId)?->skadik_id;
            if ($skadikAsal && $allSkadik->pluck('id')->contains($skadikAsal)) {
                $skadikId = $skadikAsal;
            }
        }

        $angkatanQuery = Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc');
        if ($skadikId) $angkatanQuery->where('skadik_id', $skadikId);
        $allAngkatan = $angkatanQuery->get();

        if (!$request->filled('angkatan_id') || !$allAngkatan->pluck('id')->contains($angkatanId)) {
            $angkatanId = $allAngkatan->first()?->id;
        }

        $angkatan = Angkatan::with('skadik.lemdik')->find($angkatanId);

        return [$allSkadik, $skadikId, $allAngkatan, $angkatanId, $angkatan];
    }

    /**
     * Σ(MP×HN) — total nilai tertimbang yang TAMPIL pada kolom "Σ(MP×HN)"
     * Report NPA (dihitung live dari detail_nilai × harga_nilai_calc — sama
     * persis dengan perhitungan di view/ekspor).
     * CATATAN: kolom jumlah_nilai di DB hanyalah Σ(MP) TANPA bobot, sehingga
     * tidak boleh dipakai untuk urutan/tie-break ranking.
     * Revisi 1 Oktober 2026.
     */
    private function sigmaMPHN($d, $mataPelajaran): float
    {
        // Tanpa konfigurasi mapel, view menampilkan jumlah_nilai (Σ MP) —
        // pakai itu supaya urutan tetap sesuai kolom yang dilihat user.
        if (!$mataPelajaran || $mataPelajaran->isEmpty()) {
            return (float)($d->jumlah_nilai ?? -1);
        }
        $detail = is_array($d->detail_nilai ?? null)
            ? $d->detail_nilai
            : (json_decode($d->detail_nilai ?? '[]', true) ?: []);
        $sum = 0;
        foreach ($mataPelajaran as $idx => $mp) {
            $sum += (float)($detail[$idx] ?? 0) * (float)$mp->harga_nilai_calc;
        }
        return round($sum, 2);
    }

    // ── Report NPA Angkatan ──────────────────────────────────
    // Revisi 2 September 2026: hasil TIDAK langsung ditampilkan. User memilih
    // Sekolah–Angkatan lalu menekan tombol "🔍 Tampilkan" (?tampilkan=1)
    // supaya yakin kombinasi sekolah-angkatan yang dimuat tidak keliru.
    public function reportNPA(Request $request)
    {
        try {
            [$allSkadik, $skadikId, $allAngkatan, $angkatanId, $angkatan] = $this->resolveSekolahAngkatan($request);

            // Baru muat data setelah tombol "Tampilkan" ditekan
            $submitted = $request->filled('tampilkan');

            // Autoload mata pelajaran from mata_pelajaran table per sekolah (via pivot)
            // — dimuat lebih dulu karena diperlukan untuk menghitung Σ(MP×HN)
            //   pada saat sorting daftar peserta.
            $mataPelajaran = collect();
            if ($angkatan && $angkatan->skadik_id) {
                $mataPelajaran = \App\Models\MataPelajaran::forSkadik($angkatan->skadik_id, true);
            }

            // Daftar BERBASIS PESERTA agar jumlah siswa konsisten dengan NPK & NPS:
            // semua peserta tampil, termasuk yang belum diberi NPA.
            $data = collect();
            if ($angkatan && $submitted) {
                $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
                $nilaiMap = NilaiAkademik::where('angkatan_id', $angkatanId)->get()->keyBy('peserta_didik_id');
                $data = $pesertaList->map(function ($p) use ($nilaiMap) {
                    $na = $nilaiMap->get($p->id);
                    if ($na) { $na->setRelation('peserta', $p); return $na; }
                    return (object)[
                        'peserta' => $p, 'peserta_didik_id' => $p->id,
                        'npa' => null, 'jumlah_nilai' => null, 'detail_nilai' => [],
                    ];
                })
                // Revisi 1 Oktober 2026 — urut NPA desc; NPA sama → tie-breaker
                // Σ(MP×HN) TERBESAR diperingkat paling atas (kolom yang
                // benar-benar ditampilkan, bukan jumlah_nilai di DB yang
                // berupa Σ(MP) tanpa bobot).
                ->sortByDesc(fn($d) => $this->sigmaMPHN($d, $mataPelajaran))
                ->sortByDesc(fn($d) => $d->npa ?? -1)
                ->values();
            }

            return view('report.report-angkatan', array_merge(compact(
                'allSkadik','skadikId','allAngkatan','angkatanId','angkatan','data', 'mataPelajaran','submitted'
            ), ['title' => 'Report NPA — Nilai Prestasi Akademik', 'type' => 'NPA']));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memuat report NPA: ' . $e->getMessage());
        }
    }

    // ── Report NPK Angkatan (All Period) ──────────────────────────────
    public function reportNPK(Request $request)
    {
        try {
            [$allSkadik, $skadikId, $allAngkatan, $angkatanId, $angkatan] = $this->resolveSekolahAngkatan($request);

            // Revisi 2 September 2026: hasil hanya dimuat setelah tombol
            // "🔍 Tampilkan" (?tampilkan=1) ditekan — selaras dengan NPA & NPS.
            $submitted = $request->filled('tampilkan');

            $data = collect();
            $periodes = collect();
            $periodeKelola = collect();
            if ($angkatan && $submitted) {
                $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();

                // Query nilai kepribadian sekali — load periode relation juga
                $semuaNilai = NilaiKepribadian::with('periode')
                    ->whereIn('peserta_didik_id', $pesertaList->pluck('id'))
                    ->get();

                // Bangun daftar periode dari DATA YANG ADA (bukan hanya dari
                // tabel PeriodeNilai) agar tetap muncul meski PeriodeNilai
                // tidak punya angkatan_id yang sesuai.
                $periodes = $semuaNilai->pluck('periode')
                    ->filter()
                    ->unique('id')
                    ->sortBy('tanggal_mulai')
                    ->values();

                // Revisi 26 Agustus 2026: daftar periode utk kartu “Kelola Periode”
                // (hard delete). Diambil dari tabel periode_nilai milik angkatan +
                // periode yatim (punya nilai tapi periode->angkatan_id beda) agar
                // semuanya bisa dibersihkan dari tabel report ini.
                $periodeDariTabel = PeriodeNilai::where('angkatan_id', $angkatanId)
                    ->withCount('nilaiKepribadian as jumlah_nilai')
                    ->orderBy('tanggal_mulai')
                    ->get();
                $idDariTabel = $periodeDariTabel->pluck('id');
                $periodeKelola = $periodeDariTabel
                    ->concat($periodes->filter(fn($p) => !$idDariTabel->contains($p->id)))
                    ->map(function ($p) {
                        if (!isset($p->jumlah_nilai)) {
                            $p->jumlah_nilai = NilaiKepribadian::where('periode_nilai_id', $p->id)->count();
                        }
                        return $p;
                    })->values();

                $data = $pesertaList->map(function ($p) use ($periodes, $semuaNilai) {
                    $nilaiPerPeriode = [];
                    $total = 0;
                    $cnt = 0;
                    foreach ($periodes as $per) {
                        $nk = $semuaNilai->first(fn($n) => $n->peserta_didik_id == $p->id && $n->periode_nilai_id == $per->id);
                        $val = $nk ? $nk->nilai_akhir : null;
                        $nilaiPerPeriode[$per->id] = ['nilai' => $val];
                        if ($val !== null) { $total += $val; $cnt++; }
                    }
                    return [
                        'peserta'             => $p,
                        'nilai_per_periode'   => $nilaiPerPeriode,
                        // Revisi 2 Oktober 2026: kolom acuan ranking NPK diganti
                        // dari RATA-RATA menjadi AKUMULATIF (Σ / sum) seluruh
                        // periode — sesuai permintaan WingDik. Peserta yang
                        // belum dinilai di satu periode otomatis punya akumulasi
                        // lebih kecil (tidak ada pembagi jumlah periode).
                        'akumulatif'          => $cnt > 0 ? round($total, 2) : null,
                    ];
                })->sortByDesc('akumulatif')->values();
            }

            return view('report.report-npk', array_merge(compact(
                'allSkadik','skadikId','allAngkatan','angkatanId','angkatan','data','periodes','periodeKelola','submitted'
            ), ['title' => 'Report NPK — Nilai Prestasi Kepribadian', 'type' => 'NPK']));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memuat report NPK: ' . $e->getMessage());
        }
    }

    // ── Report NPS Angkatan ─────────────────────────────────
    // Revisi 26 Agustus 2026: hasil TIDAK langsung ditampilkan.
    // User memilih Sekolah–Angkatan–Putaran lalu menekan tombol
    // "🔍 Tampilkan" (param ?tampilkan=1) untuk memuat hasil.
    public function reportNPS(Request $request)
    {
        try {
            [$allSkadik, $skadikId, $allAngkatan, $angkatanId, $angkatan] = $this->resolveSekolahAngkatan($request);

            $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));
            $putaranList  = $angkatanId ? NilaiSamapta::getPutaranList($angkatanId) : [];

            // Baru muat data setelah tombol "Tampilkan" ditekan
            $submitted = $request->filled('tampilkan');

            // Daftar BERBASIS PESERTA agar konsisten dengan NPA & NPK.
            $data = collect();
            if ($angkatan && $submitted) {
                $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
                $nilaiMap = NilaiSamapta::where('angkatan_id', $angkatanId)
                    ->where('putaran_label', $putaranLabel)
                    ->get()->keyBy('peserta_didik_id');
                $data = $pesertaList->map(function ($p) use ($nilaiMap) {
                    $ns = $nilaiMap->get($p->id);
                    if ($ns) { $ns->setRelation('peserta', $p); return $ns; }
                    return (object)[
                        'peserta' => $p, 'peserta_didik_id' => $p->id,
                        'nilai_akhir' => null, 'jarak_lari' => null, 'nilai_lari' => null,
                        'garjas_b_nilai' => null, 'nilai_konversi' => null,
                    ];
                })->sortByDesc(fn($d) => $d->nilai_akhir ?? -1)->values();
            }

            return view('report.report-angkatan', array_merge(compact(
                'allSkadik','skadikId','allAngkatan','angkatanId','angkatan','data','putaranLabel','putaranList','submitted'
            ), ['title' => 'Report NPS — Nilai Prestasi Samapta', 'type' => 'NPS']));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memuat report NPS: ' . $e->getMessage());
        }
    }

    // ── Report Individual (Akhir Pendidikan) ─────────────────
    // Revisi 2 September 2026:
    // 1) GUARD: angkatan wajib milik sekolah terpilih — sebelumnya saat user
    //    mengganti dropdown Sekolah, angkatan_id lama (milik sekolah lain) ikut
    //    ter-submit sehingga drop-down Peserta menampilkan daftar siswa dari
    //    sekolah/angkatan yang keliru (mis. bukan Sejurlaba APS — Angkatan 1).
    // 2) Tombol "🔍 Tampilkan": hasil individu hanya dimuat setelah user
    //    menekan tombol tsb. Link langsung ?peserta_id= tetap langsung menampilkan.
    public function reportIndividu(Request $request)
    {
        try {
            [$allSkadik, $skadikId, $allAngkatan, $angkatanId, $angkatan] = $this->resolveSekolahAngkatan($request);
            $pesertaId  = $request->get('peserta_id');

            // Drop-down Peserta HARUS berisi siswa dari angkatan terpilih
            // (bukan peserta yatim / sekolah lain).
            $pesertaList = $angkatanId
                ? PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get()
                : collect();

            // Hasil hanya dimuat setelah tombol "Tampilkan" ditekan.
            // Kecuali link langsung dengan peserta_id (dari tabel NPA/NPK/NPS)
            // agar tombol 📋 tetap langsung membuka laporan siswa tsb.
            $submitted = $request->filled('tampilkan') || $request->filled('peserta_id');

            $peserta = null;
            // Revisi 30 Sept 2026: $npsAvg WAJIB diinisialisasi null — sebelumnya
            // hanya didefinisikan di dalam if($peserta), sehingga saat menu
            // Report Individual dibuka TANPA peserta terpilih, compact()
            // melempar "Undefined variable $npsAvg" dan halaman gagal dimuat.
            $npsAvg = null;
            $akademik = null; $kepribadianList = null; $kepribadianAvg = null; $samapta = null; $kompilasi = null; $mataPelajaran = collect();

            if ($submitted && $pesertaId) {
                // Pastikan peserta yg diminta memang anggota angkatan terpilih
                $peserta = $pesertaList->first(fn($p) => $p->id == $pesertaId) ?? null;
            }

            if ($peserta) {
                $akademik = NilaiAkademik::where('peserta_didik_id', $pesertaId)->where('angkatan_id', $angkatanId)->first();
                $kepribadianList = NilaiKepribadian::with('periode','detail.aspek')->where('peserta_didik_id', $pesertaId)->orderBy('periode_nilai_id')->get();
                // Revisi 2 Oktober 2026: kartu NPS pada Report Individual kini
                // memakai record dari PUTARAN TERAKHIR (bukan ->first() yang
                // selalu mengambil Putaran 1), sehingga jarak lari/garjas/nilai
                // konversi/label putaran yang tampil = putaran yang sama dengan
                // nilai NPS yang dipakai sebagai INPUT NPP ($npsAvg).
                $samapta = NilaiSamapta::recordUntukNpp($angkatanId, $pesertaId);
                // Revisi 30 Sept 2026: NPP individu memakai nilai konversi NPS
                // dari PUTARAN TERAKHIR & NPK dari PERIODE TERAKHIR (konsisten
                // dengan NppCalculator / halaman NPP).
                $npsAvg = NilaiSamapta::npsUntukNpp($angkatanId, $pesertaId);
                $kepribadianAvg = NilaiKepribadian::npkUntukNpp($pesertaId, null, $angkatanId);
                $kompilasi = KompilasiNilai::where('peserta_didik_id', $pesertaId)->where('angkatan_id', $angkatanId)->first();

                // Fallback: bila peserta belum dikompilasi, tetap bangun objek bobot
                // agar kartu NPP & laporan individu tampil lengkap.
                if (!$kompilasi) {
                    $ref = KompilasiNilai::where('angkatan_id', $angkatanId)->first();
                    $kompilasi = (object) [
                        'bobot_akademik'    => $ref->bobot_akademik    ?? NppCalculator::BOBOT_AKADEMIK_DEFAULT,
                        'bobot_kepribadian' => $ref->bobot_kepribadian ?? NppCalculator::BOBOT_KEPRIBADIAN_DEFAULT,
                        'bobot_samapta'     => $ref->bobot_samapta     ?? NppCalculator::BOBOT_SAMAPTA_DEFAULT,
                        'nilai_akademik'    => $akademik ? round($akademik->npa, 2) : 0,
                        'nilai_kepribadian' => round($kepribadianAvg, 2),
                        'nilai_samapta'     => $npsAvg,
                        'predikat_huruf'    => '-',
                        'predikat_angka'    => 0,
                    ];
                }

                // Autoload mata pelajaran from mata_pelajaran table per sekolah (via pivot)
                if ($angkatan && $angkatan->skadik_id) {
                    $mataPelajaran = \App\Models\MataPelajaran::forSkadik($angkatan->skadik_id, true);
                }
            }

            return view('report.report-individu', compact(
                'allSkadik','skadikId','allAngkatan','angkatanId','angkatan','submitted',
                'pesertaList','pesertaId','peserta',
                'akademik','kepribadianList','kepribadianAvg','samapta','npsAvg','kompilasi','mataPelajaran'
            ));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memuat report individu: ' . $e->getMessage());
        }
    }

    // ── Report NPP (Angkatan) ───────────────────────────────
    public function reportNPP(Request $request)
    {
        try {
            // Revisi 2 Sept 2026: pakai resolver konsisten (anti sekolah-angkatan
            // keliru) — perilaku filter NPP lainnya tidak berubah.
            [$allSkadik, $skadikId, $allAngkatan, $angkatanId, $angkatan] = $this->resolveSekolahAngkatan($request);

            // Revisi 30 Sept 2026: pilihan SUMBER NPS (putaran) & NPK (periode).
            // Default = TERAKHIR (putaran/periode terbaru) — sesuai kebijakan NPP.
            $npsPutaran = NppCalculator::normalizeSumber($request->get('nps_putaran'));
            $npkPeriode = NppCalculator::normalizeSumber($request->get('npk_periode'));
            $putaranList = $angkatanId ? NilaiSamapta::getPutaranList($angkatanId) : [];
            $periodeList = $angkatanId
                ? PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get()
                : collect();

            if (!$angkatan) {
                return view('report.report-npp', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'putaranList', 'periodeList', 'npsPutaran', 'npkPeriode') + [
                    'angkatan' => null, 'data' => collect(), 'stats' => []
                ]);
            }

            // SEMUA peserta (bulk), dihitung live — NPS dari putaran TERAKHIR
            // / pilihan user, NPK dari periode TERAKHIR / pilihan user.
            $data = NppCalculator::forAngkatan($angkatanId, $npsPutaran, $npkPeriode);

            $totalPeserta = PesertaDidik::where('angkatan_id', $angkatanId)->count();

            // Revisi 22 Sept 2026: rata-rata angkatan PER KOMPONEN (NPA/NPK/NPS)
            // — hanya peserta yang punya nilai (0 = belum dinilai, tidak ikut
            // dihitung), selaras dengan footer "Rata-rata Angkatan" di report
            // NPA/NPK/NPS.
            $avgKomponen = fn(string $field) => round($data->filter(fn($d) => ($d->{$field} ?? 0) > 0)->avg($field) ?? 0, 2);

            $stats = [
                'total_peserta'   => $totalPeserta,
                'sudah_kompilasi' => $data->count(),
                'belum_kompilasi' => $totalPeserta - $data->count(),
                'belum_akademik'  => PesertaDidik::where('angkatan_id', $angkatanId)
                    ->whereDoesntHave('nilaiAkademik', fn($q) => $q->where('angkatan_id', $angkatanId))->count(),
                'belum_samapta'   => PesertaDidik::where('angkatan_id', $angkatanId)
                    ->whereDoesntHave('nilaiSamapta', fn($q) => $q->where('angkatan_id', $angkatanId))->count(),
                'belum_kepribadian' => PesertaDidik::where('angkatan_id', $angkatanId)
                    ->whereDoesntHave('nilaiKepribadian')->count(),
                'rata_akhir' => $data->count() > 0 ? round($data->avg('nilai_akhir'), 2) : 0,
                'rata_npa'   => $avgKomponen('nilai_akademik'),
                'rata_npk'   => $avgKomponen('nilai_kepribadian'),
                'rata_nps'   => $avgKomponen('nilai_samapta'),
                'tertinggi'  => $data->max('nilai_akhir') ?? 0,
                'terendah'   => $data->min('nilai_akhir') ?? 0,
            ];

            return view('report.report-npp', compact(
                'allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'putaranList', 'periodeList', 'npsPutaran', 'npkPeriode', 'angkatan', 'data', 'stats'
            ));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memuat report NPP: ' . $e->getMessage());
        }
    }

    /**
     * Baris info penandatangan utk sheet Excel — Revisi 22 Sept 2026:
     * Danskadik (kolom kiri "Mengetahui") kini berlaku di SEMUA report
     * (NPA/NPK/NPS/NPP — cetak & ekspor), bukan NPP saja.
     * Format: "Mengetahui: <danskadik>   |   Penandatangan: <jenis laporan>"
     */
    private function infoPenandatanganExcel($sheet, string $cell, string $jenis, ?int $skadikId, int $fontSize = 10): void
    {
        $ttdKiri  = Penandatangan::getPenandatangan('danskadik', $skadikId);
        $ttdKanan = Penandatangan::getPenandatangan($jenis, $skadikId);

        $kiri = '';
        if ($ttdKiri) {
            $kiri = 'Mengetahui: ' . $ttdKiri->nama
                . ($ttdKiri->pangkat ? ', ' . $ttdKiri->pangkat : '')
                . ' — ' . $ttdKiri->jabatan;
        }
        $kanan = '';
        if ($ttdKanan) {
            $kanan = 'Penandatangan: ' . $ttdKanan->nama
                . ($ttdKanan->pangkat ? ', ' . $ttdKanan->pangkat : '')
                . ($ttdKanan->nrp ? ', ' . $ttdKanan->nrp : '')
                . ' — ' . $ttdKanan->jabatan;
        }

        $gabung = implode('   |   ', array_filter([$kiri, $kanan]));
        if ($gabung !== '') {
            $sheet->setCellValue($cell, $gabung);
            $sheet->getStyle($cell)->getFont()->setItalic(true)->setSize($fontSize);
        }
    }

    // ── Ekspor Report NPA ───────────────────────────────────
    // Revisi 18 September 2026: struktur Excel disamakan dengan tabel PREVIEW
    // aplikasi & PDF cetak NPA:
    //  - kolom No, Rank, NRP, Pangkat, Nama
    //  - satu kolom per mata pelajaran: header nama mapel VERTIKAL dibaca
    //    dari BAWAH ke ATAS (textRotation 90) + info JP/B/HN juga vertikal
    //    bawah→atas, sejajar DI BAWAH nama mapel (bukan miring) — persis
    //    header preview/PDF
    //  - kolom Σ(MP×HN) dan NPA (nilai per mapel diambil dari detail_nilai)
    //  - daftar BERBASIS PESERTA (belum diberi NPA tetap tampil), urut NPA desc
    public function eksporNPA(Request $request)
    {
        try {
            $angkatanId = $request->get('angkatan_id');
            $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

            // Mata pelajaran sekolah (urutan pivot = urutan index detail_nilai)
            $mataPelajaran = collect();
            if ($angkatan && $angkatan->skadik_id) {
                $mataPelajaran = \App\Models\MataPelajaran::forSkadik($angkatan->skadik_id, true);
            }
            $nMapel = $mataPelajaran->count();

            // Data berbasis peserta — identik dengan reportNPA (preview)
            $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
            $nilaiMap = NilaiAkademik::where('angkatan_id', $angkatanId)->get()->keyBy('peserta_didik_id');
            $data = $pesertaList->map(function ($p) use ($nilaiMap) {
                $na = $nilaiMap->get($p->id);
                if ($na) { $na->setRelation('peserta', $p); return $na; }
                return (object)[
                    'peserta' => $p, 'peserta_didik_id' => $p->id,
                    'npa' => null, 'jumlah_nilai' => null, 'detail_nilai' => [],
                ];
            })
            // Revisi 1 Oktober 2026 — NPA sama → tie-breaker Σ(MP×HN) desc
            // (identik preview/cetak agar rank Excel = rank preview).
            ->sortByDesc(fn($d) => $this->sigmaMPHN($d, $mataPelajaran))
            ->sortByDesc(fn($d) => $d->npa ?? -1)
            ->values();

            $totalHN = $mataPelajaran->sum(fn($mp) => $mp->harga_nilai_calc);
            $maxLenNama = $mataPelajaran->max(fn($mp) => mb_strlen($mp->nama)) ?? 0;

            $spreadsheet = new Spreadsheet();
            ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
            $sh = $spreadsheet->getActiveSheet()->setTitle('Report NPA');
            $colName = fn(int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);

            $colSum = $colName(5 + $nMapel + 1); // kolom Σ(MP×HN)
            $colNpa = $colName(5 + $nMapel + 2); // kolom NPA
            $lastCol = $nMapel > 0 ? $colNpa : 'G';

            // Judul
            $sh->mergeCells("A1:{$lastCol}1");
            $sh->setCellValue('A1', 'REPORT NILAI PRESTASI AKADEMIK (NPA) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
            $sh->getStyle('A1')->applyFromArray([
                'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'000000']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $this->infoPenandatanganExcel($sh, 'A2', 'akademik', $angkatan?->skadik_id);

            // Baris info rumus — sama seperti sub-header PDF cetak NPA
            if ($nMapel > 0) {
                $sh->mergeCells("A3:{$lastCol}3");
                $sh->setCellValue('A3', 'Rumus: NPA = Σ(Nilai MP × HN) / Σ(HN)  |  Σ JP = ' . $mataPelajaran->sum('jp')
                    . ' · Σ Bobot = ' . $mataPelajaran->sum('bobot') . ' · Σ HN = ' . $totalHN);
                $sh->getStyle('A3')->getFont()->setItalic(true)->setSize(10);
                $sh->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // ── Header 2 baris: nama mapel VERTIKAL + JP/B/HN MIRING 45° ──
            foreach (['No','Rank','Nama','Pangkat','NRP'] as $i => $h) {
                $c = $colName($i + 1);
                $sh->mergeCells("{$c}4:{$c}5");
                $sh->setCellValue("{$c}4", $h);
            }
            if ($nMapel > 0) {
                $sh->mergeCells("{$colSum}4:{$colSum}5");
                $sh->setCellValue("{$colSum}4", 'Σ(MP×HN)');
                $sh->mergeCells("{$colNpa}4:{$colNpa}5");
                $sh->setCellValue("{$colNpa}4", 'NPA');
            } else {
                $sh->mergeCells('F4:F5'); $sh->setCellValue('F4', 'Jumlah Nilai');
                $sh->mergeCells('G4:G5'); $sh->setCellValue('G4', 'NPA');
            }
            $sh->getStyle("A4:{$lastCol}5")->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'000000']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
            ]);
            foreach ($mataPelajaran as $i => $mp) {
                $c = $colName(6 + $i);
                // Baris 4: nama mapel VERTIKAL dibaca dari BAWAH ke ATAS
                $sh->setCellValue("{$c}4", $mp->nama);
                $sh->getStyle("{$c}4")->applyFromArray([
                    'alignment'=>['textRotation'=>90,'horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_BOTTOM],
                ]);
                $sh->getStyle("{$c}4")->getFont()->setSize(8);
                // Baris 5: info JP/B/HN TIDAK miring — vertikal bawah→atas,
                // sejajar di bawah nama mapel (spt preview/PDF)
                $sh->setCellValue("{$c}5", "JP={$mp->jp}|B={$mp->bobot}|HN={$mp->harga_nilai_calc}");
                $sh->getStyle("{$c}5")->applyFromArray([
                    'alignment'=>['textRotation'=>90,'horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_TOP],
                ]);
                $sh->getStyle("{$c}5")->getFont()->setSize(8);
            }
            $sh->getRowDimension(4)->setRowHeight(min(230, max(80, $maxLenNama * 6 + 12)));
            $sh->getRowDimension(5)->setRowHeight(95);

            // Lebar kolom
            $sh->getColumnDimension('A')->setWidth(6);
            $sh->getColumnDimension('B')->setWidth(6);
            $sh->getColumnDimension('C')->setWidth(32);
            $sh->getColumnDimension('D')->setWidth(12);
            $sh->getColumnDimension('E')->setWidth(14);
            for ($i = 0; $i < $nMapel; $i++) {
                $sh->getColumnDimension($colName(6 + $i))->setWidth(7);
            }
            if ($nMapel > 0) {
                $sh->getColumnDimension($colSum)->setWidth(12);
                $sh->getColumnDimension($colNpa)->setWidth(10);
            } else {
                $sh->getColumnDimension('F')->setWidth(14);
                $sh->getColumnDimension('G')->setWidth(10);
            }

            // ── Data per peserta ──
            // Revisi 25 September 2026: akumulasi utk footer "Rata-rata Angkatan"
            // — per mapel & NPA (nilai 0 / null tidak dihitung), konsisten dgn
            // ekspor NPK & NPS.
            $sumMapel = [];
            $cntMapel = [];
            $totalNPA = $cntNPA = 0;
            foreach ($data as $i => $d) {
                $r = $i + 6;
                $detailNilai = is_array($d->detail_nilai) ? $d->detail_nilai : (json_decode($d->detail_nilai ?? '[]', true) ?: []);
                $sumMPHN = 0;
                if ($d->npa !== null) { $totalNPA += (float) $d->npa; $cntNPA++; }
                $sh->setCellValue("A{$r}", $i + 1);
                $sh->setCellValue("B{$r}", $i + 1);
                $sh->setCellValue("C{$r}", $d->peserta->nama);
                $sh->setCellValue("D{$r}", $d->peserta->pangkat);
                ExportFile::setText($sh, "E{$r}", $d->peserta->nrp);
                foreach ($mataPelajaran as $mpIdx => $mp) {
                    $c = $colName(6 + $mpIdx);
                    $valMP = $detailNilai[$mpIdx] ?? 0;
                    $sumMPHN += $valMP * $mp->harga_nilai_calc;
                    if (((float) $valMP) > 0) {
                        $sumMapel[$mpIdx] = ($sumMapel[$mpIdx] ?? 0) + (float) $valMP;
                        $cntMapel[$mpIdx] = ($cntMapel[$mpIdx] ?? 0) + 1;
                    }
                    $sh->setCellValue("{$c}{$r}", ((float) $valMP) > 0 ? $valMP : '-');
                    $sh->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                if ($nMapel > 0) {
                    $sh->setCellValue("{$colSum}{$r}", round($sumMPHN, 2));
                    $sh->getStyle("{$colSum}{$r}")->applyFromArray([
                        'font'=>['bold'=>true],
                        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sh->setCellValue("{$colNpa}{$r}", $d->npa ?? '-');
                    $sh->getStyle("{$colNpa}{$r}")->applyFromArray([
                        'font'=>['bold'=>true,'color'=>['rgb'=>ExportFile::TEXT_BLACK],'size'=>12],
                        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
                    ]);
                } else {
                    $sh->setCellValue("F{$r}", $d->jumlah_nilai ?? '-');
                    $sh->setCellValue("G{$r}", $d->npa ?? '-');
                }
            }

            // ── Footer "Rata-rata Angkatan" (spt ekspor NPK & NPS) ──
            if ($cntNPA > 0) {
                $r = $data->count() + 6;
                $sh->mergeCells("A{$r}:E{$r}");
                $sh->setCellValue("A{$r}", 'Rata-rata Angkatan');
                $sh->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                foreach ($mataPelajaran as $mpIdx => $mp) {
                    $c = $colName(6 + $mpIdx);
                    $avg = ($cntMapel[$mpIdx] ?? 0) > 0 ? round($sumMapel[$mpIdx] / $cntMapel[$mpIdx], 2) : '-';
                    $sh->setCellValue("{$c}{$r}", $avg);
                    $sh->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                if ($nMapel > 0) {
                    $sh->setCellValue("{$colSum}{$r}", '');
                    $sh->setCellValue("{$colNpa}{$r}", round($totalNPA / $cntNPA, 2));
                    $sh->getStyle("{$colNpa}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $sh->setCellValue("G{$r}", round($totalNPA / $cntNPA, 2));
                    $sh->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $sh->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                    'font'=>['bold'=>true],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                ]);
            }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(fn() => $writer->save('php://output'), ExportFile::name($angkatan, 'Report NPA'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengekspor NPA: ' . $e->getMessage());
        }
    }

    // ── Cetak NPA (Print) ───────────────────────────────────
    public function cetakNPA(Request $request)
    {
        try {
            $angkatanId = $request->get('angkatan_id');
            $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

            $mataPelajaran = collect();
            if ($angkatan && $angkatan->skadik_id) {
                $mataPelajaran = \App\Models\MataPelajaran::forSkadik($angkatan->skadik_id, true);
            }

            // Revisi 1 Oktober 2026: urut NPA desc; NPA sama → tie-breaker
            // Σ(MP×HN) desc (jumlah_nilai di DB = Σ(MP) tanpa bobot, tidak
            // sesuai kolom Σ(MP×HN) yang dicetak).
            $data = NilaiAkademik::with('peserta')->where('angkatan_id', $angkatanId)->get()
                ->sortByDesc(fn($d) => $this->sigmaMPHN($d, $mataPelajaran))
                ->sortByDesc(fn($d) => $d->npa ?? -1)
                ->values();

            $totalHN = $mataPelajaran->sum(fn($mp) => $mp->harga_nilai_calc);

            $ttdKanan = Penandatangan::getPenandatangan('akademik', $angkatan?->skadik_id);
            $ttdKiri = Penandatangan::getPenandatangan('danskadik', $angkatan?->skadik_id);

            return view('report.cetak-npa', compact('angkatan', 'data', 'mataPelajaran', 'totalHN', 'ttdKanan', 'ttdKiri'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mencetak NPA: ' . $e->getMessage());
        }
    }

    // ── Cetak NPK (Print) — redirect ke rekap cetak ─────────
    public function cetakNPK(Request $request)
    {
        // NPK cetak menggunakan data rekap kepribadian
        $angkatanId = $request->get('angkatan_id');
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

        if (!$angkatan) {
            return back()->with('error', 'Angkatan tidak ditemukan.');
        }

        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
        $periodes = PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get();
        \App\Models\AspekKepribadian::ensureSeeded();
        $aspekList = \App\Models\AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();

        // Muat detail nilai kepribadian per peserta × periode × aspek
        $detailMap = [];
        if ($periodes->isNotEmpty() && $aspekList->isNotEmpty()) {
            $nilaiKep = NilaiKepribadian::with('detail')
                ->whereIn('peserta_didik_id', $pesertaList->pluck('id'))
                ->whereIn('periode_nilai_id', $periodes->pluck('id'))
                ->get();
            foreach ($nilaiKep as $nk) {
                foreach ($nk->detail as $d) {
                    $detailMap[$nk->peserta_didik_id][$nk->periode_nilai_id][$d->aspek_kepribadian_id] = $d->kriteria;
                }
            }
        }

        $ttdKanan = Penandatangan::getPenandatangan('kepribadian', $angkatan?->skadik_id);
        $ttdKiri = Penandatangan::getPenandatangan('danskadik', $angkatan?->skadik_id);

        return view('report.cetak-npk', compact('angkatan', 'pesertaList', 'periodes', 'aspekList', 'detailMap', 'ttdKanan', 'ttdKiri'));
    }

    // ── Ekspor Report NPK ───────────────────────────────────
    // Revisi 18 September 2026: struktur Excel disamakan dengan tabel PREVIEW
    // aplikasi & PDF cetak NPK:
    //  - Sheet "Report NPK": No, Rank, Nama, Pangkat, NRP, nilai per periode,
    //    Rata-rata + baris "Rata-rata Angkatan" (spt footer preview).
    //  - Sheet "Detail Parameter": kriteria aspek kepribadian per peserta ×
    //    periode (spt Tabel 2 pada PDF) lengkap dgn warna BS/B/C/K/KS.
    //  - Daftar periode dibangun dari DATA yang ada (termasuk periode yatim),
    //    identik dengan reportNPK (preview) — bukan hanya dari tabel periode.
    public function eksporNPK(Request $request)
    {
        try {
            $angkatanId  = $request->get('angkatan_id');
            $angkatan    = Angkatan::with('skadik.lemdik')->find($angkatanId);
            $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();

            // Muat semua nilai kepribadian sekali (eager periode + detail aspek)
            $semuaNilai = NilaiKepribadian::with('periode', 'detail')
                ->whereIn('peserta_didik_id', $pesertaList->pluck('id'))
                ->get();

            // Daftar periode dari DATA yang ada — identik dengan preview
            $periodes = $semuaNilai->pluck('periode')->filter()->unique('id')
                ->sortBy('tanggal_mulai')->values();
            $nPer = $periodes->count();

            // Index nilai per peserta × periode (hindari query N+1)
            $nilaiIndex = [];
            foreach ($semuaNilai as $nk) {
                $nilaiIndex[$nk->peserta_didik_id][$nk->periode_nilai_id] = $nk;
            }

            $spreadsheet = new Spreadsheet();
            ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
            $colName = fn(int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $colRata = $colName(6 + $nPer); // setelah No, Rank, Nama, Pangkat, NRP + n periode

            // ===================== SHEET 1: REKAP NPK =====================
            $sh = $spreadsheet->getActiveSheet()->setTitle('Report NPK');

            $sh->mergeCells("A1:{$colRata}1");
            $sh->setCellValue('A1', 'REPORT NILAI PRESTASI KEPRIBADIAN (NPK) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
            $sh->getStyle('A1')->applyFromArray([
                'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'000000']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $this->infoPenandatanganExcel($sh, 'A2', 'kepribadian', $angkatan?->skadik_id);

            // Header — urutan kolom sama seperti preview (Rank, Nama, Pangkat, NRP, …)
            // Revisi 2 Oktober 2026: kolom acuan = AKUMULATIF (Σ periode), bukan rata-rata.
            $headers = array_merge(
                ['No', 'Rank', 'Nama', 'Pangkat', 'NRP'],
                $periodes->map(fn($p) => strtoupper($p->label))->all(),
                ['Akumulatif']
            );
            foreach ($headers as $col => $h) {
                $sh->setCellValue($colName($col + 1) . '4', $h);
            }
            $sh->getStyle("A4:{$colRata}4")->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'000000']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'wrapText'=>true],
            ]);

            $sh->getColumnDimension('A')->setWidth(6);
            $sh->getColumnDimension('B')->setWidth(6);
            $sh->getColumnDimension('C')->setWidth(28);
            $sh->getColumnDimension('D')->setWidth(14);
            $sh->getColumnDimension('E')->setWidth(18);
            for ($i = 0; $i < $nPer; $i++) $sh->getColumnDimension($colName(6 + $i))->setWidth(14);
            $sh->getColumnDimension($colRata)->setWidth(12);

            // Data — urut AKUMULATIF (Σ periode) desc (yang belum dinilai di akhir), spt preview
            // Revisi 2 Oktober 2026: pengganti rata-rata sebagai acuan ranking.
            $sorted = [];
            foreach ($pesertaList as $p) {
                $vals = []; $total = 0; $cnt = 0;
                foreach ($periodes as $per) {
                    $nk = $nilaiIndex[$p->id][$per->id] ?? null;
                    $v = $nk ? $nk->nilai_akhir : null;
                    $vals[] = $v;
                    if ($v !== null) { $total += $v; $cnt++; }
                }
                $sorted[] = ['peserta' => $p, 'vals' => $vals, 'akum' => $cnt > 0 ? round($total, 2) : null];
            }
            usort($sorted, fn($a, $b) => ($b['akum'] ?? -999) <=> ($a['akum'] ?? -999));

            foreach ($sorted as $i => $s) {
                $r = $i + 5;
                $sh->setCellValue("A{$r}", $i + 1);
                $sh->setCellValue("B{$r}", $i + 1);
                $sh->setCellValue("C{$r}", $s['peserta']->nama);
                $sh->setCellValue("D{$r}", $s['peserta']->pangkat);
                ExportFile::setText($sh, "E{$r}", $s['peserta']->nrp);
                foreach ($s['vals'] as $j => $v) {
                    $sh->setCellValue($colName(6 + $j) . "{$r}", $v ?? '-');
                    $sh->getStyle($colName(6 + $j) . "{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $sh->setCellValue("{$colRata}{$r}", $s['akum'] ?? '-');
                $sh->getStyle("{$colRata}{$r}")->applyFromArray([
                    'font'=>['bold'=>true,'color'=>['rgb'=>ExportFile::TEXT_BLACK]],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
                ]);
            }

            // Baris footer "Akumulatif Angkatan" — Revisi 2 Oktober 2026: SUM
            // (Σ) seluruh peserta per periode + total akumulatif, konsisten dgn preview.
            if (count($sorted) > 0) {
                $r = count($sorted) + 5;
                $sh->mergeCells("A{$r}:E{$r}");
                $sh->setCellValue("A{$r}", 'Akumulatif Angkatan');
                $sh->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                foreach ($periodes as $j => $per) {
                    $sumPer = collect($sorted)->map(fn($s) => $s['vals'][$j] ?? null)->filter()->sum();
                    $sh->setCellValue($colName(6 + $j) . "{$r}", $sumPer > 0 ? round($sumPer, 2) : '-');
                    $sh->getStyle($colName(6 + $j) . "{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $sumAll = collect($sorted)->map(fn($s) => $s['akum'])->filter()->sum();
                $sh->setCellValue("{$colRata}{$r}", $sumAll > 0 ? round($sumAll, 2) : '-');
                $sh->getStyle("{$colRata}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("A{$r}:{$colRata}{$r}")->applyFromArray([
                    'font'=>['bold'=>true,'color'=>['rgb'=>ExportFile::TEXT_BLACK]],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                ]);
            }

            // ============ SHEET 2: DETAIL PARAMETER (spt Tabel 2 PDF) ============
            \App\Models\AspekKepribadian::ensureSeeded();
            $aspekList = \App\Models\AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();

            if ($aspekList->isNotEmpty() && $nPer > 0) {
                $sh2 = $spreadsheet->createSheet()->setTitle('Detail Parameter');
                $nAspek = $aspekList->count();
                $lastCol2 = $colName(2 + ($nAspek + 1) * $nPer);

                $sh2->mergeCells("A1:{$lastCol2}1");
                $sh2->setCellValue('A1', 'TABEL DETAIL PARAMETER NILAI KEPRIBADIAN — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
                $sh2->getStyle('A1')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>12,'color'=>['rgb'=>'000000']],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
                ]);
                $sh2->mergeCells("A2:{$lastCol2}2");
                $sh2->setCellValue('A2', 'Keterangan: BS = Baik Sekali (+0,5) · B = Baik (+0,25) · C = Cukup (0) · K = Kurang (-0,25) · KS = Kurang Sekali (-0,5) | Nilai Akhir = 75 + Σ poin');
                $sh2->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

                // Header: No, Nama, lalu per periode → per aspek (vertikal) + kolom AKHIR
                $sh2->setCellValue('A4', 'No');
                $sh2->setCellValue('B4', 'Nama');
                $maxLenAspek = 0;
                foreach ($periodes as $pi => $per) {
                    foreach ($aspekList as $ai => $asp) {
                        $c = $colName(3 + $pi * ($nAspek + 1) + $ai);
                        $sh2->setCellValue("{$c}4", strtoupper($asp->nama) . ' (' . $per->label . ')');
                        $sh2->getStyle("{$c}4")->applyFromArray([
                            'alignment'=>['textRotation'=>90,'horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_BOTTOM],
                        ]);
                        $sh2->getStyle("{$c}4")->getFont()->setSize(7);
                        $maxLenAspek = max($maxLenAspek, mb_strlen($asp->nama . ' (' . $per->label . ')'));
                    }
                    $cAkhir = $colName(3 + $pi * ($nAspek + 1) + $nAspek);
                    $sh2->setCellValue("{$cAkhir}4", strtoupper($per->label) . ' AKHIR');
                }
                $sh2->getStyle("A4:{$lastCol2}4")->applyFromArray([
                    'font'=>['bold'=>true,'color'=>['rgb'=>'000000']],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                $sh2->getRowDimension(4)->setRowHeight(min(230, max(90, $maxLenAspek * 5 + 12)));

                $sh2->getColumnDimension('A')->setWidth(6);
                $sh2->getColumnDimension('B')->setWidth(28);
                for ($i = 2; $i < 2 + ($nAspek + 1) * $nPer; $i++) {
                    $sh2->getColumnDimension($colName($i + 1))->setWidth(5);
                }

                // Kriteria — tabel polos: background putih, teks hitam
                foreach ($pesertaList->sortBy('nama') as $pi => $p) {  // urut nama, spt PDF
                    $r = $pi + 5;
                    $sh2->setCellValue("A{$r}", $pi + 1);
                    $sh2->setCellValue("B{$r}", $p->nama);
                    foreach ($periodes as $peri => $per) {
                        $nk = $nilaiIndex[$p->id][$per->id] ?? null;
                        $det = $nk ? collect($nk->detail)->keyBy('aspek_kepribadian_id') : collect();
                        foreach ($aspekList as $ai => $asp) {
                            $c = $colName(3 + $peri * ($nAspek + 1) + $ai);
                            $kriteria = $det->get($asp->id)?->kriteria ?? '';
                            $sh2->setCellValue("{$c}{$r}", $kriteria ?: '-');
                            $styleK = [
                                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
                                'font'=>['size'=>9],
                            ];
                            $sh2->getStyle("{$c}{$r}")->applyFromArray($styleK);
                        }
                        $cAkhir = $colName(3 + $peri * ($nAspek + 1) + $nAspek);
                        $sh2->setCellValue("{$cAkhir}{$r}", $nk ? round($nk->nilai_akhir, 2) : '-');
                        $sh2->getStyle("{$cAkhir}{$r}")->applyFromArray([
                            'font'=>['bold'=>true],
                            'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                            'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
                        ]);
                    }
                }
            }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(fn() => $writer->save('php://output'), ExportFile::name($angkatan, 'Report NPK'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengekspor NPK: ' . $e->getMessage());
        }
    }

    // ── Ekspor Report NPS ───────────────────────────────────
    // Revisi 18 September 2026: struktur Excel disamakan dengan tabel PREVIEW
    // aplikasi & PDF cetak NPS:
    //  - kolom "Nilai Konversi (NPS)" + "Kategori" (kategori dihitung dari
    //    nilai konversi, bukan nilai akhir)
    //  - daftar BERBASIS PESERTA (belum dinilai tetap tampil), urut nilai akhir desc
    //  - baris "Rata-rata Angkatan" dihitung dari NILAI KONVERSI (spt PDF)
    public function eksporNPS(Request $request)
    {
        try {
            $angkatanId   = $request->get('angkatan_id');
            $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));
            $angkatan     = Angkatan::with('skadik.lemdik')->find($angkatanId);

            // Data berbasis peserta — identik dengan reportNPS (preview)
            $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
            $nilaiMap = NilaiSamapta::where('angkatan_id', $angkatanId)
                ->where('putaran_label', $putaranLabel)
                ->get()->keyBy('peserta_didik_id');
            $data = $pesertaList->map(function ($p) use ($nilaiMap) {
                $ns = $nilaiMap->get($p->id);
                if ($ns) { $ns->setRelation('peserta', $p); return $ns; }
                return (object)[
                    'peserta' => $p, 'peserta_didik_id' => $p->id,
                    'nilai_akhir' => null, 'jarak_lari' => null, 'nilai_lari' => null,
                    'garjas_b_nilai' => null, 'nilai_konversi' => null,
                ];
            })->sortByDesc(fn($d) => $d->nilai_akhir ?? -1)->values();

            $spreadsheet = new Spreadsheet();
            ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
            $sh = $spreadsheet->getActiveSheet()->setTitle('Report NPS');

            $sh->mergeCells('A1:K1');
            $sh->setCellValue('A1', 'REPORT NILAI PRESTASI SAMAPTA (NPS) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan . ' — ' . $putaranLabel);
            $sh->getStyle('A1')->applyFromArray([
                'font'=>['bold'=>true,'size'=>13,'color'=>['rgb'=>'000000']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $this->infoPenandatanganExcel($sh, 'A2', 'samapta', $angkatan?->skadik_id);

            // Header — sama seperti preview/PDF (label kolom "Kategori",
            // bukan "Predikat")
            $headers = ['No','Rank','Nama','Pangkat','NRP','Jarak Lari (m)','Nilai Lari (Garjas A)','Garjas B','Nilai Akhir','Nilai Konversi (NPS)','Kategori'];
            foreach ($headers as $col => $h) {
                $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col+1);
                $sh->setCellValue($c.'4', $h);
            }
            $sh->getStyle('A4:K4')->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'000000']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'wrapText'=>true],
            ]);
            $widths = [6,6,32,14,16,14,18,12,12,14,16];
            foreach ($widths as $i => $w) {
                $sh->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1))->setWidth($w);
            }

            $totalNPS = 0;
            $countNPS = 0;
            foreach ($data as $i => $d) {
                $r = $i+5;
                // Guard: objek placeholder (peserta belum dinilai) tidak punya
                // properti "predikat" — sama seperti guard di preview.
                $predikat = isset($d->predikat) ? $d->predikat : ['label' => '-'];
                $nps = $d->nilai_konversi;
                if ($nps !== null && (float) $nps > 0) { $totalNPS += (float) $nps; $countNPS++; }

                $sh->setCellValue("A{$r}", $i+1);
                $sh->setCellValue("B{$r}", $i+1);
                $sh->setCellValue("C{$r}", $d->peserta->nama);
                $sh->setCellValue("D{$r}", $d->peserta->pangkat);
                ExportFile::setText($sh, "E{$r}", $d->peserta->nrp);
                $sh->setCellValue("F{$r}", $d->jarak_lari ?? '-');
                $sh->setCellValue("G{$r}", $d->nilai_lari ?? '-');
                $sh->setCellValue("H{$r}", $d->garjas_b_nilai ?? '-');
                $sh->setCellValue("I{$r}", $d->nilai_akhir ?? '-');
                $sh->setCellValue("J{$r}", $d->nilai_konversi ?? '-');
                $sh->setCellValue("K{$r}", ($d->nilai_konversi !== null && (float) $d->nilai_konversi > 0) ? $predikat['label'] : '-');

                foreach (['F','G','H','I','J','K'] as $cc) {
                    $sh->getStyle("{$cc}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $sh->getStyle("I{$r}")->getFont()->setBold(true);
                $sh->getStyle("J{$r}")->getFont()->setBold(true)->getColor()->setRGB(ExportFile::TEXT_BLACK);
            }

            // Baris "Rata-rata Angkatan" — dihitung dari NILAI KONVERSI
            // (Revisi 17 Sept 2026 pada PDF, kini disamakan di Excel)
            if ($countNPS > 0) {
                $r = $data->count() + 5;
                $sh->mergeCells("A{$r}:I{$r}");
                $sh->setCellValue("A{$r}", 'Rata-rata Angkatan');
                $sh->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sh->setCellValue("J{$r}", round($totalNPS / $countNPS, 2));
                $sh->getStyle("J{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("A{$r}:K{$r}")->applyFromArray([
                    'font'=>['bold'=>true],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFFFF']],
                ]);
            }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(fn() => $writer->save('php://output'), ExportFile::name($angkatan, 'Report NPS', $putaranLabel));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengekspor NPS: ' . $e->getMessage());
        }
    }

    // ── Ekspor Report NPP (Angkatan) ────────────────────────
    // Revisi 18 September 2026: isi Excel disamakan dengan "Cetak NPP
    // Angkatan" (laporan.cetak.semua / cetak-semua.blade.php):
    //  - data LIVE dari NppCalculator (SEMUA peserta, NPS dari NILAI
    //    KONVERSI) — bukan record kompilasi lama yang stale
    //  - kop surat WINGDIK/SKADIK/LEMDIK + judul rekap + baris bobot
    //  - header 2 baris dgn grup "Komponen Nilai" (Akademik/Kepribadian/
    //    Samapta) + kolom Predikat "huruf (angka)" + Keterangan
    //  - warna baris rank 1–3 + footer "Rata-rata Angkatan"
    public function eksporNPP(Request $request)
    {
        try {
            $angkatanId = $request->get('angkatan_id');
            $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

            // Revisi 30 Sept 2026: ikuti pilihan sumber NPS/NPK dari halaman
            // Report NPP (default = putaran/periode terakhir).
            $npsPutaran = NppCalculator::normalizeSumber($request->get('nps_putaran'));
            $npkPeriode = NppCalculator::normalizeSumber($request->get('npk_periode'));

            // Data live — sama seperti LaporanController::cetakSemua (PDF)
            $data = NppCalculator::forAngkatan($angkatanId, $npsPutaran, $npkPeriode);
            $first = $data->first();

            $spreadsheet = new Spreadsheet();
            ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
            $sh = $spreadsheet->getActiveSheet()->setTitle('Report NPP');

            $lastCol = 'J'; // Rank..Keterangan (10 kolom)

            // ── Kop surat (sama seperti PDF cetak NPP angkatan) ──
            $wingdik   = strtoupper($angkatan?->skadik?->lemdik?->wingdik ?? 'WINGDIK');
            $skadikNama = strtoupper($angkatan?->skadik?->nama_singkat ?? ($angkatan?->skadik?->nama ?? 'SEKOLAH'));
            $lemdikNama = strtoupper($angkatan?->skadik?->lemdik?->nama ?? '');

            $sh->mergeCells("A1:{$lastCol}1");
            $sh->setCellValue('A1', $wingdik);
            $sh->getStyle('A1')->applyFromArray([
                'font'=>['bold'=>true,'size'=>16],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);
            $sh->mergeCells("A2:{$lastCol}2");
            $sh->setCellValue('A2', $skadikNama);
            $sh->getStyle('A2')->applyFromArray([
                'font'=>['bold'=>true,'size'=>13],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);
            if ($lemdikNama && $lemdikNama !== $skadikNama) {
                $sh->mergeCells("A3:{$lastCol}3");
                $sh->setCellValue('A3', $lemdikNama);
                $sh->getStyle('A3')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>11],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
                ]);
            }

            // ── Judul rekap + info angkatan & bobot ──
            $rTitle = 4;
            $sh->mergeCells("A{$rTitle}:{$lastCol}{$rTitle}");
            $sh->setCellValue("A{$rTitle}", 'REKAP NILAI PRESTASI PENDIDIKAN (NPP)');
            $sh->getStyle("A{$rTitle}")->applyFromArray([
                'font'=>['bold'=>true,'size'=>12,'underline'=>true],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $sub = '';
            if ($angkatan?->jurusan) $sub .= strtoupper($angkatan->jurusan) . ' — ';
            $sub .= 'Angkatan ' . $angkatan?->nomor_angkatan . ' Tahun ' . $angkatan?->tahun_masuk;
            $sh->mergeCells("A5:{$lastCol}5");
            $sh->setCellValue('A5', $sub);
            $sh->getStyle('A5')->applyFromArray([
                'font'=>['size'=>11],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $bA = $first->bobot_akademik ?? NppCalculator::BOBOT_AKADEMIK_DEFAULT;
            $bK = $first->bobot_kepribadian ?? NppCalculator::BOBOT_KEPRIBADIAN_DEFAULT;
            $bS = $first->bobot_samapta ?? NppCalculator::BOBOT_SAMAPTA_DEFAULT;
            $sh->mergeCells("A6:{$lastCol}6");
            // Revisi 30 Sept 2026: info sumber NPS (putaran) & NPK (periode) ditambah
            // di baris bobot agar Excel jelas sumber nilainya.
            $infoSumber = $first && ($first->sumber_nps || $first->sumber_npk)
                ? " — Sumber NPS: {$first->sumber_nps} · NPK: {$first->sumber_npk}"
                : '';
            $sh->setCellValue('A6', "Bobot: Akademik {$bA}% · Kepribadian {$bK}% · Samapta {$bS}%{$infoSumber}");
            $sh->getStyle('A6')->applyFromArray([
                'font'=>['size'=>10,'italic'=>true,'color'=>['rgb'=>'000000']],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            // Info penandatangan (kiri = Danskadik, kanan = Kompilasi — spt PDF)
            $sh->mergeCells("A7:{$lastCol}7");
            $this->infoPenandatanganExcel($sh, 'A7', 'kompilasi', $angkatan?->skadik_id, 9);

            // ── Header 2 baris (spt tabel PDF) ──
            $styleHead = [
                'font'=>['bold'=>true],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFFFF']],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
            ];
            foreach (['A'=>'Rank','B'=>'Nama','C'=>'Pangkat','D'=>'NRP','H'=>'NPP','I'=>'Predikat','J'=>'Keterangan'] as $c => $h) {
                $sh->mergeCells("{$c}8:{$c}9");
                $sh->setCellValue("{$c}8", $h);
            }
            $sh->mergeCells('E8:G8');
            $sh->setCellValue('E8', 'Komponen Nilai');
            $sh->setCellValue('E9', 'Akademik');
            $sh->setCellValue('F9', 'Kepribadian');
            $sh->setCellValue('G9', 'Samapta');
            $sh->getStyle("A8:{$lastCol}9")->applyFromArray($styleHead);

            // Lebar kolom
            foreach (['A'=>8,'B'=>32,'C'=>12,'D'=>14,'E'=>13,'F'=>14,'G'=>12,'H'=>10,'I'=>14,'J'=>16] as $c => $w) {
                $sh->getColumnDimension($c)->setWidth($w);
            }

            // ── Data (live) ──
            $ket = fn($n) => $n >= 85 ? 'Sangat Baik' : ($n >= 75 ? 'Baik' : ($n >= 65 ? 'Cukup' : ($n >= 55 ? 'Kurang' : 'Sangat Kurang')));
            $totalNPP = 0; $countNPP = 0;
            // Revisi 22 Sept 2026: akumulasi per komponen (NPA/NPK/NPS) untuk
            // footer "Rata-rata Angkatan" — hanya nilai > 0 yang dihitung.
            $sumKomponen = ['E' => 0, 'F' => 0, 'G' => 0];
            $cntKomponen = ['E' => 0, 'F' => 0, 'G' => 0];
            $fieldKomponen = ['E' => 'nilai_akademik', 'F' => 'nilai_kepribadian', 'G' => 'nilai_samapta'];
            foreach ($data as $i => $d) {
                $r = $i + 10;
                $npp = $d->nilai_akhir ?? 0;
                $totalNPP += $npp;
                if ($npp > 0) $countNPP++;

                foreach ($fieldKomponen as $cc => $f) {
                    $v = (float) ($d->{$f} ?? 0);
                    if ($v > 0) { $sumKomponen[$cc] += $v; $cntKomponen[$cc]++; }
                }

                $sh->setCellValue("A{$r}", $d->rank);
                $sh->setCellValue("B{$r}", $d->peserta->nama);
                $sh->setCellValue("C{$r}", $d->peserta->pangkat);
                ExportFile::setText($sh, "D{$r}", $d->peserta->nrp);
                $sh->setCellValue("E{$r}", $d->nilai_akademik);
                $sh->setCellValue("F{$r}", $d->nilai_kepribadian);
                $sh->setCellValue("G{$r}", $d->nilai_samapta);
                $sh->setCellValue("H{$r}", $d->nilai_akhir);
                $sh->setCellValue("I{$r}", $d->predikat_huruf . ' (' . $d->predikat_angka . ')');
                $sh->setCellValue("J{$r}", $ket($npp));

                $sh->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
                    'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                foreach (['A','E','F','G','H','I','J'] as $cc) {
                    $sh->getStyle("{$cc}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $sh->getStyle("H{$r}")->getFont()->setBold(true);
            }

            // ── Footer "Rata-rata Angkatan" (spt PDF) ──
            // Revisi 22 Sept 2026: kini memuat rata-rata PER KOMPONEN
            // (Akademik/NPA · Kepribadian/NPK · Samapta/NPS) + NPP.
            if ($countNPP > 0) {
                $r = $data->count() + 10;
                $sh->mergeCells("A{$r}:D{$r}");
                $sh->setCellValue("A{$r}", 'Rata-rata Angkatan');
                $sh->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                foreach (['E', 'F', 'G'] as $cc) {
                    $sh->setCellValue("{$cc}{$r}", $cntKomponen[$cc] > 0 ? round($sumKomponen[$cc] / $cntKomponen[$cc], 2) : '-');
                    $sh->getStyle("{$cc}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $sh->setCellValue("H{$r}", round($totalNPP / $countNPP, 2));
                $sh->getStyle("H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                    'font'=>['bold'=>true],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFFFF']],
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
                ]);
            }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(fn() => $writer->save('php://output'), ExportFile::name($angkatan, 'Report NPP'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengekspor NPP: ' . $e->getMessage());
        }
    }
}