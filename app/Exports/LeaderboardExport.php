<?php
namespace App\Exports;

use App\Models\{PesertaDidik, PeriodeNilai, Angkatan};
use App\Services\ExportFile;
use Maatwebsite\Excel\Concerns\{
    FromCollection, WithHeadings, WithStyles,
    WithTitle, WithMapping, ShouldAutoSize, WithEvents,
    WithCustomValueBinder
};
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class LeaderboardExport implements
    FromCollection, WithHeadings, WithStyles,
    WithTitle, WithMapping, ShouldAutoSize, WithEvents,
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
        // Font default ARIAL untuk seluruh sheet (revisi 21 Sept 2026)
        ExportFile::plain($sheet->getParent());

        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        if ($lastRow > 1) {
            // Top 3 — teks tebal, tanpa warna (tabel polos hitam-putih)
            if ($lastRow >= 4) {
                $sheet->getStyle('A2:J4')->getFont()->setBold(true);
            }

            $sheet->getStyle("A2:J{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
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
                    'font' => ['italic' => true, 'color' => ['rgb' => '000000'], 'size' => 10],
                ]);
            },
        ];
    }
}
