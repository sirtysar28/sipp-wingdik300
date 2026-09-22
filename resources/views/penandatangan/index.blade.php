@extends('layouts.app')
@section('page-title', 'Penandatangan')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">✍️ Penandatangan</h2>
    <p style="font-size:12px;color:#888">Kelola data penandatangan laporan. Cakupan bertingkat: Sekolah › Skadron (Danskadik 30x) › Global (Kasibinjas).</p>
  </div>
  <button type="button" class="btn btn-smart" onclick="openTambah()">➕ Tambah Penandatangan</button>
</div>

{{-- Modal Tambah / Duplikat --}}
<div id="modalTambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:2000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-size:16px;font-weight:600" id="judulTambah">Tambah Penandatangan</h3>
      <button onclick="document.getElementById('modalTambah').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888">&times;</button>
    </div>
    <form method="POST" action="{{ route('penandatangan.store') }}">
      @csrf

      <div class="form-group">
        <label>Cakupan Berlaku <span style="color:#dc2626">*</span></label>
        <select name="cakupan" id="tambahCakupan" onchange="toggleCakupan('tambah')" required>
          <option value="sekolah">🏫 Sekolah Tertentu</option>
          <option value="skadron">🛩️ Skadron / Skadik 30x (semua sekolah under skadron — utk Danskadik)</option>
          <option value="global">🌍 Global (Semua Sekolah — utk Kasibinjas)</option>
        </select>
        <div style="font-size:10px;color:#999;margin-top:2px">Urutan pakai di laporan: Sekolah → Skadron → Global. Baris lebih spesifik menang.</div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group" id="wrapTambahSkadik">
          <label>Sekolah <span style="color:#dc2626">*</span></label>
          <select name="skadik_id" id="tambahSkadik">
            <option value="">— pilih sekolah —</option>
            @foreach($allSkadik as $sk)
              <option value="{{ $sk->id }}">{{ $sk->nama }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" id="wrapTambahLemdik" style="display:none">
          <label>Skadron / Skadik <span style="color:#dc2626">*</span></label>
          <select name="lemdik_id" id="tambahLemdik">
            <option value="">— pilih skadron —</option>
            @foreach($allLemdik as $lm)
              <option value="{{ $lm->id }}">{{ $lm->singkat }} — {{ $lm->nama }}</option>
            @endforeach
          </select>
          <div style="font-size:10px;color:#999;margin-top:2px">1 baris danskadik per skadron otomatis dipakai SEMUA sekolah under skadron tsb (cukup 4 baris utk 4 danskadik).</div>
        </div>
        <div class="form-group">
          <label>Jenis / Kategori <span style="color:#dc2626">*</span></label>
          <select name="jenis" id="tambahJenis" required>
            @foreach($jenisList as $val => $label)
              <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Nama Lengkap <span style="color:#dc2626">*</span></label>
          <input type="text" name="nama" id="tambahNama" required placeholder="Nama Lengkap">
        </div>
        <div class="form-group">
          <label>NRP <span style="color:#dc2626">*</span></label>
          <input type="text" name="nrp" id="tambahNrp" required placeholder="NRP">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Pangkat</label>
          <input type="text" name="pangkat" id="tambahPangkat" placeholder="cth: Kaptes Kes">
        </div>
        <div class="form-group">
          <label>Jabatan <span style="color:#dc2626">*</span></label>
          <input type="text" name="jabatan" id="tambahJabatan" required placeholder="Jabatan">
        </div>
      </div>

      <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary">💾 Simpan</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalTambah').style.display='none'">Batal</button>
      </div>
      <div style="font-size:10px;color:#999;margin-top:8px">💡 Jika utk jenis &amp; cakupan yg sama sudah ada baris aktif, baris lama otomatis <strong>dinonaktifkan</strong> (riwayat tetap tersimpan) — cocok utk pergantian pejabat.</div>
    </form>
  </div>
</div>

{{-- Filter --}}
<div class="filter-bar">
  <form method="GET" action="{{ route('penandatangan.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;min-width:160px">
      <label>Filter Cakupan</label>
      <select name="cakupan" onchange="this.form.submit()">
        <option value="semua" {{ $cakupan=='semua'?'selected':'' }}>Semua Cakupan</option>
        <option value="global" {{ $cakupan=='global'?'selected':'' }}>🌍 Global</option>
        <option value="skadron" {{ $cakupan=='skadron'?'selected':'' }}>🛩️ Skadron (Danskadik)</option>
        <option value="sekolah" {{ $cakupan=='sekolah'?'selected':'' }}>🏫 Sekolah</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:180px">
      <label>Filter Sekolah</label>
      <select name="skadik_id" onchange="this.form.submit()">
        <option value="semua" {{ $skadikId=='semua'?'selected':'' }}>Semua Sekolah</option>
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $skadikId==$sk->id?'selected':'' }}>{{ $sk->nama }}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:200px">
      <label>Filter Jenis</label>
      <select name="jenis" onchange="this.form.submit()">
        <option value="semua" {{ $jenis=='semua'?'selected':'' }}>Semua Jenis</option>
        @foreach($jenisList as $val => $label)
          <option value="{{ $val }}" {{ $jenis==$val?'selected':'' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </form>
</div>

{{-- Tabel --}}
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Cakupan</th>
          <th>Nama</th>
          <th>Pangkat</th>
          <th>NRP</th>
          <th>Jabatan</th>
          <th>Jenis</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center;width:150px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($penandatangan as $idx => $p)
        <tr data-id="{{ $p->id }}"
            data-nama="{{ $p->nama }}"
            data-nrp="{{ $p->nrp }}"
            data-pangkat="{{ $p->pangkat }}"
            data-jabatan="{{ $p->jabatan }}"
            data-jenis="{{ $p->jenis }}"
            data-skadik="{{ $p->skadik_id }}"
            data-lemdik="{{ $p->lemdik_id }}"
            data-aktif="{{ $p->aktif ? 1 : 0 }}">
          <td>{{ $idx + 1 }}</td>
          <td>
            @if($p->skadik_id)
              <span class="badge badge-purple">🏫 {{ $p->skadik->nama_singkat }}</span>
            @elseif($p->lemdik_id)
              <span class="badge badge-blue">🛩️ {{ $p->lemdik->singkat }} (Skadron)</span>
            @else
              <span class="badge" style="background:#f3f4f6;color:#666">🌍 Global</span>
            @endif
          </td>
          <td><strong>{{ $p->nama }}</strong></td>
          <td style="font-size:12px;color:#666">{{ $p->pangkat ?? '-' }}</td>
          <td style="font-family:Arial,Helvetica,sans-serif;color:#666">{{ $p->nrp }}</td>
          <td>{{ $p->jabatan }}</td>
          <td>
            <span class="badge badge-{{ $p->jenis=='umum'?'purple':($p->jenis=='akademik'?'green':($p->jenis=='kepribadian'?'blue':($p->jenis=='samapta'?'orange':($p->jenis=='danskadik'?'amber':'blue')))) }}">
              {{ $jenisList[$p->jenis] ?? $p->jenis }}
            </span>
          </td>
          <td style="text-align:center">
            @if($p->aktif)
              <span class="badge badge-green">Aktif</span>
            @else
              <span class="badge badge-amber">Nonaktif</span>
            @endif
          </td>
          <td style="text-align:center;white-space:nowrap">
            <button type="button" onclick="openEdit(this.closest('tr'))" class="btn btn-sm btn-outline" title="Edit">✏️</button>
            <form method="POST" action="{{ route('penandatangan.duplicate', $p->id) }}" style="display:inline" onsubmit="return confirm('Duplikat baris ini? Salinan dibuat NONAKTIF — edit lalu aktifkan bila perlu.')">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline" title="Duplikat baris">📄</button>
            </form>
            <form method="POST" action="{{ route('penandatangan.destroy', $p->id) }}" style="display:inline" onsubmit="return confirm('Hapus penandatangan ini?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger" title="Hapus">🗑️</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="9"><div class="empty-state">Belum ada data penandatangan.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Info --}}
<div style="margin-top:12px;font-size:11px;color:#999;display:flex;gap:20px;flex-wrap:wrap">
  <span>🌍 <strong>Global</strong> = dipakai semua sekolah (cth. Kasibinjas)</span>
  <span>🛩️ <strong>Skadron</strong> = 1 baris danskadik 30x utk semua sekolah under skadron 30x (cukup 4 baris)</span>
  <span>🏫 <strong>Sekolah</strong> = hanya utk sekolah tsb (paling kuat / menimpa)</span>
  <span>📝 <strong>Danskadik</strong> = kolom kiri "Mengetahui" di SEMUA laporan (NPA/NPK/NPS/NPP — cetak &amp; ekspor)</span>
  <span>🔄 <strong>Danskadik diganti?</strong> Tambah baris baru (yg lama auto-nonaktif jadi riwayat) atau edit nama baris lama — dua-duanya bisa</span>
</div>

{{-- Modal Edit --}}
<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:2000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-size:16px;font-weight:600">✏️ Edit Penandatangan</h3>
      <button onclick="document.getElementById('modalEdit').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888">&times;</button>
    </div>
    <form method="POST" id="formEdit">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <input type="hidden" name="id" id="editId">

      <div class="form-group">
        <label>Cakupan Berlaku</label>
        <select name="cakupan" id="editCakupan" onchange="toggleCakupan('edit')">
          <option value="sekolah">🏫 Sekolah Tertentu</option>
          <option value="skadron">🛩️ Skadron / Skadik 30x (semua sekolah under skadron — utk Danskadik)</option>
          <option value="global">🌍 Global (Semua Sekolah — utk Kasibinjas)</option>
        </select>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group" id="wrapEditSkadik">
          <label>Sekolah</label>
          <select name="skadik_id" id="editSkadik">
            <option value="">— pilih sekolah —</option>
            @foreach($allSkadik as $sk)
              <option value="{{ $sk->id }}">{{ $sk->nama }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" id="wrapEditLemdik" style="display:none">
          <label>Skadron / Skadik</label>
          <select name="lemdik_id" id="editLemdik">
            <option value="">— pilih skadron —</option>
            @foreach($allLemdik as $lm)
              <option value="{{ $lm->id }}">{{ $lm->singkat }} — {{ $lm->nama }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label>Jenis</label>
          <select name="jenis" id="editJenis" required>
            @foreach($jenisList as $val => $label)
              <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Nama</label>
          <input type="text" name="nama" id="editNama" required>
        </div>
        <div class="form-group">
          <label>NRP</label>
          <input type="text" name="nrp" id="editNrp" required>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Pangkat</label>
          <input type="text" name="pangkat" id="editPangkat">
        </div>
        <div class="form-group">
          <label>Jabatan</label>
          <input type="text" name="jabatan" id="editJabatan" required>
        </div>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="aktif" id="editAktif" value="1" checked>
          Aktif
        </label>
      </div>

      <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary">💾 Simpan</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalEdit').style.display='none'">Batal</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
// Tampilkan/sembunyikan dropdown sekolah vs skadron sesuai cakupan
function toggleCakupan(prefix) {
  var cakupan = document.getElementById(prefix + 'Cakupan').value;
  document.getElementById('wrap' + ucfirst(prefix) + 'Skadik').style.display = (cakupan === 'sekolah') ? '' : 'none';
  document.getElementById('wrap' + ucfirst(prefix) + 'Lemdik').style.display = (cakupan === 'skadron') ? '' : 'none';

  // Nonaktifkan field tersembunyi agar tidak ikut ter-submit
  document.getElementById(prefix + 'Skadik').disabled = (cakupan !== 'sekolah');
  document.getElementById(prefix + 'Lemdik').disabled = (cakupan !== 'skadron');
  document.getElementById(prefix + 'Skadik').required = (cakupan === 'sekolah');
  document.getElementById(prefix + 'Lemdik').required = (cakupan === 'skadron');
}
function ucfirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

function openTambah(prefill) {
  var m = document.getElementById('modalTambah');
  document.getElementById('judulTambah').textContent = prefill ? 'Duplikat → Tambah Penandatangan' : 'Tambah Penandatangan';

  var v = prefill || {};
  var cakupan = v.cakupan || 'sekolah';
  document.getElementById('tambahCakupan').value = cakupan;
  document.getElementById('tambahJenis').value  = v.jenis || 'umum';
  document.getElementById('tambahNama').value   = v.nama || '';
  document.getElementById('tambahNrp').value    = v.nrp || '';
  document.getElementById('tambahPangkat').value = v.pangkat || '';
  document.getElementById('tambahJabatan').value = v.jabatan || '';
  document.getElementById('tambahSkadik').value = v.skadik || '';
  document.getElementById('tambahLemdik').value = v.lemdik || '';
  toggleCakupan('tambah');
  m.style.display = 'flex';
}

function openEdit(tr) {
  var d = tr.dataset;
  var cakupan = d.skadik ? 'sekolah' : (d.lemdik ? 'skadron' : 'global');

  document.getElementById('editId').value = d.id;
  document.getElementById('editNama').value = d.nama;
  document.getElementById('editNrp').value = d.nrp;
  document.getElementById('editPangkat').value = d.pangkat;
  document.getElementById('editJabatan').value = d.jabatan;
  document.getElementById('editJenis').value = d.jenis;
  document.getElementById('editSkadik').value = d.skadik || '';
  document.getElementById('editLemdik').value = d.lemdik || '';
  document.getElementById('editCakupan').value = cakupan;
  document.getElementById('editAktif').checked = d.aktif === '1';
  document.getElementById('formEdit').action = '/penandatangan/' + d.id;
  toggleCakupan('edit');
  document.getElementById('modalEdit').style.display = 'flex';
}

document.querySelectorAll('#modalTambah, #modalEdit').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});
</script>
@endpush
