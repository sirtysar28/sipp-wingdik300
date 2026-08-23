@extends('layouts.app')
@section('title', 'Edit Peserta')
@section('page-title', 'Edit Peserta')

@section('topbar-actions')
<a href="{{ route('peserta.show', $peserta) }}" class="btn btn-outline btn-sm">← Profil</a>
@endsection

@section('content')
<div style="max-width:600px">
  <div class="card">
    <form method="POST" action="{{ route('peserta.update', $peserta) }}">
      @csrf @method('PUT')

      <div style="background:#f4f5f7;border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:#888">
        NRP: <strong style="color:#444">{{ $peserta->nrp }}</strong> —
        Angkatan: <strong style="color:#444">{{ $peserta->angkatan->nomor_angkatan }}</strong>
        (tidak dapat diubah)
      </div>

      <div class="form-group">
        <label>Nama Lengkap <span style="color:#dc2626">*</span></label>
        <input type="text" name="nama" value="{{ old('nama', $peserta->nama) }}" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Pangkat <span style="color:#dc2626">*</span></label>
          <input type="text" name="pangkat" value="{{ old('pangkat', $peserta->pangkat) }}" required>
        </div>
        <div class="form-group">
          <label>NRP <span style="color:#dc2626">*</span></label>
          <input type="text" name="nrp" value="{{ old('nrp', $peserta->nrp) }}" required>
        </div>
        <div class="form-group">
          <label>Nosis <span style="color:#dc2626">*</span></label>
          <input type="text" name="nosis" value="{{ old('nosis', $peserta->nosis) }}" placeholder="diisi WingDik" required>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Perbarui</button>
        <a href="{{ route('peserta.show', $peserta) }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
