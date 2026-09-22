<?php

namespace App\Imports;

use App\Models\{PesertaDidik, Angkatan};
use Illuminate\Support\Facades\DB;

class PesertaImport
{
    protected $angkatanId;
    protected $importedCount = 0;
    protected $skippedCount  = 0;
    protected $errors        = [];

    public function __construct($angkatanId)
    {
        $this->angkatanId = $angkatanId;
    }

    public function import($filePath)
    {
        $angkatan = Angkatan::find($this->angkatanId);
        if (!$angkatan) {
            $this->errors[] = 'Angkatan tidak ditemukan.';
            return;
        }

        // Baca file Excel menggunakan PhpSpreadsheet
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, true);

        // Cari baris header (baris yang berisi kolom Nama / NRP)
        $headerRow = null;
        $dataStart = null;
        foreach ($rows as $rowNum => $row) {
            $rowStr = strtolower(implode(' ', array_filter($row)));
            if (str_contains($rowStr, 'nama') && (str_contains($rowStr, 'nrp') || str_contains($rowStr, 'nosis'))) {
                $headerRow = $row;
                $dataStart = $rowNum + 1;
                break;
            }
        }

        // Fallback: jika tidak ada header, asumsikan baris 1 = header, data mulai baris 2
        if (!$headerRow) {
            $dataStart = 2;
            // Default kolom: A=Nama, B=Pangkat, C=NRP, D=Nosis
            $colNama   = 'A';
            $colPangkat = 'B';
            $colNrp    = 'C';
            $colNosis  = 'D';
        } else {
            // Deteksi kolom dari header
            $colNama    = null;
            $colPangkat = null;
            $colNrp     = null;
            $colNosis   = null;

            foreach ($headerRow as $col => $val) {
                $v = strtolower(trim($val ?? ''));
                if (str_contains($v, 'nama') && !$colNama)     $colNama   = $col;
                if (str_contains($v, 'pangkat') && !$colPangkat) $colPangkat = $col;
                if (str_contains($v, 'nrp') && !$colNrp)       $colNrp    = $col;
                if (str_contains($v, 'nosis') && !$colNosis)   $colNosis  = $col;
            }

            // Fallback posisi default
            if (!$colNama)    $colNama    = 'A';
            if (!$colPangkat) $colPangkat = 'B';
            if (!$colNrp)     $colNrp     = 'C';
            if (!$colNosis)   $colNosis   = 'D';
        }

        // Proses tiap baris
        for ($rowNum = $dataStart; $rowNum <= count($rows); $rowNum++) {
            if (!isset($rows[$rowNum])) continue;
            $row = $rows[$rowNum];

            $nama    = trim($row[$colNama] ?? '');
            $pangkat = trim($row[$colPangkat] ?? '');
            // NRP/Nosis dinormalisasi: angka panjang & notasi ilmiah → digit penuh
            $nrp     = \App\Services\ExportFile::digitText($row[$colNrp] ?? '');
            $nosis   = \App\Services\ExportFile::digitText($row[$colNosis] ?? '');

            // Skip baris kosong
            if (empty($nama) && empty($nrp) && empty($nosis)) continue;

            // Validasi minimal
            if (empty($nama)) {
                $this->errors[] = "Baris {$rowNum}: Nama kosong, dilewati.";
                $this->skippedCount++;
                continue;
            }

            // Nosis wajib diisi
            if (empty($nosis)) {
                $this->errors[] = "Baris {$rowNum}: Nosis kosong untuk peserta '{$nama}', dilewati. Nosis wajib diisi.";
                $this->skippedCount++;
                continue;
            }

            // Cek duplikat NRP (GLOBAL — lintas angkatan). NRP tidak boleh sama
            // dengan peserta lain; bila sama, tolak dengan pesan error.
            if (!empty($nrp)) {
                $dupNrp = PesertaDidik::where('nrp', $nrp)->first();
                if ($dupNrp) {
                    $this->errors[] = "Baris {$rowNum}: NRP '{$nrp}' sudah dipakai peserta lain ('{$dupNrp->nama}'), dilewati. NRP yang baru diinput tidak bisa dipakai karena sudah ada yang punya.";
                    $this->skippedCount++;
                    continue;
                }
            }

            // Cek duplikat Nosis (GLOBAL). Nosis tidak boleh sama dengan peserta
            // lain; bila sama, tolak dengan pesan error.
            $dupNosis = PesertaDidik::where('nosis', $nosis)->first();
            if ($dupNosis) {
                $this->errors[] = "Baris {$rowNum}: Nosis '{$nosis}' sudah dipakai peserta lain ('{$dupNosis->nama}'), dilewati. Nosis yang baru diinput tidak bisa dipakai karena sudah ada yang punya.";
                $this->skippedCount++;
                continue;
            }

            // Buat peserta baru
            try {
                PesertaDidik::create([
                    'angkatan_id' => $this->angkatanId,
                    'nama'        => $nama,
                    'nrp'         => $nrp ?: ('AUTO-' . str_pad(PesertaDidik::max('id') + 1, 6, '0', STR_PAD_LEFT)),
                    'pangkat'     => $pangkat ?: '-',
                    'nosis'       => $nosis,
                    'aktif'       => true,
                ]);
                $this->importedCount++;
            } catch (\Exception $e) {
                $this->errors[] = "Baris {$rowNum}: Gagal menyimpan '{$nama}' ({$e->getMessage()})";
                $this->skippedCount++;
            }
        }
    }

    public function getImportedCount(): int { return $this->importedCount; }
    public function getSkippedCount(): int  { return $this->skippedCount; }
    public function getErrors(): array      { return $this->errors; }
}
