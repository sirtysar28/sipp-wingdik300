<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, NilaiAkademik, Skadik, User, MataPelajaran};
use App\Services\ExportFile;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class NilaiAkademikController extends Controller
{
    /**
     * Ambil mata pelajaran yang dipetakan ke sekolah (via pivot), urut per-pivot.
     */
    private function getSubjek(int $skadikId)
    {
        return MataPelajaran::forSkadik($skadikId, true);
    }

    /**
     * Hitung total bobot (Σ bobot) dari subjek
     */
    private function getTotalBobot($subjek): int
    {
        return $subjek->sum('bobot');
    }

    /**
     * Hitung total Harga Nilai (Σ HN) dari subjek
     * HN = kolom harga_nilai di DB (manual), fallback = JP × Bobot
     */
    private function getTotalHargaNilai($subjek): int
    {
        return $subjek->sum(function ($s) {
            return $s->harga_nilai_calc;
        });
    }

    public function index(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $angkatanQuery = Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc');
        if ($skadikId) $angkatanQuery->where('skadik_id', $skadikId);
        $allAngkatan = $angkatanQuery->get();
        $angkatanId  = $request->get('angkatan_id', $allAngkatan->first()?->id);

        $angkatan = Angkatan::with('skadik.lemdik')->find($angkatanId);
        if (!$angkatan) {
            return view('nilai-akademik.index', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId') + ['angkatan' => null, 'data' => collect()]);
        }

        // Daftar BERBASIS PESERTA (bukan record) agar konsisten dengan NPK & NPS:
        // semua peserta angkatan tampil, termasuk yang belum diberi NPA. Ini
        // menyelesaikan issue “jumlah siswa NPA tidak sama dengan NPK/NPS”.
        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
        $nilaiMap = NilaiAkademik::where('angkatan_id', $angkatanId)
            ->get()->keyBy('peserta_didik_id');

        $data = $pesertaList->map(function ($p) use ($nilaiMap) {
            $na = $nilaiMap->get($p->id);
            if ($na) {
                $na->setRelation('peserta', $p);
                $na->sudah_input = true;
                return $na;
            }
            return (object)[
                'peserta'            => $p,
                'peserta_didik_id'   => $p->id,
                'npa'                => null,
                'jumlah_nilai'       => null,
                'detail_nilai'       => [],
                'sudah_input'        => false,
            ];
        })
        // NPA null diurutkan terakhir (pakai -1 sebagai pengganti null).
        ->sortByDesc(fn($d) => $d->npa ?? -1)->values();

        return view('nilai-akademik.index', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'angkatan', 'data'));
    }

    public function importForm(Request $request)
    {
        $allAngkatan = Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get();
        return view('nilai-akademik.import', compact('allAngkatan'));
    }

    public function manualForm(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $allAngkatan = Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get();
        // Tampilkan SEMUA peserta angkatan (konsisten dengan NPK & NPS),
        // beserta nilai yg sudah ada agar bisa diisi / diperbarui.
        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)
            ->with(['nilaiAkademik' => fn($q) => $q->where('angkatan_id', $angkatanId)])
            ->orderBy('nama')->get();

        // Ambil angkatan untuk dapatkan skadik_id
        $angkatan = $angkatanId ? Angkatan::with('skadik')->find($angkatanId) : null;
        $skadikId = $angkatan?->skadik_id;

        // Ambil mata pelajaran dari DB
        $subjek = $skadikId ? $this->getSubjek($skadikId) : collect();
        $totalBobot = $this->getTotalBobot($subjek);
        $totalHN = $this->getTotalHargaNilai($subjek);

        return view('nilai-akademik.manual', compact(
            'allAngkatan', 'angkatanId', 'pesertaList', 'subjek', 'totalBobot', 'totalHN', 'angkatan'
        ));
    }

    public function manualStore(Request $request)
    {
        $request->validate([
            'angkatan_id'      => 'required|exists:angkatan,id',
            'peserta_didik_id' => 'required|exists:peserta_didik,id',
            'nilai.*'         => 'nullable|numeric|min:0|max:100',
        ]);

        $angkatan = Angkatan::with('skadik')->find($request->angkatan_id);
        $skadikId = $angkatan?->skadik_id;

        if (!$skadikId) {
            return back()->with('error', 'Angkatan tidak memiliki sekolah terkait.');
        }

        $subjek = $this->getSubjek($skadikId);
        if ($subjek->isEmpty()) {
            return back()->with('error', 'Belum ada mata pelajaran yang dikonfigurasi untuk sekolah ini. Atur terlebih dahulu di menu <a href="' . route('mata-pelajaran.index', ['skadik_id' => $skadikId]) . '" style="color:#4f46e5;text-decoration:underline">Manage Mata Pelajaran</a>.');
        }

        $nilai = $request->input('nilai', []);
        $totalBobot = $this->getTotalBobot($subjek);
        $totalHN = $this->getTotalHargaNilai($subjek);

        $detailNilai = [];
        $jumlahNilai = 0;
        $totalHargaInput = 0;

        foreach ($subjek as $idx => $s) {
            $val = isset($nilai[$idx]) ? (float)$nilai[$idx] : 0;
            $detailNilai[] = round($val, 2);
            $jumlahNilai += $val;
            // HN = harga_nilai dari DB (manual), fallback = JP × Bobot
            $hargaNilai = $s->harga_nilai_calc;
            $totalHargaInput += $val * $hargaNilai;
        }

        // NPA = Σ(MP × HN) / Σ(HN)
        $npa = $totalHN > 0 ? round(($totalHargaInput / $totalHN), 2) : 0;

        NilaiAkademik::updateOrCreate(
            ['peserta_didik_id' => $request->peserta_didik_id, 'angkatan_id' => $request->angkatan_id],
            [
                'detail_nilai' => $detailNilai,
                'jumlah_nilai' => round($jumlahNilai, 2),
                'npa'          => min($npa, 100),
                'rank'         => 0,
                'input_oleh'   => auth()->id(),
            ]
        );

        return redirect()->route('nilai-akademik.index', ['angkatan_id' => $request->angkatan_id])
            ->with('success', 'Nilai akademik berhasil disimpan. NPA = Σ(MP × HN) / Σ(HN) = ' . min($npa, 100));
    }

    /**
     * Baca isi sel sebagai string — angka besar (mis. NRP 16 digit) dan notasi
     * ilmiah dinormalisasi menjadi digit penuh agar cocok saat dicocokkan ke DB.
     */
    private function cellString($sheet, int $col, int $row): string
    {
        return ExportFile::digitText($sheet->getCellByColumnAndRow($col, $row)->getValue());
    }

    public function import(Request $request)
    {
        $request->validate([
            'angkatan_id' => 'required|exists:angkatan,id',
            'file'        => 'required|file|extensions:xlsx,xls',
        ]);

        $angkatanId = $request->angkatan_id;
        $angkatan   = Angkatan::with('skadik')->find($angkatanId);

        // Ambil mata pelajaran dari DB untuk perhitungan NPA yang benar
        $subjek = ($angkatan && $angkatan->skadik_id)
            ? $this->getSubjek($angkatan->skadik_id)
            : collect();

        if ($subjek->isEmpty()) {
            return back()->with('error', 'Belum ada mata pelajaran yang dikonfigurasi untuk sekolah ini. Atur terlebih dahulu di menu Manage Mata Pelajaran.');
        }
        $totalHN = $this->getTotalHargaNilai($subjek);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'File bukan Excel yang valid / tidak dapat dibaca.');
        }
        $sheet = $spreadsheet->getActiveSheet();

        /* ════════════════════════════════════════════════════════════
           Revisi 21 September 2026 — BULK UPLOAD NPA FLEKSIBEL.
           Dulu import hanya membaca format kaku (data mulai baris 17,
           kolom B/C/D tetap), sehingga gagal ketika file memiliki baris
           info JP / B / HN di atasnya. Sekarang posisi kolom & baris awal
           data dideteksi OTOMATIS dari baris header, sehingga file berikut
           semuanya diterima:
             1. Template bulk upload standar  (NO | NAMA | PKT | NRP | mapel…)
             2. Hasil ekspor "Report NPA"     (header vertikal + baris JP/B/HN)
             3. Format lama tanpa header      (fallback: data mulai baris 17)
           ════════════════════════════════════════════════════════════ */
        $highestRow  = $sheet->getHighestDataRow();
        $lastColIdx  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $sheet->getHighestDataColumn()
        );

        // Header yang BUKAN kolom nilai mapel (abaikan saat membaca nilai)
        $nonNilai = ['JUMLAH', 'JUMLAH NILAI', 'JML', 'NPA', 'RANK', 'RNKG', 'TOTAL', 'Σ', 'Σ(MP×HN)', 'KETERANGAN'];

        // ── 1) Cari baris header yang memuat NAMA + NRP ──
        $headerRow = null;
        $colNama = $colPangkat = $colNrp = null;
        for ($r = 1; $r <= min($highestRow, 30); $r++) {
            $cNama = $cPkt = $cNrp = null;
            for ($c = 1; $c <= $lastColIdx; $c++) {
                $v = strtoupper(trim((string) $sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($v === '') continue;
                if ($cNama === null && in_array($v, ['NAMA', 'NAMA SISWA', 'NAMA PESERTA'])) $cNama = $c;
                elseif ($cNrp === null && $v === 'NRP') $cNrp = $c;
                elseif ($cPkt === null && in_array($v, ['PANGKAT', 'PKT', 'PGKT'])) $cPkt = $c;
            }
            if ($cNama !== null && $cNrp !== null) {
                $headerRow  = $r;
                $colNama    = $cNama;
                $colPangkat = $cPkt;
                $colNrp     = $cNrp;
                break;
            }
        }

        // ── 2) Tentukan kolom nilai mapel + baris awal data ──
        $nilaiCols = [];
        if ($headerRow !== null) {
            $startCol = max($colNama, $colPangkat ?? 0, $colNrp) + 1;
            for ($c = $startCol; $c <= $lastColIdx && count($nilaiCols) < $subjek->count(); $c++) {
                $h = strtoupper(trim((string) $sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
                $skip = false;
                foreach ($nonNilai as $kw) {
                    if ($h !== '' && str_contains($h, $kw)) { $skip = true; break; }
                }
                if (!$skip) $nilaiCols[] = $c;
            }

            // Baris data pertama = baris pertama setelah header yang berisi
            // NAMA / NRP — baris info JP/B/HN (sel identitas kosong) otomatis
            // dilewati, termasuk header 2 baris pada file Report NPA.
            $dataStart = $headerRow + 1;
            while ($dataStart <= $highestRow
                && $this->cellString($sheet, $colNama, $dataStart) === ''
                && $this->cellString($sheet, $colNrp, $dataStart) === '') {
                $dataStart++;
            }
        } else {
            // Format lama: tanpa header terdeteksi → posisi tetap seperti awal
            $colNama    = 2;
            $colPangkat = 3;
            $colNrp     = 4;
            $dataStart  = 17;
            for ($c = 5; $c <= 22; $c++) $nilaiCols[] = $c;
        }

        if (count($nilaiCols) === 0) {
            $nilaiCols = range(5, 4 + $subjek->count());
        }

        $imported = 0;
        $dilewati = 0;
        for ($row = $dataStart; $row <= $highestRow; $row++) {
            $nrp  = $this->cellString($sheet, $colNrp, $row);
            $nama = $this->cellString($sheet, $colNama, $row);
            $pangkat = $this->cellString($sheet, $colPangkat ?? ($colNrp - 1), $row);

            if ($nrp === '' && $nama === '') continue; // baris kosong

            // Cocokkan peserta: NRP di angkatan → NRP global → nama persis → nama mirip
            $peserta = PesertaDidik::where('angkatan_id', $angkatanId)->where('nrp', $nrp)->first();
            if (!$peserta && $nrp !== '') {
                $peserta = PesertaDidik::where('nrp', $nrp)->first();
            }
            if (!$peserta && $nama !== '') {
                $peserta = PesertaDidik::where('angkatan_id', $angkatanId)
                    ->where('nama', $nama)->first();
            }
            if (!$peserta && $nama !== '') {
                $peserta = PesertaDidik::where('angkatan_id', $angkatanId)
                    ->where('nama', 'LIKE', '%' . $nama . '%')->first();
            }
            if (!$peserta) { $dilewati++; continue; }

            // Ambil nilai per mata pelajaran dari kolom nilai yang terdeteksi.
            // Baris yang SEMUA kolom nilainya kosong dilewati supaya nilai
            // yang sudah tersimpan tidak tertimpa 0 oleh baris kosong.
            $detailNilai = [];
            $jumlah = 0;
            $adaIsi = false;
            foreach ($nilaiCols as $i => $c) {
                $raw = $sheet->getCellByColumnAndRow($c, $row)->getCalculatedValue();
                if ($raw !== null && $raw !== '') $adaIsi = true;
                $val = (float) $raw;
                $detailNilai[] = $val;
                $jumlah += $val;
            }
            if (!$adaIsi) continue;
            // Mapel yang kolomnya tidak ada di file → 0
            while (count($detailNilai) < $subjek->count()) {
                $detailNilai[] = 0;
            }

            // Hitung NPA dengan formula benar: Σ(Nilai × HN) / Σ(HN)
            $totalHargaInput = 0;
            foreach ($subjek as $idx => $s) {
                $val = $detailNilai[$idx] ?? 0;
                $totalHargaInput += $val * $s->harga_nilai_calc;
            }
            $npa = $totalHN > 0 ? min(round(($totalHargaInput / $totalHN), 2), 100) : 0;

            NilaiAkademik::updateOrCreate(
                ['peserta_didik_id' => $peserta->id, 'angkatan_id' => $angkatanId],
                [
                    'detail_nilai' => $detailNilai,
                    'jumlah_nilai' => round($jumlah, 2),
                    'npa'          => $npa,
                    'rank'         => 0,
                    'input_oleh'   => auth()->id(),
                ]
            );
            $imported++;
        }

        $msg = "Berhasil mengimpor {$imported} data nilai akademik.";
        if ($dilewati > 0) {
            $msg .= " {$dilewati} baris dilewati (peserta tidak ditemukan di angkatan ini).";
        }

        return redirect()->route('nilai-akademik.index', ['angkatan_id' => $angkatanId])
            ->with('success', $msg);
    }

    /**
     * Download TEMPLATE bulk upload NPA — format sederhana seperti awal:
     * NO | NAMA | PKT | NRP | [kolom mata pelajaran] | JUMLAH NILAI | NPA | RANK
     * Baris peserta sudah terisi otomatis; Admin hanya mengisi kolom nilai.
     * (Revisi 21 Sept 2026: template polos hitam-putih + font Arial.)
     */
    public function downloadTemplate(Request $request)
    {
        $angkatan = Angkatan::with('skadik')->find($request->get('angkatan_id'));
        $skadikId = $angkatan?->skadik_id;

        $subjek = $skadikId ? $this->getSubjek($skadikId) : collect();
        if ($subjek->isEmpty()) {
            return back()->with('error', 'Belum ada mata pelajaran yang dikonfigurasi untuk sekolah ini. Atur terlebih dahulu di menu Manage Mata Pelajaran.');
        }

        $pesertaList = PesertaDidik::where('angkatan_id', $angkatan?->id)->orderBy('nama')->get();
        $nilaiMap    = NilaiAkademik::where('angkatan_id', $angkatan?->id)->get()->keyBy('peserta_didik_id');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template NPA');

        $colName = fn(int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $nMapel  = $subjek->count();
        $colJml  = 5 + $nMapel + 1; // kolom JUMLAH NILAI

        // ── Header (baris 1) ──
        $headers = array_merge(
            ['NO', 'NAMA', 'PKT', 'NRP'],
            $subjek->map(fn($s) => strtoupper($s->nama))->all(),
            ['JUMLAH NILAI', 'NPA', 'RANK']
        );
        foreach ($headers as $i => $h) {
            $c = $colName($i + 1);
            $sheet->setCellValue("{$c}1", $h);
            $sheet->getStyle("{$c}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 10],
                'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(30);

        // ── Data peserta (nilai yang sudah pernah diinput ikut terisi) ──
        foreach ($pesertaList as $i => $p) {
            $r = $i + 2;
            $na = $nilaiMap->get($p->id);
            $detail = $na && is_array($na->detail_nilai) ? $na->detail_nilai : [];

            $sheet->setCellValue("A{$r}", $i + 1);
            $sheet->setCellValue("B{$r}", $p->nama);
            $sheet->setCellValue("C{$r}", $p->pangkat);
            ExportFile::setText($sheet, "D{$r}", $p->nrp);
            foreach ($subjek as $mi => $s) {
                $c = $colName(5 + $mi);
                $sheet->setCellValue("{$c}{$r}", $detail[$mi] ?? '');
            }
            $sheet->setCellValue($colName($colJml) . "{$r}", $na?->jumlah_nilai ?? '');
            $sheet->setCellValue($colName($colJml + 1) . "{$r}", $na?->npa ?? '');
            $sheet->setCellValue($colName($colJml + 2) . "{$r}", $na?->rank ?: '');

            $lastC = $colName($colJml + 2);
            $sheet->getStyle("A{$r}:{$lastC}{$r}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($colName(5) . "{$r}:{$lastC}{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($r)->setRowHeight(18);
        }

        // ── Lebar kolom ──
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(32);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(20);
        foreach ($subjek as $mi => $s) {
            $sheet->getColumnDimension($colName(5 + $mi))->setWidth(12);
        }
        for ($c = $colJml; $c <= $colJml + 2; $c++) {
            $sheet->getColumnDimension($colName($c))->setWidth(13);
        }

        // ── Catatan di bawah tabel ──
        $noteRow = $pesertaList->count() + 3;
        $lastC   = $colName($colJml + 2);
        $sheet->mergeCells("A{$noteRow}:{$lastC}{$noteRow}");
        $sheet->setCellValue("A{$noteRow}", '* Isi HANYA kolom nilai mata pelajaran (0-100). Kolom JUMLAH NILAI / NPA / RANK dihitung otomatis sistem saat diimpor.');
        $sheet->mergeCells("A" . ($noteRow + 1) . ":{$lastC}" . ($noteRow + 1));
        $sheet->setCellValue("A" . ($noteRow + 1), '* Jangan mengubah / menghapus kolom NAMA, PKT, dan NRP — data dicocokkan berdasarkan NRP.');
        $sheet->getStyle("A{$noteRow}:A" . ($noteRow + 1))->getFont()->setItalic(true)->setSize(9);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            ExportFile::name($angkatan, 'Template NPA'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function editForm(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $pesertaId  = $request->get('peserta_id');

        $nilai = NilaiAkademik::where('angkatan_id', $angkatanId)
            ->where('peserta_didik_id', $pesertaId)
            ->with('peserta')->first();

        $angkatan = $angkatanId ? Angkatan::with('skadik')->find($angkatanId) : null;
        $skadikId = $angkatan?->skadik_id;

        // Ambil mata pelajaran dari DB (termasuk nonaktif, karena data sudah ada)
        $subjek = $skadikId
            ? MataPelajaran::forSkadik($skadikId, false)
            : collect();
        $totalBobot = $this->getTotalBobot($subjek);
        $totalHN = $this->getTotalHargaNilai($subjek);

        return view('nilai-akademik.edit', compact('nilai', 'angkatanId', 'subjek', 'totalBobot', 'totalHN'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'nilai_id'    => 'required|exists:nilai_akademik,id',
            'nilai.*'     => 'nullable|numeric|min:0|max:100',
        ]);

        $nilaiRecord = NilaiAkademik::find($request->nilai_id);
        $angkatan = $nilaiRecord->angkatan;
        $skadikId = $angkatan?->skadik_id;

        $subjek = $skadikId ? $this->getSubjek($skadikId) : collect();
        $totalBobot = $this->getTotalBobot($subjek);
        $totalHN = $this->getTotalHargaNilai($subjek);

        $nilaiInput = $request->input('nilai', []);

        $detailNilai = [];
        $totalHargaInput = 0;
        foreach ($subjek as $idx => $s) {
            $val = isset($nilaiInput[$idx]) ? (float)$nilaiInput[$idx] : 0;
            $detailNilai[] = round($val, 2);
            // HN = harga_nilai dari DB (manual), fallback = JP × Bobot
            $hargaNilai = $s->harga_nilai_calc;
            $totalHargaInput += $val * $hargaNilai;
        }

        // NPA = Σ(MP × HN) / Σ(HN)
        $npa = $totalHN > 0 ? min(round(($totalHargaInput / $totalHN), 2), 100) : 0;

        $nilaiRecord->update([
            'detail_nilai' => $detailNilai,
            'jumlah_nilai' => round(array_sum($detailNilai), 2),
            'npa'          => $npa,
            'input_oleh'   => auth()->id(),
        ]);

        return back()->with('success', 'Nilai akademik berhasil diperbarui. NPA = ' . $npa);
    }

    public function destroy(Request $request)
    {
        NilaiAkademik::where('angkatan_id', $request->angkatan_id)->delete();
        return back()->with('success', 'Semua nilai akademik angkatan ini berhasil dihapus.');
    }

    /**
     * Recalculate semua NPA angkatan dengan rumus weighted average yang benar
     */
    public function recalculate(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $angkatan = Angkatan::with('skadik')->find($angkatanId);
        if (!$angkatan) {
            return back()->with('error', 'Angkatan tidak ditemukan.');
        }

        $skadikId = $angkatan->skadik_id;
        $subjek = MataPelajaran::forSkadik($skadikId, true);
        $totalBobot = $subjek->sum('bobot');
        $totalHN = $subjek->sum(fn($s) => $s->harga_nilai_calc);

        if ($subjek->isEmpty()) {
            return back()->with('error', 'Tidak ada mata pelajaran untuk sekolah ini. Atur dulu di Manage Mata Pelajaran.');
        }

        $data = NilaiAkademik::where('angkatan_id', $angkatanId)->get();
        $updated = 0;

        foreach ($data as $nilai) {
            $detail = is_array($nilai->detail_nilai) ? $nilai->detail_nilai : (json_decode($nilai->detail_nilai, true) ?: []);

            // NPA = Σ(MP × HN) / Σ(HN), dimana HN = JP × Bobot
            $totalHarga = 0;
            $jumlahNilai = 0;
            foreach ($subjek as $idx => $s) {
                $val = $detail[$idx] ?? 0;
                $hargaNilai = $s->harga_nilai_calc;
                $totalHarga += $val * $hargaNilai;
                $jumlahNilai += $val;
            }

            $npa = $totalHN > 0 ? min(round(($totalHarga / $totalHN), 2), 100) : 0;

            $nilai->update([
                'npa'          => $npa,
                'jumlah_nilai' => round($jumlahNilai, 2),
            ]);
            $updated++;
        }

        return back()->with('success', "Berhasil menghitung ulang NPA untuk {$updated} peserta. NPA = Σ(MP × HN) / Σ(HN)");
    }

    public function ekspor(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

        $data = NilaiAkademik::with('peserta')
            ->where('angkatan_id', $angkatanId)
            ->orderBy('npa', 'desc')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('NPA');

        $sh->mergeCells('A1:F1');
        $sh->setCellValue('A1', 'NILAI PRESTASI AKADEMI — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = ['No', 'Nama', 'Pangkat', 'NRP', 'Jumlah Nilai', 'NPA', 'Rank'];
        foreach ($headers as $col => $h) {
            $sh->setCellValue(chr(65 + $col) . '3', $h);
        }
        $sh->getStyle('A3:G3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $sh->getColumnDimension('A')->setWidth(5);
        $sh->getColumnDimension('B')->setWidth(32);
        $sh->getColumnDimension('C')->setWidth(12);
        $sh->getColumnDimension('D')->setWidth(14);
        $sh->getColumnDimension('E')->setWidth(14);
        $sh->getColumnDimension('F')->setWidth(10);
        $sh->getColumnDimension('G')->setWidth(8);

        $rank = 1;
        foreach ($data as $d) {
            $r = $rank + 3;
            $sh->setCellValue("A{$r}", $rank);
            $sh->setCellValue("B{$r}", $d->peserta->nama);
            $sh->setCellValue("C{$r}", $d->peserta->pangkat);
            ExportFile::setText($sh, "D{$r}", $d->peserta->nrp);
            $sh->setCellValue("E{$r}", $d->jumlah_nilai);
            $sh->setCellValue("F{$r}", $d->npa);
            $sh->setCellValue("G{$r}", $rank);
            $rank++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(function() use ($writer) { $writer->save('php://output'); },
            ExportFile::name($angkatan, 'NPA'));
    }
}
