@extends('layouts.app')
@section('title', 'Tambah Putaran NPS')
@section('page-title', 'Tambah Putaran NPS')

@section('topbar-actions')
<a href="{{ route('report.nps') }}" class="btn btn-outline btn-sm">← Kembali ke Report NPS</a>
@endsection

@section('content')
<div style="max-width:520px">
  <div class="card">
    <div style="font-size:13px;color:#888;margin-bottom:18px;line-height:1.6">
      Buat putaran baru untuk NPS (Nilai Prestasi Samapta). Setiap putaran memiliki label dan periode tanggal. Membuat putaran baru akan menonaktifkan putaran aktif sebelumnya pada angkatan yang sama.
    </div>

    @if(session('error'))
      <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px">
        {{ session('error') }}
      </div>
    @endif

    <form method="POST" action="{{ route('nilai-samapta.putaran.store') }}">
      @csrf

      {{-- List Putaran Yang Sudah Ada (info saja) --}}
      <div id="existing-putaran" style="margin-bottom:18px"></div>

      <div class="form-group">
        <label>Sekolah <span style="color:#dc2626">*</span></label>
        <select name="skadik_id" id="select-skadik" required onchange="populateAngkatan()">
          <option value="">-- Pilih Sekolah --</option>
          @foreach($allSkadik as $sk)
            <option value="{{ $sk->id }}" {{ ($skadikId ?? old('skadik_id')) == $sk->id ? 'selected' : '' }}>
              {{ $sk->nama }} ({{ $sk->lemdik?->nama ?? '-' }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label>Angkatan <span style="color:#dc2626">*</span></label>
        <select name="angkatan_id" id="select-angkatan" required>
          <option value="">-- Pilih Sekolah dulu --</option>
        </select>
        <span id="angkatan-empty" style="display:none;font-size:11px;color:#dc2626">Belum ada angkatan aktif untuk sekolah ini.</span>
      </div>

      <div class="form-group">
        <label>Label Putaran <span style="color:#dc2626">*</span></label>
        <input type="text" name="label" value="{{ old('label') }}" placeholder="Contoh: Putaran 1, Putaran 2, Mid-Term, Final" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Tanggal Mulai <span style="color:#dc2626">*</span></label>
          <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" required id="tgl-mulai" oninput="autoIsi()">
        </div>
        <div class="form-group">
          <label>Tanggal Selesai <span style="color:#dc2626">*</span></label>
          <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" required id="tgl-selesai">
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">📅 Buat Putaran</button>
        <a href="{{ route('report.nps') }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function autoIsi() {
  const mulai = document.getElementById('tgl-mulai').value;
  if (!mulai) return;
  const d = new Date(mulai);
  d.setDate(d.getDate() + 13); // +13 hari = 2 minggu
  document.getElementById('tgl-selesai').value = d.toISOString().split('T')[0];
}

// ── Peta angkatan aktif per sekolah (dari server) ──
const ANGKATAN_BY_SKADIK = @json($allAngkatan->groupBy('skadik_id')->map(fn($c) => $c->map(fn($a) => [
  'id' => $a->id,
  'text' => 'Angkatan ' . $a->nomor_angkatan . ' (' . $a->tahun_masuk . ')',
]))->toArray());
const OLD_ANGKATAN = {{ old('angkatan_id') ? (int) old('angkatan_id') : 'null' }};

function populateAngkatan() {
  const skadikId = document.getElementById('select-skadik').value;
  const select   = document.getElementById('select-angkatan');
  const emptyMsg = document.getElementById('angkatan-empty');

  select.innerHTML = '';
  const list = (skadikId && ANGKATAN_BY_SKADIK[skadikId]) ? ANGKATAN_BY_SKADIK[skadikId] : [];

  if (list.length === 0) {
    select.innerHTML = '<option value="">-- Tidak ada angkatan --</option>';
    emptyMsg.style.display = skadikId ? '' : 'none';
    return;
  }
  emptyMsg.style.display = 'none';

  let opts = '<option value="">-- Pilih Angkatan --</option>';
  list.forEach(a => {
    const sel = (OLD_ANGKATAN === a.id) ? ' selected' : '';
    opts += `<option value="${a.id}"${sel}>${a.text}</option>`;
  });
  select.innerHTML = opts;

  if (list.length === 1) select.value = list[0].id;
  else if (OLD_ANGKATAN) select.value = OLD_ANGKATAN;
}

// Auto-pilih sekolah bila hanya 1, lalu populate angkatan
(function init() {
  const skadikSelect = document.getElementById('select-skadik');
  const realOpts = Array.from(skadikSelect.options).filter(o => o.value !== '');
  if (!skadikSelect.value && realOpts.length === 1) skadikSelect.value = realOpts[0].value;
  populateAngkatan();
})();
</script>
@endpush
@endsection
