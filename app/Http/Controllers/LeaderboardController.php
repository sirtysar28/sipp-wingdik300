<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, KompilasiNilai, NilaiAkademik, NilaiKepribadian, NilaiSamapta, Skadik};
use App\Services\NppCalculator;
use App\Services\ExportFile;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, Font};

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get();

        $angkatanId = $request->get('angkatan_id', $allAngkatan->first()?->id);
        $angkatan  = Angkatan::with('skadik.lemdik')->find($angkatanId);
        $q         = $request->get('q', '');

        // Data dihitung LIVE dari sumber asli & diurutkan berdasarkan NPP (bukan NPK / rank lama)
        $sorted = NppCalculator::forAngkatan($angkatanId); // urut by nilai_akhir desc, rank sudah benar

        // Kompatibilitas properti yg dipakai view lama
        foreach ($sorted as $k) {
            $k->nilai_akademik_fix    = $k->nilai_akademik;
            $k->nilai_kepribadian_fix = $k->nilai_kepribadian;
            $k->nilai_samapta_fix     = $k->nilai_samapta;
            $k->npp_fix               = $k->nilai_akhir;
        }

        if ($q) {
            $sorted = $sorted->filter(fn($k) =>
                str_contains(strtolower($k->peserta->nama), strtolower($q)) ||
                str_contains($k->peserta->nrp, $q)
            )->values();
        }

        $page      = (int) $request->get('page', 1);
        $perPage   = 15;
        $total     = $sorted->count();
        $lastPage  = max(1, (int) ceil($total / $perPage));
        $paginated = $sorted->slice(($page - 1) * $perPage, $perPage)->values();
        $sudahKompilasi = $sorted->count();
        $rataNPP   = $sorted->count() > 0 ? round($sorted->avg('nilai_akhir'), 2) : 0;
        $totalPeserta = PesertaDidik::where('angkatan_id', $angkatanId)->count();

        return view('leaderboard.index', compact(
            'paginated','sorted','angkatan','allSkadik','skadikId','allAngkatan',
            'angkatanId','q',
            'page','lastPage','total','perPage',
            'sudahKompilasi','rataNPP','totalPeserta'
        ));
    }

    public function ekspor(Request $request)
    {
        $angkatanId = $request->get('angkatan_id', Angkatan::where('aktif',true)->latest()->first()?->id);
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);
        $sorted     = NppCalculator::forAngkatan($angkatanId);

        foreach ($sorted as $k) {
            $k->nilai_akademik_fix    = $k->nilai_akademik;
            $k->nilai_kepribadian_fix = $k->nilai_kepribadian;
            $k->nilai_samapta_fix     = $k->nilai_samapta;
            $k->npp_fix               = $k->nilai_akhir;
        }

        $spreadsheet = new Spreadsheet();
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Leaderboard NPP');

        // Header institusi
        $headers = [
            [1, $angkatan?->skadik?->lemdik?->nama ?? 'LEMBAGA PENDIDIKAN'],
            [2, $angkatan?->skadik?->nama ?? 'SKADRON PENDIDIKAN'],
            [3, 'LEADERBOARD NILAI PRESTASI PENDIDIKAN (NPP) — ANGKATAN ' . ($angkatan?->nomor_angkatan ?? '')],
            [4, 'TAHUN ANGGARAN '.($angkatan?->tahun_masuk ?? date('Y'))],
        ];
        foreach ($headers as [$row, $val]) {
            $sheet->mergeCells("A{$row}:I{$row}");
            $sheet->setCellValue("A{$row}", strtoupper($val));
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => $row <= 2 ? 10 : 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Column headers
        $cols = ['A'=>'NO','B'=>'NAMA','C'=>'PGKT','D'=>'NRP','E'=>'NPA','F'=>'NPK','G'=>'NPS','H'=>'NPP','I'=>'RNKG'];
        foreach ($cols as $col => $label) {
            $sheet->setCellValue("{$col}6", $label);
            $sheet->getStyle("{$col}6")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(8);

        // Data
        foreach ($sorted as $i => $k) {
            $row = $i + 7;
            $rank = $i + 1;
            $sheet->setCellValue("A{$row}", $rank);
            $sheet->setCellValue("B{$row}", $k->peserta->nama);
            $sheet->setCellValue("C{$row}", $k->peserta->pangkat);
            ExportFile::setText($sheet, "D{$row}", $k->peserta->nrp);
            $sheet->setCellValue("E{$row}", $k->nilai_akademik_fix);
            $sheet->setCellValue("F{$row}", $k->nilai_kepribadian_fix);
            $sheet->setCellValue("G{$row}", $k->nilai_samapta_fix);
            $sheet->setCellValue("H{$row}", $k->npp_fix);
            $sheet->setCellValue("I{$row}", $rank);

            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$row}")->applyFromArray(['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Highlight top 3 — teks tebal saja (tabel polos hitam-putih)
            if ($rank <= 3) {
                $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB(ExportFile::TEXT_BLACK);
            }

            $sheet->getRowDimension($row)->setRowHeight(18);
        }

        $filename = ExportFile::name($angkatan, 'Leaderboard NPP');

        $writer = new XlsxWriter($spreadsheet);
        return response()->streamDownload(function () use ($writer) { $writer->save('php://output'); },
            $filename, ['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'max-age=0']);
    }
}