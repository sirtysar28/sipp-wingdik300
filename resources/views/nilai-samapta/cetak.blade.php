<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak NPS — {{ $angkatan->skadik->nama_singkat ?? '' }} Angkatan {{ $angkatan->nomor_angkatan ?? '' }} — {{ $putaranLabel }}</title>
<style>
  @page { size: landscape; margin: 10mm; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; }
  .header { text-align: center; margin-bottom: 12px; border-bottom: 3px double #000; padding-bottom: 8px; }
  .header h1 { font-size: 13pt; font-weight: bold; letter-spacing: 2px; }
  .header h2 { font-size: 11pt; font-weight: bold; margin-top: 2px; }
  .header h3 { font-size: 10pt; font-weight: normal; margin-top: 3px; }
  .sub-header { text-align: center; margin-bottom: 10px; }
  .sub-header h3 { font-size: 12pt; font-weight: bold; text-decoration: underline; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
  th, td { border: 1px solid #000; padding: 4px 6px; text-align: center; font-size: 9pt; vertical-align: middle; }
  th { background: #ffffff; font-weight: bold; }
  .text-left { text-align: left; }
  .fw-bold { font-weight: bold; }
  .nps-val { font-weight: bold; font-size: 10pt; }

  /* ── Blok Tanda Tangan (Revisi 8 September 2026) ──
     1) "Mengetahui," di atas jabatan Komandan Skadron Pendidikan.
     2) Baris kiri & kanan DIJAMIN sejajar (jabatan-jabatan, nama-nama,
        pangkat·NRP-pangkat·NRP) karena memakai baris tabel yang sama
        (sebelumnya memakai dua kolom flex terpisah).
     3) Pangkat & NRP tetap ditulis BERSEBELAHAN satu baris:
        mis. "Letkol Pnb - NRP. 489201". */
  table.ttd-table { width: 100%; border-collapse: collapse; margin-top: 30px; page-break-inside: avoid; break-inside: avoid; }
  table.ttd-table tr { page-break-inside: avoid; break-inside: avoid; }
  table.ttd-table td { border: none; padding: 1px 6px; font-size: 10pt; line-height: 1.4; text-align: center; vertical-align: top; }
  table.ttd-table td.tepi { width: 38%; }
  table.ttd-table td.spasi { width: 24%; }
  table.ttd-table tr.ruang td { height: 55px; }
  table.ttd-table td.nama { font-weight: bold; text-decoration: underline; }
  table.ttd-table td.pangkat-nrp { font-size: 9pt; }

  .footer-info { margin-top: 10px; font-size: 8pt; color: #555; display: flex; justify-content: space-between; }

  @media print {
    body { font-size: 9pt; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:10px;margin-bottom:10px;background:#f0f0f0">
  <button onclick="window.print()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#555;color:#fff;border:none;border-radius:6px">🖨️ Cetak Halaman</button>
  <button onclick="window.close()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#888;color:#fff;border:none;border-radius:6px;margin-left:8px">Tutup</button>
</div>

<div class="header">
  <h1>{{ strtoupper($angkatan->skadik->lemdik->wingdik ?? 'WINGDIK') }}</h1>
  <h2>{{ strtoupper($angkatan->skadik->nama_singkat ?? ($angkatan->skadik->nama ?? 'SEKOLAH')) }}</h2>
  @if($angkatan->skadik->lemdik)
  <h3>{{ strtoupper($angkatan->skadik->lemdik->nama ?? '') }}</h3>
  @endif
</div>

<div class="sub-header">
  <h3>DAFTAR NILAI PRESTASI SAMAPTA (NPS)</h3>
  <div style="font-size:10pt;margin-top:3px">
    Angkatan {{ $angkatan->nomor_angkatan ?? '' }} Tahun {{ $angkatan->tahun_masuk ?? '' }}
    &nbsp;—&nbsp; <strong>{{ $putaranLabel }}</strong>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th rowspan="2">No</th>
      <th rowspan="2">Nama</th>
      <th rowspan="2">Pangkat</th>
      <th rowspan="2">NRP</th>
      <th rowspan="2">Jarak Lari (m)</th>
      <th rowspan="2">Nilai Lari (Garjas A)</th>
      <th rowspan="2">Garjas B</th>
      <th rowspan="2">Nilai Akhir</th>
      <th rowspan="2" style="background:#ffffff;color:#000">Nilai Konversi (NPS)</th>
      <th rowspan="2">Kategori</th>
    </tr>
  </thead>
  <tbody>
    @php
      $no = 1;
      $totalNPS = 0;
      $countNPS = 0;
    @endphp
    @foreach($data as $d)
      @php
        // Revisi 17 Sept 2026: rata-rata angkatan dihitung dari NILAI KONVERSI (NPS),
        // bukan dari nilai_akhir.
        $nps = $d->nilai_konversi ?? 0;
        if ($nps > 0) { $totalNPS += $nps; $countNPS++; }
        $predikat = $d->predikat;
      @endphp
      <tr>
        <td>{{ $no++ }}</td>
        <td class="text-left" style="font-weight:500">{{ $d->peserta->nama }}</td>
        <td>{{ $d->peserta->pangkat }}</td>
        <td style="font-size:8pt">{{ $d->peserta->nrp }}</td>
        <td>{{ $d->jarak_lari ?? '-' }}</td>
        <td style="font-weight:600">{{ $d->nilai_lari ?? '-' }}</td>
        <td style="font-weight:600">{{ $d->garjas_b_nilai ?? '-' }}</td>
        <td class="nps-val">{{ $d->nilai_akhir ?? '-' }}</td>
        {{-- Revisi 26 Agustus 2026: predikat berbasis NILAI KONVERSI --}}
        <td style="font-weight:600">{{ $d->nilai_konversi ?? '-' }}</td>
        <td>{{ ($d->nilai_konversi !== null && (float)$d->nilai_konversi > 0) ? $predikat['label'] : '-' }}</td>
      </tr>
    @endforeach

    @if($countNPS > 0)
    <tr style="background:#ffffff;font-weight:bold">
      <td colspan="7" style="text-align:right">Rata-rata Angkatan</td>
      <td></td>
      <td>{{ number_format($totalNPS / $countNPS, 2) }}</td>
      <td></td>
    </tr>
    @endif
  </tbody>
</table>

@php
  /* Revisi 2 September 2026 — kolom tanda tangan Danskadik (tetap di KIRI/awal)
     menjadi dua baris: "Mengetahui, Komandan Skadron Pendidikan 303".
     Nama skadron dinamis dari Lemdik sekolah.

     Revisi 22 September 2026 — Danskadik berlaku di SEMUA report (bukan NPP
     saja): cetak NPS kini mengambil penandatangan dari master Penandatangan
     (kiri = jenis 'danskadik', kanan = jenis 'samapta') dgn fallback ke data
     Kepala Sekolah di master Skadik bila belum diatur. */
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
    <td class="tepi">{{ $angkatan->skadik->lemdik->kota ?? '' }}, {{ date('d F Y') }}</td>
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
