<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, NilaiSamapta, PeriodeNps, Skadik};
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Alignment};
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NilaiSamaptaController extends Controller
{
    // ════════════════════════════════════════════════════════════════
    //  NPS — NILAI PRESTASI SAMAPTA (VERSI SEDERHANA)
    //  5 field manual, TANPA rumus:
    //    1. Jarak Lari (meter)
    //    2. Nilai Lari (Garjas A)
    //    3. Garjas B
    //    4. Nilai Akhir
    //    5. Nilai Konversi
    //  Input utama: bulk upload via template. Putaran membedakan input.
    // ════════════════════════════════════════════════════════════════

    // ════════════════════════════════════════════════════════════════
    //  PERIODE / PUTARAN NPS
    // ════════════════════════════════════════════════════════════════

    /** Form tambah putaran baru untuk NPS. */
    public function createPeriode(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id');
        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->orderBy('created_at', 'desc')->get();

        return view('nilai-samapta.create-putaran', compact('allSkadik', 'skadikId', 'allAngkatan'));
    }

    /** Simpan putaran baru NPS. */
    public function storePeriode(Request $request)
    {
        $data = $request->validate([
            'angkatan_id'      => 'required|exists:angkatan,id',
            'label'            => 'required|string|max:50',
            'tanggal_mulai'    => 'required|date',
            'tanggal_selesai'  => 'required|date|after:tanggal_mulai',
        ]);

        // Cek duplikasi label pada angkatan yang sama
        $exists = PeriodeNps::where('angkatan_id', $data['angkatan_id'])
            ->where('label', $data['label'])
            ->exists();
        if ($exists) {
            return back()->withInput()->with('error', "Label putaran \"{$data['label']}\" sudah ada untuk angkatan ini. Gunakan label lain.");
        }

        PeriodeNps::where('angkatan_id', $data['angkatan_id'])->update(['aktif' => false]);
        $data['aktif'] = true;
        PeriodeNps::create($data);

        return redirect()->route('report.nps', ['angkatan_id' => $data['angkatan_id']])
            ->with('success', "Putaran \"{$data['label']}\" berhasil dibuat.");
    }

    /** Halaman utama NPS: filter + tabel + area upload + template. */
    public function index(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $angkatanQuery = Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc');
        if ($skadikId) $angkatanQuery->where('skadik_id', $skadikId);
        $allAngkatan = $angkatanQuery->get();
        $angkatanId  = $request->get('angkatan_id', $allAngkatan->first()?->id);

        $angkatan = Angkatan::with('skadik.lemdik')->find($angkatanId);
        $putaranList = $angkatanId ? NilaiSamapta::getPutaranList($angkatanId) : [];
        $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));

        $data = collect();
        if ($angkatan) {
            $data = NilaiSamapta::with('peserta')
                ->where('angkatan_id', $angkatanId)
                ->where('putaran_label', $putaranLabel)
                ->orderByDesc('nilai_akhir')
                ->get();
        }

        return view('nilai-samapta.index', compact(
            'allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'angkatan', 'data', 'putaranLabel', 'putaranList'
        ));
    }

    /** Unduh template Excel (peserta pre-filled + 5 kolom kosong). */
    public function downloadTemplate(Request $request)
    {
        $angkatanId  = $request->get('angkatan_id');
        $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));
        $angkatan    = Angkatan::with('skadik.lemdik')->find($angkatanId);
        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();

        $spreadsheet = new Spreadsheet();
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('Template NPS');

        $headers = [
            'NRP',
            'Nama',
            'Pangkat',
            'Jarak Lari (meter)',
            'Nilai Lari (Garjas A)',
            'Garjas B',
            'Nilai Akhir',
            'Nilai Konversi',
        ];
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sh->setCellValue("{$col}1", $h);
            $sh->getStyle("{$col}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EA580C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ]);
        }
        $sh->getColumnDimension('A')->setWidth(18);
        $sh->getColumnDimension('B')->setWidth(32);
        $sh->getColumnDimension('C')->setWidth(16);
        $sh->getColumnDimension('D')->setWidth(20);
        $sh->getColumnDimension('E')->setWidth(22);
        $sh->getColumnDimension('F')->setWidth(14);
        $sh->getColumnDimension('G')->setWidth(14);
        $sh->getColumnDimension('H')->setWidth(16);
        $sh->getRowDimension(1)->setRowHeight(30);

        foreach ($pesertaList as $i => $p) {
            $r = $i + 2;
            $sh->setCellValue("A{$r}", $p->nrp);
            $sh->setCellValue("B{$r}", $p->nama);
            $sh->setCellValue("C{$r}", $p->pangkat);
        }

        // Keterangan pengisian
        $noteRow = $pesertaList->count() + 3;
        $sh->setCellValue("A{$noteRow}", "✓ Isi kolom D, E, F, G, H dengan data Anda. TIDAK ADA rumus — semua nilai diisi manual.");
        $sh->setCellValue("A" . ($noteRow + 1), "✓ Jangan mengubah / menghapus kolom A, B, C (data peserta).");
        $sh->setCellValue("A" . ($noteRow + 2), "✓ Putaran aktif saat upload: {$putaranLabel} (dapat diubah di halaman NPS).");
        $sh->getStyle("A{$noteRow}:A" . ($noteRow + 2))->getFont()->setItalic(true)->setSize(10);

        $writer = new Xlsx($spreadsheet);
        $filename = "Template_NPS_{$angkatan?->skadik?->kode}_Angk{$angkatan?->nomor_angkatan}_{$putaranLabel}.xlsx";
        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /** Bulk upload template NPS. Semua 5 field disimpan apa adanya (tanpa rumus). */
    public function import(Request $request)
    {
        $request->validate([
            'angkatan_id'   => 'required|exists:angkatan,id',
            'file'          => 'required|file|extensions:xlsx,xls',
            'putaran_label' => 'nullable|string|max:50',
        ]);

        $angkatanId   = $request->angkatan_id;
        $putaranLabel = NilaiSamapta::normalizePutaran($request->putaran_label);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();
        $highestRow  = $sheet->getHighestRow();

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $nrp  = trim((string) $sheet->getCellByColumnAndRow(1, $row)->getValue());
            $nama = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getValue());

            if ($nrp === '' && $nama === '') continue;

            $peserta = null;
            if ($nrp !== '') {
                $peserta = PesertaDidik::where('nrp', $nrp)->first();
            }
            if (!$peserta && $nama !== '') {
                $peserta = PesertaDidik::where('angkatan_id', $angkatanId)
                    ->where('nama', 'LIKE', '%' . $nama . '%')->first();
            }
            if (!$peserta) { $skipped++; $errors[] = "Baris {$row}: peserta tidak ditemukan"; continue; }

            $num = function (int $c) use ($sheet, $row) {
                $v = $sheet->getCellByColumnAndRow($c, $row)->getValue();
                return is_numeric($v) ? (float) $v : null;
            };

            NilaiSamapta::updateOrCreate(
                [
                    'peserta_didik_id' => $peserta->id,
                    'angkatan_id'      => $angkatanId,
                    'putaran_label'    => $putaranLabel,
                ],
                [
                    'jarak_lari'     => $num(4),  // D
                    'nilai_lari'     => $num(5),  // E
                    'garjas_b_nilai' => $num(6),  // F
                    'nilai_akhir'    => $num(7),  // G
                    'nilai_konversi' => $num(8),  // H
                    'input_oleh'     => auth()->id(),
                ]
            );
            $imported++;
        }

        $msg = "Berhasil mengimpor {$imported} data NPS (Putaran: {$putaranLabel}).";
        if ($skipped > 0) $msg .= " {$skipped} peserta tidak ditemukan.";

        return redirect()->route('nilai-samapta.index', [
            'angkatan_id'   => $angkatanId,
            'putaran_label' => $putaranLabel,
        ])->with('success', $msg);
    }

    /** Form input manual satu peserta (5 field). */
    public function manualForm(Request $request)
    {
        $angkatanId   = $request->get('angkatan_id');
        $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));
        $pesertaList  = $angkatanId
            ? PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get()
            : collect();
        $allAngkatan  = Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get();
        $putaranList  = $angkatanId ? NilaiSamapta::getPutaranList($angkatanId) : [NilaiSamapta::PUTARAN_DEFAULT];

        return view('nilai-samapta.manual', compact('allAngkatan', 'angkatanId', 'pesertaList', 'putaranLabel', 'putaranList'));
    }

    /** Simpan input manual satu peserta (5 field, tanpa rumus). */
    public function manualStore(Request $request)
    {
        $validated = $request->validate([
            'angkatan_id'      => 'required|exists:angkatan,id',
            'peserta_didik_id' => 'required|exists:peserta_didik,id',
            'putaran_label'    => 'nullable|string|max:50',
            'jarak_lari'       => 'nullable|numeric|min:0',
            'nilai_lari'       => 'nullable|numeric|min:0',
            'garjas_b_nilai'   => 'nullable|numeric|min:0',
            'nilai_akhir'      => 'nullable|numeric|min:0',
            'nilai_konversi'   => 'nullable|numeric|min:0',
        ]);

        $putaranLabel = NilaiSamapta::normalizePutaran($validated['putaran_label']);

        NilaiSamapta::updateOrCreate(
            [
                'peserta_didik_id' => $validated['peserta_didik_id'],
                'angkatan_id'      => $validated['angkatan_id'],
                'putaran_label'    => $putaranLabel,
            ],
            [
                'jarak_lari'     => $validated['jarak_lari'],
                'nilai_lari'     => $validated['nilai_lari'],
                'garjas_b_nilai' => $validated['garjas_b_nilai'],
                'nilai_akhir'    => $validated['nilai_akhir'],
                'nilai_konversi' => $validated['nilai_konversi'],
                'input_oleh'     => auth()->id(),
            ]
        );

        return redirect()->route('nilai-samapta.index', [
            'angkatan_id'   => $validated['angkatan_id'],
            'putaran_label' => $putaranLabel,
        ])->with('success', "Nilai NPS berhasil disimpan (Putaran: {$putaranLabel}).");
    }

    /** Form edit satu record NPS (5 field). */
    public function editForm(Request $request)
    {
        $angkatanId   = $request->get('angkatan_id');
        $pesertaId    = $request->get('peserta_id');
        $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));

        $nilai = NilaiSamapta::where('angkatan_id', $angkatanId)
            ->where('peserta_didik_id', $pesertaId)
            ->where('putaran_label', $putaranLabel)
            ->with('peserta')
            ->first();

        $putaranList = NilaiSamapta::getPutaranList($angkatanId);

        return view('nilai-samapta.edit', compact('nilai', 'angkatanId', 'pesertaId', 'putaranLabel', 'putaranList'));
    }

    /** Simpan perubahan edit (5 field, tanpa rumus). */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'nilai_id'         => 'required|exists:nilai_samapta,id',
            'jarak_lari'       => 'nullable|numeric|min:0',
            'nilai_lari'       => 'nullable|numeric|min:0',
            'garjas_b_nilai'   => 'nullable|numeric|min:0',
            'nilai_akhir'      => 'nullable|numeric|min:0',
            'nilai_konversi'   => 'nullable|numeric|min:0',
        ]);

        $nilai = NilaiSamapta::find($validated['nilai_id']);
        $nilai->update([
            'jarak_lari'     => $validated['jarak_lari'],
            'nilai_lari'     => $validated['nilai_lari'],
            'garjas_b_nilai' => $validated['garjas_b_nilai'],
            'nilai_akhir'    => $validated['nilai_akhir'],
            'nilai_konversi' => $validated['nilai_konversi'],
            'input_oleh'     => auth()->id(),
        ]);

        return redirect()->route('nilai-samapta.index', [
            'angkatan_id'   => $nilai->angkatan_id,
            'putaran_label' => $nilai->putaran_label,
        ])->with('success', 'Nilai NPS berhasil diperbarui.');
    }

    /** Hapus data NPS. Bila putaran_label diisi → hapus putaran itu saja. */
    public function destroy(Request $request)
    {
        $angkatanId   = $request->get('angkatan_id');
        $putaranLabel = $request->get('putaran_label');

        $query = NilaiSamapta::query();
        if ($angkatanId) $query->where('angkatan_id', $angkatanId);
        if ($putaranLabel && $putaranLabel !== 'all') {
            $query->where('putaran_label', NilaiSamapta::normalizePutaran($putaranLabel));
        }
        $count = $query->count();
        $query->delete();

        $msg = $putaranLabel && $putaranLabel !== 'all'
            ? "Data NPS Putaran {$putaranLabel} ({$count} record) berhasil dihapus."
            : "Semua data NPS angkatan ini ({$count} record) berhasil dihapus.";

        return back()->with('success', $msg);
    }

    /** Ekspor data NPS (5 field) ke Excel. */
    public function ekspor(Request $request)
    {
        $angkatanId   = $request->get('angkatan_id');
        $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));
        $angkatan     = Angkatan::with('skadik.lemdik')->find($angkatanId);

        $data = NilaiSamapta::with('peserta')
            ->where('angkatan_id', $angkatanId)
            ->where('putaran_label', $putaranLabel)
            ->orderByDesc('nilai_akhir')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sh = $spreadsheet->getActiveSheet()->setTitle('NPS');

        $sh->mergeCells('A1:J1');
        $sh->setCellValue('A1', 'NILAI PRESTASI SAMAPTA (NPS) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan . ' — ' . $putaranLabel);
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EA580C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = ['Rank', 'NRP', 'Pangkat', 'Nama', 'Jarak Lari (m)', 'Nilai Lari (Garjas A)', 'Garjas B', 'Nilai Akhir', 'Nilai Konversi', 'Predikat'];
        foreach ($headers as $i => $h) {
            $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sh->setCellValue("{$c}3", $h);
        }
        $sh->getStyle('A3:J3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F97316']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);

        $widths = [6, 16, 14, 32, 14, 18, 12, 12, 14, 14];
        foreach ($widths as $i => $w) {
            $sh->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);
        }

        foreach ($data as $i => $d) {
            $r = $i + 4;
            $sh->setCellValue("A{$r}", $i + 1);
            $sh->setCellValue("B{$r}", $d->peserta->nrp);
            $sh->setCellValue("C{$r}", $d->peserta->pangkat);
            $sh->setCellValue("D{$r}", $d->peserta->nama);
            $sh->setCellValue("E{$r}", $d->jarak_lari);
            $sh->setCellValue("F{$r}", $d->nilai_lari);
            $sh->setCellValue("G{$r}", $d->garjas_b_nilai);
            $sh->setCellValue("H{$r}", $d->nilai_akhir);
            $sh->setCellValue("I{$r}", $d->nilai_konversi);
            $sh->setCellValue("J{$r}", $d->predikat['label']);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "NPS_{$angkatan?->skadik?->kode}_Angk{$angkatan?->nomor_angkatan}_{$putaranLabel}.xlsx";
        return response()->streamDownload(fn() => $writer->save('php://output'), $filename);
    }

    /** Cetak (print) data NPS (5 field). */
    public function cetak(Request $request)
    {
        $angkatanId   = $request->get('angkatan_id');
        $putaranLabel = NilaiSamapta::normalizePutaran($request->get('putaran_label'));
        $angkatan     = Angkatan::with('skadik.lemdik')->find($angkatanId);

        if (!$angkatan) {
            return redirect()->route('nilai-samapta.index')->with('error', 'Angkatan tidak ditemukan.');
        }

        $data = NilaiSamapta::with('peserta')
            ->where('angkatan_id', $angkatanId)
            ->where('putaran_label', $putaranLabel)
            ->orderByDesc('nilai_akhir')
            ->get();

        return view('nilai-samapta.cetak', compact('angkatan', 'data', 'putaranLabel'));
    }
}
