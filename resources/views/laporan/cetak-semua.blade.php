<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak NPP — {{ $angkatan->skadik->nama_singkat ?? '' }} Angkatan {{ $angkatan->nomor_angkatan ?? '' }}</title>
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
  th, td { border: 1px solid #000; padding: 5px 8px; text-align: center; font-size: 10pt; vertical-align: middle; }
  th { background: #e0e0e0; font-weight: bold; }
  .text-left { text-align: left; }

  /* ── Blok Tanda Tangan (Revisi 8 September 2026) ──
     1) "Mengetahui," di atas jabatan Komandan Skadron Pendidikan.
     2) Baris kiri & kanan DIJAMIN sejajar (jabatan-jabatan, nama-nama,
        pangkat·NRP-pangkat·NRP) karena memakai baris tabel yang sama.
     3) Pangkat & NRP ditulis BERSEBELAHAN satu baris:
        mis. "Letkol Pnb - NRP. 489201". */
  table.ttd-table { width: 100%; border-collapse: collapse; margin-top: 40px; page-break-inside: avoid; break-inside: avoid; }
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
  <button onclick="window.print()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#4f46e5;color:#fff;border:none;border-radius:6px">🖨️ Cetak Halaman</button>
  <button onclick="window.close()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#888;color:#fff;border:none;border-radius:6px;margin-left:8px">Tutup</button>
</div>

{{-- Kop: WINGDIK 300 → SKADRON PENDIDIKAN 302 → AFSMBSC --}}
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
  <h3>REKAP NILAI PRESTASI PENDIDIKAN (NPP)</h3>
  <div style="font-size:11pt;margin-top:4px">
    @if($angkatan->jurusan) {{ strtoupper($angkatan->jurusan) }} — @endif
    Angkatan {{ $angkatan->nomor_angkatan }} Tahun {{ $angkatan->tahun_masuk }}
  </div>
  @php $first = $data->first(); @endphp
  @if($first)
  <div style="font-size:10pt;margin-top:2px;color:#555">
    Bobot: Akademik {{ $first->bobot_akademik }}% · Kepribadian {{ $first->bobot_kepribadian }}% · Samapta {{ $first->bobot_samapta }}%
  </div>
  @endif
</div>

<table>
  <thead>
    <tr>
      <th rowspan="2">Rank</th>
      <th rowspan="2">NRP</th>
      <th rowspan="2">Pangkat</th>
      <th rowspan="2">Nama</th>
      <th colspan="3">Komponen Nilai</th>
      <th rowspan="2">NPP</th>
      <th rowspan="2">Predikat</th>
      <th rowspan="2">Keterangan</th>
    </tr>
    <tr>
      <th>Akademik</th>
      <th>Kepribadian</th>
      <th>Samapta</th>
    </tr>
  </thead>
  <tbody>
    @php $totalNPP = 0; $countNPP = 0; @endphp
    @foreach($data as $d)
      @php
        $npp = $d->nilai_akhir ?? 0;
        $totalNPP += $npp;
        if ($npp > 0) $countNPP++;
        $ket = $npp >= 85 ? 'Sangat Baik' : ($npp >= 75 ? 'Baik' : ($npp >= 65 ? 'Cukup' : ($npp >= 55 ? 'Kurang' : 'Sangat Kurang')));
        $bgRow = '';
        if ($d->rank === 1) $bgRow = 'background:#fef3c7;';
        elseif ($d->rank === 2) $bgRow = 'background:#f3f4f6;';
        elseif ($d->rank === 3) $bgRow = 'background:#fef9c3;';
      @endphp
      <tr style="{{ $bgRow }}">
        <td>{{ $d->rank }}</td>
        <td>{{ $d->peserta->nrp }}</td>
        <td>{{ $d->peserta->pangkat }}</td>
        <td class="text-left" style="font-weight:500">{{ $d->peserta->nama }}</td>
        <td>{{ $d->nilai_akademik }}</td>
        <td>{{ $d->nilai_kepribadian }}</td>
        <td>{{ $d->nilai_samapta }}</td>
        <td style="font-weight:bold">{{ $d->nilai_akhir }}</td>
        <td>{{ $d->predikat_huruf }} ({{ $d->predikat_angka }})</td>
        <td>{{ $ket }}</td>
      </tr>
    @endforeach

    @if($countNPP > 0)
    <tr style="background:#f0f0f0;font-weight:bold">
      <td colspan="7" class="text-left" style="text-align:right">Rata-rata Angkatan</td>
      <td>{{ number_format($totalNPP / $countNPP, 2) }}</td>
      <td colspan="2"></td>
    </tr>
    @endif
  </tbody>
</table>

{{-- Tanda Tangan: KIRI = Mengetahui, Komandan Skadron Pendidikan — KANAN = Kepala Sekolah --}}
@php
  /* Revisi 8 September 2026:
     - Sumber jabatan = kolom `jabatan` penandatangan (bukan label jenis
       "Danskadik"/"Kepala Sekolah"), selaras dengan laporan NPA, NPK & NPS. */
  $sk = $angkatan->skadik;
  $lemdikNama = trim($sk->lemdik->nama ?? '');
  $jabatanDanskadik = 'Komandan ' . ($lemdikNama !== '' ? $lemdikNama : 'Skadron Pendidikan');

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
  <span>Dicetak dari SIPP</span>
  <span>{{ date('d/m/Y H:i') }}</span>
</div>

</body>
</html>
