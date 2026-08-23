@extends('layouts.app')
@section('page-title', 'Kompilasi Nilai')

@section('topbar-actions')
@if($data->count() > 0)
<a href="{{ route('kompilasi.ekspor', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline btn-sm">📥 Ekspor</a>
<a href="{{ route('laporan.cetak.semua', ['angkatan_id' => $angkatanId]) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak Laporan</a>
@endif
@endsection

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">🔢 Kompilasi Nilai Prestasi Pendidikan</h2>
    <p style="font-size:12px;color:#888">
      NPP = 70% Akademik + 20% Kepribadian + 10% Samapta
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
    <form id="filterForm" method="GET" action="{{ route('kompilasi.index') }}">
      <select name="angkatan_id" onchange="this.form.submit()">
        @foreach($allAngkatan as $a)
          <option value="{{ $a->id }}" {{ $angkatanId == $a->id ? 'selected' : '' }}>Angkatan {{ $a->nomor_angkatan }} ({{ $a->tahun_masuk }})</option>
        @endforeach
      </select>
    </form>
  </div>
</div>

@if(!$angkatan)
  <div class="empty-state"><p>Pilih angkatan untuk melihat kompilasi nilai.</p></div>
@else

  {{-- Form Proses Kompilasi --}}
  <div class="card" style="margin-bottom:20px;border-left:4px solid #7c3aed">
    <div class="card-title">⚙️ Proses Kompilasi</div>
    <form method="POST" action="{{ route('kompilasi.proses') }}" id="formKompilasi">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end">
        <div class="form-group" style="margin:0">
          <label>Bobot Akademik (%)</label>
          <input type="number" name="bobot_akademik" value="70" min="0" max="100" step="1" id="ba">
        </div>
        <div class="form-group" style="margin:0">
          <label>Bobot Kepribadian (%)</label>
          <input type="number" name="bobot_kepribadian" value="20" min="0" max="100" step="1" id="bk">
        </div>
        <div class="form-group" style="margin:0">
          <label>Bobot Samapta (%)</label>
          <input type="number" name="bobot_samapta" value="10" min="0" max="100" step="1" id="bs">
        </div>
        <button type="submit" class="btn btn-smart" onclick="return confirm('Proses kompilasi nilai? Data sebelumnya akan diperbarui.')">
          🔄 Proses Kompilasi
        </button>
      </div>
      <div id="bobotWarning" style="display:none;margin-top:8px;color:#dc2626;font-size:12px;font-weight:600">
        ⚠️ Total bobot harus 100%!
      </div>
    </form>
  </div>

  {{-- Status Ketersediaan Data --}}
  @if(isset($stats))
  <div style="margin-bottom:16px">
    <div class="card-title" style="margin-bottom:10px">📋 Status Ketersediaan Data</div>
    <div class="metric-grid" style="grid-template-columns:repeat(5,1fr)">
      <div class="metric-card">
        <div class="metric-label">Total Peserta</div>
        <div class="metric-value">{{ $stats['total_peserta'] }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Sudah Kompilasi</div>
        <div class="metric-value" style="color:#059669">{{ $stats['sudah_kompilasi'] }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Belum Ada Akademik</div>
        <div class="metric-value" style="color:{{ $stats['belum_akademik'] > 0 ? '#dc2626' : '#059669' }}">{{ $stats['belum_akademik'] }}</div>
        @if($stats['belum_akademik'] > 0)
        <div class="metric-sub">Input nilai NPA dulu di menu Akademik</div>
        @endif
      </div>
      <div class="metric-card">
        <div class="metric-label">Belum Ada Samapta</div>
        <div class="metric-value" style="color:{{ $stats['belum_samapta'] > 0 ? '#dc2626' : '#059669' }}">{{ $stats['belum_samapta'] }}</div>
        @if($stats['belum_samapta'] > 0)
        <div class="metric-sub">Input nilai NPS dulu di menu Samapta</div>
        @endif
      </div>
      <div class="metric-card">
        <div class="metric-label">Belum Ada Kepribadian</div>
        <div class="metric-value" style="color:{{ $stats['belum_kepribadian'] > 0 ? '#dc2626' : '#059669' }}">{{ $stats['belum_kepribadian'] }}</div>
        @if($stats['belum_kepribadian'] > 0)
        <div class="metric-sub">{{ $stats['belum_kepribadian_info'] }}</div>
        @else
        <div class="metric-sub">Semua sudah punya nilai kepribadian</div>
        @endif
      </div>
    </div>

    {{-- Info card kalau ada data yang belum lengkap --}}
    @if($stats['belum_akademik'] > 0 || $stats['belum_kepribadian'] > 0 || $stats['belum_samapta'] > 0)
    <div class="card" style="margin-top:12px;background:#fffbeb;border-color:#fde68a">
      <div style="font-size:12px;color:#92400e">
        ⚠️ <strong>Perhatian:</strong>
        @if($stats['belum_akademik'] > 0)
          {{ $stats['belum_akademik'] }} peserta <strong>belum memiliki nilai akademik (NPA)</strong>.
          Peserta tersebut akan mendapat NPA = 0 dalam kompilasi.
        @endif
        @if($stats['belum_kepribadian'] > 0)
          {{ $stats['belum_kepribadian'] }} peserta <strong>belum memiliki nilai kepribadian (NPK)</strong>. {{ $stats['belum_kepribadian_info'] }}
          Peserta tersebut akan mendapat NPK = 0 dalam kompilasi.
        @endif
        @if($stats['belum_samapta'] > 0)
          {{ $stats['belum_samapta'] }} peserta <strong>belum memiliki nilai samapta (NPS)</strong>.
          Peserta tersebut akan mendapat NPS = 0 dalam kompilasi.
        @endif
        <br>Pastikan semua data sudah diinput sebelum memproses kompilasi.
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Tabel Kompilasi --}}
  @if($data->count() > 0)
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="text-align:center;width:50px">Rank</th>
            <th>NRP</th>
            <th>Pangkat</th>
            <th>Nama</th>
            <th style="text-align:right">N. Akademik<br><span style="font-weight:400;font-size:9px">({{ $data->first()->bobot_akademik }}%)</span></th>
            <th style="text-align:right">N. Kepribadian<br><span style="font-weight:400;font-size:9px">({{ $data->first()->bobot_kepribadian }}%)</span></th>
            <th style="text-align:right">N. Samapta<br><span style="font-weight:400;font-size:9px">({{ $data->first()->bobot_samapta }}%)</span></th>
            <th style="text-align:right">NPP</th>
            <th style="text-align:center">Predikat</th>
            <th style="text-align:center;width:40px">Laporan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($data as $d)
            <?php
              $bgRow = '';
              if($d->rank === 1) $bgRow = 'background:#fef3c7;';
              elseif($d->rank === 2) $bgRow = 'background:#f3f4f6;';
              elseif($d->rank === 3) $bgRow = 'background:#fef9c3;';
            ?>
            <tr style="{{ $bgRow }}">
              <td style="text-align:center;font-weight:600">
                @if($d->rank === 1) 🥇 @elseif($d->rank === 2) 🥈 @elseif($d->rank === 3) 🥉 @else {{ $d->rank }} @endif
              </td>
              <td>{{ $d->peserta->nrp }}</td>
              <td>{{ $d->peserta->pangkat }}</td>
              <td><strong>{{ $d->peserta->nama }}</strong></td>
              <td style="text-align:right">{{ $d->nilai_akademik }}</td>
              <td style="text-align:right">{{ $d->nilai_kepribadian }}</td>
              <td style="text-align:right">{{ $d->nilai_samapta }}</td>
              <td style="text-align:right">
                <span style="font-weight:700;font-size:16px;color:#4f46e5">
                  {{ $d->nilai_akhir }}
                </span>
              </td>
              <td style="text-align:center">
                <span class="badge badge-{{ $d->nilai_akhir >= 85 ? 'green' : ($d->nilai_akhir >= 75 ? 'blue' : ($d->nilai_akhir >= 65 ? 'amber' : 'red')) }}">
                  {{ $d->predikat_huruf }}
                </span>
              </td>
              <td style="text-align:center">
                <a href="{{ route('laporan.cetak.individu', ['angkatan_id' => $angkatanId, 'peserta_id' => $d->peserta_didik_id]) }}" class="btn btn-sm btn-outline" title="Cetak Laporan" target="_blank">🖨️</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @else
    <div class="empty-state card">
      <p>Belum ada data kompilasi. Klik "Proses Kompilasi" untuk memulai.</p>
    </div>
  @endif

@endif

@section('scripts')
<script>
const ba=document.getElementById('ba'), bk=document.getElementById('bk'), bs=document.getElementById('bs'), warn=document.getElementById('bobotWarning');
function checkBobot(){
  const total=parseFloat(ba.value||0)+parseFloat(bk.value||0)+parseFloat(bs.value||0);
  warn.style.display=(Math.abs(total-100)>0.01)?'block':'none';
}
ba.addEventListener('input',checkBobot);
bk.addEventListener('input',checkBobot);
bs.addEventListener('input',checkBobot);
</script>
@endsection

@endsection
