@extends('layouts.app')
@section('page-title', 'Impor Nilai Akademik')
@section('content')

<div style="max-width:640px">
  <h2 style="font-size:18px;font-weight:600;margin-bottom:16px">📄 Impor Data NPA dari Excel</h2>

  <div class="card" style="margin-bottom:20px">
    <p style="font-size:13px;color:#666;margin-bottom:12px">
      Upload file Excel (.xlsx) berisi data Nilai Prestasi Akademik (NPA).
      Format file sesuai template standar: <strong>NO, NAMA, PKT, NRP, [kolom mata pelajaran], Jumlah Nilai, NPA, RANK</strong>.
    </p>
    <p style="font-size:12px;color:#888;margin-bottom:12px">
      Data dicocokkan berdasarkan <strong>NRP</strong> peserta yang sudah terdaftar di sistem.
      Baris info <strong>JP / B / HN</strong> (seperti pada file Report NPA) otomatis dilewati —
      posisi kolom terdeteksi otomatis, jadi file hasil ekspor Report NPA juga bisa langsung di-upload ulang.
    </p>
    <a href="{{ route('nilai-akademik.template', ['angkatan_id' => $allAngkatan->first()?->id]) }}"
       id="btnTemplateNpa" class="btn btn-outline btn-sm">⬇ Download Template NPA</a>
  </div>

  <form method="POST" action="{{ route('nilai-akademik.import') }}" enctype="multipart/form-data">
    @csrf
    <div class="form-group">
      <label>Pilih Angkatan</label>
      <select name="angkatan_id" id="selectAngkatanNpa" required>
        <option value="">— Pilih Angkatan —</option>
        @foreach($allAngkatan as $a)
          <option value="{{ $a->id }}" {{ old('angkatan_id') == $a->id ? 'selected' : '' }}>
            Angkatan {{ $a->nomor_angkatan }} ({{ $a->tahun_masuk }}) — {{ $a->skadik?->nama }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="form-group">
      <label>File Excel (NPA)</label>
      <input type="file" name="file" accept=".xlsx,.xls" required>
    </div>

    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-primary">📤 Upload & Impor</button>
      <a href="{{ route('nilai-akademik.index') }}" class="btn btn-outline">← Kembali</a>
    </div>
  </form>
</div>

@push('scripts')
<script>
// Link template mengikuti angkatan yang dipilih agar kolom mata pelajaran sesuai
(function () {
  var sel = document.getElementById('selectAngkatanNpa');
  var btn = document.getElementById('btnTemplateNpa');
  if (!sel || !btn) return;
  var base = btn.getAttribute('href');
  function sync() {
    if (!sel.value) return;
    var url = base.split('?')[0] + '?angkatan_id=' + encodeURIComponent(sel.value);
    btn.setAttribute('href', url);
  }
  sel.addEventListener('change', sync);
  sync();
})();
</script>
@endpush

@endsection
