@extends('layouts.app')
@section('title', 'Tambah Peserta')
@section('page-title', 'Tambah Peserta Baru')

@section('topbar-actions')
<a href="{{ route('peserta.index') }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="max-width:600px">
  <div class="card">
    <form method="POST" action="{{ route('peserta.store') }}">
      @csrf

      <div class="form-group">
        <label>Sekolah <span style="color:#dc2626">*</span></label>
        <select name="skadik_id" id="select-skadik" required onchange="populateAngkatan()">
          <option value="">-- Pilih Sekolah --</option>
          @foreach($allSkadik as $sk)
            <option value="{{ $sk->id }}" {{ old('skadik_id') == $sk->id ? 'selected' : '' }}>
              {{ $sk->nama }} ({{ $sk->lemdik->nama }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label>Angkatan <span style="color:#dc2626">*</span></label>
        <select name="angkatan_id" id="select-angkatan" required>
          <option value="">-- Pilih Sekolah dulu --</option>
        </select>
        <span id="angkatan-empty" style="display:none;font-size:11px;color:#dc2626">Belum ada angkatan aktif untuk sekolah ini. Tambahkan angkatan di menu Angkatan terlebih dahulu.</span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Nama Lengkap <span style="color:#dc2626">*</span></label>
          <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Agil Maulana Wardhana" required>
        </div>
        <div class="form-group">
          <label>Pangkat <span style="color:#dc2626">*</span></label>
          <input type="text" name="pangkat" value="{{ old('pangkat') }}" placeholder="Serda" required>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>NRP <span style="color:#dc2626">*</span></label>
          <input type="text" name="nrp" value="{{ old('nrp') }}" placeholder="3525101070562147" required>
        </div>
        <div class="form-group">
          <label>Nosis <span style="color:#dc2626">*</span></label>
          <input type="text" name="nosis" value="{{ old('nosis') }}" placeholder="diisi WingDik" required>
          <span style="font-size:11px;color:#aaa">Wajib diisi. Nomor Induk Siswa dari WingDik.</span>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Simpan Peserta</button>
        <a href="{{ route('peserta.index') }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
// ── Peta angkatan aktif per sekolah (dari server) ──
// Di-render sebagai JSON agar dropdown Angkatan bisa di-populate ulang
// tanpa AJAX & bekerja di SEMUA browser (lebih andal daripada display:none).
const ANGKATAN_BY_SKADIK = @json($allAngkatan->groupBy('skadik_id')->map(fn($c) => $c->map(fn($a) => [
  'id' => $a->id,
  'text' => 'Angkatan ' . $a->nomor_angkatan . ' (' . $a->tahun_masuk . ')',
]))->toArray());

const OLD_ANGKATAN = {{ old('angkatan_id') ? (int) old('angkatan_id') : 'null' }};

function populateAngkatan() {
  const skadikId = document.getElementById('select-skadik').value;
  const select   = document.getElementById('select-angkatan');
  const emptyMsg = document.getElementById('angkatan-empty');

  // Reset dropdown
  select.innerHTML = '';
  const list = (skadikId && ANGKATAN_BY_SKADIK[skadikId]) ? ANGKATAN_BY_SKADIK[skadikId] : [];

  if (list.length === 0) {
    select.innerHTML = '<option value="">-- Tidak ada angkatan --</option>';
    emptyMsg.style.display = skadikId ? '' : 'none';
    select.disabled = false;
    return;
  }
  emptyMsg.style.display = 'none';

  let opts = '<option value="">-- Pilih Angkatan --</option>';
  list.forEach(a => {
    const sel = (OLD_ANGKATAN === a.id) ? ' selected' : '';
    opts += `<option value="${a.id}"${sel}>${a.text}</option>`;
  });
  select.innerHTML = opts;

  // Auto-pilih bila hanya 1 angkatan (UX)
  if (list.length === 1) select.value = list[0].id;
  else if (OLD_ANGKATAN) select.value = OLD_ANGKATAN;
}

// Auto-pilih sekolah bila hanya 1 (UX), lalu populate angkatan
(function init() {
  const skadikSelect = document.getElementById('select-skadik');
  const realOpts = Array.from(skadikSelect.options).filter(o => o.value !== '');
  if (!skadikSelect.value && realOpts.length === 1) {
    skadikSelect.value = realOpts[0].value;
  }
  populateAngkatan();
})();
</script>
@endpush
@endsection
