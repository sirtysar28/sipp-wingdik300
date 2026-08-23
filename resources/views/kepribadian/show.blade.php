@extends('layouts.app')
@section('title','Detail Kepribadian')
@section('page-title','Detail Nilai Kepribadian')

@section('topbar-actions')
<a href="{{ route('kepribadian.index',['angkatan_id'=>$peserta->angkatan_id,'periode_id'=>$periodeId]) }}" class="btn btn-outline btn-sm">← Kembali</a>
@if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin() || auth()->user()->isAdminKepribadian())
<a href="{{ route('kepribadian.form',['peserta'=>$peserta->id,'periode_id'=>$periodeId]) }}" class="btn btn-outline btn-sm">✏ Edit</a>
@endif
@endsection

@section('content')
<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start">

  {{-- Kartu identitas --}}
  <div>
    <div class="card" style="margin-bottom:12px">
      <div style="text-align:center;padding:8px 0 14px">
        <div style="width:56px;height:56px;border-radius:50%;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;margin:0 auto 10px">
          {{ strtoupper(collect(explode(' ',$peserta->nama))->slice(1)->map(fn($w)=>$w[0]??'')->join('')) }}
        </div>
        <div style="font-size:15px;font-weight:600">{{ $peserta->nama }}</div>
        <div style="font-size:12px;color:#aaa">{{ $peserta->pangkat }} · {{ $peserta->nrp }}</div>
      </div>
      <div style="border-top:1px solid #f0f0f5;padding-top:14px">
        <table style="width:100%;font-size:12px">
          <tr><td style="color:#aaa;padding:4px 0">NRP</td><td style="text-align:right;font-weight:500;padding:4px 0">{{ $peserta->nrp }}</td></tr>
          <tr><td style="color:#aaa;padding:4px 0">Nosis</td><td style="text-align:right;font-weight:500;padding:4px 0">{{ $peserta->nosis }}</td></tr>
          <tr><td style="color:#aaa;padding:4px 0">Angkatan</td><td style="text-align:right;font-weight:500;padding:4px 0">{{ $peserta->angkatan->nomor_angkatan }}</td></tr>
          <tr><td style="color:#aaa;padding:4px 0">Sekolah</td><td style="text-align:right;font-weight:500;padding:4px 0">{{ $peserta->angkatan->skadik->nama }}</td></tr>
        </table>
      </div>
      <div style="border-top:1px solid #f0f0f5;padding-top:12px">
        @php
          $avgKep = round($riwayat->avg('nilai_akhir'), 2);
          $maxKep = $riwayat->max('nilai_akhir');
          $minKep = $riwayat->min('nilai_akhir');
        @endphp
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div style="background:#f4f5f7;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:18px;font-weight:700;color:#4f46e5">{{ $avgKep }}</div>
            <div style="font-size:10px;color:#aaa">Rata-rata</div>
          </div>
          <div style="background:#f4f5f7;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:18px;font-weight:700">{{ $riwayat->count() }}</div>
            <div style="font-size:10px;color:#aaa">Periode</div>
          </div>
          <div style="background:#ecfdf5;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:18px;font-weight:700;color:#059669">{{ $maxKep }}</div>
            <div style="font-size:10px;color:#aaa">Tertinggi</div>
          </div>
          <div style="background:#fef2f2;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:18px;font-weight:700;color:#dc2626">{{ $minKep }}</div>
            <div style="font-size:10px;color:#aaa">Terendah</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Pilih periode --}}
    <div class="card" style="margin-bottom:12px">
      <div class="card-title">Pilih Periode</div>
      @foreach($periodes as $per)
      @php $nk = $riwayat->where('periode_nilai_id',$per->id)->first(); @endphp
      <a href="{{ route('kepribadian.show',['peserta'=>$peserta->id,'periode_id'=>$per->id]) }}"
         style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:8px;margin-bottom:4px;font-size:13px;background:{{ $per->id==$periodeId?'#eef2ff':'transparent' }};color:{{ $per->id==$periodeId?'#4f46e5':'#555' }}">
        <span>{{ $per->label }}</span>
        @if($nk)
          <span style="font-weight:600;color:{{ $per->id==$periodeId?'#4f46e5':'#059669' }}">{{ $nk->nilai_akhir }}</span>
        @else
          <span style="color:#ddd;font-size:11px">—</span>
        @endif
      </a>
      @endforeach
    </div>
  </div>

  <div>
    {{-- Grafik tren --}}
    <div class="card" style="margin-bottom:12px">
      <div class="card-title">Tren nilai kepribadian</div>
      <div style="position:relative;height:160px">
        <canvas id="chart-kep"></canvas>
      </div>
    </div>

    {{-- Detail aspek periode terpilih --}}
    @if($current)
    <div class="card" style="margin-bottom:12px">
      <div style="display:flex;align-items:center;margin-bottom:14px">
        <span class="card-title" style="margin:0">Detail aspek — {{ $current->periode?->label }}</span>
        <span style="margin-left:auto;font-size:20px;font-weight:700;color:#4f46e5">{{ $current->nilai_akhir }}</span>
      </div>

      @php $detailMap = $current->detail->keyBy('aspek_kepribadian_id'); @endphp
      <div style="display:grid;gap:8px">
        @foreach($aspekList as $aspek)
        @php
          $d = $detailMap->get($aspek->id);
          $k = $d?->kriteria ?? '—';
          $colors = ['BS'=>['#ecfdf5','#059669'],'B'=>['#eef2ff','#4f46e5'],'C'=>['#f4f5f7','#888'],'K'=>['#fffbeb','#b45309'],'KS'=>['#fee2e2','#b91c1c'],'—'=>['#f4f5f7','#ddd']];
          [$bg,$color] = $colors[$k] ?? $colors['—'];
        @endphp
        <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;background:{{ $bg }}">
          <span style="width:20px;height:20px;border-radius:50%;background:white;color:#888;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;flex-shrink:0">{{ $aspek->nomor }}</span>
          <span style="flex:1;font-size:12px;font-weight:500;color:#444">{{ $aspek->nama }}</span>
          <span style="font-size:12px;font-weight:700;color:{{ $color }};min-width:28px;text-align:center">{{ $k }}</span>
          <span style="font-size:11px;color:{{ $color }};min-width:40px;text-align:right">
            {{ $d ? ($d->poin > 0 ? '+' : '').$d->poin : '' }}
          </span>
        </div>
        @endforeach
      </div>
    </div>

    @if($current->penjelasan || $current->rekomendasi)
    <div class="card">
      @if($current->penjelasan)
      <div style="margin-bottom:12px">
        <div style="font-size:11px;font-weight:600;color:#aaa;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Penjelasan</div>
        <div style="font-size:13px;color:#444;line-height:1.7">{{ $current->penjelasan }}</div>
      </div>
      @endif
      @if($current->rekomendasi)
      <div style="border-top:1px solid #f0f0f5;padding-top:12px">
        <div style="font-size:11px;font-weight:600;color:#aaa;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Rekomendasi</div>
        <div style="font-size:13px;color:#444;line-height:1.7">{{ $current->rekomendasi }}</div>
      </div>
      @endif
    </div>
    @endif

    @else
    <div class="empty-state">Belum ada nilai kepribadian untuk periode ini.</div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
const labels = @json($grafikLabels);
const nilai  = @json($grafikNilai);
const ctx    = document.getElementById('chart-kep').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Nilai Kepribadian',
      data: nilai,
      borderColor: '#4f46e5',
      backgroundColor: 'rgba(79,70,229,.08)',
      tension: .35,
      pointRadius: 5,
      pointBackgroundColor: '#4f46e5',
      fill: true,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#888', font: { size: 11 } } },
      y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#888', font: { size: 11 } }, min: 74, suggestedMax: 80 }
    }
  }
});
</script>
@endpush
