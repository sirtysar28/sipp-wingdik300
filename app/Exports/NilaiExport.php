<?php
namespace App\Exports;

use App\Models\{PesertaDidik, PeriodeNilai};
use App\Services\ExportFile;
use Maatwebsite\Excel\Concerns\{
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithMapping, ShouldAutoSize,
    WithCustomValueBinder
};
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class NilaiExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithMapping, ShouldAutoSize,
    WithCustomValueBinder
{
    /** Binder teks: NRP 16 digit & Nosis "001" tampil penuh (bukan angka/ilmiah) */
    private static ?StringValueBinder $textBinder = null;

    public function bindValue(Cell $cell, $value)
    {
        self::$textBinder ??= (new StringValueBinder())->setNumericConversion(false); // false = angka asli tetap numerik, STRING (NRP/Nosis) tetap teks

        return self::$textBinder->bindValue($cell, $value);
    }
    protected $angkatanId;
    protected $periodeId;
    protected $periode;

    public function __construct($angkatanId, $periodeId)
    {
        $this->angkatanId = $angkatanId;
        $this->periodeId  = $periodeId;
        $this->periode    = PeriodeNilai::find($periodeId);
    }

    public function collection()
    {
        return PesertaDidik::where('angkatan_id', $this->angkatanId)
            ->orderBy('nama')
            ->with(['nilai' => fn($q) => $q->where('periode_nilai_id', $this->periodeId)])
            ->get();
    }

    public function map($peserta): array
    {
        $n = $peserta->nilai->first();
        return [
            $peserta->nama,
            $peserta->pangkat,
            $peserta->nrp,
            $peserta->nosis,
            $n?->akademik ?? '',
            $n?->fisik ?? '',
            $n?->sikap ?? '',
            $n?->kepemimpinan ?? '',
            $n?->total ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            'Nama',
            'Pangkat',
            'NRP',
            'Nosis',
            'Akademik',
            'Fisik',
            'Sikap',
            'Kepemimpinan',
            'Total',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 15,
            'C' => 15,
            'D' => 15,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 16,
            'I' => 12,
        ];
    }

    public function title(): string
    {
        return $this->periode?->label ?? 'Nilai';
    }

    public function styles(Worksheet $sheet)
    {
        // Font default ARIAL untuk seluruh sheet (revisi 21 Sept 2026)
        ExportFile::plain($sheet->getParent());

        $lastRow = $sheet->getHighestRow();

        // Header style
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        // Data rows
        if ($lastRow > 1) {
            $sheet->getStyle("A2:I{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Tabel polos — tanpa zebra (revisi 21 Sept 2026)

            // Center kolom identitas & angka
            $sheet->getStyle("B2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E2:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getRowDimension(1)->setRowHeight(22);

        return [];
    }
}
