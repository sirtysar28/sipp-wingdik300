<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, NilaiAkademik, NilaiSamapta, NilaiKepribadian, KompilasiNilai, PeriodeNilai, Skadik};
use App\Services\ExportFile;
use App\Services\NppCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KompilasiNilaiController extends Controller
{
    public function index(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $angkatanQuery = Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc');
        if ($skadikId) $angkatanQuery->where('skadik_id', $skadikId);
        $allAngkatan = $angkatanQuery->get();
        $angkatanId  = $request->get('angkatan_id', $allAngkatan->first()?->id);

        // Revisi 30 Sept 2026: daftar pilihan sumber NPS (putaran) & NPK (periode)
        // untuk dropdown "Sumber Nilai" pada form Proses Kompilasi.
        $putaranList = $angkatanId ? NilaiSamapta::getPutaranList($angkatanId) : [];
        $periodeList = $angkatanId
            ? PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get()
            : collect();

        $angkatan = Angkatan::with('skadik.lemdik')->find($angkatanId);
        if (!$angkatan) {
            return view('kompilasi.index', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'putaranList', 'periodeList') + ['angkatan' => null, 'data' => collect(), 'stats' => []]);
        }

        $data = KompilasiNilai::with('peserta', 'nilaiAkademik')
            ->where('angkatan_id', $angkatanId)
            ->orderBy('rank')
            ->get();

        // Stats
        $stats = [
            'total_peserta' => PesertaDidik::where('angkatan_id', $angkatanId)->count(),
            'sudah_kompilasi' => $data->count(),
            'belum_akademik' => PesertaDidik::where('angkatan_id', $angkatanId)
                ->whereDoesntHave('nilaiAkademik', fn($q) => $q->where('angkatan_id', $angkatanId))->count(),
            'belum_samapta' => PesertaDidik::where('angkatan_id', $angkatanId)
                ->whereDoesntHave('nilaiSamapta', fn($q) => $q->where('angkatan_id', $angkatanId))->count(),
            'belum_kepribadian' => PesertaDidik::where('angkatan_id', $angkatanId)
                ->whereDoesntHave('nilaiKepribadian')->count(),
            'belum_kepribadian_info' => $this->getBelumKepribadianInfo($angkatanId),
            'rata_akhir' => $data->count() > 0 ? round($data->avg('nilai_akhir'), 2) : 0,
            'tertinggi' => $data->max('nilai_akhir') ?? 0,
            'terendah' => $data->min('nilai_akhir') ?? 0,
        ];

        return view('kompilasi.index', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'putaranList', 'periodeList', 'angkatan', 'data', 'stats'));
    }

    public function proses(Request $request)
    {
        $request->validate([
            'angkatan_id'       => 'required|exists:angkatan,id',
            'bobot_akademik'    => 'required|numeric|min:0|max:100',
            'bobot_kepribadian' => 'required|numeric|min:0|max:100',
            'bobot_samapta'     => 'required|numeric|min:0|max:100',
            // Revisi 30 Sept 2026: pilihan sumber nilai utk NPP.
            //   nps_putaran: '' = putaran TERAKHIR (default), atau label putaran.
            //   npk_periode: '' = periode TERAKHIR (default), atau id periode_nilai.
            'nps_putaran'       => 'nullable|string|max:100',
            'npk_periode'       => 'nullable|integer|exists:periode_nilai,id',
        ]);

        $angkatanId = $request->angkatan_id;
        $ba = $request->bobot_akademik / 100;
        $bk = $request->bobot_kepribadian / 100;
        $bs = $request->bobot_samapta / 100;

        // Validate bobot total = 100%
        $totalBobot = $ba + $bk + $bs;
        if (abs($totalBobot - 1) > 0.01) {
            return back()->withErrors(['Total bobot harus 100% (saat ini ' . round($totalBobot * 100) . '%)']);
        }

        // ── Resolusi sumber NPS & NPK (Revisi 30 Sept 2026) ──
        $npsPutaran = NppCalculator::normalizeSumber($request->nps_putaran); // null = terakhir
        $npkPeriodeId = NppCalculator::normalizeSumber($request->npk_periode);
        $npkPeriodeId = $npkPeriodeId !== null ? (int) $npkPeriodeId : null;

        // Pastikan periode milik angkatan yg dipilih
        if ($npkPeriodeId && !PeriodeNilai::where('id', $npkPeriodeId)->where('angkatan_id', $angkatanId)->exists()) {
            return back()->withErrors(['Periode NPK tidak ditemukan pada angkatan ini.']);
        }

        $sumberNps = $npsPutaran
            ?? NilaiSamapta::putaranTerakhirLabel($angkatanId)
            ?? NilaiSamapta::PUTARAN_DEFAULT;
        $sumberNpkObj = $npkPeriodeId
            ? PeriodeNilai::find($npkPeriodeId)
            : NilaiKepribadian::periodeTerakhir($angkatanId);
        $sumberNpk = $sumberNpkObj?->label ?? '-';

        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
        $pesertaIds  = $pesertaList->pluck('id');

        // Muat SEKALI untuk semua peserta (efisien, tidak per-peserta query).
        // Revisi 30 Sept 2026: NPS = nilai konversi PUTARAN TERAKHIR / pilihan,
        // NPK = nilai PERIODE TERAKHIR / pilihan (bukan rata-rata).
        $samaptaMap     = NilaiSamapta::npsUntukNppPerPeserta($angkatanId, $pesertaIds, $npsPutaran);
        $kepribadianMap = NilaiKepribadian::npkUntukNppPerPeserta($pesertaIds, $npkPeriodeId, $angkatanId);

        $hasil = [];

        DB::beginTransaction();
        try {
            foreach ($pesertaList as $peserta) {
                // Nilai Akademik (NPA) — langsung ambil NPA yang sudah ada
                $na = NilaiAkademik::where('peserta_didik_id', $peserta->id)
                    ->where('angkatan_id', $angkatanId)->first();

                $nilaiAkademik = $na ? round($na->npa, 2) : 0;
                $akademikId = $na?->id;

                // Nilai Kepribadian (NPK) — dari periode TERAKHIR / pilihan
                $nilaiKepribadian = $kepribadianMap[$peserta->id] ?? 0;

                // Nilai Samapta (NPS) — nilai konversi putaran TERAKHIR / pilihan;
                // fallback nilai_akhir utk data lama tanpa konversi (di model).
                $nilaiSamapta = $samaptaMap[$peserta->id] ?? 0;

                // Kompilasi
                $nilaiAkhir = ($nilaiAkademik * $ba) + ($nilaiKepribadian * $bk) + ($nilaiSamapta * $bs);
                $predikat = KompilasiNilai::getPredikat($nilaiAkhir);

                KompilasiNilai::updateOrCreate(
                    ['peserta_didik_id' => $peserta->id, 'angkatan_id' => $angkatanId],
                    [
                        'nilai_akademik_id'  => $akademikId,
                        'nilai_akademik'     => round($nilaiAkademik, 2),
                        'bobot_akademik'     => $request->bobot_akademik,
                        'nilai_kepribadian'  => $nilaiKepribadian,
                        'bobot_kepribadian'  => $request->bobot_kepribadian,
                        'nilai_samapta'      => round($nilaiSamapta, 2),
                        'bobot_samapta'      => $request->bobot_samapta,
                        'sumber_nps'         => $sumberNps,
                        'sumber_npk'         => $sumberNpk,
                        'nilai_akhir'        => round($nilaiAkhir, 2),
                        'predikat_angka'     => $predikat['angka'],
                        'predikat_huruf'     => $predikat['huruf'],
                        'rank'               => 0,
                        'input_oleh'         => auth()->id(),
                    ]
                );

                $hasil[] = [
                    'nama' => $peserta->nama,
                    'akademik' => round($nilaiAkademik, 2),
                    'kepribadian' => $nilaiKepribadian,
                    'samapta' => round($nilaiSamapta, 2),
                    'akhir' => round($nilaiAkhir, 2),
                ];
            }

            // Update ranks
            $allKompilasi = KompilasiNilai::where('angkatan_id', $angkatanId)
                ->orderByDesc('nilai_akhir')->get();
            foreach ($allKompilasi as $idx => $k) {
                $k->update(['rank' => $idx + 1]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['Gagal memproses kompilasi: ' . $e->getMessage()]);
        }

        return redirect()->route('kompilasi.index', ['angkatan_id' => $angkatanId])
            ->with('success', 'Kompilasi nilai berhasil diproses untuk ' . count($hasil) . ' peserta — sumber NPS: "' . $sumberNps . '", sumber NPK: "' . $sumberNpk . '".');
    }

    public function ekspor(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

        $data = KompilasiNilai::with('peserta')
            ->where('angkatan_id', $angkatanId)
            ->orderBy('rank')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('Kompilasi Nilai');

        $sh->mergeCells('A1:I1');
        $sh->setCellValue('A1', 'NILAI PRESTASI PENDIDIKAN (NPP) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $sh->mergeCells('A2:I2');
        $sh->setCellValue('A2', 'A=' . ($data->first()?->bobot_akademik ?? 70) . '% | K=' . ($data->first()?->bobot_kepribadian ?? 20) . '% | S=' . ($data->first()?->bobot_samapta ?? 10) . '% | Dicetak: ' . now()->format('d/m/Y H:i'));
        $sh->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        $headers = ['Rank', 'Nama', 'Pangkat', 'NRP', 'N. Akademik (' . ($data->first()?->bobot_akademik ?? 70) . '%)', 'N. Kepribadian (' . ($data->first()?->bobot_kepribadian ?? 20) . '%)', 'N. Samapta (' . ($data->first()?->bobot_samapta ?? 10) . '%)', 'NPP', 'Predikat'];
        foreach ($headers as $col => $h) {
            $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sh->setCellValue($c . '4', $h);
        }
        $sh->getStyle('A4:I4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);

        $sh->getColumnDimension('A')->setWidth(6);
        $sh->getColumnDimension('B')->setWidth(32);
        $sh->getColumnDimension('C')->setWidth(12);
        $sh->getColumnDimension('D')->setWidth(14);
        $sh->getColumnDimension('E')->setWidth(18);
        $sh->getColumnDimension('F')->setWidth(18);
        $sh->getColumnDimension('G')->setWidth(18);
        $sh->getColumnDimension('H')->setWidth(12);
        $sh->getColumnDimension('I')->setWidth(12);

        $row = 5;
        // Revisi 25 September 2026: akumulasi per komponen (NPA/NPK/NPS) untuk
        // footer "Rata-rata Angkatan" — nilai 0 (belum dinilai) tidak dihitung.
        $totalNPP = $countNPP = 0;
        $sumKomponen = ['E' => 0, 'F' => 0, 'G' => 0];
        $cntKomponen = ['E' => 0, 'F' => 0, 'G' => 0];
        $fieldKomponen = ['E' => 'nilai_akademik', 'F' => 'nilai_kepribadian', 'G' => 'nilai_samapta'];
        foreach ($data as $d) {
            $npp = $d->nilai_akhir ?? 0;
            $totalNPP += $npp;
            if ($npp > 0) $countNPP++;
            foreach ($fieldKomponen as $cc => $f) {
                $v = (float) ($d->{$f} ?? 0);
                if ($v > 0) { $sumKomponen[$cc] += $v; $cntKomponen[$cc]++; }
            }

            $sh->setCellValue("A{$row}", $d->rank);
            $sh->setCellValue("B{$row}", $d->peserta->nama);
            $sh->setCellValue("C{$row}", $d->peserta->pangkat);
            ExportFile::setText($sh, "D{$row}", $d->peserta->nrp);
            $sh->setCellValue("E{$row}", $d->nilai_akademik);
            $sh->setCellValue("F{$row}", $d->nilai_kepribadian);
            $sh->setCellValue("G{$row}", $d->nilai_samapta);
            $sh->setCellValue("H{$row}", $d->nilai_akhir);
            $sh->setCellValue("I{$row}", $d->predikat_huruf);

            // Highlight rank 1-3 — skala abu-abu
            if ($d->rank === 1) {
                $sh->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFFFF');
                $sh->getStyle("H{$row}")->getFont()->setBold(true);
            } elseif ($d->rank === 2) {
                $sh->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFFFF');
            } elseif ($d->rank === 3) {
                $sh->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFFFF');
            }

            $row++;
        }

        // Footer "Rata-rata Angkatan" per komponen (NPA · NPK · NPS) + NPP
        if ($countNPP > 0) {
            $sh->mergeCells("A{$row}:D{$row}");
            $sh->setCellValue("A{$row}", 'Rata-rata Angkatan');
            $sh->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            foreach (['E', 'F', 'G'] as $cc) {
                $sh->setCellValue("{$cc}{$row}", $cntKomponen[$cc] > 0 ? round($sumKomponen[$cc] / $cntKomponen[$cc], 2) : '-');
                $sh->getStyle("{$cc}{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
            $sh->setCellValue("H{$row}", round($totalNPP / $countNPP, 2));
            $sh->getStyle("H{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("A{$row}:I{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            ]);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(function() use ($writer) { $writer->save('php://output'); },
            ExportFile::name($angkatan, 'NPP Kompilasi'));
    }

    /**
     * Info detail: peserta mana saja yang belum punya nilai kepribadian
     */
    private function getBelumKepribadianInfo(int $angkatanId): string
    {
        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)
            ->whereDoesntHave('nilaiKepribadian')
            ->orderBy('nama')
            ->limit(5)
            ->pluck('nama')
            ->join(', ');

        $total = PesertaDidik::where('angkatan_id', $angkatanId)
            ->whereDoesntHave('nilaiKepribadian')->count();

        if ($total === 0) return '';

        $text = "({$total} peserta";
        if ($pesertaList) {
            $text .= ": {$pesertaList}";
        }
        if ($total > 5) {
            $text .= ', dll.';
        }
        $text .= ')';

        return $text;
    }
}
