<?php
namespace App\Http\Controllers;

use App\Models\{Penandatangan, Skadik};
use Illuminate\Http\Request;

class PenandatanganController extends Controller
{
    public function index(Request $request)
    {
        $jenis = $request->get('jenis', 'semua');
        $skadikId = $request->get('skadik_id', 'semua');

        $query = Penandatangan::with('skadik')->orderBy('jenis')->orderBy('created_at', 'desc');
        if ($jenis !== 'semua') $query->where('jenis', $jenis);
        if ($skadikId !== 'semua') $query->where('skadik_id', $skadikId);

        $penandatangan = $query->get();

        $allSkadik = Skadik::listForUser();

        $jenisList = [
            'umum' => 'Umum (Semua Laporan)',
            'akademik' => 'NPA (Akademik)',
            'kepribadian' => 'NPK (Kepribadian)',
            'samapta' => 'NPS (Samapta)',
            'kompilasi' => 'NPP (Kompilasi)',
            'danskadik' => 'Danskadik (NPP Kiri)',
        ];

        return view('penandatangan.index', compact('penandatangan', 'jenis', 'skadikId', 'allSkadik', 'jenisList'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'     => 'required|string|max:150',
            'nrp'      => 'required|string|max:30',
            'pangkat'  => 'nullable|string|max:100',
            'jabatan'  => 'required|string|max:200',
            'jenis'    => 'required|in:umum,akademik,kepribadian,samapta,kompilasi,danskadik',
            'skadik_id' => 'nullable|exists:skadik,id',
        ]);

        // Validasi unik: 1 kombinasi jenis + skadik_id hanya boleh 1 yang aktif
        $exists = Penandatangan::where('jenis', $request->jenis)
            ->where('skadik_id', $request->skadik_id)
            ->where('aktif', true)
            ->exists();

        if ($exists) {
            $skadikNama = $request->skadik_id
                ? Skadik::find($request->skadik_id)?->nama ?? 'Global'
                : 'Global';
            return back()->with('error', "Sudah ada penandatangan aktif untuk jenis '{$request->jenis}' di {$skadikNama}. Nonaktifkan dulu yang lama atau pilih sekolah/jenis berbeda.");
        }

        Penandatangan::create($request->only('nama', 'nrp', 'pangkat', 'jabatan', 'jenis', 'skadik_id'));

        return back()->with('success', 'Penandatangan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $pen = Penandatangan::findOrFail($id);
        $request->validate([
            'nama'     => 'required|string|max:150',
            'nrp'      => 'required|string|max:30',
            'pangkat'  => 'nullable|string|max:100',
            'jabatan'  => 'required|string|max:200',
            'jenis'    => 'required|in:umum,akademik,kepribadian,samapta,kompilasi,danskadik',
            'skadik_id' => 'nullable|exists:skadik,id',
            'aktif'    => 'boolean',
        ]);

        // Validasi unik: cek apakah kombinasi jenis + skadik_id sudah dipakai penandatangan lain yang aktif
        $exists = Penandatangan::where('jenis', $request->jenis)
            ->where('skadik_id', $request->skadik_id)
            ->where('aktif', true)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            $skadikNama = $request->skadik_id
                ? Skadik::find($request->skadik_id)?->nama ?? 'Global'
                : 'Global';
            return back()->with('error', "Sudah ada penandatangan aktif untuk jenis '{$request->jenis}' di {$skadikNama}. Nonaktifkan dulu yang lama atau pilih sekolah/jenis berbeda.");
        }

        $pen->update($request->only('nama', 'nrp', 'pangkat', 'jabatan', 'jenis', 'skadik_id', 'aktif'));

        return back()->with('success', 'Penandatangan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        Penandatangan::findOrFail($id)->delete();
        return back()->with('success', 'Penandatangan berhasil dihapus.');
    }
}
