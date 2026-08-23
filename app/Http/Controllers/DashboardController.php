<?php
namespace App\Http\Controllers;

use App\Models\{Angkatan, PesertaDidik, PeriodeNilai, NilaiKepribadian, AspekKepribadian, Skadik, KompilasiNilai, NilaiAkademik, NilaiSamapta};
use App\Services\NppCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    public function index(Request $request) {
        // ── Data untuk filter dropdown ──
        $allSkadik = Skadik::listForUser();
        $skadikId  = $request->get('skadik_id', $allSkadik->first()?->id);

        $allAngkatan = $skadikId
            ? Angkatan::where('skadik_id', $skadikId)->where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get()
            : Angkatan::where('aktif', true)->with('skadik')->orderBy('created_at', 'desc')->get();

        $angkatanId = $request->get('angkatan_id', $allAngkatan->first()?->id);
        $angkatan   = Angkatan::with('skadik.lemdik')->find($angkatanId);

        if (!$angkatan) {
            return view('dashboard.index', [
                'angkatan'        => null,
                'kompilasiData'   => collect(),
                'allSkadik'       => $allSkadik,
                'skadikId'        => $skadikId,
                'allAngkatan'     => $allAngkatan,
                'angkatanId'      => $angkatanId,
            ] + $this->emptyStats());
        }

        // ── Kompilasi NPP dihitung LIVE dari sumber asli (semua peserta) ──
        // Memastikan podium & ranking 4-10 selalu lengkap & benar.
        $kompilasiData = NppCalculator::forAngkatan($angkatanId);

        // ── Stats Komponen Nilai ──
        $pesertaIds = PesertaDidik::where('angkatan_id', $angkatanId)->pluck('id');
        $totalPeserta = $pesertaIds->count();

        $sudahNPA = NilaiAkademik::whereIn('peserta_didik_id', $pesertaIds)->where('angkatan_id', $angkatanId)->count();
        $sudahNPK = NilaiKepribadian::whereIn('peserta_didik_id', $pesertaIds)->count();
        $sudahNPS = NilaiSamapta::whereIn('peserta_didik_id', $pesertaIds)->where('angkatan_id', $angkatanId)->count();

        $avgNPA = NilaiAkademik::whereIn('peserta_didik_id', $pesertaIds)->where('angkatan_id', $angkatanId)->avg('npa');
        $avgNPK = NilaiKepribadian::whereIn('peserta_didik_id', $pesertaIds)->avg('nilai_akhir');
        $avgNPS = NilaiSamapta::whereIn('peserta_didik_id', $pesertaIds)->where('angkatan_id', $angkatanId)->avg('nilai_akhir');

        $sudahKompilasi = $kompilasiData->count();
        $avgNPP = $kompilasiData->count() > 0 ? round($kompilasiData->avg('nilai_akhir'), 2) : 0;
        $tertinggiNPP = $kompilasiData->max('nilai_akhir') ?? 0;
        $terendahNPP = $kompilasiData->min('nilai_akhir') ?? 0;
        $dibawahRataNPP = $kompilasiData->filter(fn($k) => $k->nilai_akhir < $avgNPP)->count();

        // ── Status kelengkapan ──
        $belumLengkap = 0;
        $lengkap = 0;
        foreach ($pesertaIds as $pid) {
            $hasNPA = NilaiAkademik::where('peserta_didik_id', $pid)->where('angkatan_id', $angkatanId)->exists();
            $hasNPK = NilaiKepribadian::where('peserta_didik_id', $pid)->exists();
            $hasNPS = NilaiSamapta::where('peserta_didik_id', $pid)->where('angkatan_id', $angkatanId)->exists();
            if ($hasNPA && $hasNPK && $hasNPS) $lengkap++;
            else $belumLengkap++;
        }

        // ── Distribusi NPP ──
        $distribusi = [
            '≥85' => 0, '80-84' => 0, '75-79' => 0, '70-74' => 0, '65-69' => 0, '<65' => 0,
        ];
        foreach ($kompilasiData as $k) {
            $v = $k->nilai_akhir;
            if ($v >= 85) $distribusi['≥85']++;
            elseif ($v >= 80) $distribusi['80-84']++;
            elseif ($v >= 75) $distribusi['75-79']++;
            elseif ($v >= 70) $distribusi['70-74']++;
            elseif ($v >= 65) $distribusi['65-69']++;
            else $distribusi['<65']++;
        }

        return view('dashboard.index', compact(
            'angkatan', 'kompilasiData',
            'totalPeserta', 'sudahNPA', 'sudahNPK', 'sudahNPS', 'sudahKompilasi',
            'avgNPA', 'avgNPK', 'avgNPS', 'avgNPP', 'tertinggiNPP', 'terendahNPP',
            'dibawahRataNPP', 'belumLengkap', 'lengkap', 'distribusi',
            'allSkadik', 'skadikId', 'allAngkatan', 'angkatanId'
        ));
    }

    /** Nilai default ketika belum ada angkatan */
    private function emptyStats(): array
    {
        return [
            'totalPeserta' => 0, 'sudahNPA' => 0, 'sudahNPK' => 0, 'sudahNPS' => 0,
            'sudahKompilasi' => 0, 'avgNPA' => 0, 'avgNPK' => 0, 'avgNPS' => 0,
            'avgNPP' => 0, 'tertinggiNPP' => 0, 'terendahNPP' => 0,
            'dibawahRataNPP' => 0, 'belumLengkap' => 0, 'lengkap' => 0,
            'distribusi' => ['≥85' => 0, '80-84' => 0, '75-79' => 0, '70-74' => 0, '65-69' => 0, '<65' => 0],
        ];
    }

    // ── Aktifkan periode ─────────────────────────────────────
    public function aktifkanPeriode(PeriodeNilai $periode)
    {
        $angkatanId = $periode->angkatan_id;
        PeriodeNilai::where('angkatan_id', $angkatanId)->update(['aktif' => false]);
        $periode->update(['aktif' => true]);

        $params = [
            'skadik_id'   => request('redirect_skadik_id'),
            'angkatan_id' => request('redirect_angkatan_id'),
        ];

        return redirect()->route('dashboard', $params)->with('success', "Periode {$periode->label} berhasil diaktifkan.");
    }
}
