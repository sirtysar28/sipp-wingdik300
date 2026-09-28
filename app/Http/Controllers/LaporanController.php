<?php

namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, KompilasiNilai, NilaiAkademik, NilaiKepribadian, NilaiSamapta, PeriodeNilai, Skadik, Penandatangan};
use App\Services\NppCalculator;
use App\Services\ExportFile;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, Font};

class LaporanController extends Controller
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
            return view('laporan.index', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId') + ['angkatan' => null, 'data' => collect()]);
        }

        // Gunakan perhitungan LIVE agar SEMUA peserta angkatan tampil (bulk),
        // tidak hanya yg sudah ada record di kompilasi_nilai.
        $data = NppCalculator::forAngkatan($angkatanId);

        return view('laporan.index', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'angkatan', 'data'));
    }

    /**
     * Cetak laporan individu per peserta — HTML Print
     */
    public function cetakIndividu(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $pesertaId  = $request->get('peserta_id');
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);
        $peserta    = PesertaDidik::find($pesertaId);

        if (!$angkatan || !$peserta) {
            abort(404, 'Data tidak ditemukan.');
        }

        $kompilasi = KompilasiNilai::where('peserta_didik_id', $pesertaId)
            ->where('angkatan_id', $angkatanId)->first();

        // Jika peserta belum punya record kompilasi, bangun bobot dari kompilasi
        // angkatan (atau default) agar laporan tetap lengkap & konsisten.
        if (!$kompilasi) {
            $ref = KompilasiNilai::where('angkatan_id', $angkatanId)->first();
            $kompilasi = (object) [
                'bobot_akademik'    => $ref->bobot_akademik    ?? NppCalculator::BOBOT_AKADEMIK_DEFAULT,
                'bobot_kepribadian' => $ref->bobot_kepribadian ?? NppCalculator::BOBOT_KEPRIBADIAN_DEFAULT,
                'bobot_samapta'     => $ref->bobot_samapta     ?? NppCalculator::BOBOT_SAMAPTA_DEFAULT,
                'nilai_akademik'    => 0,
                'nilai_kepribadian' => 0,
                'nilai_samapta'     => 0,
                'predikat_huruf'    => '-',
                'predikat_angka'    => 0,
            ];
        }

        $akademik = NilaiAkademik::where('peserta_didik_id', $pesertaId)
            ->where('angkatan_id', $angkatanId)->first();

        // Revisi 29 Agustus 2026: detail nilai per mata pelajaran ditampilkan
        // di PDF NPP individu. Muat daftar matpel yang dipetakan ke sekolah
        // angkatan tsb (urutan pivot = urutan index detail_nilai).
        $mataPelajaran = collect();
        if ($angkatan && $angkatan->skadik_id) {
            $mataPelajaran = \App\Models\MataPelajaran::forSkadik($angkatan->skadik_id, true);
        }

        $samapta = NilaiSamapta::where('peserta_didik_id', $pesertaId)
            ->where('angkatan_id', $angkatanId)->first();

        $kepribadianList = NilaiKepribadian::with('periode', 'detail.aspek')
            ->where('peserta_didik_id', $pesertaId)
            ->orderBy('periode_nilai_id')->get();
        $kepribadianAvg = $kepribadianList->avg('nilai_akhir') ?? 0;

        // Penandatangan: kanan = Kepala Sekolah (kompilasi), kiri = Danskadik
        $ttdKanan = Penandatangan::getPenandatangan('kompilasi', $angkatan?->skadik_id);
        $ttdKiri = Penandatangan::getPenandatangan('danskadik', $angkatan?->skadik_id);

        return view('laporan.cetak-individu', compact(
            'angkatan', 'peserta', 'kompilasi', 'akademik', 'mataPelajaran', 'samapta', 'kepribadianList', 'kepribadianAvg',
            'ttdKanan', 'ttdKiri'
        ));
    }

    /**
     * Cetak semua laporan rekap — HTML Print
     */
    public function cetakSemua(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

        if (!$angkatan) {
            return redirect()->route('laporan.index')->with('error', 'Angkatan tidak ditemukan.');
        }

        // SEMUA peserta angkatan tampil (bulk) dengan NPP dihitung live.
        $data = NppCalculator::forAngkatan($angkatanId);

        // Penandatangan
        $ttdKiri = Penandatangan::getPenandatangan('danskadik', $angkatan?->skadik_id);
        $ttdKanan = Penandatangan::getPenandatangan('kompilasi', $angkatan?->skadik_id);

        return view('laporan.cetak-semua', compact('angkatan', 'data', 'ttdKiri', 'ttdKanan'));
    }

    /**
     * Ekspor rekap semua ke Excel
     */
    public function eksporSemua(Request $request)
    {
        $angkatanId = $request->get('angkatan_id');
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

        // SEMUA peserta angkatan tampil (bulk) dengan NPP dihitung live.
        $data = NppCalculator::forAngkatan($angkatanId);

        $spreadsheet = new Spreadsheet();
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('Rekap NPP');

        $sh->mergeCells('A1:K1');
        $sh->setCellValue('A1', 'NILAI PRESTASI PENDIDIKAN (NPP)');
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sh->mergeCells('A2:K2');
        $sh->setCellValue('A2', strtoupper($angkatan?->skadik?->lemdik?->wingdik ?? '') . ' — ' . strtoupper($angkatan?->skadik?->nama ?? ''));
        $sh->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        $sh->mergeCells('A3:K3');
        $jurusan = $angkatan->jurusan ?? '';
        $sh->setCellValue('A3', "SEKOLAH KEJURUAN LANJUTAN {$jurusan} TA. {$angkatan?->tahun_masuk}");
        $sh->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        $headers = ['Rank', 'Nama', 'Pangkat', 'NRP', 'NPA', 'N. Kepribadian', 'NPS', 'NPP', 'Predikat', 'Predikat Angka', 'Keterangan'];
        foreach ($headers as $col => $h) {
            $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sh->setCellValue($c . '5', $h);
        }
        $sh->getStyle('A5:K5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $row = 6;
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
            $sh->setCellValue("J{$row}", $d->predikat_angka);
            $sh->setCellValue("K{$row}", $this->getKeterangan($d->nilai_akhir));

            for ($c = 1; $c <= 11; $c++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $sh->getStyle("{$col}{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }
            $sh->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("E{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        // Footer "Rata-rata Angkatan" per komponen (NPA · NPK · NPS) + NPP
        if ($countNPP > 0) {
            $sh->mergeCells("A{$row}:D{$row}");
            $sh->setCellValue("A{$row}", 'Rata-rata Angkatan');
            $sh->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            foreach (['E', 'F', 'G'] as $cc) {
                $sh->setCellValue("{$cc}{$row}", $cntKomponen[$cc] > 0 ? round($sumKomponen[$cc] / $cntKomponen[$cc], 2) : '-');
                $sh->getStyle("{$cc}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sh->setCellValue("H{$row}", round($totalNPP / $countNPP, 2));
            $sh->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("A{$row}:K{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
            $row++;
        }

        $sh->getColumnDimension('A')->setWidth(6);
        $sh->getColumnDimension('B')->setWidth(32);
        $sh->getColumnDimension('C')->setWidth(12);
        $sh->getColumnDimension('D')->setWidth(14);
        $sh->getColumnDimension('E')->setWidth(12);
        $sh->getColumnDimension('F')->setWidth(16);
        $sh->getColumnDimension('G')->setWidth(10);
        $sh->getColumnDimension('H')->setWidth(10);
        $sh->getColumnDimension('I')->setWidth(10);
        $sh->getColumnDimension('J')->setWidth(12);
        $sh->getColumnDimension('K')->setWidth(14);

        $filename = ExportFile::name($angkatan, 'Rekap NPP');
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function() use ($writer) { $writer->save('php://output'); }, $filename);
    }

    private function getKeterangan($nilai): string
    {
        if ($nilai >= 85) return 'Sangat Baik';
        if ($nilai >= 75) return 'Baik';
        if ($nilai >= 65) return 'Cukup';
        if ($nilai >= 55) return 'Kurang';
        return 'Sangat Kurang';
    }
}