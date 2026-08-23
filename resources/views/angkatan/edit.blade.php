@extends('layouts.app')
@section('title', 'Edit Angkatan')
@section('page-title', 'Edit Angkatan')
@section('topbar-actions')
<a href="{{ route('angkatan.index') }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection
@section('content')
<div style="max-width:500px">
  <div class="card">
    <form method="POST" action="{{ route('angkatan.update', $angkatan) }}">
      @csrf @method('PUT')
      <div style="background:#f4f5f7;border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12px;color:#888">
        Sekolah: <strong>{{ $angkatan->skadik->nama }}</strong> (tidak dapat diubah)
      </div>
      <div class="form-group">
        <label>Nomor Angkatan <span style="color:#dc2626">*</span></label>
        <input type="text" name="nomor_angkatan" value="{{ old('nomor_angkatan', $angkatan->nomor_angkatan) }}" required>
      </div>
      <div class="form-group">
        <label>Tahun Masuk <span style="color:#dc2626">*</span></label>
        <input type="number" name="tahun_masuk" value="{{ old('tahun_masuk', $angkatan->tahun_masuk) }}" min="2000" max="2099" required>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Perbarui</button>
        <a href="{{ route('angkatan.index') }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
