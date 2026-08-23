<?php
namespace App\Http\Controllers;

use App\Models\{Skadik, Lemdik};
use Illuminate\Http\Request;

class SkadikController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $query = Skadik::with('lemdik');

        // Filter pencarian: nama sekolah, kode, lembaga pendidikan, atau keterangan.
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('nama', 'like', $like)
                  ->orWhere('kode', 'like', $like)
                  ->orWhere('keterangan', 'like', $like)
                  ->orWhereHas('lemdik', fn($qq) => $qq->where('nama', 'like', $like));
            });
        }

        $skadik = $query->orderBy('nama')
            ->paginate(12)
            ->appends($request->only(['search']));

        return view('skadik.index', compact('skadik', 'search'));
    }

    public function create()
    {
        $lemdik = Lemdik::where('aktif', true)->get();
        return view('skadik.create', compact('lemdik'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lemdik_id'        => 'required|exists:lemdik,id',
            'nama'             => 'required|string|max:100',
            'kode'             => 'required|string|unique:skadik,kode',
            'keterangan'       => 'nullable|string',
            'kepala_sekolah'   => 'nullable|string|max:100',
            'pangkat_kepala'   => 'nullable|string|max:50',
            'nrp_kepala'       => 'nullable|string|max:50',
            'jenjang'          => 'required|in:perwira,bintara,tamtama,pns',
            'jenis_pendidikan' => 'required|in:dikbangspes,dikcabpa,dikjurbata,dikjurPNS,dikmatuklih',
        ]);
        Skadik::create($data);
        return redirect()->route('skadik.index')->with('success', 'Sekolah berhasil ditambahkan.');
    }

    public function edit(Skadik $skadik)
    {
        $lemdik = Lemdik::where('aktif', true)->get();
        return view('skadik.edit', compact('skadik', 'lemdik'));
    }

    public function update(Request $request, Skadik $skadik)
    {
        $data = $request->validate([
            'lemdik_id'        => 'required|exists:lemdik,id',
            'nama'             => 'required|string|max:100',
            'kode'             => 'required|string|unique:skadik,kode,' . $skadik->id,
            'keterangan'       => 'nullable|string',
            'kepala_sekolah'   => 'nullable|string|max:100',
            'pangkat_kepala'   => 'nullable|string|max:50',
            'nrp_kepala'       => 'nullable|string|max:50',
            'jenjang'          => 'required|in:perwira,bintara,tamtama,pns',
            'jenis_pendidikan' => 'required|in:dikbangspes,dikcabpa,dikjurbata,dikjurPNS,dikmatuklih',
        ]);
        $skadik->update($data);
        return redirect()->route('skadik.index')->with('success', 'Sekolah berhasil diperbarui.');
    }

    public function destroy(Skadik $skadik)
    {
        // Cek apakah masih dipakai angkatan
        if ($skadik->angkatan()->count() > 0) {
            return back()->with('error', 'Sekolah tidak dapat dihapus karena masih memiliki angkatan.');
        }
        $skadik->delete();
        return redirect()->route('skadik.index')->with('success', 'Sekolah berhasil dihapus.');
    }
}
