<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, NilaiAkademik, NilaiSamapta, NilaiKepribadian, KompilasiNilai, PeriodeNilai, Skadik};
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

        $angkatan = Angkatan::with('skadik.lemdik')->find($angkatanId);
        if (!$angkatan) {
            return view('kompilasi.index', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId') + ['angkatan' => null, 'data' => collect(), 'stats' => []]);
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

        return view('kompilasi.index', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'angkatan', 'data', 'stats'));
    }

    public function proses(Request $request)
    {
        $request->validate([
            'angkatan_id'       => 'required|exists:angkatan,id',
            'bobot_akademik'    => 'required|numeric|min:0|max:100',
            'bobot_kepribadian' => 'required|numeric|min:0|max:100',
            'bobot_samapta'     => 'required|numeric|min:0|max:100',
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

        $pesertaList = PesertaDidik::where('angkatan_id', $angkatanId)->orderBy('nama')->get();
        $hasil = [];

        DB::beginTransaction();
        try {
            foreach ($pesertaList as $peserta) {
                // Nilai Akademik (NPA) — langsung ambil NPA yang sudah ada
                $na = NilaiAkademik::where('peserta_didik_id', $peserta->id)
                    ->where('angkatan_id', $angkatanId)->first();

                $nilaiAkademik = $na ? round($na->npa, 2) : 0;
                $akademikId = $na?->id;

                // Nilai Kepribadian (rata-rata semua periode)
                $nkAvg = NilaiKepribadian::where('peserta_didik_id', $peserta->id)
                    ->avg('nilai_akhir') ?? 0;
                $nilaiKepribadian = round($nkAvg, 2);

                // Nilai Samapta (NPS - nilai_akhir)
                $ns = NilaiSamapta::where('peserta_didik_id', $peserta->id)
                    ->where('angkatan_id', $angkatanId)->first();
                $nilaiSamapta = $ns ? $ns->nilai_akhir : 0;

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
            ->with('success', 'Kompilasi nilai berhasil diproses untuk ' . count($hasil) . ' peserta.');
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
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('Kompilasi Nilai');

        $sh->mergeCells('A1:I1');
        $sh->setCellValue('A1', 'NILAI PRESTASI PENDIDIKAN (NPP) — ' . $angkatan?->skadik?->nama . ' Angkatan ' . $angkatan?->nomor_angkatan);
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $sh->mergeCells('A2:I2');
        $sh->setCellValue('A2', 'A=' . ($data->first()?->bobot_akademik ?? 70) . '% | K=' . ($data->first()?->bobot_kepribadian ?? 20) . '% | S=' . ($data->first()?->bobot_samapta ?? 10) . '% | Dicetak: ' . now()->format('d/m/Y H:i'));
        $sh->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        $headers = ['Rank', 'NRP', 'Pangkat', 'Nama', 'N. Akademik (' . ($data->first()?->bobot_akademik ?? 70) . '%)', 'N. Kepribadian (' . ($data->first()?->bobot_kepribadian ?? 20) . '%)', 'N. Samapta (' . ($data->first()?->bobot_samapta ?? 10) . '%)', 'NPP', 'Predikat'];
        foreach ($headers as $col => $h) {
            $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sh->setCellValue($c . '4', $h);
        }
        $sh->getStyle('A4:I4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '6366F1']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);

        $sh->getColumnDimension('A')->setWidth(6);
        $sh->getColumnDimension('B')->setWidth(14);
        $sh->getColumnDimension('C')->setWidth(12);
        $sh->getColumnDimension('D')->setWidth(32);
        $sh->getColumnDimension('E')->setWidth(18);
        $sh->getColumnDimension('F')->setWidth(18);
        $sh->getColumnDimension('G')->setWidth(18);
        $sh->getColumnDimension('H')->setWidth(12);
        $sh->getColumnDimension('I')->setWidth(12);

        $row = 5;
        foreach ($data as $d) {
            $sh->setCellValue("A{$row}", $d->rank);
            $sh->setCellValue("B{$row}", $d->peserta->nrp);
            $sh->setCellValue("C{$row}", $d->peserta->pangkat);
            $sh->setCellValue("D{$row}", $d->peserta->nama);
            $sh->setCellValue("E{$row}", $d->nilai_akademik);
            $sh->setCellValue("F{$row}", $d->nilai_kepribadian);
            $sh->setCellValue("G{$row}", $d->nilai_samapta);
            $sh->setCellValue("H{$row}", $d->nilai_akhir);
            $sh->setCellValue("I{$row}", $d->predikat_huruf);

            // Color code rank
            if ($d->rank === 1) {
                $sh->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FEF3C7');
                $sh->getStyle("H{$row}")->getFont()->setBold(true);
            } elseif ($d->rank === 2) {
                $sh->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F3F4F6');
            } elseif ($d->rank === 3) {
                $sh->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FEF9C3');
            }

            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(function() use ($writer) { $writer->save('php://output'); },
            "NPP_Kompilasi_{$angkatan?->skadik?->nama}_Angkatan_{$angkatan?->nomor_angkatan}.xlsx");
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
