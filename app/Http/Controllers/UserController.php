<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Eager-load relasi pivot skadiks() agar daftar multi-skadik tampil di tabel.
        // skadik.lemdik & skadiks.lemdik di-load agar label ringkas per-skadron
        // ("Skadik 301/302/303/304") bisa dihitung di tabel Manage User.
        $query = User::with(['skadik.lemdik', 'skadiks.lemdik']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        // Filter berdasarkan skadik: cek kolom tunggal (skadik_id) maupun pivot.
        if ($skadikId = $request->get('skadik_id')) {
            $query->where(function ($q) use ($skadikId) {
                $q->where('skadik_id', $skadikId)
                  ->orWhereHas('skadiks', fn($qq) => $qq->where('skadik_id', $skadikId));
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(12);

        $roles = [
            'super_admin'        => 'Super Admin (WingDik)',
            'admin'              => 'Opsdik',
            'admin_akademik'     => 'Kepala Sekolah',
            'admin_kepribadian'  => 'Danflight',
            'admin_samapta'      => 'Binjaswing',
        ];

        // Only show skadik from Lembaga Pendidikan
        $skadikList = \App\Models\Skadik::with('lemdik')->orderBy('nama')->get();

        // Daftar Lembaga Pendidikan (Skadron 301/302/303/304) untuk tombol
        // quick-select "Skadik XXX" di form tambah/edit user.
        $lemdikList = \App\Models\Lemdik::orderBy('nama')->get();

        // Map lemdik_id => [skadik ids] — sumber kebenaran untuk:
        //   1. toggling chip quick-select di frontend (pilih semua sekolah dlm 1 skadron)
        //   2. menghitung apakah user mencakup seluruh skadron (→ label ringkas)
        $lemdikSkadikIds = $skadikList->groupBy('lemdik_id')
            ->map(fn ($g) => $g->pluck('id')->map(fn ($i) => (int) $i)->values()->all());

        return view('manage-user.index', compact('users', 'roles', 'skadikList', 'lemdikList', 'lemdikSkadikIds'));
    }

    public function store(Request $request)
    {
        $isSuper = $request->role === 'super_admin';

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:super_admin,admin_akademik,admin_kepribadian,admin_samapta,admin',
            'skadik_id' => 'nullable|exists:skadik,id',
            'skadik_ids'=> 'sometimes|array',
            'skadik_ids.*'=> 'exists:skadik,id',
        ]);

        // Admin modul (akademik/kepribadian/samapta) & admin skadik wajib punya
        // minimal 1 sekolah. Super Admin bebas.
        if (!$isSuper && empty($request->input('skadik_ids')) && !$request->filled('skadik_id')) {
            return back()->withErrors(['skadik_ids' => 'Pilih minimal 1 sekolah (skadik) untuk user ini.'])->withInput();
        }

        // Admin modul (akademik/kepribadian/samapta) & admin skadik boleh akses
        // >1 sekolah. Skadik diambil dari array multi-select bila ada, kalau tidak
        // fallback ke skadik_id tunggal (kompatibilitas form lama).
        $skadikIds = $this->resolveSkadikIds($request, $isSuper);

        // skadik_id kolom tunggal = skadik utama (default), pivot = akses penuh.
        $primarySkadikId = $isSuper ? null : ($skadikIds[0] ?? $request->skadik_id);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'skadik_id' => $primarySkadikId,
        ]);

        // Sinkronkan akses multi-skadik via pivot.
        $user->skadiks()->sync($skadikIds);

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Super admin tidak bisa diubah role-nya kecuali oleh super admin lain
        if ($user->role === 'super_admin' && auth()->user()->role !== 'super_admin') {
            abort(403, 'Tidak bisa mengubah Super Admin.');
        }

        $isSuper = $request->role === 'super_admin';

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,' . $id,
            'role'      => 'required|in:super_admin,admin_akademik,admin_kepribadian,admin_samapta,admin',
            'skadik_id' => 'nullable|exists:skadik,id',
            'skadik_ids'=> 'sometimes|array',
            'skadik_ids.*'=> 'exists:skadik,id',
        ]);

        if (!$isSuper && empty($request->input('skadik_ids')) && !$request->filled('skadik_id')) {
            return back()->withErrors(['skadik_ids' => 'Pilih minimal 1 sekolah (skadik) untuk user ini.'])->withInput();
        }

        $skadikIds = $this->resolveSkadikIds($request, $isSuper);
        $primarySkadikId = $isSuper ? null : ($skadikIds[0] ?? $request->skadik_id);

        $user->update([
            'name'      => $request->name,
            'email'     => $request->email,
            'role'      => $request->role,
            'skadik_id' => $primarySkadikId,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        // Sinkronkan akses multi-skadik via pivot.
        $user->skadiks()->sync($skadikIds);

        return back()->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Kumpulkan ID skadik dari input. Utamakan multi-select (skadik_ids[]),
     * fallback ke skadik_id tunggal. Super Admin → kosong (akses semua).
     */
    private function resolveSkadikIds(Request $request, bool $isSuper): array
    {
        if ($isSuper) return [];

        $ids = array_filter(array_map('intval', (array) $request->input('skadik_ids', [])));
        if (empty($ids) && $request->filled('skadik_id')) {
            $ids = [(int) $request->skadik_id];
        }
        return array_values(array_unique($ids));
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Tidak bisa hapus diri sendiri
        if ($user->id === auth()->id()) {
            return back()->withErrors(['Tidak bisa menghapus akun sendiri.']);
        }

        // Hanya super admin yang bisa hapus super admin lain
        if ($user->role === 'super_admin') {
            abort(403, 'Tidak bisa menghapus Super Admin.');
        }

        $user->delete();
        return back()->with('success', 'User berhasil dihapus.');
    }
}
