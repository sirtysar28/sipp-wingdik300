{{-- resources/views/lemdik/create.blade.php --}}
@extends('layouts.app')
@section('title', 'Tambah Lembaga Pendidikan')
@section('page-title', 'Tambah Lembaga Pendidikan')
@section('topbar-actions')
<a href="{{ route('lemdik.index') }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection
@section('content')
<div style="max-width:500px">
  <div class="card">
    <form method="POST" action="{{ route('lemdik.store') }}">
      @csrf
      <div class="form-group">
        <label>Nama Lembaga Pendidikan <span style="color:#dc2626">*</span></label>
        <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Skadron Pendidikan 302" required>
      </div>
      <div class="form-group">
        <label>Kode <span style="color:#dc2626">*</span></label>
        <input type="text" name="kode" value="{{ old('kode') }}" placeholder="SKADIK302" required>
      </div>
      <div class="form-group">
        <label>Alamat</label>
        <textarea name="alamat" rows="2" placeholder="Alamat Lembaga Pendidikan...">{{ old('alamat') }}</textarea>
      </div>
      <div class="form-group">
        <label>Kota <span style="color:#dc2626">*</span></label>
        <input type="text" name="kota" value="{{ old('kota') }}" placeholder="Bandung" required>
        <span style="font-size:11px;color:#aaa">Kota akan tampil pada kop laporan cetak.</span>
      </div>
      <div class="form-group">
        <label>Wingdik <span style="font-weight:400;color:#888">(opsional)</span></label>
        <input type="text" name="wingdik" value="{{ old('wingdik') }}" placeholder="Wingdik 300">
        <span style="font-size:11px;color:#aaa">Satuan di atas lemdik yang memberikan Nosis kepada peserta. Sekolah dikelola di menu Lembaga Pendidikan.</span>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('lemdik.index') }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
