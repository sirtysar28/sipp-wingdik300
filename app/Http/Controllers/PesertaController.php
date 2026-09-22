<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, PeriodeNilai, NilaiKepribadian, DetailKepribadian, AspekKepribadian, Skadik};
use App\Imports\PesertaImport;
use App\Services\ExportFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment};

class PesertaController extends Controller {

    public function index(Request $request) {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->orderBy('created_at', 'desc')->get();

        // Sinkronkan angkatan dengan skadik terpilih: bila angkatan_id dari request
        // bukan milik skadik tsb (skadik diganti tapi angkatan_id lama masih
        // terkirim), pakai angkatan pertama dari skadik yang baru dipilih.
        $angkatanId = $request->get('angkatan_id');
        if (!$allAngkatan->contains('id', $angkatanId)) {
            $angkatanId = $allAngkatan->first()?->id;
        }

        // Tampilkan semua peserta termasuk nonaktif, dengan pencarian
        $query = PesertaDidik::where('angkatan_id', $angkatanId)
            ->with('angkatan.skadik');

        $search = $request->get('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('nrp', 'like', '%' . $search . '%');
            });
        }

        $peserta = $query->paginate(12)->appends($request->only(['skadik_id', 'angkatan_id', 'search']));
        return view('peserta.index', compact('peserta', 'allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'search'));
    }

    public function create() {
        $allSkadik = Skadik::listForUser();
        $allAngkatan = Angkatan::where('aktif', true)->with('skadik')->get();
        return view('peserta.create', compact('allSkadik', 'allAngkatan'));
    }

    public function store(Request $request) {
        // Validasi: NRP & Nosis harus unik (tidak boleh sama dengan peserta lain).
        // Bila ada yang sama, validasi menolak & menampilkan pesan error bahwa
        // nilai yg baru diinput tidak bisa dipakai karena sudah ada yang punya.
        $data = $request->validate([
            'angkatan_id' => 'required|exists:angkatan,id',
            'nama'        => 'required|string|max:100',
            'nrp'         => 'required|string|max:50|unique:peserta_didik,nrp',
            'pangkat'     => 'required|string|max:50',
            'nosis'       => 'required|string|max:50|unique:peserta_didik,nosis',
        ], [
            'nrp.unique'     => 'NRP "' . $request->nrp . '" sudah dipakai peserta lain. NRP yang baru diinput tidak bisa dipakai karena sudah ada yang punya.',
            'nosis.required' => 'Nosis wajib diisi.',
            'nosis.unique'   => 'Nosis "' . $request->nosis . '" sudah dipakai peserta lain. Nosis yang baru diinput tidak bisa dipakai karena sudah ada yang punya.',
        ]);

        $peserta = PesertaDidik::create($data);

        return redirect()->route('peserta.show', $peserta)->with('success', 'Peserta berhasil ditambahkan.');
    }

    // public function show(PesertaDidik $peserta) {
    //     $user = auth()->user();
    //     if ($user->isPeserta() && $user->peserta_didik_id !== $peserta->id) {
    //         abort(403);
    //     }

    //     $periodes = PeriodeNilai::where('angkatan_id', $peserta->angkatan_id)
    //         ->orderBy('tanggal_mulai')->get();

    //     $nilaiHistory = Nilai::where('peserta_didik_id', $peserta->id)
    //         ->with('periode')
    //         ->orderBy('periode_nilai_id')
    //         ->get();

    //     $chartLabels = $nilaiHistory->pluck('periode.label');
    //     $chartAkd    = $nilaiHistory->pluck('akademik');
    //     $chartFis    = $nilaiHistory->pluck('fisik');
    //     $chartSik    = $nilaiHistory->pluck('sikap');
    //     $chartKep    = $nilaiHistory->pluck('kepemimpinan');
    //     $chartTotal  = $nilaiHistory->pluck('total');

    //     $rataRata = round($nilaiHistory->avg('total'), 1);
    //     $rank = PesertaDidik::where('angkatan_id', $peserta->angkatan_id)
    //         ->get()
    //         ->filter(fn($p) => $p->nilai->avg('total') > $rataRata)
    //         ->count() + 1;

    //     $totalPeserta = PesertaDidik::where('angkatan_id', $peserta->angkatan_id)->count();

    //     return view('peserta.show', compact(
    //         'peserta', 'nilaiHistory', 'periodes',
    //         'chartLabels', 'chartAkd', 'chartFis', 'chartSik', 'chartKep', 'chartTotal',
    //         'rataRata', 'rank', 'totalPeserta'
    //     ));
    // }
    
    public function show(PesertaDidik $peserta, Request $request)
    {
        return $this->showPeserta($peserta, $request);
    }

    private function showPeserta(PesertaDidik $peserta, Request $request)
    {
        $periodes  = PeriodeNilai::where('angkatan_id', $peserta->angkatan_id)->orderBy('tanggal_mulai')->get();
        $periodeId = $request->get('periode_id', $periodes->last()?->id);
        $riwayat   = NilaiKepribadian::where('peserta_didik_id', $peserta->id)->with(['periode','detail.aspek'])->orderBy('periode_nilai_id')->get();
        $current   = $riwayat->where('periode_nilai_id', $periodeId)->first();
        AspekKepribadian::ensureSeeded();
        $aspekList = AspekKepribadian::where('aktif', true)->orderBy('nomor')->get();
        $grafikLabels = $riwayat->pluck('periode.label');
        $grafikNilai  = $riwayat->pluck('nilai_akhir');
        return view('peserta.show', compact('peserta','periodes','periodeId','riwayat','current','aspekList','grafikLabels','grafikNilai'));
    }

    public function edit(PesertaDidik $peserta) {
        // Hanya super_admin, admin skadik, admin akademik, admin kepribadian, admin samapta yang boleh edit
        $allowed = ['super_admin', 'admin', 'admin_akademik', 'admin_kepribadian', 'admin_samapta'];
        if (!in_array(auth()->user()->role, $allowed)) {
            abort(403, 'Hanya admin yang dapat mengedit data peserta.');
        }

        $allSkadik = Skadik::listForUser();
        $allAngkatan = Angkatan::where('aktif', true)->with('skadik')->get();
        return view('peserta.edit', compact('peserta', 'allSkadik', 'allAngkatan'));
    }

    public function update(Request $request, PesertaDidik $peserta) {
        // Hanya super_admin, admin skadik, admin akademik, admin kepribadian, admin samapta yang boleh edit
        $allowed = ['super_admin', 'admin', 'admin_akademik', 'admin_kepribadian', 'admin_samapta'];
        if (!in_array(auth()->user()->role, $allowed)) {
            abort(403, 'Hanya admin yang dapat mengedit data peserta.');
        }

        $data = $request->validate([
            'nama'    => 'required|string|max:100',
            'pangkat' => 'required|string|max:50',
            'nrp'     => 'required|string|max:50|unique:peserta_didik,nrp,'.$peserta->id,
            'nosis'   => 'required|string|max:50|unique:peserta_didik,nosis,'.$peserta->id,
        ], [
            'nrp.unique'     => 'NRP "' . $request->nrp . '" sudah dipakai peserta lain. NRP yang baru diinput tidak bisa dipakai karena sudah ada yang punya.',
            'nosis.required' => 'Nosis wajib diisi.',
            'nosis.unique'   => 'Nosis "' . $request->nosis . '" sudah dipakai peserta lain. Nosis yang baru diinput tidak bisa dipakai karena sudah ada yang punya.',
        ]);
        $peserta->update($data);

        return redirect()->route('peserta.show', $peserta)->with('success', 'Data peserta diperbarui.');
    }

    public function destroy(PesertaDidik $peserta) {
        // Toggle aktif/nonaktif
        $peserta->update(['aktif' => !$peserta->aktif]);
        $status = $peserta->aktif ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('peserta.index')->with('success', "Peserta berhasil $status.");
    }

    public function forceDelete(PesertaDidik $peserta) {
        // Hapus permanen dari database
        $nama = $peserta->nama;
        $peserta->delete();
        return redirect()->route('peserta.index')->with('success', "Peserta {$nama} berhasil dihapus permanen.");
    }

    // ── Impor Peserta dari Excel ─────────────────────────────────
    public function importForm(Request $request)
    {
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->orderBy('created_at', 'desc')->get();

        // Pastikan angkatan_id sinkron dengan skadik yang dipilih: bila angkatan_id
        // dari URL bukan milik skadik tsb (mis. user baru mengganti sekolah lalu
        // angkatan_id lama masih ikut terkirim), jatuhkan ke angkatan pertama dari
        // skadik yang baru — agar info tujuan impor & form upload tidak ketinggalan.
        $angkatanId = $request->get('angkatan_id');
        if (!$allAngkatan->contains('id', $angkatanId)) {
            $angkatanId = $allAngkatan->first()?->id;
        }
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

        return view('peserta.import', compact('allSkadik', 'skadikId', 'allAngkatan', 'angkatanId', 'angkatan'));
    }

    public function downloadTemplate(Request $request)
    {
        $angkatan = Angkatan::with('skadik')->find($request->get('angkatan_id'));

        $spreadsheet = new Spreadsheet();
        ExportFile::plain($spreadsheet); // font default: Arial (revisi 21 Sept 2026)
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Peserta');

        // Header row
        $headers = ['Nama', 'Pangkat', 'NRP', 'Nosis'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}1", $h);
            $sheet->getStyle("{$col}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $sheet->getColumnDimension('A')->setWidth(32);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(16);

        // Baris contoh (opsional, baris 2-4)
        $contoh = [
            ['Agil Maulana Wardhana', 'Serda', '3525101070562147', '001'],
            ['Budi Santoso', 'Kopda', '3525101070562148', '002'],
            ['Candra Wijaya', 'Serda', '3525101070562149', '003'],
        ];
        foreach ($contoh as $i => $c) {
            $row = $i + 2;
            foreach ($c as $j => $val) {
                $col = chr(65 + $j);
                // NRP (C) & Nosis (D) ditulis sebagai teks agar 16 digit tampil penuh
                // dan nosis "001" tidak kehilangan nol depan.
                if (in_array($col, ['C', 'D'])) {
                    ExportFile::setText($sheet, "{$col}{$row}", $val);
                } else {
                    $sheet->setCellValue("{$col}{$row}", $val);
                }
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ExportFile::BG_PLAIN]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
            }
        }

        // Keterangan
        $noteRow = count($contoh) + 3;
        $sheet->setCellValue("A{$noteRow}", "* Isi kolom Nama, Pangkat, NRP, Nosis dari Angkatan peserta yang baru");
        $sheet->setCellValue("A" . ($noteRow + 1), "* Baris contoh (abu-abu) boleh dihapus atau ditimpa");
        $sheet->getStyle("A{$noteRow}:D" . ($noteRow + 1))->getFont()->setItalic(true)->setSize(10);

        $writer = new XlsxWriter($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, ExportFile::name($angkatan, 'Template Peserta'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|extensions:xlsx,xls|max:5120',
            'angkatan_id' => 'required|exists:angkatan,id',
        ]);

        // Baca langsung dari file upload (tanpa Storage/Flysystem),
        // karena hosting tidak punya ekstensi PHP fileinfo.
        $full = $request->file('file')->getRealPath();

        try {
            $import = new PesertaImport($request->angkatan_id);
            $import->import($full);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'File bukan Excel yang valid / tidak dapat dibaca.']);
        }

        $msg = "Berhasil impor {$import->getImportedCount()} peserta.";
        if ($import->getSkippedCount() > 0) {
            $msg .= " {$import->getSkippedCount()} baris dilewati.";
        }
        if (!empty($import->getErrors())) {
            $msg .= ' | ' . implode(' | ', array_slice($import->getErrors(), 0, 5));
        }

        return redirect()->route('peserta.index', ['angkatan_id' => $request->angkatan_id])
            ->with('success', $msg);
    }
}