<?php
namespace App\Http\Controllers;

use App\Models\{Penandatangan, Skadik, Lemdik};
use Illuminate\Http\Request;

class PenandatanganController extends Controller
{
    public function index(Request $request)
    {
        $jenis   = $request->get('jenis', 'semua');
        $skadikId = $request->get('skadik_id', 'semua');
        $cakupan = $request->get('cakupan', 'semua');

        $query = Penandatangan::with(['skadik', 'lemdik'])->orderBy('jenis')->orderBy('created_at', 'desc');
        if ($jenis !== 'semua') $query->where('jenis', $jenis);
        if ($skadikId !== 'semua') $query->where('skadik_id', $skadikId);

        // Filter cakupan: global (Kasibinjas) / skadron (Danskadik 30x) / sekolah
        if ($cakupan === 'global')  $query->whereNull('skadik_id')->whereNull('lemdik_id');
        if ($cakupan === 'skadron') $query->whereNull('skadik_id')->whereNotNull('lemdik_id');
        if ($cakupan === 'sekolah') $query->whereNotNull('skadik_id');

        $penandatangan = $query->get();

        $allSkadik = Skadik::listForUser();
        $allLemdik = Lemdik::orderBy('nama')->get();

        // Revisi 22 Sept 2026: jenis 'danskadik' kini dipakai kolom KIRI
        // ("Mengetahui") di SEMUA laporan (NPA/NPK/NPS/NPP — cetak & ekspor),
        // bukan NPP saja.
        $jenisList = [
            'umum'        => 'Umum (Semua Laporan)',
            'akademik'    => 'NPA (Akademik)',
            'kepribadian' => 'NPK (Kepribadian)',
            'samapta'     => 'NPS (Samapta)',
            'kompilasi'   => 'NPP (Kompilasi)',
            'danskadik'   => 'Danskadik (Semua Laporan — Kiri)',
        ];

        return view('penandatangan.index', compact('penandatangan', 'jenis', 'skadikId', 'cakupan', 'allSkadik', 'allLemdik', 'jenisList'));
    }

    /**
     * Normalisasi cakupan dari input form:
     *   cakupan=global  → skadik_id = null, lemdik_id = null   (mis. Kasibinjas)
     *   cakupan=skadron → lemdik_id wajib, skadik_id = null    (Danskadik 30x)
     *   cakupan=sekolah → skadik_id wajib, lemdik_id = null    (sekolah spesifik)
     *
     * @return array{0: array|null data utk create/update, 1: string label cakupan / pesan error}
     */
    private function resolveCakupan(Request $request, array $data): array
    {
        $cakupan = $request->input('cakupan', 'sekolah');

        if (!in_array($cakupan, ['global', 'skadron', 'sekolah'])) {
            $cakupan = 'sekolah';
        }

        if ($cakupan === 'global') {
            $data['skadik_id'] = null;
            $data['lemdik_id'] = null;
            $label = 'Global (Semua Sekolah)';
        } elseif ($cakupan === 'skadron') {
            if (empty($data['lemdik_id'])) {
                return [null, 'Cakupan Skadron dipilih — pilih Skadron (mis. Skadik 301) terlebih dahulu.'];
            }
            $data['skadik_id'] = null;
            $label = (Lemdik::find($data['lemdik_id'])?->singkat ?? 'Skadron') . ' (semua sekolah under skadron)';
        } else {
            if (empty($data['skadik_id'])) {
                return [null, 'Cakupan Sekolah dipilih — pilih sekolah terlebih dahulu, atau gunakan cakupan Global/Skadron.'];
            }
            $data['lemdik_id'] = null;
            $label = Skadik::find($data['skadik_id'])?->nama ?? 'Sekolah';
        }

        return [$data, $label];
    }

    /**
     * Cek bentrok: sudah ada penandatangan AKTIF lain utk kombinasi jenis + cakupan yg sama.
     * Jika bentrok → penandatangan LAMA otomatis dinonaktifkan (riwayat tetap tersimpan),
     * sehingga pergantian pejabat (mis. Danskadik diganti) cukup TAMBAH BARIS baru;
     * atau tetap bisa EDIT nama di baris lama — dua-duanya didukung.
     *
     * @return string|null pesan info utk user jika ada yg dinonaktifkan
     */
    private function nonaktifkanYangLama(array $data, ?int $ignoreId = null): ?string
    {
        $q = Penandatangan::where('jenis', $data['jenis'])->where('aktif', true);
        if ($data['skadik_id']) $q->where('skadik_id', $data['skadik_id']);
        elseif ($data['lemdik_id']) $q->whereNull('skadik_id')->where('lemdik_id', $data['lemdik_id']);
        else $q->whereNull('skadik_id')->whereNull('lemdik_id');

        if ($ignoreId) $q->where('id', '!=', $ignoreId);

        $lama = $q->first();
        if (!$lama) return null;

        if ($lama->update(['aktif' => false])) {
            return "Penandatangan lama utk jenis '{$data['jenis']}' di cakupan ini ({$lama->nama}) otomatis dinonaktifkan — baris lama tetap tersimpan sebagai riwayat.";
        }
        return null;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama'      => 'required|string|max:150',
            'nrp'       => 'required|string|max:30',
            'pangkat'   => 'nullable|string|max:100',
            'jabatan'   => 'required|string|max:200',
            'jenis'     => 'required|in:umum,akademik,kepribadian,samapta,kompilasi,danskadik',
            'cakupan'   => 'nullable|in:global,skadron,sekolah',
            'skadik_id' => 'nullable|exists:skadik,id',
            'lemdik_id' => 'nullable|exists:lemdik,id',
        ]);

        [$data, $labelCakupan] = $this->resolveCakupan($request, $request->only('nama', 'nrp', 'pangkat', 'jabatan', 'jenis', 'skadik_id', 'lemdik_id'));
        if ($data === null) return back()->with('error', $labelCakupan);

        $info = $this->nonaktifkanYangLama($data);

        Penandatangan::create($data);

        $pesan = 'Penandatangan berhasil ditambahkan (cakupan: ' . $labelCakupan . ').';
        if ($info) $pesan .= ' ℹ️ ' . $info;
        return back()->with('success', $pesan);
    }

    public function update(Request $request, $id)
    {
        $pen = Penandatangan::findOrFail($id);
        $request->validate([
            'nama'      => 'required|string|max:150',
            'nrp'       => 'required|string|max:30',
            'pangkat'   => 'nullable|string|max:100',
            'jabatan'   => 'required|string|max:200',
            'jenis'     => 'required|in:umum,akademik,kepribadian,samapta,kompilasi,danskadik',
            'cakupan'   => 'nullable|in:global,skadron,sekolah',
            'skadik_id' => 'nullable|exists:skadik,id',
            'lemdik_id' => 'nullable|exists:lemdik,id',
            'aktif'     => 'boolean',
        ]);

        [$data, $labelCakupan] = $this->resolveCakupan($request, $request->only('nama', 'nrp', 'pangkat', 'jabatan', 'jenis', 'skadik_id', 'lemdik_id'));
        if ($data === null) return back()->with('error', $labelCakupan);

        $data['aktif'] = $request->boolean('aktif');

        // Jika baris ini diaktifkan & menimpa baris aktif lain di cakupan sama → nonaktifkan yg lama
        $info = null;
        if ($data['aktif']) {
            $info = $this->nonaktifkanYangLama($data, (int) $id);
        }

        $pen->update($data);

        $pesan = 'Penandatangan berhasil diperbarui (cakupan: ' . $labelCakupan . ').';
        if ($info) $pesan .= ' ℹ️ ' . $info;
        return back()->with('success', $pesan);
    }

    /**
     * Duplikat baris: buat salinan (nonaktif) sebagai titik awal.
     * Praktis utk bikin 4 baris Danskadik (301–304) atau baris Kasibinjas baru
     * tanpa mengulang input data dari nol.
     */
    public function duplicate($id)
    {
        $asli = Penandatangan::findOrFail($id);

        $salinan = $asli->replicate();
        $salinan->nama  = $asli->nama;          // data diisi persis, tinggal disesuaikan user
        $salinan->aktif = false;                 // nonaktif agar tidak bentrok dgn baris asli
        $salinan->save();

        return back()->with('success', "Baris berhasil diduplikat: \"{$salinan->nama}\" (status NONAKTIF). Edit salinan tsb, sesuaikan nama/cakupan, lalu aktifkan bila perlu.");
    }

    public function destroy($id)
    {
        Penandatangan::findOrFail($id)->delete();
        return back()->with('success', 'Penandatangan berhasil dihapus.');
    }
}
