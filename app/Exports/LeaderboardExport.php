<?php
namespace App\Exports;

use App\Models\{PesertaDidik, PeriodeNilai, Angkatan};
use Maatwebsite\Excel\Concerns\{
    FromCollection, WithHeadings, WithStyles,
    WithTitle, WithMapping, ShouldAutoSize, WithEvents
};
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class LeaderboardExport implements
    FromCollection, WithHeadings, WithStyles,
    WithTitle, WithMapping, ShouldAutoSize, WithEvents
{
    protected $angkatanId;
    protected $periodeId;
    protected $angkatan;
    protected $periode;
    protected $peserta;

    public function __construct($angkatanId, $periodeId)
    {
        $this->angkatanId = $angkatanId;
        $this->periodeId  = $periodeId;
        $this->angkatan   = Angkatan::find($angkatanId);
        $this->periode    = PeriodeNilai::find($periodeId);

        $this->peserta = PesertaDidik::where('angkatan_id', $angkatanId)
            ->with(['nilai' => fn($q) => $q->where('periode_nilai_id', $periodeId)])
            ->get()
            ->map(function ($p) {
                $n = $p->nilai->first();
                $p->n_akd   = $n?->akademik ?? 0;
                $p->n_fis   = $n?->fisik ?? 0;
                $p->n_sik   = $n?->sikap ?? 0;
                $p->n_kep   = $n?->kepemimpinan ?? 0;
                $p->n_total = $n?->total ?? 0;
                return $p;
            })
            ->sortByDesc('n_total')
            ->values();
    }

    public function collection()
    {
        return $this->peserta;
    }

    public function map($p): array
    {
        static $rank = 0;
        $rank++;
        return [
            $rank,
            $p->nosis,
            $p->nrp,
            $p->pangkat,
            $p->nama,
            $p->n_akd,
            $p->n_fis,
            $p->n_sik,
            $p->n_kep,
            $p->n_total,
        ];
    }

    public function headings(): array
    {
        return ['No', 'Nosis', 'NRP', 'Pangkat', 'Nama', 'Akademik', 'Fisik', 'Sikap', 'Kepemimpinan', 'Total'];
    }

    public function title(): string
    {
        return 'Leaderboard';
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        if ($lastRow > 1) {
            // Top 3 highlight
            $colors = ['2' => 'FEF3C7', '3' => 'DBEAFE', '4' => 'D1FAE5'];
            foreach ($colors as $row => $color) {
                if ($row <= $lastRow) {
                    $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                        'font' => ['bold' => true],
                    ]);
                }
            }

            $sheet->getStyle("A2:J{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("A2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getRowDimension(1)->setRowHeight(22);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $angkatan = $this->angkatan;
                $periode  = $this->periode;

                // Tambah info di bawah tabel
                $lastRow = $sheet->getHighestRow() + 2;
                $sheet->setCellValue("A{$lastRow}", "{$angkatan?->skadik?->nama} — Angkatan: {$angkatan?->nomor_angkatan}");
                $sheet->setCellValue("A" . ($lastRow + 1), "Periode: {$periode?->label}");
                $sheet->setCellValue("A" . ($lastRow + 2), "Dicetak: " . now()->format('d/m/Y H:i'));
                $sheet->getStyle("A{$lastRow}:A" . ($lastRow + 2))->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['rgb' => '6B7280'], 'size' => 10],
                ]);
            },
        ];
    }
}
