<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, Skadik};
use Illuminate\Http\Request;

class AngkatanController extends Controller
{
    public function index()
    {
        $angkatan = Angkatan::with('skadik.lemdik')->orderBy('created_at', 'desc')->paginate(12);
        $skadik   = Skadik::with('lemdik')->get();
        return view('angkatan.index', compact('angkatan', 'skadik'));
    }

    public function create()
    {
        $skadik = Skadik::with('lemdik')->get();
        return view('angkatan.create', compact('skadik'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'skadik_id'      => 'required|exists:skadik,id',
            'nomor_angkatan' => 'required|string|max:50',
            'tahun_masuk'    => 'required|digits:4',
        ]);
        Angkatan::create($data);
        return redirect()->route('angkatan.index')->with('success', 'Angkatan berhasil dibuat.');
    }

    public function edit(Angkatan $angkatan)
    {
        $skadik = Skadik::with('lemdik')->get();
        return view('angkatan.edit', compact('angkatan', 'skadik'));
    }

    public function update(Request $request, Angkatan $angkatan)
    {
        $data = $request->validate([
            'nomor_angkatan' => 'required|string|max:50',
            'tahun_masuk'    => 'required|digits:4',
        ]);
        $angkatan->update($data);
        return redirect()->route('angkatan.index')->with('success', 'Angkatan diperbarui.');
    }

    public function destroy(Angkatan $angkatan)
    {
        // Jika masih aktif, nonaktifkan dulu
        if ($angkatan->aktif) {
            $jumlahPeserta = $angkatan->peserta()->count();
            if ($jumlahPeserta > 0) {
                return back()->with('error', "Tidak dapat menonaktifkan angkatan yang masih memiliki {$jumlahPeserta} peserta. Hapus peserta terlebih dahulu.");
            }
            $angkatan->update(['aktif' => false]);
            return redirect()->route('angkatan.index')->with('success', 'Angkatan berhasil dinonaktifkan. Angkatan nonaktif bisa dihapus permanen.');
        }

        // Sudah nonaktif → hapus permanen (cascade)
        \DB::transaction(function () use ($angkatan) {
            // Hapus semua data terkait angkatan
            $pesertaIds = $angkatan->peserta()->pluck('id');
            if ($pesertaIds->isNotEmpty()) {
                \App\Models\NilaiKepribadian::whereIn('peserta_didik_id', $pesertaIds)->delete();
                \App\Models\DetailKepribadian::whereIn('nilai_kepribadian_id',
                    \App\Models\NilaiKepribadian::whereIn('peserta_didik_id', $pesertaIds)->pluck('id')
                )->delete();
                \App\Models\NilaiAkademik::whereIn('peserta_didik_id', $pesertaIds)->delete();
                \App\Models\NilaiSamapta::whereIn('peserta_didik_id', $pesertaIds)->delete();
                \App\Models\KompilasiNilai::whereIn('peserta_didik_id', $pesertaIds)->delete();
                \App\Models\PeriodeNilai::where('angkatan_id', $angkatan->id)->delete();
                \App\Models\PesertaDidik::whereIn('id', $pesertaIds)->delete();
            }
            // Juga hapus periode yang mungkin tersisa
            \App\Models\PeriodeNilai::where('angkatan_id', $angkatan->id)->delete();
            $angkatan->delete();
        });
        return redirect()->route('angkatan.index')->with('success', 'Angkatan dan semua data terkait berhasil dihapus permanen.');
    }
}
