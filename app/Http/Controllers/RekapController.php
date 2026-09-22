<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, PeriodeNilai, NilaiKepribadian, AspekKepribadian, Skadik};
use App\Services\ExportFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class RekapController extends Controller
{
    public function index(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $angkatanQuery = Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc');
        if ($skadikId) {
            $angkatanQuery->where('skadik_id', $skadikId);
        }
        $allAngkatan = $angkatanQuery->get();
        $angkatanId  = $request->get('angkatan_id', $allAngkatan->first()?->id);

        $angkatan    = Angkatan::with('skadik.lemdik')->find($angkatanId);
        $allPeriode  = PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get();
        $allPeserta  = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
        AspekKepribadian::ensureSeeded();
        $aspekList   = AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();

        // Filter periode range
        $periodeFrom = $request->get('periode_from', $allPeriode->first()?->id);
        $periodeTo   = $request->get('periode_to',   $allPeriode->last()?->id);

        // Filter peserta (bisa pilih semua atau individu)
        $pesertaId   = $request->get('peserta_id', 'semua');

        // Periode yang masuk range
        $periodeRange = $allPeriode->filter(function ($p) use ($periodeFrom, $periodeTo) {
            return $p->id >= $periodeFrom && $p->id <= $periodeTo;
        })->values();

        // Query nilai kepribadian
        $nilaiQuery = NilaiKepribadian::with(['peserta', 'periode', 'detail.aspek'])
            ->whereIn('periode_nilai_id', $periodeRange->pluck('id'))
            ->whereHas('peserta', fn($q) => $q->where('angkatan_id', $angkatanId));

        if ($pesertaId !== 'semua') {
            $nilaiQuery->where('peserta_didik_id', $pesertaId);
        }

        $semuaNilai = $nilaiQuery->get();

        // ── Data tabel histori ────────────────────────────────
        $pesertaList = $pesertaId === 'semua'
            ? $allPeserta
            : $allPeserta->where('id', $pesertaId);

        $tabelData = $pesertaList->map(function ($p) use ($periodeRange, $semuaNilai) {
            $baris = ['peserta' => $p, 'nilai' => []];
            foreach ($periodeRange as $per) {
                $n = $semuaNilai->first(fn($n) => $n->peserta_didik_id == $p->id && $n->periode_nilai_id == $per->id);
                $baris['nilai'][$per->id] = $n;
            }
            $baris['rata'] = round(
                $semuaNilai->where('peserta_didik_id', $p->id)->avg('nilai_akhir') ?? 0, 2
            );
            return $baris;
        })->sortByDesc('rata')->values();

        // ── Data grafik rata-rata angkatan per periode ────────
        $grafikAngkatan = $periodeRange->map(function ($per) use ($semuaNilai, $aspekList) {
            $nilaiPer = $semuaNilai->where('periode_nilai_id', $per->id);
            $data = [
                'label' => $per->label,
                'total' => round($nilaiPer->avg('nilai_akhir') ?? 0, 2),
                'aspek' => [],
            ];
            // Rata-rata poin per aspek
            foreach ($aspekList as $asp) {
                $poin = $nilaiPer->flatMap(fn($nk) => $nk->detail->where('aspek_kepribadian_id', $asp->id))
                    ->avg('poin') ?? 0;
                $data['aspek'][$asp->nama] = round($poin, 2);
            }
            return $data;
        });

        // ── Data grafik per peserta (jika filter individu) ───
        $grafikIndividu = null;
        if ($pesertaId !== 'semua') {
            $pesertaObj = $allPeserta->find($pesertaId);
            $grafikIndividu = $periodeRange->map(function ($per) use ($semuaNilai, $pesertaId, $aspekList) {
                $n = $semuaNilai->first(fn($n) => $n->peserta_didik_id == $pesertaId && $n->periode_nilai_id == $per->id);
                $data = [
                    'label' => $per->label,
                    'total' => $n?->nilai_akhir ?? 0,
                    'aspek' => [],
                ];
                if ($n) {
                    foreach ($aspekList as $asp) {
                        $detail = $n->detail->firstWhere('aspek_kepribadian_id', $asp->id);
                        $data['aspek'][$asp->nama] = $detail?->poin ?? 0;
                    }
                } else {
                    foreach ($aspekList as $asp) {
                        $data['aspek'][$asp->nama] = 0;
                    }
                }
                return $data;
            });
        }

        // ── Statistik ringkasan ───────────────────────────────
        $totalSel = $pesertaList->count() * $periodeRange->count();
        $sudahInput = $semuaNilai->count();
        $stats = [
            'total_peserta'  => $pesertaList->count(),
            'total_periode'  => $periodeRange->count(),
            'sudah_input'    => $sudahInput,
            'belum_input'    => $totalSel - $sudahInput,
            'rata_total'     => round($semuaNilai->avg('nilai_akhir') ?? 0, 2),
            'tertinggi'      => round($semuaNilai->max('nilai_akhir') ?? 0, 2),
            'terendah'       => round($semuaNilai->where('nilai_akhir', '>', 0)->min('nilai_akhir') ?? 0, 2),
        ];

        // ── Data radar: rata-rata aspek untuk periode terakhir ─
        $radarAspek = [];
        $nilaiLastPeriod = $semuaNilai->where('periode_nilai_id', $periodeRange->last()?->id);
        foreach ($aspekList as $asp) {
            $avgPoin = $nilaiLastPeriod->flatMap(fn($nk) => $nk->detail->where('aspek_kepribadian_id', $asp->id))
                ->avg('poin') ?? 0;
            // Konversi poin ke skala 0-100: (poin + 0.5) * 100
            $radarAspek[$asp->nama] = round(($avgPoin + 0.5) * 100, 1);
        }

        return view('rekap.index', compact(
            'angkatan', 'allSkadik', 'skadikId', 'allAngkatan', 'allPeriode', 'allPeserta',
            'periodeRange', 'periodeFrom', 'periodeTo',
            'pesertaId', 'aspekList', 'radarAspek',
            'tabelData', 'grafikAngkatan', 'grafikIndividu',
            'stats'
        ));
    }

    public function ekspor(Request $request)
    {
        $angkatanId  = $request->get('angkatan_id');
        $angkatan    = Angkatan::with('skadik.lemdik')->find($angkatanId);
        $allPeriode  = PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get();
        $periodeFrom = $request->get('periode_from', $allPeriode->first()?->id);
        $periodeTo   = $request->get('periode_to', $allPeriode->last()?->id);
        $pesertaId   = $request->get('peserta_id', 'semua');
        $periodeRange = $allPeriode->filter(fn($p) => $p->id >= $periodeFrom && $p->id <= $periodeTo)->values();
        AspekKepribadian::ensureSeeded();
        $aspekList   = AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();

        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)
            ->when($pesertaId !== 'semua', fn($q) => $q->where('id', $pesertaId))
            ->orderBy('nama')->get();

        $semuaNilai = NilaiKepribadian::whereIn('periode_nilai_id', $periodeRange->pluck('id'))
            ->whereIn('peserta_didik_id', $pesertaList->pluck('id'))
            ->with('detail')
            ->get();

        // ── Buat spreadsheet ──────────────────────────────────
        $spreadsheet = new Spreadsheet();
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
        $spreadsheet->getProperties()
            ->setTitle("Rekap Nilai Kepribadian — {$angkatan?->nomor_angkatan}")
            ->setCreator('SisMonik');

        // ════════ SHEET 1: REKAP NILAI AKHIR ══════════════════
        $sh1 = $spreadsheet->getActiveSheet();
        $sh1->setTitle('Rekap Nilai Akhir');

        $periodeCount = $periodeRange->count();
        // No more rata-rata column — last col is last periode
        $colEnd       = chr(68 + $periodeCount);

        // Row 1: Judul utama
        $sh1->mergeCells("A1:{$colEnd}1");
        $sh1->setCellValue('A1', 'REKAP NILAI KEPRIBADIAN');
        $sh1->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sh1->getRowDimension(1)->setRowHeight(30);

        // Row 2: Header hierarchy — WINGDIK / SKADIK / SEKOLAH
        $lemdik = $angkatan?->skadik?->lemdik;
        $skadikObj = $angkatan?->skadik;
        $headerLines = [];
        if ($lemdik?->wingdik) $headerLines[] = strtoupper($lemdik->wingdik);
        if ($skadikObj) $headerLines[] = strtoupper($skadikObj->nama);
        if ($lemdik?->nama && strtoupper($lemdik->nama) !== strtoupper($skadikObj?->nama ?? '')) {
            $headerLines[] = strtoupper($lemdik->nama);
        }
        $sh1->mergeCells("A2:{$colEnd}2");
        $sh1->setCellValue('A2', implode(' / ', $headerLines));
        $sh1->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 3: Sub judul
        $sh1->mergeCells("A3:{$colEnd}3");
        $sh1->setCellValue('A3', "Angkatan {$angkatan?->nomor_angkatan} — Periode: {$periodeRange->first()?->label} s/d {$periodeRange->last()?->label} | Dicetak: " . now()->format('d/m/Y H:i'));
        $sh1->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header baris 4
        $sh1->setCellValue('A4', 'No');
        $sh1->setCellValue('B4', 'NRP');
        $sh1->setCellValue('C4', 'Pangkat');
        $sh1->setCellValue('D4', 'Nama');

        foreach ($periodeRange as $i => $per) {
            $col = chr(69 + $i);
            $sh1->setCellValue("{$col}4", $per->label);
        }
        // No more rata-rata column

        $sh1->getStyle("A4:{$colEnd}4")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sh1->getRowDimension(4)->setRowHeight(20);

        // Lebar kolom
        $sh1->getColumnDimension('A')->setWidth(5);
        $sh1->getColumnDimension('B')->setWidth(14);
        $sh1->getColumnDimension('C')->setWidth(12);
        $sh1->getColumnDimension('D')->setWidth(32);
        for ($i = 0; $i < $periodeCount; $i++) {
            $sh1->getColumnDimension(chr(69 + $i))->setWidth(14);
        }

        // Data
        $rank = 1;
        foreach ($pesertaList->sortBy('nama') as $p) {
            $row      = $rank + 4;
            $nilaiArr = [];

            $sh1->setCellValue("A{$row}", $rank);
            ExportFile::setText($sh1, "B{$row}", $p->nrp);
            $sh1->setCellValue("C{$row}", $p->pangkat);
            $sh1->setCellValue("D{$row}", $p->nama);

            foreach ($periodeRange as $i => $per) {
                $col = chr(69 + $i);
                $n   = $semuaNilai->first(fn($n) => $n->peserta_didik_id == $p->id && $n->periode_nilai_id == $per->id);
                $val = $n ? round($n->nilai_akhir, 2) : '';
                $sh1->setCellValue("{$col}{$row}", $val);
                if ($val !== '') $nilaiArr[] = $val;
            }

            // Style baris — polos putih
            $sh1->getStyle("A{$row}:{$colEnd}{$row}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sh1->getStyle("A{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh1->getStyle("E{$row}:{$colEnd}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sh1->getRowDimension($row)->setRowHeight(18);
            $rank++;
        }

        // ════════ SHEET 2: DETAIL ASPEK KEPRIBADIAN ════════════
        $sh2 = $spreadsheet->createSheet();
        $sh2->setTitle('Detail Aspek');

        $nAspek = $aspekList->count();

        // Header
        $sh2->mergeCells("A1:A3");
        $sh2->setCellValue('A1', 'Nama');
        $sh2->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sh2->getColumnDimension('A')->setWidth(32);

        $col = 2;
        foreach ($periodeRange as $per) {
            $startColIdx = $col;
            $endColIdx   = $col + $nAspek - 1;
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx);
            $endCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endColIdx);
            $sh2->mergeCells("{$startCol}1:{$endCol}1");
            $sh2->setCellValue("{$startCol}1", $per->label);
            $sh2->getStyle("{$startCol}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Sub header aspek
            foreach ($aspekList as $j => $asp) {
                $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $j);
                $sh2->setCellValue("{$c}2", strtoupper($asp->nama));
                $sh2->getStyle("{$c}2")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '000000']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                ]);
                $sh2->getColumnDimension($c)->setWidth(11);
            }
            $col += $nAspek;
        }
        $sh2->getRowDimension(2)->setRowHeight(28);

        // Data sheet 2
        foreach ($pesertaList->sortBy('nama') as $pi => $p) {
            $row = $pi + 3;
            $sh2->setCellValue("A{$row}", $p->nama);
            $sh2->getStyle("A{$row}")->getFont()->setBold($pi < 3);

            $col = 2;
            foreach ($periodeRange as $per) {
                $nk = $semuaNilai->first(fn($n) => $n->peserta_didik_id == $p->id && $n->periode_nilai_id == $per->id);
                $detailMap = $nk ? $nk->detail->keyBy('aspek_kepribadian_id') : collect();
                foreach ($aspekList as $j => $asp) {
                    $c  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $j);
                    $kriteria = $detailMap->get($asp->id)?->kriteria ?? '';
                    $sh2->setCellValue("{$c}{$row}", $kriteria);
                    // Kriteria — tabel polos: background putih, teks hitam
                    $sh2->getStyle("{$c}{$row}")->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
                $col += $nAspek;
            }

            $lastC   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
            $sh2->getStyle("A{$row}:{$lastC}{$row}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }

        // ════════ SHEET 3: RATA-RATA PER PERIODE ══════════════
        $sh3 = $spreadsheet->createSheet();
        $sh3->setTitle('Rata-rata Angkatan');

        $colIdx = 0;
        $sh3->setCellValue("A1", 'Periode');
        $sh3->getStyle("A1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sh3->getColumnDimension('A')->setWidth(18);

        foreach ($aspekList as $j => $asp) {
            $c = chr(66 + $j);
            $sh3->setCellValue("{$c}1", $asp->nama);
            $sh3->getStyle("{$c}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sh3->getColumnDimension($c)->setWidth(14);
        }
        $colTotal = chr(66 + $nAspek);
        $sh3->setCellValue("{$colTotal}1", 'Nilai Akhir');
        $sh3->getStyle("{$colTotal}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sh3->getColumnDimension($colTotal)->setWidth(14);

        foreach ($periodeRange as $i => $per) {
            $row      = $i + 2;
            $nilaiPer = $semuaNilai->where('periode_nilai_id', $per->id);
            $sh3->setCellValue("A{$row}", $per->label);

            foreach ($aspekList as $j => $asp) {
                $c   = chr(66 + $j);
                $avgPoin = $nilaiPer->flatMap(fn($nk) => $nk->detail->where('aspek_kepribadian_id', $asp->id))
                    ->avg('poin') ?? 0;
                $sh3->setCellValue("{$c}{$row}", round($avgPoin, 2));
            }
            $sh3->setCellValue("{$colTotal}{$row}", round($nilaiPer->avg('nilai_akhir') ?? 0, 2));

            $sh3->getStyle("A{$row}:{$colTotal}{$row}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = ExportFile::name(
            $angkatan,
            'Rekap NPK',
            ($periodeRange->first()?->label ?? '') . ' sd ' . ($periodeRange->last()?->label ?? '')
        );

        $writer = new XlsxWriter($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}