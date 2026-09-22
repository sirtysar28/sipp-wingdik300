<?php
namespace App\Http\Controllers;

use App\Models\{Nilai, PesertaDidik, PeriodeNilai, Angkatan, Skadik};
use App\Exports\{NilaiExport, LeaderboardExport};
use App\Imports\NilaiImport;
use App\Services\ExportFile;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class NilaiController extends Controller {

    public function index(Request $request) {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->orderBy('created_at', 'desc')->get();

        $angkatanId = $request->get('angkatan_id', $allAngkatan->first()?->id);
        $periodes   = PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get();
        $periodeId  = $request->get('periode_id', $periodes->last()?->id);

        $peserta = PesertaDidik::where('angkatan_id', $angkatanId)
            ->with(['nilai' => fn($q) => $q->where('periode_nilai_id', $periodeId)])
            ->orderBy('nama')->get();

        $periode     = PeriodeNilai::find($periodeId);

        return view('nilai.index', compact('peserta', 'allSkadik', 'skadikId', 'allAngkatan', 'periodes', 'periodeId', 'angkatanId', 'periode'));
    }

    public function store(Request $request) {
        $request->validate([
            'periode_nilai_id' => 'required|exists:periode_nilai,id',
            'peserta_didik_id' => 'required|exists:peserta_didik,id',
            'akademik'         => 'required|numeric|min:0|max:100',
            'fisik'            => 'required|numeric|min:0|max:100',
            'sikap'            => 'required|numeric|min:0|max:100',
            'kepemimpinan'     => 'required|numeric|min:0|max:100',
        ]);

        Nilai::updateOrCreate(
            ['peserta_didik_id' => $request->peserta_didik_id, 'periode_nilai_id' => $request->periode_nilai_id],
            [
                'akademik'     => $request->akademik,
                'fisik'        => $request->fisik,
                'sikap'        => $request->sikap,
                'kepemimpinan' => $request->kepemimpinan,
                'input_oleh'   => auth()->id(),
            ]
        );

        return back()->with('success', 'Nilai berhasil disimpan.');
    }

    public function bulkStore(Request $request) {
        $request->validate([
            'periode_nilai_id'     => 'required|exists:periode_nilai,id',
            'nilai'                => 'required|array',
            'nilai.*.peserta_id'   => 'required|exists:peserta_didik,id',
            'nilai.*.akademik'     => 'required|numeric|min:0|max:100',
            'nilai.*.fisik'        => 'required|numeric|min:0|max:100',
            'nilai.*.sikap'        => 'required|numeric|min:0|max:100',
            'nilai.*.kepemimpinan' => 'required|numeric|min:0|max:100',
        ]);

        foreach ($request->nilai as $row) {
            Nilai::updateOrCreate(
                ['peserta_didik_id' => $row['peserta_id'], 'periode_nilai_id' => $request->periode_nilai_id],
                [
                    'akademik'     => $row['akademik'],
                    'fisik'        => $row['fisik'],
                    'sikap'        => $row['sikap'],
                    'kepemimpinan' => $row['kepemimpinan'],
                    'input_oleh'   => auth()->id(),
                ]
            );
        }

        return back()->with('success', 'Semua nilai berhasil disimpan.');
    }

    public function createPeriode(Request $request) {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id');
        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->orderBy('created_at', 'desc')->get();
        return view('nilai.create-periode', compact('allSkadik', 'skadikId', 'allAngkatan'));
    }

    public function storePeriode(Request $request) {
        $data = $request->validate([
            'angkatan_id'     => 'required|exists:angkatan,id',
            'label'           => 'required|string|max:50',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);
        PeriodeNilai::where('angkatan_id', $data['angkatan_id'])->update(['aktif' => false]);
        $data['aktif'] = true;
        PeriodeNilai::create($data);
        return redirect()->route('nilai.index', ['angkatan_id' => $data['angkatan_id']])->with('success', 'Periode baru dibuat.');
    }

    // ── Ekspor Nilai XLSX ─────────────────────────────────────
    public function eksporXlsx(Request $request) {
        $angkatanId = $request->get('angkatan_id');
        $periodeId  = $request->get('periode_id');
        $periode    = PeriodeNilai::find($periodeId);
        $angkatan   = Angkatan::with('skadik')->find($angkatanId);
        $filename   = ExportFile::name($angkatan, 'Nilai', $periode?->label ?? '');
        return Excel::download(new NilaiExport($angkatanId, $periodeId), $filename);
    }

    // ── Ekspor Leaderboard XLSX ───────────────────────────────
    public function eksporLeaderboard(Request $request) {
        $angkatanId = $request->get('angkatan_id');
        $periodeId  = $request->get('periode_id');
        $periode    = PeriodeNilai::find($periodeId);
        $angkatan   = Angkatan::with('skadik')->find($angkatanId);
        $filename   = ExportFile::name($angkatan, 'Leaderboard', $periode?->label ?? '');
        return Excel::download(new LeaderboardExport($angkatanId, $periodeId), $filename);
    }

    // ── Download Template ─────────────────────────────────────
    public function downloadTemplate(Request $request) {
        $angkatanId = $request->get('angkatan_id');
        $angkatan   = Angkatan::with('skadik')->find($angkatanId);
        $peserta    = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();

        $spreadsheet = new Spreadsheet();
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Nilai');

        $headers = ['Nama','Pangkat','NRP','Nosis','Akademik','Fisik','Sikap','Kepemimpinan'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}1", $h);
            $sheet->getStyle("{$col}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getColumnDimension($col)->setWidth($i === 0 ? 32 : 16);
        }

        foreach ($peserta as $i => $p) {
            $row = $i + 2;
            $sheet->setCellValue("A{$row}", $p->nama);
            $sheet->setCellValue("B{$row}", $p->pangkat);
            ExportFile::setText($sheet, "C{$row}", $p->nrp);
            ExportFile::setText($sheet, "D{$row}", $p->nosis);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
            $sheet->getStyle("E{$row}:H{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            ]);
        }

        $lastRow = $peserta->count() + 3;
        $sheet->setCellValue("A{$lastRow}", "* Isi kolom Akademik, Fisik, Sikap, Kepemimpinan dengan nilai 0-100");
        $sheet->setCellValue("A".($lastRow+1), "* Jangan mengubah kolom Nama, Pangkat, NRP, dan Nosis");
        $sheet->getStyle("A{$lastRow}:H".($lastRow+1))->getFont()->setItalic(true)->setSize(10);

        $writer = new XlsxWriter($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, ExportFile::name($angkatan, 'Template Nilai'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    // ── Impor dari Excel ─────────────────────────────────────
    public function importForm(Request $request) {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->orderBy('created_at', 'desc')->get();

        $angkatanId  = $request->get('angkatan_id', $allAngkatan->first()?->id);
        $periodes    = PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get();
        $periodeId   = $request->get('periode_id', $periodes->last()?->id);
        $periode     = PeriodeNilai::find($periodeId);
        return view('nilai.import', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'periodes', 'periodeId', 'periode'));
    }

    public function import(Request $request) {
        $request->validate([
            'file'             => 'required|file|extensions:xlsx,xls|max:2048',
            'angkatan_id'      => 'required|exists:angkatan,id',
            'periode_nilai_id' => 'required|exists:periode_nilai,id',
        ]);

        $import = new NilaiImport($request->periode_nilai_id, $request->angkatan_id);
        Excel::import($import, $request->file('file'));

        $count  = $import->getImportedCount();
        $errors = $import->getErrors();
        $msg    = "Berhasil impor {$count} data nilai.";
        if (!empty($errors)) {
            $msg .= ' Beberapa baris dilewati: ' . implode('; ', array_slice($errors, 0, 3));
        }

        return redirect()->route('nilai.index', [
            'angkatan_id' => $request->angkatan_id,
            'periode_id'  => $request->periode_nilai_id,
        ])->with('success', $msg);
    }
}