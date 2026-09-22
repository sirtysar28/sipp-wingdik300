<?php
namespace App\Imports;

use App\Models\{PesertaDidik, NilaiKepribadian, DetailKepribadian, AspekKepribadian};
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KepribadianImport
{
    protected $periodeId;
    protected $angkatanId;
    protected $importedCount = 0;
    protected $skippedCount  = 0;
    protected $errors        = [];
    protected $aspekList;

    const KRITERIA_VALID = ['BS', 'B', 'C', 'K', 'KS'];

    public function __construct($periodeId, $angkatanId)
    {
        $this->periodeId   = $periodeId;
        $this->angkatanId  = $angkatanId;
        AspekKepribadian::ensureSeeded();
        $this->aspekList   = AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();
    }

    public function import($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, true);

        // Cari baris header (baris yang ada kolom NRP / NAMA)
        $headerRow  = null;
        $dataStart  = null;
        foreach ($rows as $rowNum => $row) {
            $rowStr = strtolower(implode(' ', array_filter($row)));
            if (str_contains($rowStr, 'nrp') && str_contains($rowStr, 'nama')) {
                $headerRow = $row;
                $dataStart = $rowNum + 1;
                break;
            }
        }

        // Kalau tidak ada header row, coba langsung dari baris ke-7 (format template kita)
        if (!$headerRow) {
            $dataStart = 7;
        }

        // Map kolom: cari posisi NRP, Nama, Nosis, dan 10 aspek
        $colNrp   = null;
        $colNosis = null;
        $colAspek = []; // [aspek_id => kolom_excel]

        if ($headerRow) {
            foreach ($headerRow as $col => $val) {
                $v = strtolower(trim($val ?? ''));
                if (str_contains($v, 'nrp'))   $colNrp   = $col;
                if (str_contains($v, 'nosis'))  $colNosis = $col;
            }

            // Map aspek ke kolom berdasarkan urutan nomor
            $aspekIdx = 0;
            foreach ($headerRow as $col => $val) {
                $v = strtolower(trim($val ?? ''));
                foreach ($this->aspekList as $aspek) {
                    $aspekNama = strtolower($aspek->nama);
                    if (str_contains($v, strtolower(explode(' ', $aspek->nama)[0])) ||
                        str_contains($aspekNama, $v) && strlen($v) > 3) {
                        $colAspek[$aspek->id] = $col;
                        break;
                    }
                }
            }
        }

        // Fallback: pakai posisi kolom default dari template kita
        // Template: A=No, B=Nama, C=Pgkt, D=NRP, E=Nosis, F=NilaiAwal, G..P=Aspek, Q=JML, R=RNKG
        if (!$colNrp) $colNrp = 'D';
        if (!$colNosis) $colNosis = 'E';
        if (empty($colAspek)) {
            foreach ($this->aspekList as $i => $aspek) {
                $colAspek[$aspek->id] = chr(71 + $i); // G, H, I, ...
            }
        }

        // Proses setiap baris data
        for ($rowNum = $dataStart; $rowNum <= count($rows) + $dataStart; $rowNum++) {
            if (!isset($rows[$rowNum])) continue;
            $row = $rows[$rowNum];

            // NRP/Nosis dinormalisasi: angka panjang & notasi ilmiah → digit penuh
            $nrp   = \App\Services\ExportFile::digitText($row[$colNrp] ?? '');
            $nosis = \App\Services\ExportFile::digitText($row[$colNosis] ?? '');

            // Skip baris kosong atau baris keterangan
            if (empty($nrp) && empty($nosis)) continue;
            if (in_array(strtolower($nrp), ['nrp', 'no', '', 'keterangan'])) continue;

            // Cari peserta
            $peserta = PesertaDidik::where('angkatan_id', $this->angkatanId)
                ->where(function ($q) use ($nrp, $nosis) {
                    if ($nrp)   $q->orWhere('nrp', $nrp);
                    if ($nosis) $q->orWhere('nosis', $nosis);
                })->first();

            if (!$peserta) {
                $this->errors[] = "Baris {$rowNum}: NRP '{$nrp}' / Nosis '{$nosis}' tidak ditemukan.";
                $this->skippedCount++;
                continue;
            }

            // Ambil kriteria per aspek
            $kriteriaData = [];
            $allValid     = true;
            foreach ($colAspek as $aspekId => $col) {
                $kriteria = strtoupper(trim($row[$col] ?? 'C'));
                if (!in_array($kriteria, self::KRITERIA_VALID)) {
                    if ($kriteria === '—' || $kriteria === '' || $kriteria === '-') {
                        $kriteria = 'C'; // Default cukup
                    } else {
                        $this->errors[] = "Baris {$rowNum}: Kriteria '{$kriteria}' tidak valid (NRP: {$nrp}). Diset ke C.";
                        $kriteria = 'C';
                    }
                }
                $kriteriaData[$aspekId] = $kriteria;
            }

            // Hitung nilai akhir
            $totalPoin = 0;
            foreach ($kriteriaData as $aspekId => $kriteria) {
                $totalPoin += NilaiKepribadian::poinKriteria($kriteria);
            }
            $nilaiAkhir = round(75 + $totalPoin, 2);

            // Simpan ke database
            try {
                DB::transaction(function () use ($peserta, $kriteriaData, $nilaiAkhir) {
                    $nk = NilaiKepribadian::updateOrCreate(
                        ['peserta_didik_id' => $peserta->id, 'periode_nilai_id' => $this->periodeId],
                        ['nilai_akhir' => $nilaiAkhir, 'input_oleh' => auth()->id()]
                    );
                    $nk->detail()->delete();
                    foreach ($kriteriaData as $aspekId => $kriteria) {
                        DetailKepribadian::create([
                            'nilai_kepribadian_id' => $nk->id,
                            'aspek_kepribadian_id' => $aspekId,
                            'kriteria'             => $kriteria,
                            'poin'                 => NilaiKepribadian::poinKriteria($kriteria),
                        ]);
                    }
                });
                $this->importedCount++;
            } catch (\Exception $e) {
                $this->errors[] = "Baris {$rowNum}: Gagal menyimpan ({$e->getMessage()})";
                $this->skippedCount++;
            }
        }
    }

    public function getImportedCount(): int { return $this->importedCount; }
    public function getSkippedCount(): int  { return $this->skippedCount; }
    public function getErrors(): array      { return $this->errors; }
}