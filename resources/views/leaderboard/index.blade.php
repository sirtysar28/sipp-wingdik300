@extends('layouts.app')
@section('title','Leaderboard NPP')
@section('page-title','Leaderboard NPP — ' . ($angkatan?->skadik->nama ?? '') . ' Angkt ' . ($angkatan?->nomor_angkatan ?? ''))

@section('topbar-actions')
<a href="{{ route('leaderboard.ekspor', array_merge(request()->query(), ['angkatan_id'=>$angkatanId])) }}"
   class="btn btn-primary btn-sm">⬇ Ekspor XLSX</a>
@endsection

@section('content')

{{-- Filter --}}
<div class="card" style="margin-bottom:14px">
  <form method="GET" action="{{ route('leaderboard') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
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
          <option value="{{ $ang->id }}" {{ $ang->id==$angkatan?->id?'selected':'' }}>
            Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
          </option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Cari</label>
      <input type="text" name="q" value="{{ $q }}" placeholder="Nama / NRP..." style="width:180px">
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Cari</button>
    <div style="margin-left:auto;display:flex;gap:16px;align-items:center">
      <div style="text-align:center">
        <div style="font-size:20px;font-weight:700;color:#4f46e5">{{ $sudahKompilasi }}/{{ $totalPeserta }}</div>
        <div style="font-size:10px;color:#aaa">sudah kompile</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:20px;font-weight:700">{{ $rataNPP ?: '—' }}</div>
        <div style="font-size:10px;color:#aaa">rata-rata NPP</div>
      </div>
    </div>
  </form>
</div>

{{-- Header --}}
<div style="text-align:center;margin-bottom:10px">
  <div style="font-size:11px;font-weight:600;color:#666;text-transform:uppercase">
    {{ $angkatan?->skadik?->lemdik?->nama }} / {{ $angkatan?->skadik?->nama }}
  </div>
  <div style="font-size:15px;font-weight:700;color:#1a1a2e">
    LEADERBOARD NILAI PRESTASI PENDIDIKAN (NPP) — ANGKATAN {{ strtoupper($angkatan?->nomor_angkatan) }}
  </div>
</div>

@if($sudahKompilasi === 0)
<div class="empty-state card" style="padding:48px">
  <p>Belum ada data NPP. Proses kompilasi nilai terlebih dahulu.</p>
  <a href="{{ route('report.npp') }}" class="btn btn-primary btn-sm" style="margin-top:12px">→ Proses Kompilasi NPP</a>
</div>
@else

{{-- Tabel --}}
<div class="card" style="padding:0;overflow:hidden">
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead>
        <tr>
          <th rowspan="2" style="text-align:center;width:40px;background:#4f46e5;color:#fff;border:1px solid #6366f1;padding:8px 4px">RNKG</th>
          <th rowspan="2" style="background:#4f46e5;color:#fff;border:1px solid #6366f1;min-width:180px;padding:8px">NAMA</th>
          <th rowspan="2" style="text-align:center;background:#4f46e5;color:#fff;border:1px solid #6366f1;width:60px">PGKT</th>
          <th rowspan="2" style="text-align:center;background:#4f46e5;color:#fff;border:1px solid #6366f1;width:130px">NRP</th>
          <th colspan="3" style="text-align:center;background:#6366f1;color:#fff;border:1px solid #818cf8;padding:6px">KOMPONEN NILAI</th>
          <th rowspan="2" style="text-align:center;background:#4338ca;color:#fff;border:1px solid #6366f1;width:60px;font-size:10px">NPP</th>
          <th rowspan="2" style="text-align:center;background:#4338ca;color:#fff;border:1px solid #6366f1;width:50px">PREDIKAT</th>
        </tr>
        <tr>
          <th style="text-align:center;background:#059669;color:#fff;border:1px solid #10b981;font-size:10px;padding:4px">NPA</th>
          <th style="text-align:center;background:#2563eb;color:#fff;border:1px solid #3b82f6;font-size:10px;padding:4px">NPK</th>
          <th style="text-align:center;background:#ea580c;color:#fff;border:1px solid #f97316;font-size:10px;padding:4px">NPS</th>
        </tr>
      </thead>
      <tbody>
        @forelse($paginated as $i => $k)
        @php
          $rank = ($page-1)*$perPage + $i + 1;
          $isTop3 = $rank<=3 && $page===1;
          $bgRow = $isTop3 ? match($rank){1=>'#fffbeb',2=>'#eff6ff',3=>'#f0fdf4'} : ($i%2===0?'#fff':'#f9fafb');
        @endphp
        <tr style="background:{{ $bgRow }};cursor:pointer"
            onclick="window.location='{{ route('laporan.cetak.individu',['angkatan_id'=>$angkatanId,'peserta_id'=>$k->peserta_didik_id]) }}'">
          <td style="text-align:center;border:1px solid #e5e7eb">
            @if($rank===1)<span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#fef3c7;color:#92400e;font-weight:700;font-size:11px">1</span>
            @elseif($rank===2)<span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#dbeafe;color:#1e40af;font-weight:700;font-size:11px">2</span>
            @elseif($rank===3)<span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#d1fae5;color:#065f46;font-weight:700;font-size:11px">3</span>
            @else<span style="color:#aaa;font-weight:600">{{ $rank }}</span>
            @endif
          </td>
          <td style="border:1px solid #e5e7eb;padding:8px 10px;font-weight:{{ $isTop3?'600':'400' }}">
            {{ $k->peserta->nama }}
          </td>
          <td style="text-align:center;border:1px solid #e5e7eb">{{ $k->peserta->pangkat }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb;color:#888;font-size:11px">{{ $k->peserta->nrp }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb;background:#f0fdf4;font-weight:500;color:#059669">{{ $k->nilai_akademik_fix }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb;background:#eff6ff;font-weight:500;color:#2563eb">{{ $k->nilai_kepribadian_fix }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb;background:#fff7ed;font-weight:500;color:#ea580c">{{ $k->nilai_samapta_fix }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb;background:#eef2ff;font-weight:700;color:#4f46e5;font-size:14px">{{ $k->npp_fix }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb">
            @php
              $predColor = match($k->predikat_huruf){'A'=>'#059669','B+'=>'#059669','B'=>'#2563eb','C+'=>'#d97706','C'=>'#d97706','D'=>'#dc2626','E'=>'#dc2626',default=>'#888'};
            @endphp
            <span style="font-weight:600;color:{{ $predColor }}">{{ $k->predikat_huruf }}</span>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" style="padding:32px;text-align:center;color:#aaa">
            Tidak ada data ditemukan.
          </td>
        </tr>
        @endforelse
      </tbody>

      @if($paginated->count() > 0 && $page===1)
      <tfoot>
        <tr style="background:#f0f0ff">
          <td colspan="4" style="padding:8px 10px;font-weight:600;font-size:12px;color:#4f46e5;border:1px solid #e5e7eb">
            Rata-rata Angkatan
          </td>
          <td style="text-align:center;border:1px solid #e5e7eb;font-weight:600;color:#059669">{{ $sorted->avg('nilai_akademik_fix') ? round($sorted->avg('nilai_akademik_fix'),2) : '—' }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb;font-weight:600;color:#2563eb">{{ $sorted->avg('nilai_kepribadian_fix') ? round($sorted->avg('nilai_kepribadian_fix'),2) : '—' }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb;font-weight:600;color:#ea580c">{{ $sorted->avg('nilai_samapta_fix') ? round($sorted->avg('nilai_samapta_fix'),2) : '—' }}</td>
          <td style="text-align:center;border:1px solid #e5e7eb;font-weight:700;color:#4f46e5;font-size:14px">{{ $sorted->avg('npp_fix') ? round($sorted->avg('npp_fix'),2) : '—' }}</td>
          <td style="border:1px solid #e5e7eb"></td>
        </tr>
      </tfoot>
      @endif
    </table>
  </div>

  @if($lastPage > 1)
  <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid #e8e8ed">
    <span style="font-size:12px;color:#aaa">
      {{ ($page-1)*$perPage+1 }}–{{ min($page*$perPage,$total) }} dari {{ $total }}
    </span>
    <div style="display:flex;gap:4px">
      @if($page>1)<a href="{{ request()->fullUrlWithQuery(['page'=>$page-1]) }}" class="btn btn-outline btn-sm">← Prev</a>@endif
      @for($i=1;$i<=$lastPage;$i++)
        <a href="{{ request()->fullUrlWithQuery(['page'=>$i]) }}" class="btn btn-sm {{ $i==$page?'btn-primary':'btn-outline' }}">{{ $i }}</a>
      @endfor
      @if($page<$lastPage)<a href="{{ request()->fullUrlWithQuery(['page'=>$page+1]) }}" class="btn btn-outline btn-sm">Next →</a>@endif
    </div>
  </div>
  @else
  <div style="padding:8px 16px;font-size:12px;color:#aaa;border-top:1px solid #f0f0f5">
    {{ $total }} peserta · {{ $sudahKompilasi }} sudah dikompilasi
  </div>
  @endif
</div>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:10px;font-size:11px;align-items:center">
  <span style="color:#888;font-weight:500">Komponen:</span>
  <span style="padding:2px 8px;border-radius:99px;background:#f0fdf4;color:#059669;font-weight:600">NPA = Nilai Prestasi Akademik</span>
  <span style="padding:2px 8px;border-radius:99px;background:#eff6ff;color:#2563eb;font-weight:600">NPK = Nilai Prestasi Kepribadian</span>
  <span style="padding:2px 8px;border-radius:99px;background:#fff7ed;color:#ea580c;font-weight:600">NPS = Nilai Prestasi Samapta</span>
  <span style="padding:2px 8px;border-radius:99px;background:#eef2ff;color:#4f46e5;font-weight:600">NPP = Kompilasi NPA + NPK + NPS</span>
</div>
@endif
@endsection
