@extends('layouts.app')
@section('title', 'Impor Nilai')
@section('page-title', 'Impor Nilai dari Excel')

@section('topbar-actions')
<a href="{{ route('nilai.index', ['angkatan_id'=>$angkatanId, 'periode_id'=>$periodeId]) }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="max-width:640px">

  {{-- Info alur --}}
  <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:16px 20px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:600;color:#4f46e5;margin-bottom:10px">Cara impor nilai dari Excel:</div>
    <div style="font-size:13px;color:#555;line-height:1.8">
      <strong>1.</strong> Pilih angkatan &amp; periode di bawah<br>
      <strong>2.</strong> Download template Excel (sudah berisi daftar peserta)<br>
      <strong>3.</strong> Isi kolom <span style="background:#fef3c7;padding:1px 5px;border-radius:3px;font-weight:500">Akademik, Fisik, Sikap, Kepemimpinan</span> dengan nilai 0–100<br>
      <strong>4.</strong> Upload file yang sudah diisi<br>
      <strong>5.</strong> Sistem otomatis menyimpan semua nilai
    </div>
  </div>

  <div class="card">
    <form method="GET" action="{{ route('nilai.import.form') }}" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
      <div style="flex:1">
        <label>Sekolah</label>
        <select name="skadik_id" onchange="this.form.submit()">
          @foreach($allSkadik as $sk)
            <option value="{{ $sk->id }}" {{ $sk->id == $skadikId ? 'selected' : '' }}>
              {{ $sk->nama }} ({{ $sk->lemdik->nama }})
            </option>
          @endforeach
        </select>
      </div>
      <div style="flex:1">
        <label>Angkatan</label>
        <select name="angkatan_id" onchange="this.form.submit()">
          @foreach($allAngkatan as $ang)
            <option value="{{ $ang->id }}" {{ $ang->id == $angkatanId ? 'selected' : '' }}>
              Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
            </option>
          @endforeach
        </select>
      </div>
      <div style="flex:1">
        <label>Periode</label>
        <select name="periode_id" onchange="this.form.submit()">
          @foreach($periodes as $per)
            <option value="{{ $per->id }}" {{ $per->id == $periodeId ? 'selected' : '' }}>
              {{ $per->label }}{{ $per->aktif ? ' (aktif)' : '' }}
            </option>
          @endforeach
        </select>
      </div>
    </form>

    {{-- Download template --}}
    <div style="background:#f4f5f7;border-radius:8px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:14px">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:500">Template Excel</div>
        <div style="font-size:12px;color:#888;margin-top:2px">Sudah berisi daftar peserta angkatan yang dipilih. Kolom nilai (kuning) siap diisi.</div>
      </div>
      <a href="{{ route('nilai.template', ['angkatan_id'=>$angkatanId, 'periode_id'=>$periodeId]) }}"
         class="btn btn-outline btn-sm" style="white-space:nowrap">
        ⬇ Download Template
      </a>
    </div>

    {{-- Form upload --}}
    <form method="POST" action="{{ route('nilai.import') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <input type="hidden" name="periode_nilai_id" value="{{ $periodeId }}">

      <div class="form-group">
        <label>Upload File Excel (.xlsx / .xls) <span style="color:#dc2626">*</span></label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="padding:10px;border:2px dashed #d0d0d8;border-radius:8px;cursor:pointer"
          onchange="showFilename(this)">
        <div id="filename-info" style="font-size:12px;color:#888;margin-top:6px"></div>
      </div>

      @if($periode)
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#92400e">
        Nilai akan diimpor ke periode: <strong>{{ $periode->label }}</strong>
        ({{ $periode->tanggal_mulai?->format('d M Y') }} – {{ $periode->tanggal_selesai?->format('d M Y') }})
      </div>
      @endif

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">⬆ Impor Nilai</button>
        <a href="{{ route('nilai.index', ['angkatan_id'=>$angkatanId,'periode_id'=>$periodeId]) }}" class="btn btn-outline">Batal</a>
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
          <tr><td>A</td><td><span class="badge badge-blue">nosis</span></td><td style="color:#888">Untuk identifikasi peserta</td></tr>
          <tr><td>B</td><td><span class="badge badge-blue">nrp</span></td><td style="color:#888">Untuk identifikasi peserta</td></tr>
          <tr><td>C</td><td><span class="badge badge-blue">pangkat</span></td><td style="color:#888">Tidak diubah saat impor</td></tr>
          <tr><td>D</td><td><span class="badge badge-blue">nama</span></td><td style="color:#888">Tidak diubah saat impor</td></tr>
          <tr><td>E</td><td><span class="badge badge-amber">akademik</span></td><td style="color:#059669;font-weight:500">Wajib diisi (0–100)</td></tr>
          <tr><td>F</td><td><span class="badge badge-amber">fisik</span></td><td style="color:#059669;font-weight:500">Wajib diisi (0–100)</td></tr>
          <tr><td>G</td><td><span class="badge badge-amber">sikap</span></td><td style="color:#059669;font-weight:500">Wajib diisi (0–100)</td></tr>
          <tr><td>H</td><td><span class="badge badge-amber">kepemimpinan</span></td><td style="color:#059669;font-weight:500">Wajib diisi (0–100)</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

@push('scripts')
<script>
function showFilename(input) {
  const info = document.getElementById('filename-info');
  if (input.files && input.files[0]) {
    const file = input.files[0];
    const size = (file.size / 1024).toFixed(1);
    info.textContent = `File dipilih: ${file.name} (${size} KB)`;
    info.style.color = '#059669';
  }
}
</script>
@endpush
@endsection
