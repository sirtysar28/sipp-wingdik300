@extends('layouts.app')
@section('title', 'Rekap & Analisis')
@section('page-title', 'Rekap — ' . ($angkatan?->skadik->nama ?? '') . ' Angkt ' . ($angkatan?->nomor_angkatan ?? ''))

@section('topbar-actions')
@if(auth()->user()->isAdminKepribadian() || auth()->user()->canSeeAll())
<a href="{{ route('kepribadian.index') }}" class="btn btn-primary btn-sm">✏️ Input</a>
<a href="{{ route('kepribadian.import.form') }}" class="btn btn-outline btn-sm">📥 Upload</a>
@endif
<a href="{{ route('kepribadian.periode.create') }}" class="btn btn-warning btn-sm">➕ Tambah Periode</a>
<a href="{{ route('kepribadian.aspek') }}" class="btn btn-outline btn-sm">⚙️ Kelola Aspek</a>
<button type="button" class="btn btn-success btn-sm" onclick="eksporRekap()">⬇ Ekspor</button>
<a href="{{ route('report.npk.cetak', ['angkatan_id'=>$angkatan?->id]) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak Laporan</a>
@endsection

@section('content')

{{-- ── FILTER ──────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:16px">
  <form method="GET" action="{{ url()->current() }}" id="filter-form">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:flex-end">

      <div class="form-group" style="margin:0">
        <label>Sekolah</label>
        <select name="skadik_id" onchange="this.form.submit()">
          @foreach($allSkadik as $sk)
            <option value="{{ $sk->id }}" {{ $sk->id == $skadikId ? 'selected' : '' }}>
              {{ $sk->nama }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group" style="margin:0">
        <label>Angkatan</label>
        <select name="angkatan_id" onchange="this.form.submit()">
          @foreach($allAngkatan as $ang)
            <option value="{{ $ang->id }}" {{ $ang->id == $angkatan?->id ? 'selected' : '' }}>
              Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group" style="margin:0">
        <label>Dari Periode</label>
        <select name="periode_from" onchange="document.getElementById('filter-form').submit()">
          @foreach($allPeriode as $per)
            <option value="{{ $per->id }}" {{ $per->id == $periodeFrom ? 'selected' : '' }}>
              {{ $per->label }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group" style="margin:0">
        <label>Sampai Periode</label>
        <select name="periode_to" onchange="document.getElementById('filter-form').submit()">
          @foreach($allPeriode as $per)
            <option value="{{ $per->id }}" {{ $per->id == $periodeTo ? 'selected' : '' }}>
              {{ $per->label }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group" style="margin:0">
        <label>Peserta</label>
        <select name="peserta_id" onchange="document.getElementById('filter-form').submit()">
          <option value="semua" {{ $pesertaId=='semua' ? 'selected' : '' }}>Semua Peserta</option>
          @foreach($allPeserta as $p)
            <option value="{{ $p->id }}" {{ $p->id == $pesertaId ? 'selected' : '' }}>
              {{ $p->nama }}
            </option>
          @endforeach
        </select>
      </div>

      <div style="margin:0">
        <button type="submit" class="btn btn-primary" style="width:100%">Tampilkan</button>
      </div>
    </div>
  </form>
</div>

{{-- ── STATISTIK RINGKASAN ──────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:16px">
  <div class="metric-card">
    <div class="metric-label">Peserta</div>
    <div class="metric-value">{{ $stats['total_peserta'] }}</div>
    <div class="metric-sub">{{ $stats['total_periode'] }} periode</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Rata-rata Nilai Akhir</div>
    <div class="metric-value" style="color:#4f46e5">{{ $stats['rata_total'] }}</div>
    <div class="metric-sub">nilai kepribadian</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Nilai Tertinggi</div>
    <div class="metric-value" style="color:#059669">{{ $stats['tertinggi'] }}</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Nilai Terendah</div>
    <div class="metric-value" style="color:#dc2626">{{ $stats['terendah'] }}</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Status Input</div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
      <span style="font-size:11px;background:#ecfdf5;color:#059669;padding:2px 6px;border-radius:4px">✓ {{ $stats['sudah_input'] }}</span>
      @if($stats['belum_input'] > 0)
      <span style="font-size:11px;background:#fef3c7;color:#b45309;padding:2px 6px;border-radius:4px">✗ {{ $stats['belum_input'] }}</span>
      @endif
    </div>
  </div>
</div>

{{-- ── GRAFIK ───────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;align-items:center;margin-bottom:14px">
    <span class="card-title" style="margin:0">
      {{ $pesertaId === 'semua' ? 'Tren rata-rata nilai akhir per periode' : 'Tren nilai akhir individu per periode' }}
    </span>
    <div style="margin-left:auto;display:flex;gap:6px">
      <button class="btn btn-sm btn-primary" id="btn-line" onclick="switchChart('line')">Garis</button>
      <button class="btn btn-sm btn-outline" id="btn-radar" onclick="switchChart('radar')">Radar Aspek</button>
    </div>
  </div>
  <div style="position:relative;height:280px">
    <canvas id="chart-line"></canvas>
    <canvas id="chart-radar" style="display:none"></canvas>
  </div>
  <div id="legend-line" style="display:flex;justify-content:center;gap:16px;margin-top:10px;font-size:11px;color:#888">
    <span style="display:flex;align-items:center;gap:4px"><span style="width:12px;height:3px;background:#4f46e5;display:inline-block;border-radius:2px"></span>Nilai Akhir (rata-rata 75 + total poin)</span>
  </div>
  <div id="legend-radar" style="display:none;justify-content:center;gap:16px;margin-top:10px;font-size:11px;color:#888;flex-wrap:wrap">
    <span>Aspek kepribadian — skala 0–100 (dari poin BS/B/C/K/KS)</span>
  </div>
</div>

{{-- ── TABEL HISTORI ────────────────────────────────────── --}}
<div class="card">
  <div style="display:flex;align-items:center;margin-bottom:14px">
    <span class="card-title" style="margin:0">Tabel histori nilai kepribadian per periode</span>
    <span style="margin-left:auto;font-size:12px;color:#aaa">Nilai Akhir = 75 + Σ poin aspek</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:36px">No</th>
          <th>Nama</th>
          @foreach($periodeRange as $per)
            <th style="text-align:center;min-width:80px">{{ $per->label }}</th>
          @endforeach
          <th style="text-align:center;background:#ffffff;min-width:80px">Rata-rata</th>
          <th style="text-align:center;min-width:60px">Tren</th>
          <th style="min-width:80px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($tabelData as $i => $baris)
        @php
          $p      = $baris['peserta'];
          $nilaiArr = collect($baris['nilai'])->filter()->map(fn($n) => $n->nilai_akhir);
          $rata   = $baris['rata'];
          // Tren: bandingkan periode terakhir vs sebelumnya
          $nilaiList = array_values($baris['nilai']);
          $last   = end($nilaiList);
          $prev   = count($nilaiList) > 1 ? $nilaiList[count($nilaiList)-2] : null;
          $tren   = $last && $prev ? ($last->nilai_akhir <=> $prev->nilai_akhir) : 0;
        @endphp
        <tr onclick="window.location='{{ route('kepribadian.show', ['peserta'=>$p->id, 'periode_id'=>$periodeRange->last()?->id]) }}'" style="cursor:pointer">
          <td>{{ $i + 1 }}</td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:50%;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;flex-shrink:0">
                {{ strtoupper(collect(explode(' ',$p->nama))->slice(1)->map(fn($w)=>$w[0]??'')->join('')) }}
              </div>
              <div>
                <div style="font-size:13px;font-weight:{{ $i<3?'600':'400' }}">{{ $p->nama }}</div>
                <div style="font-size:11px;color:#aaa">{{ $p->pangkat }} · {{ $p->nrp }}</div>
              </div>
            </div>
          </td>
          @foreach($periodeRange as $per)
          @php $n = $baris['nilai'][$per->id] ?? null; $val = $n ? round($n->nilai_akhir,2) : null; @endphp
          <td style="text-align:center">
            @if($val !== null)
              @php
                $color = $val >= 78 ? '#059669' : ($val >= 76 ? '#4f46e5' : ($val >= 75 ? '#d97706' : '#dc2626'));
                $bg    = $val >= 78 ? '#ecfdf5' : ($val >= 76 ? '#eef2ff' : ($val >= 75 ? '#fffbeb' : '#fef2f2'));
              @endphp
              <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:12px;font-weight:500;background:{{ $bg }};color:{{ $color }}">
                {{ $val }}
              </span>
            @else
              <span style="color:#999;font-size:12px">—</span>
            @endif
          </td>
          @endforeach
          <td style="text-align:center;background:#ffffff">
            <strong style="font-size:14px;color:#4f46e5">{{ $rata ?: '—' }}</strong>
          </td>
          <td style="text-align:center">
            @if($tren > 0) <span class="tren-up">▲</span>
            @elseif($tren < 0) <span class="tren-dn">▼</span>
            @else <span class="tren-eq">—</span>
            @endif
          </td>
          <td>
            <div style="display:flex;gap:4px" onclick="event.stopPropagation()">
              <a href="{{ route('kepribadian.form', ['peserta'=>$p->id]) }}" class="btn btn-sm btn-primary" title="Isi Nilai" style="padding:3px 8px;font-size:11px">✏️ Isi</a>
              <a href="{{ route('kepribadian.show', ['peserta'=>$p->id, 'periode_id'=>$periodeRange->last()?->id]) }}" class="btn btn-sm btn-outline" title="Detail" style="padding:3px 8px;font-size:11px">📋</a>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="{{ $periodeRange->count() + 5 }}">
          <div class="empty-state">Belum ada data nilai kepribadian untuk filter ini.</div>
        </td></tr>
        @endforelse
      </tbody>
      {{-- Footer rata-rata angkatan --}}
      @if($tabelData->count() > 1)
      <tfoot>
        <tr style="background:#ffffff">
          <td colspan="2" style="font-weight:600;font-size:12px;color:#4f46e5">Rata-rata angkatan</td>
          @foreach($periodeRange as $per)
          @php
            $avgPer = $tabelData->map(fn($b) => $b['nilai'][$per->id]?->nilai_akhir ?? null)->filter()->avg();
          @endphp
          <td style="text-align:center;font-weight:600;color:#4f46e5">
            {{ $avgPer ? round($avgPer,2) : '—' }}
          </td>
          @endforeach
          <td style="text-align:center;font-weight:700;color:#4f46e5;font-size:15px">{{ $stats['rata_total'] }}</td>
          <td></td><td></td>
        </tr>
      </tfoot>
      @endif
    </table>
  </div>
</div>

{{-- ── KETERANGAN ──────────────────────────────────────── --}}
<div style="font-size:11px;color:#aaa;margin-top:8px;display:flex;gap:16px;flex-wrap:wrap">
  <span>BS = Baik Sekali (+0.5)</span>
  <span>B = Baik (+0.25)</span>
  <span>C = Cukup (0)</span>
  <span>K = Kurang (-0.25)</span>
  <span>KS = Kurang Sekali (-0.5)</span>
  <span>| Nilai Akhir = 75 + Σ poin</span>
</div>

@endsection

@push('scripts')
<script>
const grafikData = @json($grafikIndividu ?? $grafikAngkatan);
const labels     = grafikData.map(d => d.label);
const radarAspek = @json($radarAspek);

const baseOpts = {
  responsive: true, maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { mode: 'index', intersect: false }
  },
  scales: {
    x: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#888', font: { size: 11 } } },
    y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#888', font: { size: 11 }, callback: v => v.toFixed(1) }, min: 73, max: 82 }
  }
};

// Grafik garis — nilai akhir
const datasets = [
  { label:'Nilai Akhir', data: grafikData.map(d=>d.total), borderColor:'#4f46e5', backgroundColor:'rgba(79,70,229,.10)', tension:.35, pointRadius:5, pointBackgroundColor:'#4f46e5', fill:true },
];

const ctxLine = document.getElementById('chart-line').getContext('2d');
new Chart(ctxLine, { type:'line', data:{ labels, datasets }, options: baseOpts });

// Grafik radar — aspek kepribadian
const aspekLabels = Object.keys(radarAspek);
const aspekValues = Object.values(radarAspek);
const ctxRadar = document.getElementById('chart-radar').getContext('2d');

const radarGradient = ctxRadar.createLinearGradient(0, 0, 0, 400);
radarGradient.addColorStop(0, 'rgba(79,70,229,0.25)');
radarGradient.addColorStop(1, 'rgba(79,70,229,0.02)');

new Chart(ctxRadar, {
  type: 'radar',
  data: {
    labels: aspekLabels,
    datasets: [{
      label: 'Rata-rata Aspek',
      data: aspekValues,
      borderColor: '#4f46e5',
      backgroundColor: radarGradient,
      pointBackgroundColor: '#4f46e5',
      pointBorderColor: '#fff',
      pointBorderWidth: 2,
      pointRadius: 5,
      borderWidth: 2,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => `Skor: ${ctx.raw}` } }
    },
    scales: {
      r: {
        min: 0,
        max: 100,
        ticks: { stepSize: 25, font: { size: 10 }, backdropColor: 'transparent' },
        grid: { color: 'rgba(0,0,0,.08)' },
        pointLabels: { font: { size: 11, weight: '500' }, color: '#444' }
      }
    }
  }
});

function eksporRekap() {
  const form = document.getElementById('filter-form');
  const formData = new FormData(form);
  const params = new URLSearchParams(formData);
  window.location = '{{ route('rekap.ekspor') }}?' + params.toString();
}

function switchChart(type) {
  document.getElementById('chart-line').style.display  = type==='line'  ? 'block' : 'none';
  document.getElementById('chart-radar').style.display = type==='radar' ? 'block' : 'none';
  document.getElementById('btn-line').className  = type==='line'  ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
  document.getElementById('btn-radar').className = type==='radar' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
  document.getElementById('legend-line').style.display  = type==='line'  ? 'flex' : 'none';
  document.getElementById('legend-radar').style.display = type==='radar' ? 'flex' : 'none';
}
</script>
@endpush
