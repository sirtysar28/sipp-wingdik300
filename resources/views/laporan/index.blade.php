@extends('layouts.app')

@section('page-title', 'Cetak Laporan')

@section('topbar-actions')
@if($data->count() > 0)
<a href="{{ route('laporan.ekspor.semua', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm">📥 Ekspor Excel</a>
@endif
@endsection

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">🖨️ Cetak Laporan</h2>
    <p style="font-size:12px;color:#888">
      Pilih jenis laporan yang ingin dicetak
    </p>
  </div>
</div>

{{-- Filter --}}
<div class="filter-bar">
  <div class="form-group">
    <label>Sekolah</label>
    <select onchange="this.form.submit()" name="skadik_id" form="filterForm">
      @foreach($allSkadik as $s)
        <option value="{{ $s->id }}" {{ $skadikId == $s->id ? 'selected' : '' }}>{{ $s->nama }}</option>
      @endforeach
    </select>
  </div>
  <div class="form-group">
    <label>Angkatan</label>
    <form id="filterForm" method="GET" action="{{ route('laporan.index') }}">
      <select name="angkatan_id" onchange="this.form.submit()">
        @foreach($allAngkatan as $a)
          <option value="{{ $a->id }}" {{ $angkatanId == $a->id ? 'selected' : '' }}>Angkatan {{ $a->nomor_angkatan }} ({{ $a->tahun_masuk }})</option>
        @endforeach
      </select>
    </form>
  </div>
</div>

@if(!$angkatan)
  <div class="empty-state"><p>Pilih angkatan untuk melihat laporan.</p></div>
@else

  {{-- Info Card --}}
  <div class="card" style="margin-bottom:16px;background:linear-gradient(135deg,#eef2ff,#f5f3ff)">
    <div style="display:flex;align-items:center;gap:12px">
      <div style="font-size:28px">📋</div>
      <div>
        <div style="font-size:14px;font-weight:600;color:#1a1a2e">Cetak Laporan — Angkatan {{ $angkatan->nomor_angkatan }}</div>
        <div style="font-size:12px;color:#6b7280">
          {{ $angkatan->skadik?->lemdik?->wingdik ?? '' }} — {{ $angkatan->skadik?->nama ?? '' }}
          @if($angkatan->jurusan) | {{ $angkatan->jurusan }} @endif
        </div>
      </div>
    </div>
  </div>

  {{-- 4 Button Cetak Laporan --}}
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px">

    {{-- NPA --}}
    <div class="card" style="text-align:center;border-left:4px solid #059669;cursor:pointer" onclick="window.open('{{ route('report.npa.cetak', ['angkatan_id' => $angkatanId]) }}')">
      <div style="font-size:32px;margin-bottom:8px">📗</div>
      <div style="font-size:15px;font-weight:600;color:#059669">Cetak NPA</div>
      <div style="font-size:11px;color:#888;margin-top:4px">Nilai Prestasi Akademik</div>
      <div style="margin-top:12px">
        <a href="{{ route('report.npa.cetak', ['angkatan_id' => $angkatanId]) }}" class="btn btn-primary btn-sm" target="_blank" onclick="event.stopPropagation()">🖨️ Cetak</a>
        <a href="{{ route('report.npa', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm" onclick="event.stopPropagation()">📊 Lihat Data</a>
      </div>
    </div>

    {{-- NPK --}}
    <div class="card" style="text-align:center;border-left:4px solid #2563eb;cursor:pointer" onclick="window.open('{{ route('report.npk.cetak', ['angkatan_id' => $angkatanId]) }}')">
      <div style="font-size:32px;margin-bottom:8px">📘</div>
      <div style="font-size:15px;font-weight:600;color:#2563eb">Cetak NPK</div>
      <div style="font-size:11px;color:#888;margin-top:4px">Nilai Prestasi Kepribadian</div>
      <div style="margin-top:12px">
        <a href="{{ route('report.npk.cetak', ['angkatan_id' => $angkatanId]) }}" class="btn btn-primary btn-sm" target="_blank" onclick="event.stopPropagation()">🖨️ Cetak</a>
        <a href="{{ route('report.npk', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm" onclick="event.stopPropagation()">📊 Lihat Data</a>
      </div>
    </div>

    {{-- NPS --}}
    <div class="card" style="text-align:center;border-left:4px solid #ea580c;cursor:pointer" onclick="window.open('{{ route('report.nps.cetak', ['angkatan_id' => $angkatanId]) }}')">
      <div style="font-size:32px;margin-bottom:8px">📙</div>
      <div style="font-size:15px;font-weight:600;color:#ea580c">Cetak NPS</div>
      <div style="font-size:11px;color:#888;margin-top:4px">Nilai Prestasi Samapta</div>
      <div style="margin-top:12px">
        <a href="{{ route('report.nps.cetak', ['angkatan_id' => $angkatanId]) }}" class="btn btn-primary btn-sm" target="_blank" onclick="event.stopPropagation()">🖨️ Cetak</a>
        <a href="{{ route('report.nps', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm" onclick="event.stopPropagation()">📊 Lihat Data</a>
      </div>
    </div>

    {{-- NPP --}}
    <div class="card" style="text-align:center;border-left:4px solid #4f46e5;cursor:pointer" onclick="window.open('{{ route('laporan.cetak.semua', ['angkatan_id' => $angkatanId]) }}')">
      <div style="font-size:32px;margin-bottom:8px">📕</div>
      <div style="font-size:15px;font-weight:600;color:#4f46e5">Cetak NPP</div>
      <div style="font-size:11px;color:#888;margin-top:4px">Nilai Prestasi Pendidikan</div>
      <div style="margin-top:12px">
        <a href="{{ route('laporan.cetak.semua', ['angkatan_id' => $angkatanId]) }}" class="btn btn-smart btn-sm" target="_blank" onclick="event.stopPropagation()">🖨️ Cetak</a>
        <a href="{{ route('report.npp', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm" onclick="event.stopPropagation()">📊 Lihat Data</a>
      </div>
    </div>

  </div>

  {{-- Tabel peserta + cetak individu --}}
  @if($data->count() > 0)
  <div class="card">
    <div class="card-title">📋 Daftar Peserta — Cetak Individual</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="text-align:center;width:50px">Rank</th>
            <th>Nama</th>
            <th>Pangkat</th>
            <th>NRP</th>
            <th style="text-align:right">NPP</th>
            <th style="text-align:center">Predikat</th>
            <th style="text-align:center">Keterangan</th>
            <th style="text-align:center">Cetak</th>
          </tr>
        </thead>
        <tbody>
          @foreach($data as $d)
            @php
              $ket = $d->nilai_akhir >= 85 ? 'Sangat Baik' : ($d->nilai_akhir >= 75 ? 'Baik' : ($d->nilai_akhir >= 65 ? 'Cukup' : ($d->nilai_akhir >= 55 ? 'Kurang' : 'Sangat Kurang')));
              $ketColor = $d->nilai_akhir >= 85 ? '#059669' : ($d->nilai_akhir >= 75 ? '#2563eb' : ($d->nilai_akhir >= 65 ? '#d97706' : '#dc2626'));
            @endphp
            <tr>
              <td style="text-align:center;font-weight:600">
                @if($d->rank === 1) 🥇 @elseif($d->rank === 2) 🥈 @elseif($d->rank === 3) 🥉 @else {{ $d->rank }} @endif
              </td>
              <td><strong>{{ $d->peserta->nama }}</strong></td>
              <td>{{ $d->peserta->pangkat }}</td>
              <td>{{ $d->peserta->nrp }}</td>
              <td style="text-align:right">
                <span style="font-weight:700;font-size:16px;color:#4f46e5">{{ $d->nilai_akhir }}</span>
              </td>
              <td style="text-align:center">
                <span class="badge badge-{{ $d->nilai_akhir >= 85 ? 'green' : ($d->nilai_akhir >= 75 ? 'blue' : ($d->nilai_akhir >= 65 ? 'amber' : 'red')) }}">
                  {{ $d->predikat_huruf }}
                </span>
              </td>
              <td style="text-align:center;color:{{ $ketColor }};font-weight:500">{{ $ket }}</td>
              <td style="text-align:center">
                <a href="{{ route('laporan.cetak.individu', ['angkatan_id' => $angkatanId, 'peserta_id' => $d->peserta_didik_id]) }}" class="btn btn-primary btn-sm" target="_blank">
                  🖨️ Cetak NPP
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @else
    <div class="empty-state card">
      <p>Belum ada data kompilasi nilai. Proses kompilasi terlebih dahulu.</p>
      <a href="{{ route('report.npp', ['angkatan_id' => $angkatanId]) }}" class="btn btn-primary btn-sm" style="margin-top:12px">→ Proses Kompilasi NPP</a>
    </div>
  @endif

@endif

@endsection
