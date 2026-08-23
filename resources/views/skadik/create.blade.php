{{-- resources/views/skadik/create.blade.php --}}
@extends('layouts.app')
@section('title', 'Tambah Sekolah')
@section('page-title', 'Tambah Sekolah Baru')
@section('topbar-actions')
<a href="{{ route('skadik.index') }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection
@section('content')
<div style="max-width:500px">
  <div class="card">
    <form method="POST" action="{{ route('skadik.store') }}">
      @csrf
      <div class="form-group">
        <label>Lembaga Pendidikan <span style="color:#dc2626">*</span></label>
        <select name="lemdik_id" required>
          <option value="">-- Pilih Lembaga Pendidikan --</option>
          @foreach($lemdik as $l)
            <option value="{{ $l->id }}" {{ old('lemdik_id') == $l->id ? 'selected' : '' }}>
              {{ $l->nama }} ({{ $l->kode }})
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label>Nama Sekolah <span style="color:#dc2626">*</span></label>
        <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Sekolah 302" required>
      </div>
      <div class="form-group">
        <label>Kode <span style="color:#dc2626">*</span></label>
        <input type="text" name="kode" value="{{ old('kode') }}" placeholder="SKADIK302" required>
        <span style="font-size:11px;color:#aaa">Kode harus unik untuk setiap Sekolah</span>
      </div>
      <div class="form-group">
        <label>Jenjang <span style="color:#dc2626">*</span></label>
        <div style="display:flex;gap:16px;margin-top:4px;flex-wrap:wrap">
          @foreach(\App\Models\Skadik::JENJANG_OPTIONS as $val => $lbl)
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:400">
            <input type="radio" name="jenjang" value="{{ $val }}" {{ old('jenjang', 'perwira') === $val ? 'checked' : '' }} required> {{ $lbl }}
          </label>
          @endforeach
        </div>
        <span style="font-size:11px;color:#aaa">Perwira: starting NPK 80 | Bintara / Tamtama / PNS: starting NPK 70</span>
      </div>
      <div class="form-group">
        <label>Jenis Pendidikan <span style="color:#dc2626">*</span></label>
        <div style="display:flex;gap:16px;margin-top:4px;flex-wrap:wrap">
          @foreach(\App\Models\Skadik::JENIS_PENDIDIKAN_OPTIONS as $val => $lbl)
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:400">
            <input type="radio" name="jenis_pendidikan" value="{{ $val }}" {{ old('jenis_pendidikan', 'dikbangspes') === $val ? 'checked' : '' }} required> {{ $lbl }}
          </label>
          @endforeach
        </div>
      </div>
      <div class="form-group">
        <label>Keterangan <span style="font-size:11px;color:#aaa">(opsional)</span></label>
        <textarea name="keterangan" rows="3" placeholder="Keterangan tambahan tentang sekolah...">{{ old('keterangan') }}</textarea>
      </div>

      <div class="card-title" style="margin-top:20px">👤 Kepala Sekolah</div>
      <div style="font-size:11px;color:#888;margin-bottom:12px">Data kepala sekolah akan tampil pada cetak laporan sebagai penanggung jawab / mengetahui.</div>
      <div class="form-group">
        <label>Nama Kepala Sekolah</label>
        <input type="text" name="kepala_sekolah" value="{{ old('kepala_sekolah') }}" placeholder="Nama lengkap">
      </div>
      <div class="form-group">
        <label>Pangkat</label>
        <input type="text" name="pangkat_kepala" value="{{ old('pangkat_kepala') }}" placeholder="Contoh: Kolonel">
      </div>
      <div class="form-group">
        <label>NRP</label>
        <input type="text" name="nrp_kepala" value="{{ old('nrp_kepala') }}" placeholder="NRP kepala sekolah">
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('skadik.index') }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
