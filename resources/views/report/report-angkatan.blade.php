@extends('layouts.app')
@section('page-title', isset($title) ? $title : 'Report')

@section('topbar-actions')
@if($type === 'NPA')
<a href="{{ route('nilai-akademik.manual.form', ['angkatan_id'=>$angkatanId]) }}" class="btn btn-primary btn-sm">✏️ Input</a>
<a href="{{ route('nilai-akademik.import.form') }}" class="btn btn-outline btn-sm">📥 Upload</a>
<a href="{{ route('report.npa.ekspor', ['angkatan_id'=>$angkatanId]) }}" class="btn btn-success btn-sm">⬇ Ekspor</a>
<a href="{{ route('report.npa.cetak', ['angkatan_id'=>$angkatanId]) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak Laporan</a>
@elseif($type === 'NPS')
<a href="{{ route('nilai-samapta.manual.form', ['angkatan_id'=>$angkatanId,'putaran_label'=>$putaranLabel]) }}" class="btn btn-primary btn-sm">✏️ Input Manual</a>
<a href="{{ route('nilai-samapta.template', ['angkatan_id'=>$angkatanId,'putaran_label'=>$putaranLabel]) }}" class="btn btn-outline btn-sm">📥 Template</a>
<a href="{{ route('report.nps.ekspor', ['angkatan_id'=>$angkatanId,'putaran_label'=>$putaranLabel]) }}" class="btn btn-success btn-sm">⬇ Ekspor</a>
<a href="{{ route('report.nps.cetak', ['angkatan_id'=>$angkatanId,'putaran_label'=>$putaranLabel]) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak Laporan</a>
@endif
@endsection

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">📊 {{ $title }}</h2>
    <p style="font-size:12px;color:#888">Data nilai {{ strtolower($type) }} per angkatan</p>
  </div>
</div>

@php
  // Revisi 26 Agustus 2026: pada NPS hasil hanya tampil setelah tombol
  // "Tampilkan" ditekan (bukan auto-submit saat dropdown diganti).
  $isNPS    = ($type === 'NPS');
  $autoSub  = $isNPS ? '' : 'this.form.submit()';
  $showResult = !$isNPS || ($submitted ?? false);
@endphp

{{-- Filter --}}
<div class="card" style="margin-bottom:14px">
  <form method="GET" action="{{ request()->url() }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label style="font-size:11px;margin-bottom:3px">Sekolah</label>
      <select name="skadik_id" onchange="{{ $autoSub }}">
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $sk->id==$skadikId?'selected':'' }}>{{ $sk->nama }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Angkatan</label>
      <select name="angkatan_id" onchange="{{ $autoSub }}">
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id==$angkatanId?'selected':'' }}>Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})</option>
        @endforeach
      </select>
    </div>
    @if($type === 'NPS')
    <div>
      <label style="font-size:11px;margin-bottom:3px">Putaran</label>
      <select name="putaran_label">
        @foreach($putaranList as $pl)
          <option value="{{ $pl }}" {{ $pl==$putaranLabel?'selected':'' }}>{{ $pl }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <button type="submit" name="tampilkan" value="1" class="btn btn-primary btn-sm" style="height:38px">🔍 Tampilkan</button>
    </div>
    @endif
  </form>
</div>

@if(!$angkatan)
  <div class="empty-state">Pilih angkatan untuk melihat report.</div>
@elseif($isNPS && !$showResult)
  {{-- NPS: belum klik Tampilkan --}}
  <div class="card">
    <div class="empty-state" style="padding:48px">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:40px;height:40px;margin:0 auto 12px;opacity:.4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <div style="font-size:15px;font-weight:500;color:#888;margin-top:8px">
        Pilih <strong>Sekolah</strong>, <strong>Angkatan</strong> &amp; <strong>Putaran</strong>, lalu klik tombol
        <strong style="color:#4f46e5">🔍 Tampilkan</strong> untuk memuat hasil Report NPS.
      </div>
    </div>
  </div>
@else
  {{-- Upload Area (NPS only) --}}
  @if($type === 'NPS')
  <div class="card" style="margin-bottom:16px;border:2px dashed #d0d0d8">
    <div class="card-title">📄 Upload Data NPS (Bulk Import)</div>
    <div style="font-size:12px;color:#888;margin-bottom:12px">
      1. <a href="{{ route('nilai-samapta.template', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" style="color:#059669;font-weight:600">Unduh Template</a>
      &nbsp;→&nbsp; 2. Copy data Anda ke kolom D–H &nbsp;→&nbsp; 3. Upload file yang sudah diisi
    </div>
    <form method="POST" action="{{ route('nilai-samapta.import') }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <div class="form-group" style="margin-bottom:0;min-width:220px">
        <label style="font-size:11px">File Excel (.xlsx)</label>
        <input type="file" name="file" accept=".xlsx,.xls" required>
      </div>
      <div class="form-group" style="margin-bottom:0;min-width:170px">
        <label style="font-size:11px">Simpan ke Putaran</label>
        <input type="text" name="putaran_label" value="{{ $putaranLabel }}" placeholder="Putaran 1">
        <span style="font-size:10px;color:#aaa">Ubah nama putaran (opsional)</span>
      </div>
      <button type="submit" class="btn btn-success btn-sm">📤 Upload</button>
    </form>
    <div style="font-size:11px;color:#aaa;margin-top:8px">
      Catatan: semua nilai diisi manual. Tidak ada perhitungan otomatis. Data untuk putaran yang sama akan menimpa yang lama.
    </div>
  </div>
  @endif

  @php
    // Revisi 26 Agustus 2026 (bugfix 500): hitung berapa peserta yang
    // BENAR-BENAR sudah punya nilai NPS pada putaran terpilih.
    // Bila 0 → jangan render metrik/tabel (sebelumnya crash 500 karena
    // objek placeholder tidak punya properti "predikat"), tapi tampilkan
    // pesan "data masih kosong" + popup.
    $jumlahNilaiNPS = $isNPS ? $data->filter(function ($d) {
        return ($d->nilai_akhir ?? null) !== null || ($d->nilai_konversi ?? null) !== null;
    })->count() : 0;
  @endphp

  @if($data->count() === 0)
  <div class="empty-state card">Belum ada peserta pada angkatan ini — data {{ strtolower($type) }} belum bisa ditampilkan.</div>
  @elseif($isNPS && $jumlahNilaiNPS === 0)
  {{-- NPS: peserta ada, tapi belum ada SATU PUN nilai utk putaran terpilih --}}
  <div class="card" style="border:1px dashed #fdba74;background:#fff7ed">
    <div class="empty-state" style="padding:40px">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:40px;height:40px;margin:0 auto 12px;color:#ea580c"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <div style="font-size:15px;font-weight:600;color:#c2410c;margin-top:8px">⚠️ Belum ada data NPS — data masih kosong</div>
      <div style="font-size:13px;color:#888;margin-top:6px;line-height:1.6">
        Belum ada nilai Samapta untuk <strong>{{ $putaranLabel }}</strong> pada
        <strong>Angkatan {{ $angkatan->nomor_angkatan }}</strong>
        ({{ $data->count() }} peserta menunggu input).<br>
        Silakan input manual atau upload data melalui panel
        <strong>“Upload Data NPS (Bulk Import)”</strong> di atas.
      </div>
    </div>
  </div>
  @push('scripts')
  <script>alert('⚠️ Belum ada data NPS!\n\nData nilai Samapta untuk "{{ addslashes($putaranLabel) }}" pada Angkatan {{ $angkatan->nomor_angkatan }} masih kosong.\nSilakan input manual atau upload terlebih dahulu.');</script>
  @endpush
  @else
  {{-- Metrik --}}
  @if($type === 'NPA')
  <div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card"><div class="metric-label">Total Peserta</div><div class="metric-value">{{ $data->count() }}</div></div>
    <div class="metric-card"><div class="metric-label">Rata-rata NPA</div><div class="metric-value" style="color:#059669">{{ round($data->avg('npa') ?? 0, 2) }}</div></div>
    <div class="metric-card"><div class="metric-label">NPA Tertinggi</div><div class="metric-value" style="color:#4f46e5">{{ round($data->max('npa') ?? 0, 2) }}</div></div>
    <div class="metric-card"><div class="metric-label">NPA Terendah</div><div class="metric-value" style="color:#dc2626">{{ round($data->min('npa') ?? 0, 2) }}</div></div>
  </div>
  @elseif($type === 'NPS')
  <div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card"><div class="metric-label">Total Peserta</div><div class="metric-value">{{ $data->count() }}</div></div>
    <div class="metric-card"><div class="metric-label">Rata-rata NPS</div><div class="metric-value" style="color:#ea580c">{{ round($data->avg('nilai_akhir') ?? 0, 2) }}</div></div>
    <div class="metric-card"><div class="metric-label">NPS Tertinggi</div><div class="metric-value" style="color:#4f46e5">{{ round($data->max('nilai_akhir') ?? 0, 2) }}</div></div>
    <div class="metric-card"><div class="metric-label">NPS Terendah</div><div class="metric-value" style="color:#dc2626">{{ round($data->min('nilai_akhir') ?? 0, 2) }}</div></div>
  </div>
  @endif

  {{-- Tabel --}}
  <div class="card" style="padding:0;overflow:hidden">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Rank</th>
            <th>NRP</th>
            <th>Pangkat</th>
            <th>Nama</th>
            @if($type === 'NPA' && $mataPelajaran->count() > 0)
              @foreach($mataPelajaran as $mp)
              <th style="text-align:center;font-size:8px;white-space:normal;max-width:85px">
                {{ $mp->nama }}
                <div style="font-weight:400;font-size:7px;color:#888;line-height:1.4;margin-top:2px">
                  JP:{{ $mp->jp }} · B:{{ $mp->bobot }} · HN:{{ $mp->harga_nilai_calc }}
                </div>
              </th>
              @endforeach
              <th style="text-align:center;font-size:8px;background:#eef2ff">Σ(MP×HN)</th>
              <th style="text-align:right">NPA</th>
            @elseif($type === 'NPA')
              <th style="text-align:right">Jumlah Nilai</th>
              <th style="text-align:right">NPA</th>
            @elseif($type === 'NPS')
              <th style="text-align:center">Jarak Lari (m)</th>
              <th style="text-align:center">Nilai Lari (Garjas A)</th>
              <th style="text-align:center">Garjas B</th>
              <th style="text-align:center">Nilai Konversi</th>
              {{-- Revisi 26 Agustus 2026: kategori (Baik/Cukup/Kurang) dihitung
                   dari NILAI KONVERSI, bukan nilai akhir --}}
              <th style="text-align:center">Kategori</th>
              <th style="text-align:right">NPS</th>
            @endif
            <th style="min-width:120px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($data as $idx => $d)
          @php $peserta = $d->peserta; @endphp
          <tr>
            <td style="text-align:center;font-weight:600">{{ $idx + 1 }}</td>
            <td style="font-size:12px;color:#888">{{ $peserta?->nrp ?? '-' }}</td>
            <td>{{ $peserta?->pangkat ?? '-' }}</td>
            <td><strong>{{ $peserta?->nama ?? 'Peserta Dihapus' }}</strong></td>
            @if($type === 'NPA')
              @if($mataPelajaran->count() > 0)
                @php
                  $detailNilai = is_array($d->detail_nilai) ? $d->detail_nilai : (json_decode($d->detail_nilai, true) ?: []);
                  $sumMPHN = 0;
                  $totalHNAll = $mataPelajaran->sum(fn($mp) => $mp->harga_nilai_calc);
                @endphp
                @foreach($mataPelajaran as $mpIdx => $mp)
                @php
                  $valMP = $detailNilai[$mpIdx] ?? 0;
                  $hnMP = $mp->harga_nilai_calc;
                  $sumMPHN += $valMP * $hnMP;
                @endphp
                <td style="text-align:center;font-size:11px">{{ $valMP ?: '-' }}</td>
                @endforeach
                <td style="text-align:center;font-size:11px;background:#f0f0ff;font-weight:600">{{ number_format(round($sumMPHN, 2), 2, ',', '.') }}</td>
              @else
                <td style="text-align:right">{{ $d->jumlah_nilai ?? '-' }}</td>
              @endif
              <td style="text-align:right">
                @if($d->npa !== null)
                  <span style="font-weight:700;font-size:15px;color:#059669">{{ $d->npa }}</span>
                @else
                  <span style="color:#d97706;font-size:11px;font-weight:600">⚠ Belum</span>
                @endif
              </td>
            @elseif($type === 'NPS')
              {{-- Guard: objek placeholder (peserta belum dinilai) tidak punya
                   properti "predikat" — tanpa isset() akan ErrorException 500 --}}
              @php
                $predikatNPS = isset($d->predikat) ? $d->predikat : ['label' => '-', 'color' => '#999', 'icon' => ''];
              @endphp
              <td style="text-align:center">{{ $d->jarak_lari ?? '-' }}</td>
              <td style="text-align:center">{{ $d->nilai_lari ?? '-' }}</td>
              <td style="text-align:center">{{ $d->garjas_b_nilai ?? '-' }}</td>
              <td style="text-align:center;font-weight:600;color:{{ ($d->nilai_konversi ?? null) !== null ? $predikatNPS['color'] : '#888' }}">{{ $d->nilai_konversi ?? '-' }}</td>
              <td style="text-align:center">
                @if(($d->nilai_konversi ?? null) !== null)
                  <span style="font-size:11px;font-weight:700;color:{{ $predikatNPS['color'] }}">{{ $predikatNPS['icon'] }} {{ $predikatNPS['label'] }}</span>
                @else
                  <span style="color:#d97706;font-size:11px;font-weight:600">⚠ Belum</span>
                @endif
              </td>
              <td style="text-align:right">
                <span style="font-weight:700;font-size:15px">{{ $d->nilai_akhir ?? '-' }}</span>
              </td>
            @endif
            <td>
              <div style="display:flex;gap:4px">
                @if($type === 'NPA')
                  @php $hasNPA = isset($d->npa) && $d->npa !== null; @endphp
                  @if($hasNPA)
                  <a href="{{ route('nilai-akademik.edit', ['angkatan_id'=>$angkatanId,'peserta_id'=>$d->peserta_didik_id]) }}" class="btn btn-sm btn-primary" title="Edit NPA">✏️ Edit</a>
                  @else
                  <a href="{{ route('nilai-akademik.manual.form', ['angkatan_id'=>$angkatanId]) }}" class="btn btn-sm btn-warning" title="Input NPA">📝 Input</a>
                  @endif
                @endif
                @if($type === 'NPS')
                  <a href="{{ route('nilai-samapta.edit', ['angkatan_id'=>$angkatanId,'peserta_id'=>$d->peserta_didik_id,'putaran_label'=>$putaranLabel]) }}" class="btn btn-sm btn-primary" title="Edit NPS">✏️</a>
                @endif
                <a href="{{ route('report.individu', ['angkatan_id'=>$angkatanId,'peserta_id'=>$d->peserta_didik_id]) }}" class="btn btn-sm btn-outline" title="Laporan Individual">📋</a>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Hapus data NPS --}}
  @if($type === 'NPS')
  <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
    <form method="POST" action="{{ route('nilai-samapta.destroy') }}" onsubmit="return confirm('Hapus SEMUA data NPS Putaran {{ $putaranLabel }} pada angkatan ini?')">
      @csrf @method('DELETE')
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <input type="hidden" name="putaran_label" value="{{ $putaranLabel }}">
      <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus Putaran Ini</button>
    </form>
    <form method="POST" action="{{ route('nilai-samapta.destroy') }}" onsubmit="return confirm('Hapus SEMUA data NPS angkatan ini (SEMUA putaran)?')">
      @csrf @method('DELETE')
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <input type="hidden" name="putaran_label" value="all">
      <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus Semua Putaran</button>
    </form>
  </div>
  @endif
  @endif {{-- end data check --}}
@endif {{-- end angkatan check --}}
@endsection
