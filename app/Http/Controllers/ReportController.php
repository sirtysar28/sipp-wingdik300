<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, KompilasiNilai, NilaiAkademik, NilaiKepribadian, NilaiSamapta, PeriodeNilai, Skadik, Penandatangan};
use App\Services\NppCalculator;
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
                })->sortByDesc(fn($d) => $d->npa ?? -1)->values();
            }

            // Autoload mata pelajaran from mata_pelajaran table per sekolah (via pivot)
            $mataPelajaran = collect();
            if ($angkatan && $angkatan->skadik_id) {
                $mataPelajaran = \App\Models\MataPelajaran::forSkadik($angkatan->skadik_id, true);
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
                        'rata_rata'           => $cnt > 0 ? round($total / $cnt, 2) : null,
                    ];
                })->sortByDesc('rata_rata')->values();
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
            $akademik = null; $kepribadianList = null; $kepribadianAvg = null; $samapta = null; $kompilasi = null; $mataPelajaran = collect();

            if ($submitted && $pesertaId) {
                // Pastikan peserta yg diminta memang anggota angkatan terpilih
                $peserta = $pesertaList->first(fn($p) => $p->id == $pesertaId) ?? null;
            }

            if ($peserta) {
                $akademik = NilaiAkademik::where('peserta_didik_id', $pesertaId)->where('angkatan_id', $angkatanId)->first();
                $kepribadianList = NilaiKepribadian::with('periode','detail.aspek')->where('peserta_didik_id', $pesertaId)->orderBy('periode_nilai_id')->get();
                $kepribadianAvg = $kepribadianList->avg('nilai_akhir') ?? 0;
                $samapta = NilaiSamapta::where('peserta_didik_id', $pesertaId)->where('angkatan_id', $angkatanId)->first();
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
                        'nilai_samapta'     => $samapta ? round($samapta->nilai_akhir, 2) : 0,
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
                'akademik','kepribadianList','kepribadianAvg','samapta','kompilasi','mataPelajaran'
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

            if (!$angkatan) {
                return view('report.report-npp', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId') + [
                    'angkatan' => null, 'data' => collect(), 'stats' => []
                ]);
            }

            $data = NppCalculator::forAngkatan($angkatanId); // SEMUA peserta (bulk), dihitung live

            $totalPeserta = PesertaDidik::where('angkatan_id', $angkatanId)->count();
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
                'tertinggi'  => $data->max('nilai_akhir') ?? 0,
                'terendah'   => $data->min('nilai_akhir') ?? 0,
            ];

            return view('report.report-npp', compact(
                'allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'angkatan', 'data', 'stats'
            ));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memuat report NPP: ' . $e->getMessage());
        }
    }

    // ── Ekspor Report NPA ───────────────────────────────────
    public function eksporNPA(Request $request)
    {
        try {
            $angkatanId = $request->get('angkatan_id');
            $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);
            $data = NilaiAkademik::with('peserta')->where('angkatan_id', $angkatanId)->orderBy('npa', 'desc')->get();

            $spreadsheet = new Spreadsheet();
            $sh = $spreadsheet->getActiveSheet()->setTitle('Report NPA');

            $sh->mergeCells('A1:F1');
            $sh->setCellValue('A1', 'REPORT NILAI PRESTASI AKADEMIK (NPA) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
            $sh->getStyle('A1')->applyFromArray([
                'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'059669']],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $penandatangan = Penandatangan::getPenandatangan('akademik', $angkatan?->skadik_id);

            if ($penandatangan) {
                $ttdText = $penandatangan->nama;
                if ($penandatangan->pangkat) $ttdText .= ', ' . $penandatangan->pangkat;
                $ttdText .= ', ' . $penandatangan->nrp . ' — ' . $penandatangan->jabatan;
                $sh->setCellValue('A2', 'Penandatangan: ' . $ttdText);
                $sh->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
            }

            $headers = ['Rank','NRP','Pangkat','Nama','Jumlah Nilai','NPA'];
            foreach ($headers as $col => $h) {
                $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col+1);
                $sh->setCellValue($c.'4', $h);
            }
            $sh->getStyle('A4:F4')->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'10B981']],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $sh->getColumnDimension('A')->setWidth(6);$sh->getColumnDimension('B')->setWidth(14);
            $sh->getColumnDimension('C')->setWidth(12);$sh->getColumnDimension('D')->setWidth(32);
            $sh->getColumnDimension('E')->setWidth(14);$sh->getColumnDimension('F')->setWidth(10);

            foreach ($data as $i => $d) {
                $r = $i + 5;
                $sh->setCellValue("A{$r}", $i+1);$sh->setCellValue("B{$r}", $d->peserta->nrp);
                $sh->setCellValue("C{$r}", $d->peserta->pangkat);$sh->setCellValue("D{$r}", $d->peserta->nama);
                $sh->setCellValue("E{$r}", $d->jumlah_nilai);$sh->setCellValue("F{$r}", $d->npa);
            }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(fn() => $writer->save('php://output'), "Report_NPA_{$angkatan?->nomor_angkatan}.xlsx");
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
            $data = NilaiAkademik::with('peserta')->where('angkatan_id', $angkatanId)->orderBy('npa', 'desc')->get();

            $mataPelajaran = collect();
            if ($angkatan && $angkatan->skadik_id) {
                $mataPelajaran = \App\Models\MataPelajaran::forSkadik($angkatan->skadik_id, true);
            }

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
    public function eksporNPK(Request $request)
    {
        try {
            $angkatanId = $request->get('angkatan_id');
            $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);
            $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
            $periodes = PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get();

            $spreadsheet = new Spreadsheet();
            $sh = $spreadsheet->getActiveSheet()->setTitle('Report NPK');

            $sh->mergeCells('A1:Z1');
            $sh->setCellValue('A1', 'REPORT NILAI PRESTASI KEPRIBADIAN (NPK) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
            $sh->getStyle('A1')->applyFromArray([
                'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'2563EB']],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $penandatangan = Penandatangan::getPenandatangan('kepribadian', $angkatan?->skadik_id);
            if ($penandatangan) {
                $ttdText = $penandatangan->nama;
                if ($penandatangan->pangkat) $ttdText .= ', ' . $penandatangan->pangkat;
                $ttdText .= ', ' . $penandatangan->nrp . ' — ' . $penandatangan->jabatan;
                $sh->setCellValue('A2', 'Penandatangan: ' . $ttdText);
                $sh->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
            }

            $nPer = $periodes->count();
            $colRata = chr(65 + 3 + $nPer);
            $colRnkg = chr(65 + 4 + $nPer);

            $sh->setCellValue('A4', 'NO');$sh->setCellValue('B4', 'NAMA');$sh->setCellValue('C4', 'NRP');
            foreach ($periodes as $i => $per) {
                $col = chr(68 + $i);
                $sh->setCellValue($col.'4', strtoupper($per->label));
                $sh->getStyle($col.'4')->applyFromArray(['alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'wrapText'=>true]]);
            }
            $sh->setCellValue("{$colRata}4", 'RATA-RATA');
            $sh->setCellValue("{$colRnkg}4", 'RNKG');

            $styleH = ['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'3B82F6']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]];
            $sh->getStyle('A4:'.$colRnkg.'4')->applyFromArray($styleH);

            $sh->getColumnDimension('A')->setWidth(6);$sh->getColumnDimension('B')->setWidth(28);$sh->getColumnDimension('C')->setWidth(18);

            $sorted = [];
            foreach ($pesertaList as $p) {
                $vals = []; $total = 0; $cnt = 0;
                foreach ($periodes as $per) {
                    $nk = NilaiKepribadian::where('peserta_didik_id', $p->id)->where('periode_nilai_id', $per->id)->first();
                    $v = $nk ? $nk->nilai_akhir : null;
                    $vals[] = $v;
                    if ($v !== null) { $total += $v; $cnt++; }
                }
                $sorted[] = ['peserta' => $p, 'vals' => $vals, 'rata' => $cnt > 0 ? round($total/$cnt, 2) : null];
            }
            usort($sorted, fn($a, $b) => ($b['rata'] ?? -999) <=> ($a['rata'] ?? -999));

            foreach ($sorted as $i => $s) {
                $r = $i + 5;
                $sh->setCellValue("A{$r}", $i+1);$sh->setCellValue("B{$r}", $s['peserta']->nama);
                $sh->setCellValue("C{$r}", $s['peserta']->nrp);
                foreach ($s['vals'] as $j => $v) {
                    $col = chr(68 + $j);
                    $sh->setCellValue("{$col}{$r}", $v ?? '');
                }
                $sh->setCellValue("{$colRata}{$r}", $s['rata']);
                $sh->setCellValue("{$colRnkg}{$r}", $i+1);
            }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(fn() => $writer->save('php://output'), "Report_NPK_{$angkatan?->nomor_angkatan}.xlsx");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengekspor NPK: ' . $e->getMessage());
        }
    }

    // ── Ekspor Report NPS ───────────────────────────────────
    public function eksporNPS(Request $request)
    {
        try {
            $angkatanId   = $request->get('angkatan_id');
            $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));
            $angkatan     = Angkatan::with('skadik.lemdik')->find($angkatanId);
            $data = NilaiSamapta::with('peserta')
                ->where('angkatan_id', $angkatanId)
                ->where('putaran_label', $putaranLabel)
                ->orderBy('nilai_akhir', 'desc')->get();

            $spreadsheet = new Spreadsheet();
            $sh = $spreadsheet->getActiveSheet()->setTitle('Report NPS');

            $sh->mergeCells('A1:J1');
            $sh->setCellValue('A1', 'REPORT NILAI PRESTASI SAMAPTA (NPS) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan . ' — ' . $putaranLabel);
            $sh->getStyle('A1')->applyFromArray([
                'font'=>['bold'=>true,'size'=>13,'color'=>['rgb'=>'FFFFFF']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EA580C']],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);

            $penandatangan = Penandatangan::getPenandatangan('samapta', $angkatan?->skadik_id);
            if ($penandatangan) {
                $ttdText = $penandatangan->nama;
                if ($penandatangan->pangkat) $ttdText .= ', ' . $penandatangan->pangkat;
                $ttdText .= ', ' . $penandatangan->nrp . ' — ' . $penandatangan->jabatan;
                $sh->setCellValue('A2', 'Penandatangan: ' . $ttdText);
                $sh->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
            }

            $headers = ['Rank','NRP','Pangkat','Nama','Jarak Lari (m)','Nilai Lari (Garjas A)','Garjas B','Nilai Akhir','Nilai Konversi','Predikat'];
            foreach ($headers as $col => $h) {
                $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col+1);
                $sh->setCellValue($c.'4', $h);
            }
            $sh->getStyle('A4:J4')->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F97316']],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'wrapText'=>true],
            ]);
            $widths = [6,16,14,32,14,18,12,12,14,14];
            foreach ($widths as $i => $w) {
                $sh->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1))->setWidth($w);
            }

            foreach ($data as $i => $d) {
                $r = $i+5;
                $sh->setCellValue("A{$r}",$i+1);$sh->setCellValue("B{$r}",$d->peserta->nrp);
                $sh->setCellValue("C{$r}",$d->peserta->pangkat);$sh->setCellValue("D{$r}",$d->peserta->nama);
                $sh->setCellValue("E{$r}",$d->jarak_lari);
                $sh->setCellValue("F{$r}",$d->nilai_lari);
                $sh->setCellValue("G{$r}",$d->garjas_b_nilai);
                $sh->setCellValue("H{$r}",$d->nilai_akhir);
                $sh->setCellValue("I{$r}",$d->nilai_konversi);
                $sh->setCellValue("J{$r}",$d->predikat['label']);
            }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(fn() => $writer->save('php://output'), "Report_NPS_{$angkatan?->nomor_angkatan}_{$putaranLabel}.xlsx");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengekspor NPS: ' . $e->getMessage());
        }
    }
}
