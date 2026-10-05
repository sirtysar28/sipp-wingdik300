{{--
  Revisi 4 Oktober 2026 — FORMULIR PENILAIAN KEPRIBADIAN (NPK) INDIVIDU.
  Layout mengikuti dokumen acuan "bahan nilai individul dari NPK.docx" /
  foto "hasil cetak NPK Individual.jpeg":
    1. Kop kiri: WINGDIK (diperluas dr singkatan) + Nama Lembaga
       Pendidikan — dinamis dari master Lemdik.
    2. Judul: FORMULIR PENILAIAN KEPRIBADIAN SISWA + nama sekolah (dinamis).
    3. Identitas 3 kolom: Nama/No. Urut — Pangkat/NRP/Tahap — Sekolah/Nilai.
    4. Tabel aspek 7 kolom (NO | ASPEK | KS K C B BS) dengan tanda √.
    5. Kotak Keterangan (menempel di bawah tabel).
    6. Penjelasan + Rekomendasi + rumus nilai (75 + Σpoin = nilai akhir).
    7. TTD kiri Mengetahui (Danskadik), kanan Penilai.
  Semua nilai bersumber dari data eksisting (tidak ada hardcode aspek/siswa).
  Table-based layout + ukuran mm agar stabil di print A4 maupun PDF.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Penilaian Kepribadian Siswa — {{ $peserta->nama }}</title>
    <style>
        /* =========================================================
         * PAGE
         * ========================================================= */
        @page { size: A4 portrait; margin: 0; }
        html, body {
            margin: 0; padding: 0;
            background: #ffffff; color: #000000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
        }
        body { width: 210mm; }
        .page {
            box-sizing: border-box;   /* padding dihitung DALAM 210×297mm —
                                       total pas 1 hal. A4, tidak kena
                                       shrink-to-fit Chrome (dulu margin
                                       kanan lebih lebar krn halaman 318mm) */
            width: 210mm; min-height: 297mm;
            padding-top: 9mm; padding-right: 13mm;
            padding-bottom: 7mm; padding-left: 13mm;
            position: relative;
            background: #ffffff;
        }

        /* =========================================================
         * HEADER
         * ========================================================= */
        .header { width: 184mm; }
        .header-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        .header-left   { width: 38%; }
        .header-center { width: 27%; }
        .header-right  { width: 35%; }
        .institution {
            display: inline-block;   /* garis bawah selebar teks saja, bukan selebar kolom */
            font-size: 8.5pt; font-weight: bold;
            line-height: 1.15; text-transform: uppercase;
            border-bottom: 0.35mm solid #000;
            padding-bottom: 0.6mm;
        }

        /* =========================================================
         * TITLE
         * ========================================================= */
        .title {
            width: 184mm; text-align: center;
            font-size: 10pt; font-weight: bold;
            line-height: 1.2;
            margin-top: 4mm; margin-bottom: 4mm;
            text-transform: uppercase;
        }

        /* =========================================================
         * STUDENT INFORMATION
         * ========================================================= */
        .student-info { width: 184mm; margin-bottom: 3mm; }
        .student-info-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .student-info-table td {
            border: none; padding: 0; vertical-align: top;
            font-size: 8.5pt; line-height: 1.35;
            overflow-wrap: break-word;   /* NRP/nilai panjang wrap, tidak kepotong */
        }
        .student-info-left   { width: 34%; }
        .student-info-middle { width: 36%; }
        .student-info-right  { width: 30%; }
        .info-label      { display: inline-block; width: 17mm; }
        .info-label-wide { display: inline-block; width: 19mm; }
        .info-colon      { display: inline-block; width: 3mm; }

        /* =========================================================
         * ASSESSMENT TABLE
         * ========================================================= */
        .assessment-wrapper { width: 184mm; }
        .assessment-table {
            width: 184mm; border-collapse: collapse; table-layout: fixed;
            font-size: 7.7pt;
        }
        .assessment-table th, .assessment-table td { border: 0.35mm solid #444444; }
        .assessment-table thead th {
            height: 7mm; padding: 0.8mm 0.7mm;
            text-align: center; vertical-align: middle;
            font-weight: bold; line-height: 1.05;
        }
        .assessment-table tbody td {
            padding: 1.3mm 1.2mm; vertical-align: middle; line-height: 1.15;
        }

        /* NO = 10mm | ASPEK = 139mm | KS/K/C/B/BS = 5 x 7mm → 184mm */
        .col-no     { width: 10mm; text-align: center; }
        .col-aspect { width: 139mm; }
        .col-grade  { width: 7mm;  text-align: center; }

        .aspect-name        { font-weight: bold; }
        .aspect-description { font-weight: normal; }

        .grade-cell {
            width: 7mm; text-align: center;
            vertical-align: middle !important;
            font-size: 11pt; font-weight: bold; line-height: 1;
        }
        .check {
            display: inline-block;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11pt; line-height: 1;
        }

        /* =========================================================
         * NOTES / KETERANGAN
         * =========================================================
         * box-sizing: border-box + width 184,35mm (184mm + 2×½border)
         * agar garis kiri/kanan kotak ini NIMPah PERSIS dengan garis
         * tabel di atasnya (border-collapse menggambar border tabel
         * setengah di dalam / setengah di luar kotak 184mm). */
        .notes {
            box-sizing: border-box;
            width: 184.35mm;
            border-left: 0.35mm solid #444444;
            border-right: 0.35mm solid #444444;
            border-bottom: 0.35mm solid #444444;
            padding: 2.5mm 3.5mm;
            font-size: 7.6pt; line-height: 1.18;
        }
        .notes-title { font-weight: bold; margin-bottom: 0.8mm; }
        .notes-list { margin: 0; padding-left: 5mm; }
        .notes-list li { margin: 0 0 0.4mm 0; padding-left: 0.5mm; }

        /* =========================================================
         * PENJELASAN / REKOMENDASI
         * (kotak menyambung ke .notes di atasnya — margin-top: 0,
         *  garis kiri/kanan nimpah dgn tabel, border-bottom jadi
         *  pemisah antar seksi) */
        .explanation, .recommendation {
            box-sizing: border-box;
            width: 184.35mm;
            border-left: 0.35mm solid #444444;
            border-right: 0.35mm solid #444444;
            border-bottom: 0.35mm solid #444444;
            padding: 2mm 3.5mm;
            margin-top: 0;
            font-size: 8pt; line-height: 1.22;
        }
        .section-title { font-weight: bold; margin-bottom: 0.8mm; }
        .section-text  { margin: 0; }

        /* =========================================================
         * SCORE — kotak terakhir rangkaian (kiri/kanan sambung,
         *  teks nilai rata kanan) */
        .score-wrapper {
            box-sizing: border-box;
            width: 184.35mm;
            border-left: 0.35mm solid #444444;
            border-right: 0.35mm solid #444444;
            border-bottom: 0.35mm solid #444444;
            padding: 1.5mm 3.5mm;
            margin-top: 0;
            text-align: right;
        }
        .score { font-size: 8.5pt; font-weight: bold; }

        /* =========================================================
         * SIGNATURE
         * ========================================================= */
        .signature-wrapper { width: 184mm; margin-top: 7mm; page-break-inside: avoid; break-inside: avoid; }
        .signature-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .signature-table td {
            border: none; padding: 0; vertical-align: top;
            text-align: center; font-size: 8.5pt;
        }
        .signature-left  { width: 50%; }
        .signature-right { width: 50%; }
        .signature-date     { height: 5mm; line-height: 1.2; }
        .signature-position { height: 20mm; line-height: 1.2; }
        .signature-name     { font-weight: bold; text-decoration: underline; line-height: 1.2; }
        .signature-detail   { margin-top: 0.8mm; line-height: 1.2; }

        /* =========================================================
         * PRINT
         * ========================================================= */
        @media screen {
            body { background: #e5e5e5; }
            .page { margin: 10mm auto; box-shadow: 0 0 5mm rgba(0,0,0,0.15); }
        }
        @media print {
            html, body { margin: 0; padding: 0; background: #ffffff; }
            .page { margin: 0; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

{{-- Toolbar preview (tidak ikut tercetak) --}}
<div class="no-print" style="text-align:center;padding:10px;margin-bottom:10px;background:#f0f0f0">
    <button onclick="window.print()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#555;color:#fff;border:none;border-radius:6px">🖨️ Cetak / Simpan PDF</button>
    <button onclick="window.close()" style="padding:8px 24px;font-size:13px;cursor:pointer;background:#888;color:#fff;border:none;border-radius:6px;margin-left:8px">Tutup</button>
</div>

@php
    /* ── Data dinamis dari elemen eksisting ───────────────────── */
    $sk          = $angkatan->skadik;
    $lemdik      = $sk->lemdik;

    /* Kop: baris 1 = WINGDIK (diperluas dari singkatan, mis.
       "WINGDIK 300/TEK" -> "WING PENDIDIKAN 300/TEKNIK"),
       baris 2 = Nama Lembaga Pendidikan (lemdik->nama). */
    $wingdikNama = strtoupper(trim((string) ($lemdik->wingdik ?? '')));
    $wingdikNama = str_replace('WINGDIK', 'WING PENDIDIKAN', $wingdikNama);
    $wingdikNama = preg_replace('/\bTEK\b(?!NIK)/', 'TEKNIK', $wingdikNama);
    $wingdikNama = trim($wingdikNama) ?: 'WING PENDIDIKAN 300/TEKNIK';
    $lemdikNama  = strtoupper(trim((string) ($lemdik->nama ?? '')));
    $sekolahNama = $sk->nama_singkat ?? ($sk->nama ?? '-');

    // Tahap = label periode terpilih
    $periodeAktif = $current?->periode ?? $periodes->firstWhere('id', $periodeId);
    $tahapLabel   = $periodeAktif?->label ?? '-';

    // Detail kriteria per aspek
    $detailMap = $current?->detail?->keyBy('aspek_kepribadian_id') ?? collect();

    // Rumus nilai: 75 + Σpoin
    $nilaiAwal  = 75;
    $penambahan = (float) ($current?->detail?->sum('poin') ?? 0);
    $nilaiAkhir = $current ? (float) $current->nilai_akhir : $nilaiAwal + $penambahan;

    $nilaiTampil    = $current ? number_format($nilaiAkhir, 2, ',', '.') : '-';
    $penambahanTxt  = number_format(abs($penambahan), 2, ',', '.');
    $operatorNilai  = $penambahan >= 0 ? '+' : '-';

    // Tanggal tanda tangan: "Kota, Bulan Tahun"
    $bulanIndo = [1=>'Januari','Februari','Maret','April','Mei','Juni',
                  'Juli','Agustus','September','Oktober','November','Desember'];
    $tanggalCetak = trim(($lemdik->kota ?? '') . ', ' . $bulanIndo[(int) date('n')] . ' ' . date('Y'));

    // Tanda tangan — kiri: Danskadik (Mengetahui), kanan: Penilai
    $jabatanDanskadik = 'Komandan ' . (trim((string) ($lemdik->nama ?? '')) !== '' ? $lemdik->nama : 'Skadron Pendidikan');

    $ambil = fn($val, $fallback) => (trim((string) $val) !== '') ? $val : $fallback;
    $jabatanKiri   = $ambil($ttdKiri?->jabatan, $jabatanDanskadik);
    $namaKiri      = $ambil($ttdKiri?->nama,  $sk->kepala_sekolah ?? '................................');
    $pangkatKiri   = $ambil($ttdKiri?->pangkat, $sk->pangkat_kepala ?? '');
    $nrpKiri       = $ambil($ttdKiri?->nrp,   $sk->nrp_kepala ?? '');

    $namaKanan     = $ambil($ttdKanan?->nama,  $sk->kepala_sekolah ?? '................................');
    $pangkatKanan  = $ambil($ttdKanan?->pangkat, $sk->pangkat_kepala ?? '');
    $nrpKanan      = $ambil($ttdKanan?->nrp,   $sk->nrp_kepala ?? '');

    // Format baris pangkat/NRP sesuai formulir: "Letkol Tek NRP 533594"
    $formatTtd = fn($p, $n) => trim($p . (($p && $n) ? ' NRP ' : '') . $n);
@endphp

<div class="page">

    {{-- =========================================================
      * HEADER
      * ========================================================= --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div class="institution">
                        {{ $wingdikNama }}<br>
                        {{ $lemdikNama }}
                    </div>
                </td>
                <td class="header-center">&nbsp;</td>
                <td class="header-right">&nbsp;</td>
            </tr>
        </table>
    </div>

    {{-- =========================================================
      * TITLE
      * ========================================================= --}}
    <div class="title">
        FORMULIR PENILAIAN KEPRIBADIAN SISWA<br>
        {{ strtoupper($sekolahNama) }}
    </div>

    {{-- =========================================================
      * STUDENT INFO
      * ========================================================= --}}
    <div class="student-info">
        <table class="student-info-table">
            <tr>
                {{-- LEFT --}}
                <td class="student-info-left">
                    <span class="info-label">Nama</span>
                    <span class="info-colon">:</span>
                    {{ $peserta->nama }}
                    <br>
                    <span class="info-label">No. Urut</span>
                    <span class="info-colon">:</span>
                    {{ $peserta->nosis ?? '-' }}
                </td>

                {{-- MIDDLE --}}
                <td class="student-info-middle">
                    <span class="info-label-wide">Pangkat/NRP</span>
                    <span class="info-colon">:</span>
                    {{ $peserta->pangkat ?? '-' }}/{{ $peserta->nrp ?? '-' }}
                    <br>
                    <span class="info-label-wide">Tahap</span>
                    <span class="info-colon">:</span>
                    {{ $tahapLabel }}
                </td>

                {{-- RIGHT --}}
                <td class="student-info-right">
                    <span class="info-label-wide">Sekolah</span>
                    <span class="info-colon">:</span>
                    {{ $sekolahNama }}
                    <br>
                    <span class="info-label-wide">Nilai</span>
                    <span class="info-colon">:</span>
                    {{ $nilaiTampil }}
                </td>
            </tr>
        </table>
    </div>

    {{-- =========================================================
      * ASSESSMENT
      * ========================================================= --}}
    <div class="assessment-wrapper">
        <table class="assessment-table">
            <colgroup>
                <col style="width: 10mm">
                <col style="width: 139mm">
                <col style="width: 7mm">
                <col style="width: 7mm">
                <col style="width: 7mm">
                <col style="width: 7mm">
                <col style="width: 7mm">
            </colgroup>

            <thead>
                <tr>
                    <th rowspan="2">NO</th>
                    <th rowspan="2">ASPEK</th>
                    <th colspan="5">KRITERIA</th>
                </tr>
                <tr>
                    <th>KS</th>
                    <th>K</th>
                    <th>C</th>
                    <th>B</th>
                    <th>BS</th>
                </tr>
            </thead>

            <tbody>
                @forelse($aspekList as $aspek)
                    @php
                        $kriteria = strtoupper(trim((string) ($detailMap->get($aspek->id)?->kriteria ?? '')));
                        $deskripsi = trim((string) $aspek->deskripsi);
                    @endphp
                    <tr>
                        {{-- NO --}}
                        <td class="col-no">{{ $aspek->nomor }}</td>

                        {{-- ASPECT --}}
                        <td class="col-aspect">
                            <span class="aspect-name">{{ $aspek->nama }}</span>
                            @if($deskripsi !== '')
                                <span class="aspect-description"> ({{ $deskripsi }})</span>
                            @endif
                        </td>

                        {{-- KRITERIA: KS K C B BS --}}
                        @foreach(['KS','K','C','B','BS'] as $g)
                            <td class="grade-cell">
                                @if($kriteria === $g)
                                    <span class="check">√</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center">Belum ada aspek kepribadian.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- =========================================================
      * KETERANGAN
      * ========================================================= --}}
    <div class="notes">
        <div class="notes-title">Keterangan:</div>
        <ul class="notes-list">
            <li>Nilai Awal Kepribadian Siswa Ba/Ta adalah 75.</li>
            <li>Penambahan/Pengurangan Penilaian: BS (+0,5), B (+0,25), C (0), K (-0,25), KS (-0,5).</li>
            <li><strong>BS (Baik Sekali)</strong>: Siswa menunjukkan perilaku kepribadian sesuai dengan aspek yang dibahas berulang kali dalam frekuensi yang relatif sering atau memiliki kepribadian yang kuat dalam aspek tertentu.</li>
            <li><strong>B (Baik)</strong>: Siswa menunjukkan perilaku kepribadian sesuai dengan aspek yang dibahas satu sampai beberapa kali dalam frekuensi yang relatif sedang.</li>
            <li><strong>C (Cukup)</strong>: Siswa menunjukkan perilaku kepribadian sesuai dengan aspek yang dibahas secara umum namun tidak ada hal yang menonjol baik positif atau negatif.</li>
            <li><strong>K (Kurang)</strong>: Siswa pernah menunjukkan sikap dan kepribadian yang bertentangan dengan aspek yang dibahas.</li>
            <li><strong>KS (Kurang Sekali)</strong>: Siswa beberapa kali atau sering menunjukkan sikap dan kepribadian yang bertentangan dengan aspek yang dibahas atau memiliki kepribadian yang negatif dalam aspek tertentu sehingga membutuhkan perhatian khusus.</li>
        </ul>
    </div>

    {{-- =========================================================
      * PENJELASAN
      * ========================================================= --}}
    <div class="explanation">
        <div class="section-title">Penjelasan Penilaian Kepribadian Siswa:</div>
        <p class="section-text">{{ $current?->penjelasan ?? '-' }}</p>
    </div>

    {{-- =========================================================
      * REKOMENDASI
      * ========================================================= --}}
    <div class="recommendation">
        <div class="section-title">Rekomendasi Pengembangan Siswa:</div>
        <p class="section-text">{{ $current?->rekomendasi ?? '-' }}</p>
    </div>

    {{-- =========================================================
      * SCORE
      * ========================================================= --}}
    <div class="score-wrapper">
        <span class="score">
            Nilai Kepribadian:
            {{ number_format($nilaiAwal, 0, ',', '.') }}
            {{ $operatorNilai }}
            {{ $penambahanTxt }}
            =
            {{ number_format($nilaiAkhir, 2, ',', '.') }}
        </span>
    </div>

    {{-- =========================================================
      * SIGNATURE
      * ========================================================= --}}
    <div class="signature-wrapper">
        <table class="signature-table">
            <tr>
                {{-- LEFT SIGNATURE --}}
                <td class="signature-left">
                    <div class="signature-date">Mengetahui</div>
                    <div class="signature-position">{{ $jabatanKiri }},</div>
                    <div class="signature-name">{{ $namaKiri }}</div>
                    <div class="signature-detail">{{ $formatTtd($pangkatKiri, $nrpKiri) }}</div>
                </td>

                {{-- RIGHT SIGNATURE --}}
                <td class="signature-right">
                    <div class="signature-date">{{ $tanggalCetak }}</div>
                    <div class="signature-position">Penilai,</div>
                    <div class="signature-name">{{ $namaKanan }}</div>
                    <div class="signature-detail">{{ $formatTtd($pangkatKanan, $nrpKanan) }}</div>
                </td>
            </tr>
        </table>
    </div>

</div>

</body>
</html>
