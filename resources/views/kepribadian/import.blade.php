@extends('layouts.app')
@section('title','Impor Nilai Kepribadian')
@section('page-title','Impor Nilai Kepribadian')

@section('topbar-actions')
<a href="{{ route('kepribadian.index',['angkatan_id'=>$angkatanId,'periode_id'=>$periodeId]) }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="max-width:680px">

  {{-- Alur --}}
  <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:16px 20px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:600;color:#4f46e5;margin-bottom:10px">Cara impor nilai kepribadian dari Excel:</div>
    <div style="font-size:13px;color:#555;line-height:2">
      <strong>1.</strong> Pilih angkatan &amp; periode<br>
      <strong>2.</strong> Download template — sudah berisi daftar peserta &amp; data yang ada<br>
      <strong>3.</strong> Isi kolom aspek (G–P) dengan <code style="background:#e0e7ff;padding:1px 5px;border-radius:3px">BS / B / C / K / KS</code><br>
      <strong>4.</strong> Kolom JML &amp; RNKG <strong>tidak perlu diisi</strong> — otomatis dihitung sistem<br>
      <strong>5.</strong> Upload file yang sudah diisi
    </div>
  </div>

  <div class="card">

    {{-- Filter sekolah, angkatan & periode --}}
    <form method="GET" action="{{ route('kepribadian.import.form') }}" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
      <div style="flex:1;min-width:150px">
        <label>Sekolah</label>
        <select name="skadik_id" onchange="this.form.submit()">
          @foreach($allSkadik as $sk)
            <option value="{{ $sk->id }}" {{ $sk->id==$skadikId?'selected':'' }}>
              {{ $sk->nama }} ({{ $sk->lemdik->nama }})
            </option>
          @endforeach
        </select>
      </div>
      <div style="flex:1;min-width:140px">
        <label>Angkatan</label>
        <select name="angkatan_id" onchange="this.form.submit()">
          @foreach($allAngkatan as $ang)
            <option value="{{ $ang->id }}" {{ $ang->id==$angkatanId?'selected':'' }}>
              Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
            </option>
          @endforeach
        </select>
      </div>
      <div style="flex:1;min-width:140px">
        <label>Periode</label>
        <select name="periode_id" onchange="this.form.submit()">
          @foreach($periodes as $per)
            <option value="{{ $per->id }}" {{ $per->id==$periodeId?'selected':'' }}>
              {{ $per->label }}{{ $per->aktif?' (aktif)':'' }}
            </option>
          @endforeach
        </select>
      </div>
    </form>

    {{-- Download template --}}
    <div style="background:#f4f5f7;border-radius:8px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:14px">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:500">Template Excel</div>
        <div style="font-size:12px;color:#888;margin-top:2px">
          Berisi daftar peserta + data nilai yang sudah ada (jika ada).
          Kolom kuning = belum diisi.
        </div>
      </div>
      <a href="{{ route('kepribadian.template',['angkatan_id'=>$angkatanId,'periode_id'=>$periodeId]) }}"
         class="btn btn-outline btn-sm" style="white-space:nowrap;flex-shrink:0">
        ⬇ Download Template
      </a>
    </div>

    {{-- Form upload --}}
    <form method="POST" action="{{ route('kepribadian.import') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <input type="hidden" name="periode_nilai_id" value="{{ $periodeId }}">

      <div class="form-group">
        <label>Upload File Excel (.xlsx / .xls) <span style="color:#dc2626">*</span></label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="padding:10px;border:2px dashed #d0d0d8;border-radius:8px;cursor:pointer;width:100%"
          onchange="showFile(this)">
        <div id="file-info" style="font-size:12px;color:#888;margin-top:6px"></div>
      </div>

      @if($periode)
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#92400e">
        Nilai akan diimpor ke: <strong>{{ $periode->label }}</strong>
        ({{ $periode->tanggal_mulai?->format('d M Y') }} – {{ $periode->tanggal_selesai?->format('d M Y') }}).<br>
        Jika peserta sudah punya nilai di periode ini, nilainya akan <strong>diperbarui</strong>.
      </div>
      @endif

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">⬆ Impor Nilai</button>
        <a href="{{ route('kepribadian.index',['angkatan_id'=>$angkatanId,'periode_id'=>$periodeId]) }}"
           class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>

  {{-- Format tabel --}}
  <div class="card" style="margin-top:16px">
    <div class="card-title">Format kolom Excel</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Kolom</th><th>Isi</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
          <tr><td>A</td><td>No</td><td style="color:#aaa">Nomor urut (opsional)</td></tr>
          <tr><td>B</td><td>Nama</td><td style="color:#aaa">Tidak digunakan untuk identifikasi</td></tr>
          <tr><td>C</td><td>Pangkat</td><td style="color:#aaa">Tidak digunakan untuk identifikasi</td></tr>
          <tr><td>D</td><td><span class="badge badge-blue">NRP</span></td><td style="color:#059669;font-weight:500">Kunci identifikasi peserta</td></tr>
          <tr><td>E</td><td><span class="badge badge-blue">NOSIS</span></td><td style="color:#059669;font-weight:500">Kunci identifikasi (jika NRP kosong)</td></tr>
          <tr><td>F</td><td>Nilai Awal</td><td style="color:#aaa">Tidak perlu diubah (selalu 75)</td></tr>
          <tr><td>G–P</td><td><span class="badge badge-amber">10 Aspek</span></td><td style="color:#059669;font-weight:500">Isi dengan BS / B / C / K / KS</td></tr>
          <tr><td>Q</td><td>JML</td><td style="color:#aaa">Tidak perlu diisi — dihitung otomatis</td></tr>
          <tr><td>R</td><td>RNKG</td><td style="color:#aaa">Tidak perlu diisi — dihitung otomatis</td></tr>
        </tbody>
      </table>
    </div>

    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f0f0f5">
      <div style="font-size:11px;font-weight:600;color:#888;margin-bottom:8px;text-transform:uppercase">Nilai kriteria:</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach(['BS'=>['#ecfdf5','#059669','Baik Sekali','+0.5'],'B'=>['#eff6ff','#4f46e5','Baik','+0.25'],'C'=>['#f4f5f7','#888','Cukup','0'],'K'=>['#fffbeb','#b45309','Kurang','-0.25'],'KS'=>['#fef2f2','#b91c1c','Kurang Sekali','-0.5']] as $k=>[$bg,$cl,$label,$poin])
        <div style="padding:6px 12px;border-radius:8px;background:{{ $bg }};text-align:center">
          <div style="font-size:14px;font-weight:700;color:{{ $cl }}">{{ $k }}</div>
          <div style="font-size:10px;color:{{ $cl }}">{{ $label }}</div>
          <div style="font-size:11px;font-weight:600;color:{{ $cl }}">{{ $poin }}</div>
        </div>
        @endforeach
      </div>
      <div style="font-size:11px;color:#aaa;margin-top:10px">
        Nilai akhir = 75 + jumlah poin semua aspek &nbsp;|&nbsp;
        Maks: 80 (semua BS) &nbsp;|&nbsp; Min: 70 (semua KS)
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
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