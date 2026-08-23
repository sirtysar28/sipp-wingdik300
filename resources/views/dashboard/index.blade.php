@extends('layouts.app')
@section('title', 'Dashboard NPP')
@section('page-title', 'Dashboard — Nilai Prestasi Pendidikan')

@section('topbar-actions')
@if($angkatan)
  <span style="font-size:12px;color:#888">{{ $angkatan->skadik->nama }} — Angkatan {{ $angkatan->nomor_angkatan }}</span>
@endif
@endsection

@section('content')
@if(!$angkatan)
  <div class="empty-state">Belum ada angkatan aktif. <a href="{{ route('angkatan.create') }}" style="color:#4f46e5">Tambah angkatan</a></div>
@else

{{-- ── Filter Sekolah / Angkatan ── --}}
<div class="card" style="margin-bottom:16px">
  <form method="GET" action="{{ route('dashboard') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;min-width:160px">
      <label style="font-size:11px;margin-bottom:3px;display:block">Sekolah</label>
      <select name="skadik_id" onchange="this.form.submit()" style="font-size:12px;padding:5px 8px;border:1px solid #d0d0d8;border-radius:7px;min-width:160px">
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $sk->id == $skadikId ? 'selected' : '' }}>{{ $sk->nama }}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:180px">
      <label style="font-size:11px;margin-bottom:3px;display:block">Angkatan</label>
      <select name="angkatan_id" onchange="this.form.submit()" style="font-size:12px;padding:5px 8px;border:1px solid #d0d0d8;border-radius:7px;min-width:180px">
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id == $angkatanId ? 'selected' : '' }}>
            Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
          </option>
        @endforeach
      </select>
    </div>
  </form>
</div>

{{-- ── Metrik Utama ── --}}
<div class="metric-grid">
  <div class="metric-card">
    <div class="metric-label">Total Peserta</div>
    <div class="metric-value">{{ $totalPeserta }}</div>
    <div class="metric-sub">{{ $lengkap }} lengkap · {{ $belumLengkap }} belum</div>
  </div>
  <div class="metric-card" style="border-left:4px solid #059669">
    <div class="metric-label">Rata-rata NPA</div>
    <div class="metric-value" style="color:#059669">{{ $avgNPA ? round($avgNPA,2) : '—' }}</div>
    <div class="metric-sub">{{ $sudahNPA }}/{{ $totalPeserta }} sudah diinput</div>
  </div>
  <div class="metric-card" style="border-left:4px solid #2563eb">
    <div class="metric-label">Rata-rata NPK</div>
    <div class="metric-value" style="color:#2563eb">{{ $avgNPK ? round($avgNPK,2) : '—' }}</div>
    <div class="metric-sub">{{ $sudahNPK }}/{{ $totalPeserta }} sudah diinput</div>
  </div>
  <div class="metric-card" style="border-left:4px solid #ea580c">
    <div class="metric-label">Rata-rata NPS</div>
    <div class="metric-value" style="color:#ea580c">{{ $avgNPS ? round($avgNPS,2) : '—' }}</div>
    <div class="metric-sub">{{ $sudahNPS }}/{{ $totalPeserta }} sudah diinput</div>
  </div>
</div>

{{-- ── Metrik NPP (Kompilasi) ── --}}
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card" style="background:linear-gradient(135deg,#eef2ff,#f5f3ff);border:1px solid #c7d2fe">
    <div class="metric-label">📊 NPP (Kompilasi)</div>
    <div class="metric-value" style="color:#4f46e5;font-size:28px">{{ $avgNPP ?: '—' }}</div>
    <div class="metric-sub">Rata-rata Nilai Prestasi Pendidikan</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Tertinggi</div>
    <div class="metric-value" style="color:#059669">{{ $tertinggiNPP ?: '—' }}</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Terendah</div>
    <div class="metric-value" style="color:#dc2626">{{ $terendahNPP ?: '—' }}</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Di Bawah Rata-rata</div>
    <div class="metric-value" style="color:{{ $dibawahRataNPP>0?'#b91c1c':'#059669' }}">{{ $dibawahRataNPP }}</div>
  </div>
</div>

@if($sudahKompilasi === 0)
<div class="empty-state card" style="padding:48px">
  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
  <p>Belum ada data NPP (Kompilasi).</p>
  <p style="font-size:13px;color:#aaa;margin-top:8px">Pastikan NPA, NPK, dan NPS sudah diinput, lalu proses kompilasi.</p>
  <div style="display:flex;gap:8px;justify-content:center;margin-top:16px">
    <a href="{{ route('report.npa') }}" class="btn btn-outline btn-sm">→ Report NPA</a>
    <a href="{{ route('report.npk') }}" class="btn btn-outline btn-sm">→ Report NPK</a>
    <a href="{{ route('report.nps') }}" class="btn btn-outline btn-sm">→ Report NPS</a>
    <a href="{{ route('report.npp') }}" class="btn btn-primary btn-sm">→ Proses Kompilasi NPP</a>
  </div>
</div>
@else

<div style="display:grid;grid-template-columns:1.2fr 1fr;gap:16px;margin-bottom:16px">

  {{-- PODIUM NPP --}}
  <div class="card">
    <div class="card-title">🏆 Podium NPP Angkatan</div>
    @php $top3 = $kompilasiData->take(3); @endphp
    @if($top3->count() >= 2)
    <div style="display:flex;align-items:flex-end;justify-content:center;gap:12px;margin:8px 0 4px">

      {{-- Rank 2 --}}
      @if($top3->count() >= 2)
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <a href="{{ route('laporan.cetak.individu', ['angkatan_id'=>$angkatanId,'peserta_id'=>$top3[1]->peserta_didik_id]) }}"
           style="font-size:11px;color:#4f46e5;font-weight:500;text-align:center;max-width:84px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;text-decoration:none"
           title="Lihat laporan {{ $top3[1]->peserta->nama }}">{{ $top3[1]->peserta->nama }}</a>
        <div style="font-size:13px;font-weight:600;text-align:center">{{ round($top3[1]->nilai_akhir, 2) }}</div>
        <div style="width:84px;height:52px;border-radius:8px 8px 0 0;background:#dbeafe;color:#1e40af;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700">2</div>
      </div>
      @endif

      {{-- Rank 1 --}}
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div style="width:28px;height:28px;border-radius:50%;background:#fbbf24;color:#78350f;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700">★</div>
        <a href="{{ route('laporan.cetak.individu', ['angkatan_id'=>$angkatanId,'peserta_id'=>$top3[0]->peserta_didik_id]) }}"
           style="font-size:11px;color:#4f46e5;font-weight:500;text-align:center;max-width:84px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;text-decoration:none"
           title="Lihat laporan {{ $top3[0]->peserta->nama }}">{{ $top3[0]->peserta->nama }}</a>
        <div style="font-size:18px;font-weight:700;text-align:center;color:#4f46e5">{{ round($top3[0]->nilai_akhir, 2) }}</div>
        <div style="width:84px;height:72px;border-radius:8px 8px 0 0;background:#fef3c7;color:#92400e;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700">1</div>
      </div>

      {{-- Rank 3 --}}
      @if($top3->count() >= 3)
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <a href="{{ route('laporan.cetak.individu', ['angkatan_id'=>$angkatanId,'peserta_id'=>$top3[2]->peserta_didik_id]) }}"
           style="font-size:11px;color:#4f46e5;font-weight:500;text-align:center;max-width:84px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;text-decoration:none"
           title="Lihat laporan {{ $top3[2]->peserta->nama }}">{{ $top3[2]->peserta->nama }}</a>
        <div style="font-size:13px;font-weight:600;text-align:center">{{ round($top3[2]->nilai_akhir, 2) }}</div>
        <div style="width:84px;height:38px;border-radius:8px 8px 0 0;background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700">3</div>
      </div>
      @endif

    </div>
    <div style="text-align:center;font-size:11px;color:#aaa;margin-top:4px">Klik nama untuk lihat laporan individu</div>
    @else
    <div class="empty-state" style="padding:24px">Minimal 2 peserta untuk menampilkan podium</div>
    @endif
  </div>

  {{-- RANK 4-10 --}}
  <div class="card">
    <div class="card-title">Ranking NPP 4 – 10</div>
    @forelse($kompilasiData->slice(3,7)->values() as $i => $k)
    @php
      $rank = $i + 4;
      $link = route('laporan.cetak.individu', ['angkatan_id'=>$angkatanId,'peserta_id'=>$k->peserta_didik_id]);
    @endphp
    <a href="{{ $link }}"
       style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0f5;text-decoration:none;color:inherit">
      <span style="width:20px;text-align:center;font-size:12px;color:#aaa;font-weight:600">{{ $rank }}</span>
      <div style="width:30px;height:30px;border-radius:50%;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;flex-shrink:0">
        {{ strtoupper(collect(explode(' ',$k->peserta->nama))->slice(1)->map(fn($w)=>$w[0]??'')->join('')) }}
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $k->peserta->nama }}</div>
        <div style="font-size:10px;color:#aaa">NPA:{{ $k->nilai_akademik }} | NPK:{{ $k->nilai_kepribadian }} | NPS:{{ $k->nilai_samapta }}</div>
        <div style="background:#e8e8ed;border-radius:4px;height:5px;margin-top:4px;overflow:hidden">
          <div style="height:5px;width:{{ $tertinggiNPP>0?round($k->nilai_akhir/$tertinggiNPP*100).'%':'0%' }};background:#4f46e5;border-radius:4px"></div>
        </div>
      </div>
      <span style="font-size:13px;font-weight:600;min-width:40px;text-align:right;color:#4f46e5">{{ round($k->nilai_akhir,2) }}</span>
    </a>
    @empty
    <div class="empty-state" style="padding:20px">Belum ada data rank 4–10</div>
    @endforelse
  </div>
</div>

{{-- GRAFIK DISTRIBUSI NPP --}}
<div class="card">
  <div class="card-title">Distribusi Nilai NPP</div>
  <div style="position:relative;height:220px">
    <canvas id="chart-dist"></canvas>
  </div>
  <div style="display:flex;justify-content:center;gap:16px;margin-top:10px;font-size:11px;color:#888">
    <span style="display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;border-radius:2px;background:#818cf8;display:inline-block"></span>Jumlah peserta per rentang NPP</span>
  </div>
</div>

@endif
@endif
@endsection

@push('scripts')
<script>
const distribusi = @json($distribusi);

const ctxDist = document.getElementById('chart-dist');
if (ctxDist) {
  new Chart(ctxDist.getContext('2d'), {
    type: 'bar',
    data: {
      labels: Object.keys(distribusi),
      datasets: [{
        label: 'Peserta',
        data: Object.values(distribusi),
        backgroundColor: ['#059669','#10b981','#4f46e5','#6366f1','#f59e0b','#dc2626'],
        borderRadius: 5,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: l => l.raw + ' peserta' } } },
      scales: {
        x: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#888', font: { size: 11 } } },
        y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#888', font: { size: 11 }, stepSize: 1 }, beginAtZero: true }
      }
    }
  });
}
</script>
@endpush
