@extends('layouts.app')
@section('title', 'Impor Peserta')
@section('page-title', 'Impor Peserta dari Excel')

@section('topbar-actions')
<a href="{{ route('peserta.index') }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="max-width:680px">

  {{-- Info alur --}}
  <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:16px 20px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:600;color:#4f46e5;margin-bottom:10px">📝 Cara impor peserta dari Excel:</div>
    <div style="font-size:13px;color:#555;line-height:2">
      <strong>1.</strong> Pilih Sekolah &amp; Angkatan<br>
      <strong>2.</strong> Download template Excel<br>
      <strong>3.</strong> Isi kolom <span style="background:#fef3c7;padding:1px 5px;border-radius:3px;font-weight:500">Nama, Pangkat, NRP, Nosis</span><br>
      <strong>4.</strong> Upload file yang sudah diisi<br>
      <strong>5.</strong> Sistem otomatis menyimpan semua peserta
    </div>
  </div>

  <div class="card">

    {{-- Filter Sekolah & Angkatan --}}
    <form method="GET" action="{{ route('peserta.import.form') }}" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
      <div style="flex:1;min-width:180px">
        <label>Sekolah</label>
        <select name="skadik_id" onchange="skadikChanged(this.form)">
          @foreach($allSkadik as $sk)
            <option value="{{ $sk->id }}" {{ $sk->id == $skadikId ? 'selected' : '' }}>
              {{ $sk->nama }} ({{ $sk->lemdik->nama }})
            </option>
          @endforeach
        </select>
      </div>
      <div style="flex:1;min-width:180px">
        <label>Angkatan</label>
        <select name="angkatan_id" onchange="this.form.submit()">
          @foreach($allAngkatan as $ang)
            <option value="{{ $ang->id }}" {{ $ang->id == $angkatanId ? 'selected' : '' }}>
              Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
            </option>
          @endforeach
        </select>
      </div>
    </form>

    @if($angkatan)
    {{-- Info tujuan impor --}}
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:12px;color:#92400e">
      <strong>Daftar peserta akan diimpor ke:</strong><br>
      {{ $angkatan->skadik->nama }} — Angkatan {{ $angkatan->nomor_angkatan }} ({{ $angkatan->tahun_masuk }})<br>
      <span style="color:#aaa">Peserta dengan NRP yang sudah ada akan diperbarui datanya.</span>
    </div>
    @endif

    {{-- Download template --}}
    <div style="background:#f4f5f7;border-radius:8px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:14px">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:500">⬇ Template Excel Peserta</div>
        <div style="font-size:12px;color:#888;margin-top:2px">
          Template kosong. Kolom: <strong>Nama, Pangkat, NRP, Nosis</strong>.<br>
          Download template, lalu isi data peserta dari Angkatan yang baru.
        </div>
      </div>
      <a href="{{ route('peserta.template', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm" style="white-space:nowrap;flex-shrink:0">
        ⬇ Download Template
      </a>
    </div>

    {{-- Form upload --}}
    <form method="POST" action="{{ route('peserta.import') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">

      <div class="form-group">
        <label>Upload File Excel (.xlsx / .xls) <span style="color:#dc2626">*</span></label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="padding:10px;border:2px dashed #d0d0d8;border-radius:8px;cursor:pointer;width:100%"
          onchange="showFile(this)">
        <div id="file-info" style="font-size:12px;color:#888;margin-top:6px"></div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">⬆ Impor Peserta</button>
        <a href="{{ route('peserta.index', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>

  {{-- Format tabel --}}
  <div class="card" style="margin-top:16px">
    <div class="card-title">Format kolom Excel yang diharapkan</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Kolom</th>
            <th>Nama Header</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>A</td><td><span class="badge badge-amber">Nama</span></td><td style="color:#059669;font-weight:500">Wajib diisi</td></tr>
          <tr><td>B</td><td><span class="badge badge-amber">Pangkat</span></td><td style="color:#059669;font-weight:500">Wajib diisi (contoh: Serda, Kopda)</td></tr>
          <tr><td>C</td><td><span class="badge badge-amber">NRP</span></td><td style="color:#059669;font-weight:500">Wajib diisi (unik per peserta)</td></tr>
          <tr><td>D</td><td><span class="badge badge-amber">Nosis</span></td><td style="color:#059669;font-weight:500">Wajib diisi (unik per peserta)</td></tr>
        </tbody>
      </table>
    </div>
    <div style="margin-top:12px;font-size:11px;color:#aaa;border-top:1px solid #f0f0f5;padding-top:12px">
      <strong>Catatan:</strong> Baris pertama dianggap header dan akan dilewati. NRP dan Nosis harus unik — bila ada yang sama dengan peserta lain, baris tersebut akan dilewati dan muncul pesan error.
    </div>
  </div>
</div>

@push('scripts')
<script>
// Saat sekolah diganti, angkatan lama tidak relevan lagi — jangan ikut kirim
// angkatan_id basi (select di-disable supaya tidak ikut submit), biarkan server
// memilih angkatan pertama dari sekolah yang baru dipilih.
function skadikChanged(form) {
  form.querySelector('select[name=angkatan_id]').disabled = true;
  form.submit();
}

function showFile(input) {
  const info = document.getElementById('file-info');
  if (input.files && input.files[0]) {
    const f = input.files[0];
    info.textContent = `File: ${f.name} (${(f.size/1024).toFixed(1)} KB)`;
    info.style.color = '#059669';
  }
}
</script>
@endpush
@endsection
