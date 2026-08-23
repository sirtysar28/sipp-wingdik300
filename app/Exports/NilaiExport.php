<?php
namespace App\Exports;

use App\Models\{PesertaDidik, PeriodeNilai};
use Maatwebsite\Excel\Concerns\{
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithMapping, ShouldAutoSize
};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class NilaiExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithMapping, ShouldAutoSize
{
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
            $peserta->nosis,
            $peserta->nrp,
            $peserta->pangkat,
            $peserta->nama,
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
            'Nosis',
            'NRP',
            'Pangkat',
            'Nama',
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
            'A' => 15,
            'B' => 15,
            'C' => 15,
            'D' => 30,
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
        $lastRow = $sheet->getHighestRow();

        // Header style
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        // Data rows
        if ($lastRow > 1) {
            $sheet->getStyle("A2:I{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Zebra stripe
            for ($row = 2; $row <= $lastRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
                    ]);
                }
            }

            // Center numerik
            $sheet->getStyle("A2:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E2:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getRowDimension(1)->setRowHeight(22);

        return [];
    }
}
