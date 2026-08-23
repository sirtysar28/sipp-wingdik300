<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, NilaiAkademik, Skadik, User, MataPelajaran};
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

    public function import(Request $request)
    {
        $request->validate([
            'angkatan_id' => 'required|exists:angkatan,id',
            'file'        => 'required|file|extensions:xlsx,xls',
        ]);

        $angkatanId = $request->angkatan_id;
        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        // Ambil mata pelajaran dari DB untuk perhitungan NPA yang benar
        $angkatan = Angkatan::with('skadik')->find($angkatanId);
        $subjek = ($angkatan && $angkatan->skadik_id)
            ? $this->getSubjek($angkatan->skadik_id)
            : collect();
        $totalHN = $this->getTotalHargaNilai($subjek);

        $imported = 0;
        $highestRow = $sheet->getHighestRow();

        for ($row = 17; $row <= $highestRow; $row++) {
            $nrp  = trim((string)$sheet->getCellByColumnAndRow(4, $row)->getValue());
            $nama = trim((string)$sheet->getCellByColumnAndRow(2, $row)->getValue());
            $pangkat = trim((string)$sheet->getCellByColumnAndRow(3, $row)->getValue());

            if (empty($nrp) || empty($nama)) continue;

            $peserta = PesertaDidik::where('nrp', $nrp)->first();
            if (!$peserta) {
                $peserta = PesertaDidik::where('angkatan_id', $angkatanId)
                    ->where('nama', 'LIKE', '%' . $nama . '%')
                    ->first();
            }
            if (!$peserta) continue;

            // Collect detail values (cols E to V = 5 to 22)
            $detailNilai = [];
            $jumlah = 0;
            for ($col = 5; $col <= 22; $col++) {
                $val = (float)$sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
                $detailNilai[] = $val;
                $jumlah += $val;
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

        return redirect()->route('nilai-akademik.index', ['angkatan_id' => $angkatanId])
            ->with('success', "Berhasil mengimpor {$imported} data nilai akademik.");
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
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('NPA');

        $sh->mergeCells('A1:F1');
        $sh->setCellValue('A1', 'NILAI PRESTASI AKADEMI — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = ['No', 'NRP', 'Pangkat', 'Nama', 'Jumlah Nilai', 'NPA', 'Rank'];
        foreach ($headers as $col => $h) {
            $sh->setCellValue(chr(65 + $col) . '3', $h);
        }
        $sh->getStyle('A3:G3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '6366F1']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $sh->getColumnDimension('A')->setWidth(5);
        $sh->getColumnDimension('B')->setWidth(14);
        $sh->getColumnDimension('C')->setWidth(12);
        $sh->getColumnDimension('D')->setWidth(32);
        $sh->getColumnDimension('E')->setWidth(14);
        $sh->getColumnDimension('F')->setWidth(10);
        $sh->getColumnDimension('G')->setWidth(8);

        $rank = 1;
        foreach ($data as $d) {
            $r = $rank + 3;
            $sh->setCellValue("A{$r}", $rank);
            $sh->setCellValue("B{$r}", $d->peserta->nrp);
            $sh->setCellValue("C{$r}", $d->peserta->pangkat);
            $sh->setCellValue("D{$r}", $d->peserta->nama);
            $sh->setCellValue("E{$r}", $d->jumlah_nilai);
            $sh->setCellValue("F{$r}", $d->npa);
            $sh->setCellValue("G{$r}", $rank);
            $rank++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(function() use ($writer) { $writer->save('php://output'); },
            "NPA_{$angkatan?->nomor_angkatan}.xlsx");
    }
}
