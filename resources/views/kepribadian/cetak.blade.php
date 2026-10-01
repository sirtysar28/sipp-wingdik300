<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak NPK Individu — {{ $peserta->nama }}</title>
<style>
  @page { size: A4 portrait; margin: 15mm; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; }

  .header { text-align: center; margin-bottom: 14px; border-bottom: 3px double #000; padding-bottom: 10px; }
  .header h1 { font-size: 14pt; font-weight: bold; letter-spacing: 2px; }
  .header h2 { font-size: 12pt; font-weight: bold; margin-top: 2px; }
  .header h3 { font-size: 11pt; font-weight: normal; margin-top: 4px; }
  .sub-header { text-align: center; margin-bottom: 12px; }
  .sub-header h3 { font-size: 12pt; font-weight: bold; text-decoration: underline; }
  .sub-header .sub { font-size: 10pt; margin-top: 4px; }

  h4.seksi { font-size: 11pt; font-weight: bold; margin: 14px 0 6px; }

  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #000; padding: 5px 8px; font-size: 10pt; vertical-align: middle; }
  th { background: #fff; font-weight: bold; text-align: center; }
  td.c { text-align: center; }
  td.r { text-align: right; }
  .bold { font-weight: bold; }

  table.identitas { margin-bottom: 4px; }
  table.identitas th, table.identitas td { border: none; padding: 2px 4px; font-size: 10.5pt; }
  table.identitas th { text-align: left; width: 130px; }

  .nilai-akhir-box { margin: 10px 0 4px; text-align: center; }
  .nilai-akhir-box .label { font-size: 10pt; }
  .nilai-akhir-box .nilai { font-size: 20pt; font-weight: bold; }

  .narasi { font-size: 10.5pt; line-height: 1.6; margin: 4px 0 8px; }
  .narasi .judul { font-weight: bold; text-decoration: underline; margin-bottom: 2px; }

  /* Blok tanda tangan — pola sama dengan report.cetak-npk */
  table.ttd-table { width: 100%; border-collapse: collapse; margin-top: 36px; page-break-inside: avoid; break-inside: avoid; }
  table.ttd-table tr { page-break-inside: avoid; break-inside: avoid; }
  table.ttd-table td { border: none; padding: 1px 6px; font-size: 11pt; line-height: 1.4; text-align: center; vertical-align: top; }
  table.ttd-table td.tepi { width: 38%; }
  table.ttd-table td.spasi { width: 24%; }
  table.ttd-table tr.ruang td { height: 65px; }
  table.ttd-table td.nama { font-weight: bold; text-decoration: underline; }
  table.ttd-table td.pangkat-nrp { font-size: 10pt; }

  .footer-info { margin-top: 12px; font-size: 9pt; color: #555; display: flex; justify-content: space-between; }

  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:10px;margin-bottom:10px;background:#f0f0f0">
  <button onclick="window.print()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#555;color:#fff;border:none;border-radius:6px">🖨️ Cetak / Simpan PDF</button>
  <button onclick="window.close()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#888;color:#fff;border:none;border-radius:6px;margin-left:8px">Tutup</button>
</div>

{{-- Kop Surat --}}
@php
  $sk        = $angkatan->skadik;
  $wingdik   = strtoupper($sk->lemdik->wingdik ?? 'WINGDIK');
  $skadikNama = strtoupper($sk->nama_singkat ?? ($sk->nama ?? 'SEKOLAH'));
  $lemdikNama = strtoupper($sk->lemdik->nama ?? '');
@endphp
<div class="header">
  <h1>{{ $wingdik }}</h1>
  <h2>{{ $skadikNama }}</h2>
  @if($lemdikNama && $lemdikNama !== $skadikNama)
  <h3>{{ $lemdikNama }}</h3>
  @endif
</div>

<div class="sub-header">
  <h3>NILAI PRESTASI KEPRIBADIAN (NPK) — INDIVIDU</h3>
  <div class="sub">
    {{ $current?->periode?->label ?? 'Periode Terakhir' }}
    @if($angkatan->jurusan) | {{ $angkatan->jurusan }} @endif
    | Angkatan {{ $angkatan->nomor_angkatan }} Tahun {{ $angkatan->tahun_masuk }}
  </div>
</div>

{{-- Identitas Peserta --}}
<table class="identitas">
  <tr><th>Nama</th><td>: <strong>{{ $peserta->nama }}</strong></td><th style="width:110px">Pangkat</th><td>: {{ $peserta->pangkat }}</td></tr>
  <tr><th>NRP</th><td>: {{ $peserta->nrp }}</td><th>Nosis</th><td>: {{ $peserta->nosis }}</td></tr>
  <tr><th>Angkatan</th><td>: {{ $angkatan->nomor_angkatan }}</td><th>Sekolah</th><td>: {{ $sk->nama }}</td></tr>
</table>

{{-- Rekap nilai seluruh periode --}}
<h4 class="seksi">A. REKAP NILAI PER PERIODE</h4>
<table>
  <thead>
    <tr>
      <th style="width:36px">No</th>
      <th>Periode</th>
      <th style="width:170px">Pelaksanaan</th>
      <th style="width:110px">Nilai Akhir</th>
    </tr>
  </thead>
  <tbody>
    @forelse($riwayat as $i => $nk)
    <tr>
      <td class="c">{{ $i + 1 }}</td>
      <td>{{ $nk->periode?->label ?? '-' }} {{ $nk->periode_nilai_id == $periodeId ? '◄' : '' }}</td>
      <td class="c">
        @if($nk->periode?->tanggal_mulai)
          {{ $nk->periode->tanggal_mulai->format('d M Y') }}
          @if($nk->periode->tanggal_selesai) s.d. {{ $nk->periode->tanggal_selesai->format('d M Y') }} @endif
        @else - @endif
      </td>
      <td class="c bold">{{ round($nk->nilai_akhir, 2) }}</td>
    </tr>
    @empty
    <tr><td colspan="4" class="c">Belum ada nilai kepribadian.</td></tr>
    @endforelse
    @if($riwayat->isNotEmpty())
    <tr>
      <td colspan="3" style="text-align:right;font-weight:bold">Rata-rata</td>
      <td class="c bold">{{ round($riwayat->avg('nilai_akhir'), 2) }}</td>
    </tr>
    @endif
  </tbody>
</table>

{{-- Detail aspek periode terpilih --}}
<h4 class="seksi">B. DETAIL PENILAIAN ASPEK — {{ $current?->periode?->label ?? '-' }}</h4>
@if($current)
@php $detailMap = $current->detail->keyBy('aspek_kepribadian_id'); @endphp
<table>
  <thead>
    <tr>
      <th style="width:36px">No</th>
      <th style="text-align:left">Aspek Kepribadian</th>
      <th style="width:90px">Kriteria</th>
      <th style="width:90px">Poin</th>
    </tr>
  </thead>
  <tbody>
    @foreach($aspekList as $aspek)
    @php
      $d = $detailMap->get($aspek->id);
      $kriteria = $d?->kriteria ?? '-';
      $poin = $d ? ($d->poin > 0 ? '+' . rtrim(rtrim(number_format($d->poin, 2, '.', ''), '0'), '.') : rtrim(rtrim(number_format($d->poin, 2, '.', ''), '0'), '.')) : '-';
    @endphp
    <tr>
      <td class="c">{{ $aspek->nomor }}</td>
      <td>{{ $aspek->nama }}</td>
      <td class="c bold">{{ $kriteria }}</td>
      <td class="c">{{ $poin }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="nilai-akhir-box">
  <div class="label">NILAI AKHIR KEPRIBADIAN ({{ $current->periode?->label ?? '' }})</div>
  <div class="nilai">{{ round($current->nilai_akhir, 2) }}</div>
</div>

@if($current->penjelasan || $current->rekomendasi)
<div style="margin-top:8px">
  @if($current->penjelasan)
  <div class="narasi">
    <div class="judul">PENJELASAN</div>
    {{ $current->penjelasan }}
  </div>
  @endif
  @if($current->rekomendasi)
  <div class="narasi">
    <div class="judul">REKOMENDASI</div>
    {{ $current->rekomendasi }}
  </div>
  @endif
</div>
@endif
@else
<table>
  <tr><td class="c">Belum ada nilai kepribadian untuk periode terpilih.</td></tr>
</table>
@endif

{{-- Tanda Tangan: KIRI = Mengetahui (Danskadik) — KANAN = penandatangan laporan kepribadian --}}
@php
  $lemdikNamaTrim = trim($sk->lemdik->nama ?? '');
  $jabatanDanskadik = 'Komandan ' . ($lemdikNamaTrim !== '' ? $lemdikNamaTrim : 'Skadron Pendidikan');

  $ambil = fn($val, $fallback) => (trim((string) $val) !== '') ? $val : $fallback;
  $jabatanKiri   = $ambil($ttdKiri?->jabatan, $jabatanDanskadik);
  $namaKiri      = $ambil($ttdKiri?->nama, $sk->kepala_sekolah ?? '................................');
  $pangkatKiri   = $ambil($ttdKiri?->pangkat, $sk->pangkat_kepala ?? '');
  $nrpKiri       = $ambil($ttdKiri?->nrp, $sk->nrp_kepala ?? '');

  $jabatanKanan  = $ambil($ttdKanan?->jabatan, 'Kepala Sekolah');
  $namaKanan     = $ambil($ttdKanan?->nama, $sk->kepala_sekolah ?? '................................');
  $pangkatKanan  = $ambil($ttdKanan?->pangkat, $sk->pangkat_kepala ?? '');
  $nrpKanan      = $ambil($ttdKanan?->nrp, $sk->nrp_kepala ?? '');

  $gabungPangkatNrp = fn($p, $n) => trim($p . (($p && $n) ? ' - ' : '') . ($n ? 'NRP. ' . $n : ''));
  $pangkatNrpKiri  = $gabungPangkatNrp($pangkatKiri, $nrpKiri);
  $pangkatNrpKanan = $gabungPangkatNrp($pangkatKanan, $nrpKanan);
@endphp
<table class="ttd-table">
  <tr>
    <td class="tepi">Mengetahui,</td>
    <td class="spasi"></td>
    <td class="tepi">{{ $sk->lemdik->kota ?? '' }}, {{ date('d F Y') }}</td>
  </tr>
  <tr>
    <td class="tepi">{{ $jabatanKiri }}</td>
    <td class="spasi"></td>
    <td class="tepi">{{ $jabatanKanan }}</td>
  </tr>
  <tr class="ruang"><td colspan="3"></td></tr>
  <tr>
    <td class="tepi nama">{{ $namaKiri }}</td>
    <td class="spasi"></td>
    <td class="tepi nama">{{ $namaKanan }}</td>
  </tr>
  <tr>
    <td class="tepi pangkat-nrp">{{ $pangkatNrpKiri }}</td>
    <td class="spasi"></td>
    <td class="tepi pangkat-nrp">{{ $pangkatNrpKanan }}</td>
  </tr>
</table>

<div class="footer-info">
  <span>Dicetak: {{ date('d/m/Y H:i') }}</span>
  <span>SIPP — Sistem Informasi Penilaian Prestasi</span>
</div>

</body>
</html>
