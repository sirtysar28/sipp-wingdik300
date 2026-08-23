<?php
namespace App\Http\Controllers;

use App\Models\{Lemdik, Skadik};
use Illuminate\Http\Request;

class LemdikController extends Controller
{
    public function index()
    {
        $lemdik = Lemdik::with('skadik')->paginate(12);
        return view('lemdik.index', compact('lemdik'));
    }

    public function create()
    {
        return view('lemdik.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'    => 'required|string|max:100|unique:lemdik,nama',
            'kode'    => 'required|string|unique:lemdik,kode',
            'alamat'  => 'nullable|string|max:255',
            'kota'    => 'required|string|max:100',
            'wingdik' => 'nullable|string|max:100',
        ]);
        Lemdik::create($request->only('nama', 'kode', 'alamat', 'kota', 'wingdik'));
        return redirect()->route('lemdik.index')->with('success', 'Lembaga Pendidikan berhasil ditambahkan.');
    }

    public function edit(Lemdik $lemdik)
    {
        return view('lemdik.edit', compact('lemdik'));
    }

    public function update(Request $request, Lemdik $lemdik)
    {
        $request->validate([
            'nama'    => 'required|string|max:100|unique:lemdik,nama,' . $lemdik->id,
            'kode'    => 'required|string|unique:lemdik,kode,' . $lemdik->id,
            'alamat'  => 'nullable|string|max:255',
            'kota'    => 'required|string|max:100',
            'wingdik' => 'nullable|string|max:100',
        ]);
        $lemdik->update($request->only('nama', 'kode', 'alamat', 'kota', 'wingdik'));
        return redirect()->route('lemdik.index')->with('success', 'Data Lembaga Pendidikan diperbarui.');
    }

    public function destroy(Lemdik $lemdik)
    {
        if ($lemdik->skadik()->exists()) {
            return back()->with('error', 'Tidak bisa menghapus lembaga yang masih memiliki sekolah terkait. Hapus/lepaskan sekolah terlebih dahulu.');
        }
        $lemdik->delete();
        return redirect()->route('lemdik.index')->with('success', 'Lembaga Pendidikan berhasil dihapus.');
    }
}
