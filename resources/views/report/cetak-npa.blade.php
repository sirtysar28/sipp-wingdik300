<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak NPA — {{ $angkatan->skadik->nama_singkat ?? '' }} Angkatan {{ $angkatan->nomor_angkatan ?? '' }}</title>
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
  th, td { border: 1px solid #000; padding: 4px 6px; text-align: center; font-size: 9pt; vertical-align: middle; }
  th { background: #e0e0e0; font-weight: bold; }
  .text-left { text-align: left; }
  .text-right { text-align: right; }
  .bold { font-weight: bold; }
  .small { font-size: 7pt; }
  .hn-col { background: #f0f0ff; }

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
  <button onclick="window.print()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#059669;color:#fff;border:none;border-radius:6px">🖨️ Cetak Halaman</button>
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
  <h3>NILAI PRESTASI AKADEMIK (NPA)</h3>
  <div style="font-size:10pt;margin-top:4px">
    Angkatan {{ $angkatan->nomor_angkatan }} — Tahun Masuk {{ $angkatan->tahun_masuk }}
    @if($angkatan->jurusan) | {{ $angkatan->jurusan }} @endif
  </div>
  <div style="font-size:8pt;color:#555;margin-top:2px">
    Rumus: NPA = Σ(Nilai MP × HN) / Σ(HN) |
    Σ JP = {{ $mataPelajaran->sum('jp') }} · Σ Bobot = {{ $mataPelajaran->sum('bobot') }} · Σ HN = {{ $totalHN }}
  </div>
</div>

<table>
  <thead>
    <tr>
      <th rowspan="2" style="width:30px">No</th>
      <th rowspan="2" style="width:30px">Rank</th>
      <th rowspan="2" class="text-left" style="width:80px">NRP</th>
      <th rowspan="2" style="width:50px">Pangkat</th>
      <th rowspan="2" class="text-left" style="width:140px">Nama</th>
      @foreach($mataPelajaran as $mp)
      <th style="width:55px">{{ $mp->nama }}<br><span class="small">JP={{ $mp->jp }}|B={{ $mp->bobot }}|HN={{ $mp->harga_nilai_calc }}</span></th>
      @endforeach
      <th style="background:#d0d0ff">Σ(MP×HN)</th>
      <th style="background:#d0ffd0;width:60px">NPA</th>
    </tr>
  </thead>
  <tbody>
    @php $nMapel = $mataPelajaran->count(); @endphp
    @foreach($data as $i => $d)
    @php
      $detailNilai = is_array($d->detail_nilai) ? $d->detail_nilai : (json_decode($d->detail_nilai, true) ?: []);
      $sumMPHN = 0;
      $rank = $i + 1;
    @endphp
    <tr>
      <td>{{ $i + 1 }}</td>
      <td class="bold">{{ $rank }}</td>
      <td>{{ $d->peserta->nrp }}</td>
      <td>{{ $d->peserta->pangkat }}</td>
      <td class="text-left">{{ $d->peserta->nama }}</td>
      @foreach($mataPelajaran as $mpIdx => $mp)
      @php
        $valMP = $detailNilai[$mpIdx] ?? 0;
        $hnMP = $mp->harga_nilai_calc;
        $sumMPHN += $valMP * $hnMP;
      @endphp
      <td>{{ $valMP ?: '-' }}</td>
      @endforeach
      <td style="background:#f0f0ff" class="bold">{{ number_format(round($sumMPHN, 2), 2, ',', '.') }}</td>
      <td class="bold" style="background:#f0fff0;font-size:10pt">{{ $d->npa }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

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
