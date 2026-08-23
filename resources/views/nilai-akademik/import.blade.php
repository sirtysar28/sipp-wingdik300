@extends('layouts.app')
@section('page-title', 'Impor Nilai Akademik')
@section('content')

<div style="max-width:600px">
  <h2 style="font-size:18px;font-weight:600;margin-bottom:16px">📄 Impor Data NPA dari Excel</h2>

  <div class="card" style="margin-bottom:20px">
    <p style="font-size:13px;color:#666;margin-bottom:12px">
      Upload file Excel (.xlsx) berisi data Nilai Prestasi Akademik (NPA).
      Pastikan format file sesuai dengan template standar yang mencakup kolom: <strong>NO, NAMA, PKT, NRP, SBS..., Jumlah Nilai, NPA, RANK</strong>.
    </p>
    <p style="font-size:12px;color:#888">
      Data akan dicocokkan berdasarkan <strong>NRP</strong> peserta yang sudah terdaftar di sistem.
    </p>
  </div>

  <form method="POST" action="{{ route('nilai-akademik.import') }}" enctype="multipart/form-data">
    @csrf
    <div class="form-group">
      <label>Pilih Angkatan</label>
      <select name="angkatan_id" required>
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

@endsection
