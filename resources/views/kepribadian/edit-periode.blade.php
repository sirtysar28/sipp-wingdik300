@extends('layouts.app')
@section('title', 'Edit Periode NPK')
@section('page-title', 'Edit Periode NPK — ' . ($periode->label ?? ''))

{{--
  Revisi 2 Oktober 2026 — form EDIT PERIODE NPK (khusus Superadmin).
  Hanya label & rentang tanggal yang diubah; seluruh data nilai kepribadian
  pada periode ini tetap utuh (beda dengan Hard Delete di Report NPK).
--}}

@section('topbar-actions')
<a href="{{ $backRoute }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="max-width:500px">
  <div class="card">
    <div style="font-size:13px;color:#888;margin-bottom:18px;line-height:1.6">
      Mengedit periode <strong>{{ $periode->label }}</strong>
      @if($periode->angkatan)
        — {{ $periode->angkatan->skadik?->nama ?? '' }}, Angkatan {{ $periode->angkatan->nomor_angkatan }} ({{ $periode->angkatan->tahun_masuk }})
      @endif.
      <br>Perubahan <strong>tidak menyentuh data nilai kepribadian</strong> yang sudah tersimpan di periode ini.
    </div>

    <form method="POST" action="{{ route('kepribadian.periode.update', $periode) }}">
      @csrf
      @method('PUT')
      <input type="hidden" name="redirect" value="{{ request()->get('back') ?? url()->previous() }}">

      <div class="form-group">
        <label>Label Periode <span style="color:#dc2626">*</span></label>
        <input type="text" name="label" value="{{ old('label', $periode->label) }}" placeholder="Contoh: Mei I 2026 / Periode 7" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Tanggal Mulai <span style="color:#dc2626">*</span></label>
          <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $periode->tanggal_mulai?->format('Y-m-d')) }}" required id="tgl-mulai" oninput="autoIsi()">
        </div>
        <div class="form-group">
          <label>Tanggal Selesai <span style="color:#dc2626">*</span></label>
          <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $periode->tanggal_selesai?->format('Y-m-d')) }}" required id="tgl-selesai">
        </div>
      </div>

      <div style="font-size:11px;color:#aaa;margin-bottom:14px">
        Ubah tanggal mulai akan mengisi otomatis tanggal selesai (+13 hari / 2 minggu) — boleh disesuaikan manual.
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
        <a href="{{ $backRoute }}" class="btn btn-outline">Batal</a>
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
</script>
@endpush
@endsection
