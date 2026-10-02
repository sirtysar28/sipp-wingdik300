<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>NPP — {{ $peserta->nama ?? '' }}</title>
<style>
  @page { size: portrait; margin: 20mm; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; }
  .header { text-align: center; margin-bottom: 16px; border-bottom: 3px double #000; padding-bottom: 10px; }
  .header h1 { font-size: 14pt; font-weight: bold; letter-spacing: 2px; }
  .header h2 { font-size: 12pt; font-weight: bold; margin-top: 2px; }
  .header h3 { font-size: 11pt; font-weight: normal; margin-top: 4px; }
  .doc-title { text-align: center; margin-bottom: 16px; }
  .doc-title h3 { font-size: 13pt; font-weight: bold; text-decoration: underline; }
  .doc-title p { font-size: 11pt; margin-top: 4px; }

  .identitas { margin-bottom: 16px; }
  .identitas td { padding: 2px 8px; font-size: 11pt; vertical-align: top; }

  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #000; padding: 6px 10px; text-align: center; font-size: 10pt; vertical-align: middle; }
  th { background: #ffffff; font-weight: bold; }
  .text-left { text-align: left; }
  .text-right { text-align: right; }

  .section-title { font-size: 12pt; font-weight: bold; margin: 18px 0 8px; }

  .result-box { border: 2px solid #000; padding: 12px; text-align: center; margin: 16px 0; }
  .result-box .label { font-size: 11pt; font-weight: bold; }
  .result-box .value { font-size: 24pt; font-weight: bold; color: #000; margin: 4px 0; }
  .result-box .predikat { font-size: 14pt; font-weight: bold; }

  /* ── Blok Tanda Tangan (Revisi 8 September 2026) ──
     1) "Mengetahui," di atas jabatan Komandan Skadron Pendidikan.
     2) Baris kiri & kanan DIJAMIN sejajar (jabatan-jabatan, nama-nama,
        pangkat·NRP-pangkat·NRP) karena memakai baris tabel yang sama.
     3) Pangkat & NRP ditulis BERSEBELAHAN satu baris:
        mis. "Letkol Pnb - NRP. 489201". */
  table.ttd-table { width: 100%; border-collapse: collapse; margin-top: 50px; page-break-inside: avoid; break-inside: avoid; }
  table.ttd-table tr { page-break-inside: avoid; break-inside: avoid; }
  table.ttd-table td { border: none; padding: 1px 6px; font-size: 11pt; line-height: 1.4; text-align: center; vertical-align: top; }
  table.ttd-table td.tepi { width: 38%; }
  table.ttd-table td.spasi { width: 24%; }
  table.ttd-table tr.ruang td { height: 70px; }
  table.ttd-table td.nama { font-weight: bold; text-decoration: underline; }
  table.ttd-table td.pangkat-nrp { font-size: 10pt; }

  .footer-info { margin-top: 12px; font-size: 9pt; color: #555; display: flex; justify-content: space-between; }
  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:10px;margin-bottom:10px;background:#ffffff">
  <button onclick="window.print()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#555;color:#fff;border:none;border-radius:6px">🖨️ Cetak Halaman</button>
  <button onclick="window.close()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#888;color:#fff;border:none;border-radius:6px;margin-left:8px">Tutup</button>
</div>

{{-- Kop: WINGDIK 300 → SKADRON PENDIDIKAN 302 → AFSMBSC --}}
@php
  $wingdik = strtoupper($angkatan->skadik->lemdik->wingdik ?? 'WINGDIK');
  $skadikNama = strtoupper($angkatan->skadik->nama_singkat ?? ($angkatan->skadik->nama ?? 'SEKOLAH'));
  $lemdikNama = strtoupper($angkatan->skadik->lemdik->nama ?? '');
@endphp
<div class="header">
  <h1>{{ $wingdik }}</h1>
  <h2>{{ $skadikNama }}</h2>
  @if($lemdikNama && $lemdikNama !== $skadikNama)
  <h3>{{ $lemdikNama }}</h3>
  @endif
</div>

<div class="doc-title">
  <h3>NILAI PRESTASI PENDIDIKAN</h3>
  <p>
    @if($angkatan->jurusan) {{ strtoupper($angkatan->jurusan) }} — @endif
    Angkatan {{ $angkatan->nomor_angkatan }} Tahun {{ $angkatan->tahun_masuk }}
  </p>
</div>

{{-- Identitas Peserta --}}
<table class="identitas" style="border:none;width:auto">
  <tr style="border:none">
    <td style="border:none;text-align:left;font-weight:bold;width:80px">Nama</td>
    <td style="border:none;text-align:left">: {{ $peserta->nama }}</td>
    <td style="border:none;text-align:left;font-weight:bold;width:80px">NRP</td>
    <td style="border:none;text-align:left">: {{ $peserta->nrp }}</td>
  </tr>
  <tr style="border:none">
    <td style="border:none;text-align:left;font-weight:bold">Pangkat</td>
    <td style="border:none;text-align:left">: {{ $peserta->pangkat }}</td>
    <td style="border:none;text-align:left;font-weight:bold">Angkatan</td>
    <td style="border:none;text-align:left">: {{ $angkatan->nomor_angkatan }}</td>
  </tr>
</table>

{{-- A. Nilai Akademik — DETAIL PER MATA PELAJARAN (Revisi 29 Agustus 2026) --}}
@php
  $detailNilai = [];
  if ($akademik && is_array($akademik->detail_nilai)) {
      $detailNilai = $akademik->detail_nilai;
  } elseif ($akademik && $akademik->detail_nilai) {
      $detailNilai = json_decode($akademik->detail_nilai, true) ?: [];
  }
  $totalHN = $mataPelajaran->sum(fn($mp) => $mp->harga_nilai_calc);
  $sumMPHN = 0;
@endphp
<div class="section-title">A. NILAI PRESTASI AKADEMIK</div>
<table>
  <thead>
    <tr>
      <th style="width:36px">No</th>
      <th class="text-left">Mata Pelajaran</th>
      <th style="width:44px">JP</th>
      <th style="width:44px">Bobot</th>
      <th style="width:52px">HN</th>
      <th style="width:60px">Nilai</th>
      <th style="width:76px">Nilai × HN</th>
    </tr>
  </thead>
  <tbody>
    @if(count($detailNilai) > 0)
      @foreach($detailNilai as $i => $valMP)
      @php
        $mp = $mataPelajaran[$i] ?? null;
        $hnMP = $mp ? $mp->harga_nilai_calc : 0;
        $sumMPHN += ($valMP ?? 0) * $hnMP;
      @endphp
      <tr>
        <td>{{ $i + 1 }}</td>
        <td class="text-left">{{ $mp->nama ?? 'Mata Pelajaran ' . ($i + 1) }}</td>
        <td>{{ $mp->jp ?? '-' }}</td>
        <td>{{ $mp->bobot ?? '-' }}</td>
        <td>{{ $hnMP ?: '-' }}</td>
        <td>{{ $valMP !== null && $valMP !== '' ? $valMP : '-' }}</td>
        <td>{{ ($valMP !== null && $valMP !== '' && $hnMP) ? round(($valMP * $hnMP), 2) : '-' }}</td>
      </tr>
      @endforeach
    @elseif($mataPelajaran->count() > 0)
      @foreach($mataPelajaran as $i => $mp)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td class="text-left">{{ $mp->nama }}</td>
        <td>{{ $mp->jp }}</td>
        <td>{{ $mp->bobot }}</td>
        <td>{{ $mp->harga_nilai_calc }}</td>
        <td>-</td>
        <td>-</td>
      </tr>
      @endforeach
    @else
      <tr><td colspan="7">Belum ada data nilai akademik</td></tr>
    @endif
    <tr style="background:#ffffff;font-weight:bold">
      <td colspan="4" class="text-left">JUMLAH</td>
      <td>{{ $totalHN }}</td>
      <td>{{ $akademik ? $akademik->jumlah_nilai : '-' }}</td>
      <td>{{ count($detailNilai) > 0 ? round($sumMPHN, 2) : '-' }}</td>
    </tr>
  </tbody>
</table>
<table style="margin-top:4px">
  <tr>
    <td class="text-left" style="border:none;font-weight:bold">Nilai Prestasi Akademik (NPA)</td>
    <td style="border:none">: {{ $akademik ? $akademik->npa : '-' }}</td>
    <td style="border:none;font-weight:bold">Bobot Akademik</td>
    <td style="border:none">: {{ $kompilasi ? $kompilasi->bobot_akademik . '%' : '70%' }}</td>
    <td style="border:none;font-weight:bold">Nilai Terbobot</td>
    <td style="border:none">: {{ $kompilasi && $akademik ? round($akademik->npa * $kompilasi->bobot_akademik / 100, 2) : '-' }}</td>
  </tr>
</table>

{{-- B. Nilai Kepribadian --}}
<div class="section-title">B. NILAI KEPRIBADIAN</div>
<table>
  <thead>
    <tr>
      <th>No</th>
      <th>Periode</th>
      <th>Nilai Akhir</th>
    </tr>
  </thead>
  <tbody>
    @if($kepribadianList->count() > 0)
      @foreach($kepribadianList as $i => $nk)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $nk->periode?->label ?? '-' }}</td>
        <td>{{ round($nk->nilai_akhir, 2) }}</td>
      </tr>
      @endforeach
      <tr style="background:#ffffff;font-weight:bold">
        <td colspan="2" class="text-left">Rata-rata Kepribadian</td>
        <td>{{ round($kepribadianAvg, 2) }}</td>
      </tr>
    @else
      <tr><td colspan="3">Belum ada data</td></tr>
    @endif
  </tbody>
</table>
<table style="margin-top:4px">
  <tr>
    <td class="text-left" style="border:none;font-weight:bold">Bobot Kepribadian</td>
    <td style="border:none">{{ $kompilasi ? $kompilasi->bobot_kepribadian . '%' : '20%' }}</td>
    <td style="border:none">Nilai Terbobot:</td>
    <td style="border:none;font-weight:bold">{{ $kompilasi ? round($kompilasi->nilai_kepribadian * $kompilasi->bobot_kepribadian / 100, 2) : '-' }}</td>
  </tr>
</table>

{{-- C. Nilai Samapta --}}
<div class="section-title">C. NILAI PRESTASI SAMAPTA</div>
@if($samapta?->putaran_label)
<div style="font-size:8pt;color:#555;margin-bottom:3px">
  Sumber: <strong>{{ $samapta->putaran_label }}</strong> (putaran terakhir — nilai konversi dipakai sebagai input NPP)
</div>
@endif
<table>
  @if($samapta)
  <tr>
    <td class="text-left">Jarak Lari (meter)</td>
    <td>{{ $samapta->jarak_lari ?? '-' }}</td>
    <td class="text-left">Nilai Lari (Garjas A)</td>
    <td>{{ $samapta->nilai_lari ?? '-' }}</td>
  </tr>
  <tr>
    <td class="text-left">Garjas B</td>
    <td>{{ $samapta->garjas_b_nilai ?? '-' }}</td>
    <td class="text-left">Nilai Konversi</td>
    <td>{{ $samapta->nilai_konversi ?? '-' }}</td>
  </tr>
  <tr style="font-weight:bold">
    <td class="text-left">Nilai Akhir (NPS)</td>
    <td>{{ $samapta ? $samapta->nilai_akhir : '-' }}</td>
    <td class="text-left">Bobot Samapta</td>
    <td>{{ $kompilasi ? $kompilasi->bobot_samapta . '%' : '10%' }}</td>
  </tr>
  @else
  <tr><td colspan="4">Belum ada data nilai samapta</td></tr>
  @endif
</table>

{{-- D. Kompilasi NPP --}}
@php
  $npaCetak = $akademik ? round($akademik->npa, 2) : round($kompilasi?->nilai_akademik ?? 0, 2);
  $npkCetak = round($kepribadianAvg, 2);
  // Revisi 30 Sept 2026: NPS utk NPP = nilai konversi PUTARAN TERAKHIR (dari $npsAvg controller)
  $npsCetak = $npsAvg ?? round($kompilasi?->nilai_samapta ?? 0, 2);
  $bA = $kompilasi?->bobot_akademik ?? 70;
  $bK = $kompilasi?->bobot_kepribadian ?? 20;
  $bS = $kompilasi?->bobot_samapta ?? 10;
  $nppCetak = round(($npaCetak * $bA / 100) + ($npkCetak * $bK / 100) + ($npsCetak * $bS / 100), 2);
@endphp
<div class="section-title">D. NILAI PRESTASI PENDIDIKAN (NPP)</div>
<table>
  <thead>
    <tr>
      <th>Komponen</th>
      <th>Nilai</th>
      <th>Bobot</th>
      <th>Nilai Terbobot</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="text-left">Akademik</td>
      <td>{{ $npaCetak }}</td>
      <td>{{ $bA }}%</td>
      <td>{{ round($npaCetak * $bA / 100, 2) }}</td>
    </tr>
    <tr>
      <td class="text-left">Kepribadian</td>
      <td>{{ $npkCetak }}</td>
      <td>{{ $bK }}%</td>
      <td>{{ round($npkCetak * $bK / 100, 2) }}</td>
    </tr>
    <tr>
      <td class="text-left">Samapta</td>
      <td>{{ $npsCetak }}</td>
      <td>{{ $bS }}%</td>
      <td>{{ round($npsCetak * $bS / 100, 2) }}</td>
    </tr>
    <tr style="background:#ffffff;font-weight:bold">
      <td class="text-left">TOTAL (NPP)</td>
      <td colspan="3" style="font-size:14pt;font-weight:bold">{{ $nppCetak }}</td>
    </tr>
  </tbody>
</table>

{{-- Result Box --}}
@if($kompilasi)
@php
  $ket = $nppCetak >= 85 ? 'Sangat Baik' : ($nppCetak >= 75 ? 'Baik' : ($nppCetak >= 65 ? 'Cukup' : ($nppCetak >= 55 ? 'Kurang' : 'Sangat Kurang')));
@endphp
<div class="result-box">
  <div class="label">Nilai Prestasi Pendidikan</div>
  <div class="value">{{ number_format($nppCetak, 2) }}</div>
  <div class="predikat">Predikat: {{ $kompilasi->predikat_huruf }} ({{ $kompilasi->predikat_angka }}) — {{ $ket }}</div>
</div>
@endif

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
