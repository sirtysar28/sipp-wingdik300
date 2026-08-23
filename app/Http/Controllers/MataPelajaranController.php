<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use App\Models\Skadik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MataPelajaranController extends Controller
{
    /**
     * Halaman manage mata pelajaran per sekolah.
     * 1 matpel bisa dipetakan ke banyak sekolah (many-to-many via pivot).
     */
    public function index(Request $request)
    {
        $allSkadik = Skadik::with('lemdik')->orderBy('nama')->get()->each(function ($s) {
            $s->mata_pelajaran_count = DB::table('mata_pelajaran_skadik')
                ->where('skadik_id', $s->id)->count();
        });
        $skadikId = $request->get('skadik_id', $allSkadik->first()?->id);

        // Matpel yang sudah dipetakan ke sekolah ini (aktif & nonaktif), urut pivot.
        $mapel = $skadikId ? MataPelajaran::forSkadik($skadikId, false) : collect();

        // Matpel yang BELUM dipetakan ke sekolah ini (kandidat untuk dipetakan).
        $availableToMap = collect();
        if ($skadikId) {
            $availableToMap = MataPelajaran::whereDoesntHave('skadiks', function ($q) use ($skadikId) {
                $q->where('skadik_id', $skadikId);
            })->orderBy('nama')->get();
        }

        // Statistik (dari matpel terpetakan, hanya yg aktif).
        $mapelAktif = $mapel->filter(fn($m) => $m->pivot->aktif);
        $totalJP        = $mapelAktif->sum('jp');
        $totalBobot     = $mapelAktif->sum('bobot');
        $totalHargaNilai = $mapelAktif->sum(fn($m) => $m->harga_nilai_calc);

        return view('mata-pelajaran.index', compact(
            'allSkadik', 'skadikId', 'mapel', 'availableToMap',
            'totalJP', 'totalBobot', 'totalHargaNilai'
        ));
    }

    /** Tambah mata pelajaran BARU + petakan ke sekolah terpilih. */
    public function store(Request $request)
    {
        $request->validate([
            'skadik_id' => 'required|exists:skadik,id',
            'nama'      => 'required|string|max:200',
            'kode'      => 'nullable|string|max:20',
            'jp'        => 'nullable|integer|min:0',
            'bobot'     => 'nullable|integer|min:0',
            'harga_nilai' => 'nullable|integer|min:0',
        ]);

        $skadikId = $request->skadik_id;
        $nextUrutan = (int) (DB::table('mata_pelajaran_skadik')
            ->where('skadik_id', $skadikId)->max('urutan') ?? 0) + 1;

        DB::transaction(function () use ($request, $skadikId, $nextUrutan) {
            $mp = MataPelajaran::create([
                'skadik_id'   => $skadikId, // metadata pemilik/pembuat
                'nama'        => $request->nama,
                'kode'        => $request->kode,
                'jp'          => $request->jp ?? 0,
                'bobot'       => $request->bobot ?? 0,
                'harga_nilai' => $request->harga_nilai ?? 0,
                'urutan'      => $nextUrutan,
                'aktif'       => true,
            ]);
            $mp->skadiks()->attach($skadikId, [
                'urutan' => $nextUrutan,
                'aktif'  => true,
            ]);
        });

        return back()->with('success', 'Mata pelajaran berhasil ditambahkan & dipetakan ke sekolah ini.');
    }

    /**
     * Petakan mata pelajaran yang SUDAH ADA (dari sekolah lain) ke sekolah ini.
     * Berguna saat beberapa sekolah memakai matpel yg sama (tanpa input ulang).
     */
    public function mapExisting(Request $request)
    {
        $request->validate([
            'skadik_id'          => 'required|exists:skadik,id',
            'mata_pelajaran_id'  => 'required|exists:mata_pelajaran,id',
        ]);

        $skadikId = $request->skadik_id;
        $mpId     = $request->mata_pelajaran_id;

        $already = DB::table('mata_pelajaran_skadik')
            ->where('skadik_id', $skadikId)
            ->where('mata_pelajaran_id', $mpId)
            ->exists();
        if ($already) {
            return back()->with('error', 'Mata pelajaran sudah dipetakan ke sekolah ini.');
        }

        $nextUrutan = (int) (DB::table('mata_pelajaran_skadik')
            ->where('skadik_id', $skadikId)->max('urutan') ?? 0) + 1;

        DB::table('mata_pelajaran_skadik')->insert([
            'mata_pelajaran_id' => $mpId,
            'skadik_id'         => $skadikId,
            'urutan'            => $nextUrutan,
            'aktif'             => true,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return back()->with('success', 'Mata pelajaran berhasil dipetakan ke sekolah ini.');
    }

    /**
     * Update matpel (properti global) + pivot (urutan/aktif) untuk sekolah terpilih.
     */
    public function update(Request $request, MataPelajaran $mataPelajaran)
    {
        $skadikId = $request->input('skadik_id');
        if (!$skadikId) {
            return back()->with('error', 'Sekolah tidak diketahui.');
        }

        // Toggle aktif via JSON (dari tombol toggle di tabel)
        if (($request->isJson() || $request->wantsJson()) && $request->has('aktif') && !$request->has('nama')) {
            $aktif = $request->boolean('aktif');
            DB::table('mata_pelajaran_skadik')
                ->where('mata_pelajaran_id', $mataPelajaran->id)
                ->where('skadik_id', $skadikId)
                ->update(['aktif' => $aktif, 'updated_at' => now()]);
            return response()->json(['success' => true]);
        }

        $request->validate([
            'nama'        => 'required|string|max:200',
            'kode'        => 'nullable|string|max:20',
            'jp'          => 'nullable|integer|min:0',
            'bobot'       => 'nullable|integer|min:0',
            'harga_nilai' => 'nullable|integer|min:0',
            'urutan'      => 'nullable|integer|min:0',
            'aktif'       => 'nullable|boolean',
        ]);

        $aktif = $request->has('aktif') ? $request->boolean('aktif') : true;

        DB::transaction(function () use ($request, $mataPelajaran, $skadikId, $aktif) {
            // Properti global matpel (dipakai semua sekolah yg memetakan matpel ini)
            $mataPelajaran->update([
                'nama'        => $request->nama,
                'kode'        => $request->kode,
                'jp'          => $request->jp ?? 0,
                'bobot'       => $request->bobot ?? 0,
                'harga_nilai' => $request->harga_nilai ?? 0,
            ]);

            // Properti per-sekolah (pivot)
            DB::table('mata_pelajaran_skadik')
                ->where('mata_pelajaran_id', $mataPelajaran->id)
                ->where('skadik_id', $skadikId)
                ->update([
                    'urutan' => $request->urutan ?? 0,
                    'aktif'  => $aktif,
                    'updated_at' => now(),
                ]);
        });

        return back()->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    /**
     * Hapus pemetaan matpel dari sekolah terpilih (unmap).
     * Jika matpel tidak lagi dipetakan ke sekolah manapun → hapus permanen.
     */
    public function destroy(Request $request, MataPelajaran $mataPelajaran)
    {
        $skadikId = $request->input('skadik_id');
        if (!$skadikId) {
            return back()->with('error', 'Sekolah tidak diketahui.');
        }

        DB::table('mata_pelajaran_skadik')
            ->where('mata_pelajaran_id', $mataPelajaran->id)
            ->where('skadik_id', $skadikId)
            ->delete();

        // Cleanup: bila matpel tak dipetakan ke sekolah manapun, hapus permanen.
        $masihDipakai = DB::table('mata_pelajaran_skadik')
            ->where('mata_pelajaran_id', $mataPelajaran->id)->exists();
        if (!$masihDipakai) {
            $mataPelajaran->delete();
        }

        return back()->with('success', 'Mata pelajaran dilepas dari sekolah ini.');
    }

    /**
     * Duplikasi pemetaan: ganti pemetaan sekolah tujuan dgn salinan dari sumber.
     * (Membuat matpel BARU milik tujuan, bukan share pivot — aman & sederhana.)
     */
    public function duplikasi(Request $request)
    {
        $request->validate([
            'from_skadik_id' => 'required|exists:skadik,id',
            'to_skadik_id'   => 'required|exists:skadik,id|different:from_skadik_id',
        ]);

        $fromId = $request->from_skadik_id;
        $toId   = $request->to_skadik_id;

        // Hapus pemetaan lama di tujuan + matpel yg hanya milik tujuan.
        $oldMapelIds = DB::table('mata_pelajaran_skadik')->where('skadik_id', $toId)->pluck('mata_pelajaran_id');
        DB::table('mata_pelajaran_skadik')->where('skadik_id', $toId)->delete();
        foreach ($oldMapelIds as $mid) {
            $masihDipakai = DB::table('mata_pelajaran_skadik')->where('mata_pelajaran_id', $mid)->exists();
            if (!$masihDipakai) {
                MataPelajaran::where('id', $mid)->delete();
            }
        }

        // Salin dari sumber (buat matpel baru milik tujuan + petakan)
        $source = MataPelajaran::forSkadik($fromId, false);
        foreach ($source as $s) {
            $mp = MataPelajaran::create([
                'skadik_id'   => $toId,
                'nama'        => $s->nama,
                'kode'        => $s->kode,
                'jp'          => $s->jp,
                'bobot'       => $s->bobot,
                'harga_nilai' => $s->harga_nilai,
                'urutan'      => $s->pivot->urutan,
                'aktif'       => $s->pivot->aktif,
            ]);
            $mp->skadiks()->attach($toId, [
                'urutan' => $s->pivot->urutan,
                'aktif'  => $s->pivot->aktif,
            ]);
        }

        $copied = $source->count();
        return back()->with('success', "Berhasil menduplikasi {$copied} mata pelajaran ke sekolah tujuan.");
    }

    /**
     * Muat template matpel ke sekolah terpilih (ganti total).
     */
    public function loadTemplate(Request $request)
    {
        $request->validate([
            'skadik_id'  => 'required|exists:skadik,id',
            'template'   => 'required|string',
        ]);

        $skadikId = $request->skadik_id;

        // Hapus pemetaan lama + matpel yg hanya milik sekolah ini
        $oldMapelIds = DB::table('mata_pelajaran_skadik')->where('skadik_id', $skadikId)->pluck('mata_pelajaran_id');
        DB::table('mata_pelajaran_skadik')->where('skadik_id', $skadikId)->delete();
        foreach ($oldMapelIds as $mid) {
            $masihDipakai = DB::table('mata_pelajaran_skadik')->where('mata_pelajaran_id', $mid)->exists();
            if (!$masihDipakai) {
                MataPelajaran::where('id', $mid)->delete();
            }
        }

        $templates = $this->getTemplates();
        $items = $templates[$request->template] ?? [];

        foreach ($items as $idx => $item) {
            $mp = MataPelajaran::create([
                'skadik_id'   => $skadikId,
                'nama'        => $item['nama'],
                'kode'        => $item['kode'],
                'jp'          => $item['jp'],
                'bobot'       => $item['bobot'],
                'harga_nilai' => $item['harga_nilai'] ?? 0,
                'urutan'      => $idx + 1,
                'aktif'       => true,
            ]);
            $mp->skadiks()->attach($skadikId, [
                'urutan' => $idx + 1,
                'aktif'  => true,
            ]);
        }

        $loadedCount = count($items);
        return back()->with('success', "Template \"{$request->template}\" berhasil dimuat ({$loadedCount} subjek).");
    }

    private function getTemplates(): array
    {
        return [
            'sbs-c130-fuel' => [
                ['nama' => 'Pengetahuan VMT', 'kode' => '1.1', 'jp' => 7, 'bobot' => 4, 'harga_nilai' => 16],
                ['nama' => 'Safety', 'kode' => '1.2', 'jp' => 8, 'bobot' => 6, 'harga_nilai' => 30],
                ['nama' => 'Technical Publications and Component Maintenance Forms', 'kode' => '1.3', 'jp' => 7, 'bobot' => 6, 'harga_nilai' => 26],
                ['nama' => 'Fuel System Publications', 'kode' => '1.4', 'jp' => 7, 'bobot' => 6, 'harga_nilai' => 26],
                ['nama' => 'Tools and Equipments', 'kode' => '1.5', 'jp' => 6, 'bobot' => 6, 'harga_nilai' => 23],
                ['nama' => 'Fuel Theory', 'kode' => '2.1', 'jp' => 8, 'bobot' => 6, 'harga_nilai' => 30],
                ['nama' => 'Material and Hardware', 'kode' => '2.2', 'jp' => 7, 'bobot' => 6, 'harga_nilai' => 26],
                ['nama' => 'Fuel Components', 'kode' => '2.3', 'jp' => 18, 'bobot' => 6, 'harga_nilai' => 67],
                ['nama' => 'Fuel System Overview', 'kode' => '2.4', 'jp' => 14, 'bobot' => 6, 'harga_nilai' => 52],
                ['nama' => 'Aircraft Refueling and Defueling', 'kode' => '3.1', 'jp' => 11, 'bobot' => 6, 'harga_nilai' => 41],
                ['nama' => 'Fuel System Testing & Fuel Strainer Screen Replacement', 'kode' => '3.2', 'jp' => 10, 'bobot' => 6, 'harga_nilai' => 38],
                ['nama' => 'Main Fuel System Description, Operation and Maintenance', 'kode' => '4.1', 'jp' => 45, 'bobot' => 6, 'harga_nilai' => 169],
                ['nama' => 'Main Fuel Tank Repair', 'kode' => '4.2', 'jp' => 33, 'bobot' => 6, 'harga_nilai' => 124],
                ['nama' => 'Auxiliary Fuel System Description, Operation, & Maintenance', 'kode' => '5.1', 'jp' => 11, 'bobot' => 6, 'harga_nilai' => 41],
                ['nama' => 'Fuel Tank Vent System Description, Operation, & Maintenance', 'kode' => '5.2', 'jp' => 9, 'bobot' => 6, 'harga_nilai' => 34],
                ['nama' => 'Aircraft Air Refuelling C-130', 'kode' => '6.1', 'jp' => 7, 'bobot' => 6, 'harga_nilai' => 26],
                ['nama' => 'Ground Aircraft Air Refuelling C-130', 'kode' => '6.2', 'jp' => 7, 'bobot' => 6, 'harga_nilai' => 26],
                ['nama' => 'Latihan Praktis', 'kode' => 'PR', 'jp' => 42, 'bobot' => 6, 'harga_nilai' => 157],
            ],
            'template-kosong' => [],
        ];
    }
}
