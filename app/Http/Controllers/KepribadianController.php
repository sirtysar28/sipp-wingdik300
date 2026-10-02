<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, PeriodeNilai, NilaiKepribadian, DetailKepribadian, AspekKepribadian, Skadik, Penandatangan};
use App\Imports\KepribadianImport;
use App\Services\ExportFile;
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
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
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
            'font' => ['italic'=>true,'size'=>10,'color'=>['rgb'=>'000000']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells("G6:{$colAspekE}6");
        $sheet->setCellValue('G6', 'NILAI ASPEK MAKRO');
        $sheet->getStyle('G6')->applyFromArray([
            'font'      => ['bold'=>true,'color'=>['rgb'=>'000000']],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        ]);

        foreach (['A'=>'NO','B'=>'NAMA','C'=>'PGKT','D'=>'NRP','E'=>'NOSIS','F'=>'NILAI AWAL'] as $col=>$label) {
            $sheet->mergeCells("{$col}6:{$col}7");
            $sheet->setCellValue("{$col}6", $label);
            $sheet->getStyle("{$col}6")->applyFromArray([
                'font'      => ['bold'=>true,'color'=>['rgb'=>'000000'],'size'=>9],
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]],
            ]);
        }

        foreach ($aspekList as $i => $aspek) {
            $col = chr(71 + $i);
            $sheet->setCellValue("{$col}7", strtoupper($aspek->nama));
            $sheet->getStyle("{$col}7")->applyFromArray([
                'font'      => ['bold'=>true,'color'=>['rgb'=>'000000'],'size'=>8],
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth(11);
        }

        foreach ([$colJml=>'JML',$colRnkg=>'RNKG'] as $col=>$label) {
            $sheet->mergeCells("{$col}6:{$col}7");
            $sheet->setCellValue("{$col}6", $label);
            $sheet->getStyle("{$col}6")->applyFromArray([
                'font'      => ['bold'=>true,'color'=>['rgb'=>'000000']],
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]],
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
            ExportFile::setText($sheet, "D{$row}", $p->nrp);
            ExportFile::setText($sheet, "E{$row}", $p->nosis ?? '');
            $sheet->setCellValue("F{$row}", 75);

            foreach ($aspekList as $i => $aspek) {
                $col      = chr(71 + $i);
                $kriteria = $detailMap->get($aspek->id)?->kriteria ?? '';
                $sheet->setCellValue("{$col}{$row}", $kriteria);
                // Kriteria — tabel polos: background putih, teks hitam
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
                    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]],
                ]);
            }

            $sheet->setCellValue("{$colJml}{$row}", $nk?->nilai_akhir ?? '');
            $sheet->getStyle("{$colJml}{$row}")->applyFromArray([
                'font'      => ['bold'=>true],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
            ]);
            $sheet->setCellValue("{$colRnkg}{$row}", $nk ? ($pi+1) : '');
            $sheet->getStyle("{$colRnkg}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("A{$row}:{$colRnkg}{$row}")->applyFromArray([
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]],
                'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(18);
        }

        $fr = $pesertaList->count() + 9;
        $sheet->mergeCells("A{$fr}:{$colRnkg}{$fr}");
        $sheet->setCellValue("A{$fr}", 'Keterangan: BS=Baik Sekali(+0.5) | B=Baik(+0.25) | C=Cukup(0) | K=Kurang(-0.25) | KS=Kurang Sekali(-0.5)');
        $sheet->getStyle("A{$fr}")->applyFromArray(['font'=>['italic'=>true,'size'=>9,'color'=>['rgb'=>'000000']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFFFFF']]]);

        $sheet->mergeCells("A".($fr+1).":{$colRnkg}".($fr+1));
        $sheet->setCellValue("A".($fr+1), 'Cara impor: Isi kolom aspek dengan BS/B/C/K/KS lalu upload via menu Impor Nilai Kepribadian.');
        $sheet->getStyle("A".($fr+1))->applyFromArray(['font'=>['italic'=>true,'size'=>9,'color'=>['rgb'=>'000000']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>ExportFile::BG_PLAIN]]]);

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

    /**
     * Revisi 30 September 2026 — CETAK PDF NPK INDIVIDU.
     * Halaman cetak (HTML print → PDF via browser) untuk detail kepribadian
     * satu peserta: identitas, rekap nilai per periode, detail aspek periode
     * terpilih, penjelasan/rekomendasi + blok tanda tangan.
     * Tombolnya ada DI DALAM halaman detail NPK individu (kepribadian.show).
     */
    public function cetak(PesertaDidik $peserta, Request $request)
    {
        $angkatan  = Angkatan::with('skadik.lemdik')->find($peserta->angkatan_id);
        if (!$angkatan) {
            return back()->with('error', 'Angkatan peserta tidak ditemukan.');
        }

        $periodes  = PeriodeNilai::where('angkatan_id', $peserta->angkatan_id)->orderBy('tanggal_mulai')->get();
        $periodeId = $request->get('periode_id', $periodes->last()?->id);
        $riwayat   = NilaiKepribadian::where('peserta_didik_id', $peserta->id)
            ->with(['periode','detail.aspek'])->orderBy('periode_nilai_id')->get();
        $current   = $riwayat->where('periode_nilai_id', $periodeId)->first();

        AspekKepribadian::ensureSeeded();
        $aspekList = AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();

        // Penandatangan: kanan = pejabat laporan kepribadian, kiri = Danskadik
        // (pola sama dengan cetak NPK angkatan / report.cetak-npk).
        $ttdKanan = Penandatangan::getPenandatangan('kepribadian', $angkatan->skadik_id);
        $ttdKiri  = Penandatangan::getPenandatangan('danskadik',   $angkatan->skadik_id);

        return view('kepribadian.cetak', compact(
            'peserta', 'angkatan', 'periodes', 'periodeId', 'riwayat', 'current', 'aspekList', 'ttdKanan', 'ttdKiri'
        ));
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
        // Revisi 26 Agustus 2026: kirim route store & back milik modul kepribadian
        // agar Danflight (admin_kepribadian) tidak lagi ter-POST ke route
        // nilai.periode.store (role:admin) yang mengakibatkan error 403.
        return view('nilai.create-periode', compact('allSkadik', 'skadikId', 'allAngkatan') + [
            'storeRoute' => route('kepribadian.periode.store'),
            'backRoute'  => route('kepribadian.index'),
        ]);
    }

    public function storePeriode(Request $request)
    {
        $data = $request->validate(['angkatan_id'=>'required|exists:angkatan,id','label'=>'required|string|max:50','tanggal_mulai'=>'required|date','tanggal_selesai'=>'required|date|after:tanggal_mulai']);
        PeriodeNilai::where('angkatan_id', $data['angkatan_id'])->update(['aktif'=>false]);
        $data['aktif'] = true;
        PeriodeNilai::create($data);
        return redirect()->route('kepribadian.index',['angkatan_id'=>$data['angkatan_id']])->with('success','Periode baru berhasil dibuat.');
    }

    /**
     * Revisi 2 Oktober 2026 — FORM EDIT PERIODE NPK (khusus SUPERADMIN).
     * Mengubah label & rentang tanggal periode TANPA menyentuh data nilai
     * kepribadian di dalamnya (beda dengan hard delete).
     */
    public function editPeriode(Request $request, PeriodeNilai $periode)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Hanya Superadmin yang dapat mengedit periode NPK.');
        }

        $periode->load('angkatan.skadik.lemdik');
        return view('kepribadian.edit-periode', [
            'periode'   => $periode,
            'backRoute' => $request->get('back') ?: route('report.npk', ['angkatan_id' => $periode->angkatan_id, 'tampilkan' => 1]),
        ]);
    }

    /**
     * Revisi 2 Oktober 2026 — SIMPAN EDIT PERIODE NPK (khusus SUPERADMIN).
     * Hanya label + rentang tanggal yang bisa diubah; seluruh data nilai
     * kepribadian pada periode tsb tetap utuh.
     */
    public function updatePeriode(Request $request, PeriodeNilai $periode)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Hanya Superadmin yang dapat mengedit periode NPK.');
        }

        $data = $request->validate([
            'label'         => 'required|string|max:50',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        $periode->update($data);

        $msg = 'Periode "' . $data['label'] . '" berhasil diperbarui (data nilai tidak berubah).';
        $target = $request->input('redirect');
        if ($target && !preg_match('#^https?://#i', $target)) {
            return redirect()->to($target)->with('success', $msg);
        }
        return redirect()
            ->route('report.npk', ['angkatan_id' => $periode->angkatan_id, 'tampilkan' => 1])
            ->with('success', $msg);
    }

    /**
     * HARD DELETE periode NPK beserta seluruh isinya.
     * Revisi 26 Agustus 2026: periode uji coba (mis. Periode 1 & 6) yang
     * sudah tidak terpakai masih tampil di Report NPK — fitur ini menghapus
     * permanen: detail_kepribadian → nilai_kepribadian → periode_nilai.
     */
    public function destroyPeriode(Request $request, PeriodeNilai $periode)
    {
        try {
            $label       = $periode->label;
            $angkatanId  = $periode->angkatan_id;
            $wasAktif    = (bool) $periode->aktif;

            $jumlahNilai = DB::transaction(function () use ($periode) {
                $nilaiIds = NilaiKepribadian::where('periode_nilai_id', $periode->id)->pluck('id');
                DetailKepribadian::whereIn('nilai_kepribadian_id', $nilaiIds)->delete();
                $jumlah = NilaiKepribadian::where('periode_nilai_id', $periode->id)->delete();
                $periode->delete(); // hard delete (model tidak pakai SoftDeletes)
                return $jumlah;
            });

            // Bila periode yang dihapus adalah periode aktif, aktifkan periode terakhir yang tersisa
            if ($wasAktif) {
                $terakhir = PeriodeNilai::where('angkatan_id', $angkatanId)
                    ->orderByDesc('tanggal_mulai')->first();
                if ($terakhir) $terakhir->update(['aktif' => true]);
            }

            $msg = "Periode \"{$label}\" beserta {$jumlahNilai} data nilai kepribadian berhasil dihapus permanen.";
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }
            // Redirect kembali hanya ke URL internal aplikasi (cek skema/host relatif)
            $target = $request->input('redirect');
            if ($target && !preg_match('#^https?://#i', $target)) {
                return redirect()->to($target)->with('success', $msg);
            }
            return redirect()
                ->route('report.npk', ['angkatan_id' => $angkatanId])
                ->with('success', $msg);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal menghapus periode: ' . $e->getMessage());
        }
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
        $filename    = ExportFile::name($angkatan, 'NPK', $periode?->label ?? '');
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