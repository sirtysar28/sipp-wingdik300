@extends('layouts.app')
@section('page-title', 'Report NPK — Rekap & Analisis Kepribadian')

@section('topbar-actions')
<a href="{{ route('kepribadian.index') }}" class="btn btn-primary btn-sm">✏️ Input Manual</a>
<a href="{{ route('kepribadian.import.form') }}" class="btn btn-outline btn-sm">📥 Bulk Upload</a>
<a href="{{ route('report.npk.ekspor', ['angkatan_id'=>$angkatanId]) }}" class="btn btn-success btn-sm">⬇ Ekspor NPK</a>
<a href="{{ route('report.npk.cetak', ['angkatan_id'=>$angkatanId]) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak Laporan</a>
@endsection

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">📊 Report NPK — Rekap & Analisis Kepribadian</h2>
    <p style="font-size:12px;color:#888">Rekap nilai kepribadian semua periode per angkatan</p>
  </div>
</div>

{{-- Filter --}}
<div class="card" style="margin-bottom:14px">
  <form method="GET" action="{{ route('report.npk') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label style="font-size:11px;margin-bottom:3px">Sekolah</label>
      <select name="skadik_id" onchange="this.form.submit()">
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $sk->id==$skadikId?'selected':'' }}>{{ $sk->nama }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Angkatan</label>
      <select name="angkatan_id" onchange="this.form.submit()">
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id==$angkatanId?'selected':'' }}>Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})</option>
        @endforeach
      </select>
    </div>
  </form>
</div>

@if(!$angkatan)
  <div class="empty-state">Pilih angkatan untuk melihat report.</div>
@else

{{-- Metrik --}}
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-label">Total Peserta</div><div class="metric-value">{{ $data->count() }}</div></div>
  <div class="metric-card"><div class="metric-label">Total Periode</div><div class="metric-value">{{ $periodes->count() }}</div></div>
  <div class="metric-card"><div class="metric-label">Rata-rata Keseluruhan</div><div class="metric-value" style="color:#2563eb">{{ $data->avg('rata_rata') ? round($data->avg('rata_rata'),2) : '—' }}</div></div>
  <div class="metric-card"><div class="metric-label">NPK Tertinggi</div><div class="metric-value" style="color:#059669">{{ $data->max('rata_rata') ? round($data->max('rata_rata'),2) : '—' }}</div></div>
</div>

{{-- Tabel --}}
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Nama</th>
          <th>NRP</th>
          @foreach($periodes as $per)
            <th style="text-align:center;min-width:80px">{{ $per->label }}</th>
          @endforeach
          <th style="text-align:center;background:#f0f0ff;min-width:80px">Rata-rata</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($data as $i => $row)
        <tr>
          <td style="text-align:center;font-weight:600">{{ $i + 1 }}</td>
          <td><strong>{{ $row['peserta']->nama }}</strong></td>
          <td style="font-size:12px;color:#888">{{ $row['peserta']->nrp }}</td>
          @foreach($periodes as $per)
            @php $n = $row['nilai_per_periode'][$per->id]['nilai'] ?? null; @endphp
            <td style="text-align:center">
              @if($n !== null)
                @php
                  $color = $n >= 78 ? '#059669' : ($n >= 76 ? '#2563eb' : ($n >= 75 ? '#d97706' : '#dc2626'));
                  $bg = $n >= 78 ? '#ecfdf5' : ($n >= 76 ? '#eff6ff' : ($n >= 75 ? '#fffbeb' : '#fef2f2'));
                @endphp
                <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:12px;font-weight:500;background:{{ $bg }};color:{{ $color }}">{{ round($n,2) }}</span>
              @else
                <span style="color:#ddd">—</span>
              @endif
            </td>
          @endforeach
          <td style="text-align:center;background:#f0f0ff">
            <strong style="font-size:14px;color:#2563eb">{{ $row['rata_rata'] ?? '—' }}</strong>
          </td>
          <td>
            <a href="{{ route('report.individu', ['angkatan_id'=>$angkatanId,'peserta_id'=>$row['peserta']->id]) }}" class="btn btn-sm btn-outline" title="Laporan Individual">📋</a>
          </td>
        </tr>
        @endforeach
      </tbody>
      @if($data->count() > 1)
      <tfoot>
        <tr style="background:#f0f0ff">
          <td colspan="3" style="font-weight:600;font-size:12px;color:#2563eb">Rata-rata Angkatan</td>
          @foreach($periodes as $per)
            @php
              $avgPer = $data->map(fn($b) => $b['nilai_per_periode'][$per->id]['nilai'] ?? null)->filter()->avg();
            @endphp
            <td style="text-align:center;font-weight:600;color:#2563eb">{{ $avgPer ? round($avgPer,2) : '—' }}</td>
          @endforeach
          <td style="text-align:center;font-weight:700;color:#2563eb;font-size:14px">{{ $data->avg('rata_rata') ? round($data->avg('rata_rata'),2) : '—' }}</td>
          <td></td>
        </tr>
      </tfoot>
      @endif
    </table>
  </div>
</div>
@endif
@endsection
