@extends('layouts.app')
@section('page-title', 'Nilai Akademik (NPA)')

@section('topbar-actions')
<a href="{{ route('nilai-akademik.ekspor', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm">📥 Ekspor</a>
<a href="{{ route('report.npa.cetak', ['angkatan_id' => $angkatanId]) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak Laporan</a>
@endsection

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">📊 Nilai Prestasi Akademik (NPA)</h2>
    <p style="font-size:12px;color:#888">Data nilai akademik peserta didik dari hasil evaluasi sekolah kejuruan</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ route('report.npa.cetak', ['angkatan_id' => $angkatanId]) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak NPA</a>
    <a href="{{ route('nilai-akademik.ekspor', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm">📥 Ekspor</a>
    <a href="{{ route('nilai-akademik.manual.form', ['angkatan_id' => $angkatanId]) }}" class="btn btn-warning btn-sm">📝 Input Manual</a>
    <a href="{{ route('nilai-akademik.import.form') }}" class="btn btn-success btn-sm">📄 Impor Excel</a>
  </div>
</div>

{{-- Filter --}}
<div class="filter-bar">
  <div class="form-group">
    <label>Sekolah</label>
    <select onchange="this.form.submit()" name="skadik_id" form="filterForm">
      @foreach($allSkadik as $s)
        <option value="{{ $s->id }}" {{ $skadikId == $s->id ? 'selected' : '' }}>{{ $s->nama }} — {{ $s->lemdik?->wingdik }}</option>
      @endforeach
    </select>
  </div>
  <div class="form-group">
    <label>Angkatan</label>
    <form id="filterForm" method="GET" action="{{ route('nilai-akademik.index') }}" style="display:flex;gap:8px;align-items:end">
      <select name="angkatan_id" onchange="this.form.submit()">
        @foreach($allAngkatan as $a)
          <option value="{{ $a->id }}" {{ $angkatanId == $a->id ? 'selected' : '' }}>Angkatan {{ $a->nomor_angkatan }} ({{ $a->tahun_masuk }})</option>
        @endforeach
      </select>
    </form>
  </div>
</div>

@if(!$angkatan)
  <div class="empty-state">
    <p>Pilih angkatan untuk melihat data nilai akademik.</p>
  </div>
@else
  <div class="card" style="margin-bottom:16px">
    <div style="font-size:12px;color:#888">
      <strong>{{ $angkatan->skadik->lemdik?->wingdik ?? '' }}</strong> —
      {{ $angkatan->skadik->nama_singkat ?? $angkatan->skadik->nama ?? '' }} |
      Angkatan {{ $angkatan->nomor_angkatan }} ({{ $angkatan->tahun_masuk }})
      @if($angkatan->jurusan) | {{ $angkatan->jurusan }} @endif
      @php $belumNPA = $data->where('sudah_input', false)->count(); @endphp
      @if($belumNPA > 0)
        <span style="color:#dc2626;font-weight:600;margin-left:6px">⚠ {{ $belumNPA }} peserta belum diberi NPA</span>
      @endif
    </div>
  </div>

  @if($data->count() === 0)
    <div class="empty-state card">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      <p>Belum ada peserta pada angkatan ini.</p>
      <p style="font-size:12px;margin-top:4px">Tambahkan peserta terlebih dahulu di menu Peserta Didik.</p>
      <a href="{{ route('peserta.index', ['angkatan_id' => $angkatanId]) }}" class="btn btn-primary btn-sm" style="margin-top:12px">Kelola Peserta</a>
    </div>
  @else

  {{-- Metrics --}}
  <div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card">
      <div class="metric-label">Total Peserta</div>
      <div class="metric-value">{{ $data->count() }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Rata-rata NPA</div>
      <div class="metric-value" style="color:#059669">{{ ($data->avg('npa') !== null && $data->avg('npa') > 0) ? round($data->avg('npa'), 2) : '—' }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Tertinggi</div>
      <div class="metric-value" style="color:#4f46e5">{{ $data->max('npa') ? round($data->max('npa'), 2) : '—' }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Terendah</div>
      <div class="metric-value" style="color:#dc2626">{{ $data->where('npa','>',0)->min('npa') ? round($data->where('npa','>',0)->min('npa'), 2) : '—' }}</div>
    </div>
  </div>

  {{-- Tabel --}}
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Rank</th>
            <th>NRP</th>
            <th>Pangkat</th>
            <th>Nama</th>
            <th style="text-align:right">Jumlah Nilai</th>
            <th style="text-align:right">NPA</th>
            <th style="width:60px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($data as $idx => $d)
            @php $hasNPA = !empty($d->npa) || (isset($d->sudah_input) && $d->sudah_input); @endphp
            <tr @if(!$hasNPA) style="background:#fffbeb" @endif>
              <td style="text-align:center">
                @if($hasNPA)
                  @if($idx === 0) 🥇 @elseif($idx === 1) 🥈 @elseif($idx === 2) 🥉 @else {{ $idx + 1 }} @endif
                @else
                  <span style="color:#d97706">—</span>
                @endif
              </td>
              <td>{{ $d->peserta->nrp }}</td>
              <td>{{ $d->peserta->pangkat }}</td>
              <td><strong>{{ $d->peserta->nama }}</strong></td>
              <td style="text-align:right">{{ $d->jumlah_nilai ?? '-' }}</td>
              <td style="text-align:right">
                @if($hasNPA && $d->npa !== null)
                  <span style="font-weight:600;font-size:15px;color:{{ $d->npa >= 85 ? '#059669' : ($d->npa >= 70 ? '#2563eb' : '#dc2626') }}">
                    {{ $d->npa }}
                  </span>
                @else
                  <span style="color:#d97706;font-size:11px;font-weight:600">⚠ Belum input</span>
                @endif
              </td>
              <td>
                @if($hasNPA)
                  <a href="{{ route('nilai-akademik.edit', ['angkatan_id' => $angkatanId, 'peserta_id' => $d->peserta_didik_id]) }}" class="btn btn-sm btn-outline" title="Edit">✏️</a>
                @else
                  <a href="{{ route('nilai-akademik.manual.form', ['angkatan_id' => $angkatanId]) }}" class="btn btn-sm btn-warning" title="Input NPA">📝 Input</a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <form method="POST" action="{{ route('nilai-akademik.destroy') }}" style="margin-top:16px" onsubmit="return confirm('Hapus semua nilai akademik angkatan ini?')">
      @csrf @method('DELETE')
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus Semua Data</button>
    </form>
    <form method="POST" action="{{ route('nilai-akademik.recalculate') }}" style="margin-top:8px" onsubmit="return confirm('Hitung ulang semua NPA angkatan ini dengan rumus: NPA = Σ(MP × HN) / Σ(HN)?')">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <button type="submit" class="btn btn-warning btn-sm">🔄 Hitung Ulang NPA</button>
    </form>
  </div>
  @endif
@endif

@endsection
