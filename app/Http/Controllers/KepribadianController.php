<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, PeriodeNilai, NilaiKepribadian, DetailKepribadian, AspekKepribadian, Skadik};
use App\Imports\KepribadianImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class KepribadianController extends Controller
{
    private function makeSheet(?Angkatan $angkatan, ?PeriodeNilai $periode, $pesertaList, $aspekList)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Nilai Kepribadian');
        $sheet = $spreadsheet->getActiveSheet();

        $nAspek  = $aspekList->count();
        $colJml  = chr(65 + 6 + $nAspek);
        $colRnkg = chr(65 + 7 + $nAspek);
        $colAspekE = chr(65 + 5 + $nAspek);

        foreach ([
            [1, strtoupper($angkatan?->skadik?->lemdik?->nama ?? 'LEMBAGA PENDIDIKAN')],
            [2, strtoupper($angkatan?->skadik?->nama ?? 'SKADRON PENDIDIKAN')],
            [3, 'NILAI KEPRIBADIAN SISWA '.strtoupper($angkatan?->skadik?->nama ?? 'SEKOLAH').' ANGKATAN '.($angkatan?->nomor_angkatan ?? '')],
            [4, 'TAHUN ANGGARAN '.($angkatan?->tahun_masuk ?? date('Y'))],
        ] as [$r, $v]) {
            $sheet->mergeCells("A{$r}:{$colRnkg}{$r}");
            $sheet->setCellValue("A{$r}", $v);
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['bold'=>true,'size'=>$r<=2?10:11],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $sheet->mergeCells("A5:{$colRnkg}5");
        $sheet->setCellValue('A5', 'Periode: '.($periode?->label ?? ''));
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['italic'=>true,'size'=>10,'color'=>['rgb'=>'6B7280']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells("G6:{$colAspekE}6");
        $sheet->setCellValue('G6', 'NILAI ASPEK MAKRO');
        $sheet->getStyle('G6')->applyFromArray([
            'font'      => ['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'4F46E5']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        ]);

        foreach (['A'=>'NO','B'=>'NAMA','C'=>'PGKT','D'=>'NRP','E'=>'NOSIS','F'=>'NILAI AWAL'] as $col=>$label) {
            $sheet->mergeCells("{$col}6:{$col}7");
            $sheet->setCellValue("{$col}6", $label);
            $sheet->getStyle("{$col}6")->applyFromArray([
                'font'      => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'4F46E5']],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'FFFFFF']]],
            ]);
        }

        foreach ($aspekList as $i => $aspek) {
            $col = chr(71 + $i);
            $sheet->setCellValue("{$col}7", strtoupper($aspek->nama));
            $sheet->getStyle("{$col}7")->applyFromArray([
                'font'      => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>8],
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'6366F1']],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'FFFFFF']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth(11);
        }

        foreach ([$colJml=>'JML',$colRnkg=>'RNKG'] as $col=>$label) {
            $sheet->mergeCells("{$col}6:{$col}7");
            $sheet->setCellValue("{$col}6", $label);
            $sheet->getStyle("{$col}6")->applyFromArray([
                'font'      => ['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'4338CA']],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'FFFFFF']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth(8);
        }

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(8);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getRowDimension(6)->setRowHeight(20);
        $sheet->getRowDimension(7)->setRowHeight(50);

        foreach ($pesertaList as $pi => $p) {
            $row = $pi + 8;
            $nk  = $p->nilaiKepribadian->first();
            $detailMap = $nk ? $nk->detail->keyBy('aspek_kepribadian_id') : collect();

            $sheet->setCellValue("A{$row}", $pi + 1);
            $sheet->setCellValue("B{$row}", $p->nama);
            $sheet->setCellValue("C{$row}", $p->pangkat);
            $sheet->setCellValue("D{$row}", $p->nrp);
            $sheet->setCellValue("E{$row}", $p->nosis ?? '');
            $sheet->setCellValue("F{$row}", 75);

            foreach ($aspekList as $i => $aspek) {
                $col      = chr(71 + $i);
                $kriteria = $detailMap->get($aspek->id)?->kriteria ?? '';
                $sheet->setCellValue("{$col}{$row}", $kriteria);
                $bg = match($kriteria) {
                    'BS'=>'D1FAE5','B'=>'DBEAFE','C'=>'F9FAFB',
                    'K'=>'FEF3C7','KS'=>'FEE2E2',default=>'FFFBEB',
                };
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
                    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'E5E7EB']]],
                ]);
            }

            $sheet->setCellValue("{$colJml}{$row}", $nk?->nilai_akhir ?? '');
            $sheet->getStyle("{$colJml}{$row}")->applyFromArray([
                'font'      => ['bold'=>true],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EEF2FF']],
            ]);
            $sheet->setCellValue("{$colRnkg}{$row}", $nk ? ($pi+1) : '');
            $sheet->getStyle("{$colRnkg}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $bgRow = $pi%2===0?'FFFFFF':'F9FAFB';
            $sheet->getStyle("A{$row}:{$colRnkg}{$row}")->applyFromArray([
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bgRow]],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'E5E7EB']]],
                'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(18);
        }

        $fr = $pesertaList->count() + 9;
        $sheet->mergeCells("A{$fr}:{$colRnkg}{$fr}");
        $sheet->setCellValue("A{$fr}", 'Keterangan: BS=Baik Sekali(+0.5) | B=Baik(+0.25) | C=Cukup(0) | K=Kurang(-0.25) | KS=Kurang Sekali(-0.5) | Kolom kuning = belum diisi');
        $sheet->getStyle("A{$fr}")->applyFromArray(['font'=>['italic'=>true,'size'=>9,'color'=>['rgb'=>'6B7280']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F3F4F6']]]);

        $sheet->mergeCells("A".($fr+1).":{$colRnkg}".($fr+1));
        $sheet->setCellValue("A".($fr+1), 'Cara impor: Isi kolom aspek dengan BS/B/C/K/KS lalu upload via menu Impor Nilai Kepribadian.');
        $sheet->getStyle("A".($fr+1))->applyFromArray(['font'=>['italic'=>true,'size'=>9,'color'=>['rgb'=>'92400E']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FEF3C7']]]);

        return $spreadsheet;
    }

    public function index(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get();

        $angkatanId  = $request->get('angkatan_id', $allAngkatan->first()?->id);
        $angkatan    = Angkatan::find($angkatanId);
        $periodes    = $angkatanId ? PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get() : collect();
        $periodeId   = $request->get('periode_id', $periodes->last()?->id);
        $periode     = PeriodeNilai::find($periodeId);
        $periodeAktif = $periodes->where('aktif', true)->last() ?? $periodes->last();

        // Load peserta otomatis begitu angkatan dipilih (tidak perlu klik
        // "Cari" lagi). Ini mengatasi issue “NPK AFS-22 kosong padahal sudah
        // diinput” yang terjadi karena pengguna lupa menekan tombol Cari.
        if ($angkatanId) {
            // Pastikan aspek kepribadian selalu tersedia (jaga-jika DB tertimpa)
            AspekKepribadian::ensureSeeded();

            if ($periodeId) {
                $peserta = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')
                    ->with(['nilaiKepribadian' => fn($q) => $q->where('periode_nilai_id', $periodeId)])
                    ->get()->map(function ($p) { $p->kep = $p->nilaiKepribadian->first(); return $p; });
            } else {
                // Belum ada periode: tetap tampilkan nilai terbaru yg sudah diinput
                // agar data tidak terlihat “kosong” padahal sudah pernah diinput.
                $peserta = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')
                    ->with(['nilaiKepribadian' => fn($q) => $q->with('periode')->orderByDesc('periode_nilai_id')])
                    ->get()->map(function ($p) { $p->kep = $p->nilaiKepribadian->first(); return $p; });
            }
            $sudahDiinput = $peserta->filter(fn($p) => $p->kep)->count();
        } else {
            $peserta = collect();
            $sudahDiinput = 0;
        }

        return view('kepribadian.index', compact(
            'allSkadik','skadikId','allAngkatan','angkatan','angkatanId',
            'periodes','periodeId','periode','periodeAktif','peserta','sudahDiinput'
        ));
    }

    public function form(Request $request, PesertaDidik $peserta)
    {
        $periodeId      = $request->get('periode_id');
        $periode        = PeriodeNilai::find($periodeId);
        $aspekList      = AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();
        $existing       = NilaiKepribadian::where('peserta_didik_id', $peserta->id)->where('periode_nilai_id', $periodeId)->with('detail')->first();
        $existingDetail = $existing ? $existing->detail->keyBy('aspek_kepribadian_id') : collect();
        return view('kepribadian.form', compact('peserta','periode','periodeId','aspekList','existing','existingDetail'));
    }

    public function store(Request $request, PesertaDidik $peserta)
    {
        $request->validate(['periode_nilai_id'=>'required|exists:periode_nilai,id','kriteria'=>'required|array','kriteria.*'=>'required|in:KS,K,C,B,BS','penjelasan'=>'nullable|string|max:2000','rekomendasi'=>'nullable|string|max:1000']);
        DB::transaction(function () use ($request, $peserta) {
            $totalPoin  = collect($request->kriteria)->sum(fn($k) => NilaiKepribadian::poinKriteria($k));
            $nilaiAkhir = round(75 + $totalPoin, 2);
            $nk = NilaiKepribadian::updateOrCreate(
                ['peserta_didik_id'=>$peserta->id,'periode_nilai_id'=>$request->periode_nilai_id],
                ['nilai_akhir'=>$nilaiAkhir,'penjelasan'=>$request->penjelasan,'rekomendasi'=>$request->rekomendasi,'input_oleh'=>auth()->id()]
            );
            $nk->detail()->delete();
            foreach ($request->kriteria as $aspekId => $kriteria) {
                DetailKepribadian::create(['nilai_kepribadian_id'=>$nk->id,'aspek_kepribadian_id'=>$aspekId,'kriteria'=>$kriteria,'poin'=>NilaiKepribadian::poinKriteria($kriteria)]);
            }
        });
        return redirect()->route('kepribadian.index',['angkatan_id'=>$peserta->angkatan_id,'periode_id'=>$request->periode_nilai_id,'cari'=>1])->with('success',"Nilai kepribadian {$peserta->nama} berhasil disimpan.");
    }

    public function show(PesertaDidik $peserta, Request $request)
    {
        $periodes  = PeriodeNilai::where('angkatan_id', $peserta->angkatan_id)->orderBy('tanggal_mulai')->get();
        $periodeId = $request->get('periode_id', $periodes->last()?->id);
        $riwayat   = NilaiKepribadian::where('peserta_didik_id', $peserta->id)->with(['periode','detail.aspek'])->orderBy('periode_nilai_id')->get();
        $current   = $riwayat->where('periode_nilai_id', $periodeId)->first();
        AspekKepribadian::ensureSeeded();
        $aspekList = AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();
        $grafikLabels = $riwayat->pluck('periode.label');
        $grafikNilai  = $riwayat->pluck('nilai_akhir');
        return view('kepribadian.show', compact('peserta','periodes','periodeId','riwayat','current','aspekList','grafikLabels','grafikNilai'));
    }

    public function aspekIndex()
    {
        $aspek = AspekKepribadian::orderBy('nomor')->get();
        return view('kepribadian.aspek', compact('aspek'));
    }

    public function aspekStore(Request $request)
    {
        $request->validate(['nama'=>'required|string|max:100','deskripsi'=>'nullable|string|max:500']);
        AspekKepribadian::create(['nomor'=>AspekKepribadian::max('nomor')+1,'nama'=>$request->nama,'deskripsi'=>$request->deskripsi,'aktif'=>true]);
        return back()->with('success','Aspek berhasil ditambahkan.');
    }

    public function aspekUpdate(Request $request, AspekKepribadian $aspek)
    {
        $aspek->update($request->only('nama','deskripsi'));
        return back()->with('success','Aspek diperbarui.');
    }

    public function aspekDestroy(AspekKepribadian $aspek)
    {
        $aspek->update(['aktif' => !$aspek->aktif]);
        return back()->with('success','Aspek berhasil '.($aspek->aktif?'diaktifkan':'dinonaktifkan').'.');
    }

    public function createPeriode(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id');
        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->orderBy('created_at', 'desc')->get();
        return view('nilai.create-periode', compact('allSkadik', 'skadikId', 'allAngkatan'));
    }

    public function storePeriode(Request $request)
    {
        $data = $request->validate(['angkatan_id'=>'required|exists:angkatan,id','label'=>'required|string|max:50','tanggal_mulai'=>'required|date','tanggal_selesai'=>'required|date|after:tanggal_mulai']);
        PeriodeNilai::where('angkatan_id', $data['angkatan_id'])->update(['aktif'=>false]);
        $data['aktif'] = true;
        PeriodeNilai::create($data);
        return redirect()->route('kepribadian.index',['angkatan_id'=>$data['angkatan_id']])->with('success','Periode baru berhasil dibuat.');
    }

    public function downloadTemplate(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $periodeId  = $request->get('periode_id');
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);
        $periode    = PeriodeNilai::find($periodeId);
        AspekKepribadian::ensureSeeded();
        $aspekList  = AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();
        $peserta    = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')
            ->with(['nilaiKepribadian' => fn($q) => $q->where('periode_nilai_id', $periodeId)->with('detail')])->get();

        $spreadsheet = $this->makeSheet($angkatan, $periode, $peserta, $aspekList);
        $filename    = preg_replace('/[^A-Za-z0-9_\-.]/', '_', "template_kepribadian_{$angkatan?->nomor_angkatan}_{$periode?->label}.xlsx");
        $writer      = new XlsxWriter($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), $filename, ['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'max-age=0']);
    }

    public function ekspor(Request $request)
    {
        return $this->downloadTemplate($request);
    }

    public function importForm(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->orderBy('created_at', 'desc')->get();

        $angkatanId  = $request->get('angkatan_id', $allAngkatan->first()?->id);
        $periodes    = PeriodeNilai::where('angkatan_id', $angkatanId)->orderBy('tanggal_mulai')->get();
        $periodeId   = $request->get('periode_id', $periodes->last()?->id);
        $periode     = PeriodeNilai::find($periodeId);
        return view('kepribadian.import', compact('allSkadik','skadikId','allAngkatan','angkatanId','periodes','periodeId','periode'));
    }

    public function import(Request $request)
    {
        $request->validate(['file'=>'required|file|extensions:xlsx,xls|max:5120','angkatan_id'=>'required|exists:angkatan,id','periode_nilai_id'=>'required|exists:periode_nilai,id']);
        // Baca langsung dari file upload (tanpa Storage/Flysystem),
        // karena hosting tidak punya ekstensi PHP fileinfo.
        $full = $request->file('file')->getRealPath();

        try {
            $import = new KepribadianImport($request->periode_nilai_id, $request->angkatan_id);
            $import->import($full);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'File bukan Excel yang valid / tidak dapat dibaca.']);
        }

        $msg = "Berhasil impor {$import->getImportedCount()} data nilai kepribadian.";
        if ($import->getSkippedCount() > 0) $msg .= " {$import->getSkippedCount()} baris dilewati.";
        if (!empty($import->getErrors())) $msg .= ' | '.implode(' | ', array_slice($import->getErrors(),0,3));

        return redirect()->route('kepribadian.index',['angkatan_id'=>$request->angkatan_id,'periode_id'=>$request->periode_nilai_id])->with('success',$msg);
    }
}