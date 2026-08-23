<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak NPK — {{ $angkatan->skadik->nama_singkat ?? '' }} Angkatan {{ $angkatan->nomor_angkatan ?? '' }}</title>
<style>
  @page { size: landscape; margin: 15mm; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Times New Roman', serif; font-size: 11pt; color: #000; }
  .header { text-align: center; margin-bottom: 16px; border-bottom: 3px double #000; padding-bottom: 10px; }
  .header h1 { font-size: 14pt; font-weight: bold; letter-spacing: 2px; }
  .header h2 { font-size: 12pt; font-weight: bold; margin-top: 2px; }
  .header h3 { font-size: 11pt; font-weight: normal; margin-top: 4px; }
  .sub-header { text-align: center; margin-bottom: 12px; }
  .sub-header h3 { font-size: 12pt; font-weight: bold; text-decoration: underline; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
  th, td { border: 1px solid #000; padding: 5px 8px; text-align: center; font-size: 9pt; vertical-align: middle; }
  th { background: #e0e0e0; font-weight: bold; }
  .text-left { text-align: left; }
  .bold { font-weight: bold; }

  .ttd-area { margin-top: 40px; display: flex; justify-content: space-between; }
  .ttd-box { text-align: center; width: 280px; }
  .ttd-box .tempat { font-size: 11pt; margin-bottom: 4px; }
  .ttd-box .jabatan { font-size: 11pt; margin-bottom: 60px; }
  .ttd-box .nama { font-size: 11pt; font-weight: bold; text-decoration: underline; margin-bottom: 2px; }
  .ttd-box .pangkat { font-size: 10pt; }
  .ttd-box .nrp { font-size: 10pt; }

  .footer-info { margin-top: 12px; font-size: 9pt; color: #555; display: flex; justify-content: space-between; }

  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:10px;margin-bottom:10px;background:#f0f0f0">
  <button onclick="window.print()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#2563eb;color:#fff;border:none;border-radius:6px">🖨️ Cetak Halaman</button>
  <button onclick="window.close()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#888;color:#fff;border:none;border-radius:6px;margin-left:8px">Tutup</button>
</div>

{{-- Kop Surat --}}
<div class="header">
  @php
    $wingdik = strtoupper($angkatan->skadik->lemdik->wingdik ?? 'WINGDIK');
    $skadikNama = strtoupper($angkatan->skadik->nama_singkat ?? ($angkatan->skadik->nama ?? 'SEKOLAH'));
    $lemdikNama = strtoupper($angkatan->skadik->lemdik->nama ?? '');
  @endphp
  <h1>{{ $wingdik }}</h1>
  <h2>{{ $skadikNama }}</h2>
  @if($lemdikNama && $lemdikNama !== $skadikNama)
  <h3>{{ $lemdikNama }}</h3>
  @endif
</div>

<div class="sub-header">
  <h3>NILAI PRESTASI KEPRIBADIAN (NPK)</h3>
  <div style="font-size:10pt;margin-top:4px">
    Angkatan {{ $angkatan->nomor_angkatan }} — Tahun Masuk {{ $angkatan->tahun_masuk }}
    @if($angkatan->jurusan) | {{ $angkatan->jurusan }} @endif
  </div>
</div>

@php
  // Hitung ringkasan nilai akhir per peserta untuk rekap utama
  $nPer = $periodes->count();
  $sorted = [];
  foreach ($pesertaList as $p) {
    $vals = []; $total = 0; $cnt = 0;
    foreach ($periodes as $per) {
      $nk = \App\Models\NilaiKepribadian::where('peserta_didik_id', $p->id)->where('periode_nilai_id', $per->id)->first();
      $v = $nk ? $nk->nilai_akhir : null;
      $vals[] = $v;
      if ($v !== null) { $total += $v; $cnt++; }
    }
    $sorted[] = ['peserta' => $p, 'vals' => $vals, 'rata' => $cnt > 0 ? round($total/$cnt, 2) : null];
  }
  usort($sorted, fn($a, $b) => ($b['rata'] ?? -999) <=> ($a['rata'] ?? -999));
@endphp

{{-- TABEL 1: REKAP NILAI AKHIR PER PERIODE --}}
<table>
  <thead>
    <tr>
      <th style="width:30px">No</th>
      <th class="text-left" style="width:140px">Nama</th>
      <th style="width:80px">NRP</th>
      @foreach($periodes as $per)
      <th>{{ strtoupper($per->label) }}</th>
      @endforeach
      <th style="background:#d0d0ff">Rata-rata</th>
      <th style="width:30px">Rank</th>
    </tr>
  </thead>
  <tbody>
    @foreach($sorted as $i => $s)
    <tr>
      <td>{{ $i + 1 }}</td>
      <td class="text-left">{{ $s['peserta']->nama }}</td>
      <td>{{ $s['peserta']->nrp }}</td>
      @foreach($s['vals'] as $v)
      <td>{{ $v ?? '-' }}</td>
      @endforeach
      <td style="background:#f0f0ff" class="bold">{{ $s['rata'] ?? '-' }}</td>
      <td class="bold">{{ $i + 1 }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

{{-- TABEL 2: DETAIL PARAMETER / ASPEK KEPRIBADIAN per peserta per periode --}}
@if($aspekList->isNotEmpty() && $periodes->isNotEmpty())
<div style="margin-top:18px"><strong>TABEL DETAIL PARAMETER NILAI KEPRIBADIAN</strong></div>
<div style="font-size:8pt;color:#555;margin-bottom:4px">
  Keterangan: BS = Baik Sekali (+0,5) · B = Baik (+0,25) · C = Cukup (0) · K = Kurang (-0,25) · KS = Kurang Sekali (-0,5) | Nilai Akhir = 75 + Σ poin
</div>
<table style="margin-top:6px">
  <thead>
    <tr>
      <th style="width:24px">No</th>
      <th class="text-left" style="width:130px">Nama</th>
      @foreach($periodes as $per)
        @foreach($aspekList as $asp)
        <th style="font-size:7pt;writing-mode:vertical-rl;rotate:180deg;height:90px">{{ strtoupper($asp->nama) }}<br><span style="font-weight:normal;font-size:6pt">({{ $per->label }})</span></th>
        @endforeach
        <th style="background:#e8e8f8;font-size:7pt">{{ strtoupper($per->label) }}<br>AKHIR</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($pesertaList->sortBy('nama') as $pi => $p)
    <tr>
      <td>{{ $pi + 1 }}</td>
      <td class="text-left">{{ $p->nama }}</td>
      @foreach($periodes as $per)
        @php
          $nk = \App\Models\NilaiKepribadian::where('peserta_didik_id', $p->id)->where('periode_nilai_id', $per->id)->first();
          $det = $nk ? $nk->detail->keyBy('aspek_kepribadian_id') : collect();
        @endphp
        @foreach($aspekList as $asp)
          @php $kriteria = $det->get($asp->id)?->kriteria ?? ''; @endphp
          <td style="background:{{ match($kriteria){'BS'=>'#d1fae5','B'=>'#dbeafe','C'=>'#f9fafb','K'=>'#fef3c7','KS'=>'#fee2e2',default=>'#fff'} }};font-size:8pt">{{ $kriteria ?: '-' }}</td>
        @endforeach
        <td style="background:#f0f0ff;font-weight:bold">{{ $nk ? round($nk->nilai_akhir,2) : '-' }}</td>
      @endforeach
    </tr>
    @endforeach
  </tbody>
</table>
@endif

{{-- Tanda Tangan --}}
<div class="ttd-area">
  @if($ttdKiri)
  <div class="ttd-box">
    <div class="jabatan">{{ $ttdKiri->jabatan }}</div>
    <div class="nama">{{ $ttdKiri->nama }}</div>
    @if($ttdKiri->pangkat)<div class="pangkat">{{ $ttdKiri->pangkat }}</div>@endif
    <div class="nrp">NRP. {{ $ttdKiri->nrp }}</div>
  </div>
  @else
  <div class="ttd-box"></div>
  @endif

  @if($ttdKanan)
  <div class="ttd-box">
    <div class="tempat">{{ $angkatan->skadik->lemdik->kota ?? '' }}, {{ date('d F Y') }}</div>
    <div class="jabatan">{{ $ttdKanan->jabatan }}</div>
    <div class="nama">{{ $ttdKanan->nama }}</div>
    @if($ttdKanan->pangkat)<div class="pangkat">{{ $ttdKanan->pangkat }}</div>@endif
    <div class="nrp">NRP. {{ $ttdKanan->nrp }}</div>
  </div>
  @else
  <div class="ttd-box"></div>
  @endif
</div>

<div class="footer-info">
  <span>Dicetak: {{ date('d/m/Y H:i') }}</span>
  <span>SIPP — Sistem Informasi Penilaian Prestasi</span>
</div>

</body>
</html>
