<?php
namespace App\Services;

use App\Models\Angkatan;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Helper penamaan file hasil ekspor (Excel/PDF).
 *
 * Format standar untuk SEMUA nama file ekspor:
 *   {Nama Sekolah}_Angkatan_{Nomor}[_{Jenis}][_{Keterangan}].xlsx
 *
 * Contoh:
 *   AFS_Angkatan_XXII_NPA.xlsx
 *   AFS_Angkatan_XXII_Template_NPS_Putaran_1.xlsx
 *
 * Nama sekolah memakai "nama_singkat" Skadik (deskripsi dalam kurung dibuang)
 * agar nama file tetap ringkas. Semua karakter tidak aman untuk nama file
 * otomatis diganti underscore.
 */
class ExportFile
{
    /* ══════════════════════════════════════════════════════════════
       Revisi 21 September 2026 — SKEMA WARNA & FONT STANDAR:
       1) SEMUA isi tabel pada file PREVIEW, EXCEL dan PDF memakai
          warna polos: teks HITAM di atas background PUTIH (tanpa abu-abu).
       2) SEMUA font di dalam aplikasi, Excel dan PDF memakai ARIAL
          (menggantikan Calibri pada ekspor Excel).
       Konstanta & helper di bawah dipakai bersama oleh seluruh modul
       ekspor agar tampilan konsisten antar file.
       ══════════════════════════════════════════════════════════════ */

    /** Background putih polos — dipakai SEMUA sel tabel ekspor Excel. */
    public const BG_PLAIN = 'FFFFFF';

    /** Teks hitam polos — dipakai SEMUA font ekspor Excel. */
    public const TEXT_BLACK = '000000';

    /** Nama font standar seluruh aplikasi (app, Excel, PDF). */
    public const FONT_NAME = 'Arial';

    /** Ukuran font default ekspor Excel. */
    public const FONT_SIZE = 11;

    /**
     * Terapkan tema standar ke spreadsheet: font default ARIAL + teks hitam.
     * WAJIB dipanggil tepat setelah `new Spreadsheet()` agar setiap style
     * yang di-clone kemudian (bold, italic, dsb.) mewarisi Arial.
     */
    public static function plain(Spreadsheet $spreadsheet): Spreadsheet
    {
        $font = $spreadsheet->getDefaultStyle()->getFont();
        $font->setName(self::FONT_NAME)->setSize(self::FONT_SIZE)->getColor()->setRGB(self::TEXT_BLACK);

        return $spreadsheet;
    }

    /**
     * Tulis nilai ke sel sebagai TEKS MURNI (revisi 21 Sept 2026).
     * Dipakai untuk NRP & Nosis pada semua ekspor Excel supaya:
     *  - NRP 16 digit tampil PENUH (tidak berubah jadi 3.52510E+15)
     *  - Nosis "001" tidak kehilangan nol di depan
     */
    public static function setText(Worksheet $sheet, string $cell, $value): void
    {
        $sheet->getCell($cell)->setValueExplicit((string) $value, DataType::TYPE_STRING);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
    }

    /**
     * Normalisasi NRP/Nosis hasil PEMBACAAN file Excel menjadi string digit
     * penuh (dua arah konsisten dengan setText di atas):
     *   - float 3525101070562147.0   → "3525101070562147"
     *   - string "3.52510E+15"       → "3525100000000000" dgn digit aslinya
     *   - string biasa ("001", teks) → dikembalikan apa adanya
     */
    public static function digitText($value): string
    {
        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 0, '', '');
        }

        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }

        // Notasi ilmiah hasil format tampilan Excel: 3.525101070562147E+15
        if (preg_match('/^(\d)(?:\.(\d+))[Ee]\+(\d+)$/', $v, $m)) {
            $digits = $m[1] . $m[2];
            $len    = (int) $m[3] + 1;

            return substr(str_pad($digits, $len, '0'), 0, $len);
        }

        return $v;
    }

    /**
     * Bangun nama file ekspor standar.
     *
     * @param Angkatan|null $angkatan   Untuk mengambil nama sekolah & nomor angkatan
     * @param string        $jenis      Jenis laporan, mis. 'NPA', 'Report NPP', 'Template NPS'
     * @param string        $keterangan Info tambahan (periode / putaran / rentang), opsional
     * @param string        $ext        Ekstensi file (default xlsx)
     */
    public static function name(?Angkatan $angkatan, string $jenis = '', string $keterangan = '', string $ext = 'xlsx'): string
    {
        $sekolah = $angkatan?->skadik?->nama_singkat
            ?: ($angkatan?->skadik?->nama ?: 'SEKOLAH');

        $parts   = [$sekolah, 'Angkatan ' . ($angkatan?->nomor_angkatan ?: '-')];
        if ($jenis !== '')      $parts[] = $jenis;
        if ($keterangan !== '') $parts[] = $keterangan;

        $name = implode(' ', $parts);

        // Buang karakter tidak aman untuk nama file, rapikan spasi ganda
        $name = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $name);
        $name = preg_replace('/\s+/', '_', trim($name));
        $name = trim($name, '_');

        return $name . '.' . $ext;
    }
}
