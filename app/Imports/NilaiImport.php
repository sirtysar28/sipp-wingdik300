<?php
namespace App\Imports;

use App\Models\{PesertaDidik, Nilai};
use Maatwebsite\Excel\Concerns\{
    ToModel, WithHeadingRow, WithValidation,
    SkipsOnError, SkipsErrors, WithBatchInserts, WithChunkReading
};
use Throwable;

class NilaiImport implements
    ToModel, WithHeadingRow, WithValidation,
    SkipsOnError, SkipsErrors, WithBatchInserts, WithChunkReading
{
    protected $periodeId;
    protected $angkatanId;
    protected $importedCount = 0;
    protected $errors = [];

    public function __construct($periodeId, $angkatanId)
    {
        $this->periodeId  = $periodeId;
        $this->angkatanId = $angkatanId;
    }

    public function model(array $row)
    {
        // Cari peserta berdasarkan NRP atau Nosis
        $peserta = PesertaDidik::where('angkatan_id', $this->angkatanId)
            ->where(function ($q) use ($row) {
                $q->where('nrp', trim($row['nrp'] ?? ''))
                  ->orWhere('nosis', trim($row['nosis'] ?? ''));
            })->first();

        if (!$peserta) {
            $this->errors[] = "NRP/Nosis '{$row['nrp']}' tidak ditemukan di angkatan ini.";
            return null;
        }

        $akademik    = (float) ($row['akademik'] ?? 0);
        $fisik       = (float) ($row['fisik'] ?? 0);
        $sikap       = (float) ($row['sikap'] ?? 0);
        $kepemimpinan = (float) ($row['kepemimpinan'] ?? 0);

        $this->importedCount++;

        return Nilai::updateOrCreate(
            [
                'peserta_didik_id' => $peserta->id,
                'periode_nilai_id' => $this->periodeId,
            ],
            [
                'akademik'     => min(100, max(0, $akademik)),
                'fisik'        => min(100, max(0, $fisik)),
                'sikap'        => min(100, max(0, $sikap)),
                'kepemimpinan' => min(100, max(0, $kepemimpinan)),
                'input_oleh'   => auth()->id(),
            ]
        );
    }

    public function rules(): array
    {
        return [
            'akademik'     => 'nullable|numeric|min:0|max:100',
            'fisik'        => 'nullable|numeric|min:0|max:100',
            'sikap'        => 'nullable|numeric|min:0|max:100',
            'kepemimpinan' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function onError(Throwable $e)
    {
        $this->errors[] = $e->getMessage();
    }

    public function batchSize(): int { return 100; }
    public function chunkSize(): int { return 100; }

    public function getImportedCount(): int { return $this->importedCount; }
    public function getErrors(): array { return $this->errors; }
}
