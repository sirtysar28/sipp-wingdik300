@extends('layouts.app')
@section('title','Nilai Kepribadian')
@section('page-title','Nilai Kepribadian — ' . ($angkatan?->skadik->nama_singkat ?? $angkatan?->skadik->nama ?? '') . ' Angkt ' . ($angkatan?->nomor_angkatan ?? ''))

@section('topbar-actions')
@if($angkatan && $periodeAktif)
<a href="{{ route('kepribadian.import.form', ['angkatan_id'=>$angkatan->id, 'periode_id'=>$periodeId]) }}"
   class="btn btn-outline btn-sm">📥 Impor Excel</a>
<a href="{{ route('kepribadian.ekspor', array_merge(request()->query(),['angkatan_id'=>$angkatan->id])) }}"
   class="btn btn-outline btn-sm">⬇ Ekspor XLSX</a>
@endif
<a href="{{ route('kepribadian.aspek') }}" class="btn btn-outline btn-sm">⚙ Kelola Aspek</a>
@endsection

@section('content')

{{-- Filter --}}
<div class="card" style="margin-bottom:16px">
  <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="cari" value="1">
    <div class="form-group" style="margin:0;min-width:180px">
      <label>Sekolah</label>
      <select name="skadik_id">
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $sk->id==$skadikId?'selected':'' }}>
            {{ $sk->nama }} ({{ $sk->lemdik->nama }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:180px">
      <label>Angkatan</label>
      <select name="angkatan_id">
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id==$angkatanId?'selected':'' }}>
            Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:160px">
      <label>Periode</label>
      <select name="periode_id">
        @foreach($periodes as $per)
          <option value="{{ $per->id }}" {{ $per->id==$periodeId?'selected':'' }}>
            {{ $per->label }}{{ $per->aktif?' (aktif)':'' }}
          </option>
        @endforeach
      </select>
    </div>
    <div style="align-self:flex-end">
      <button type="submit" class="btn btn-primary btn-sm">🔍 Cari</button>
    </div>
  </form>
</div>

@if(!$angkatanId)
{{-- Belum pilih angkatan --}}
<div class="card">
  <div class="empty-state" style="padding:48px">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:40px;height:40px;margin:0 auto 12px;opacity:.4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <div style="font-size:15px;font-weight:500;color:#888;margin-top:8px">Pilih <strong>Sekolah</strong> &amp; <strong>Angkatan</strong> di atas untuk menampilkan data nilai kepribadian. Gunakan tombol <strong>Cari</strong> untuk mengganti periode.</div>
  </div>
</div>
@elseif(!$peserta || $peserta->count() === 0)
{{-- Sudah cari tapi data kosong --}}
<div class="card">
  <div class="empty-state" style="padding:48px">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:40px;height:40px;margin:0 auto 12px;opacity:.4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <div style="font-size:15px;font-weight:500;color:#888;margin-top:8px">Belum ada peserta di angkatan ini.</div>
    <a href="{{ route('peserta.create') }}" style="color:#4f46e5;margin-top:8px;display:inline-block">→ Tambah Peserta Baru</a>
  </div>
</div>
@else

{{-- Progress bar input --}}
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div>
      <div style="font-size:12px;color:#888;margin-bottom:4px">Progress input nilai kepribadian</div>
      <div style="font-size:22px;font-weight:700;color:#4f46e5">
        {{ $sudahDiinput }} / {{ $peserta->count() }}
        <span style="font-size:13px;font-weight:400;color:#aaa">peserta</span>
      </div>
    </div>
    <div style="flex:1;min-width:200px">
      @php $pct = $peserta->count() > 0 ? round($sudahDiinput/$peserta->count()*100) : 0; @endphp
      <div style="display:flex;justify-content:space-between;font-size:11px;color:#aaa;margin-bottom:4px">
        <span>{{ $pct }}% selesai</span>
        <span>{{ $peserta->count()-$sudahDiinput }} belum diinput</span>
      </div>
      <div class="progress-bar" style="height:10px;border-radius:6px">
        <div class="progress-fill" style="width:{{ $pct }}%;height:10px;border-radius:6px;background:{{ $pct==100?'#059669':'#4f46e5' }}"></div>
      </div>
    </div>
    @if($periode)
    <div style="font-size:12px;color:#aaa;text-align:right">
      Periode: <strong style="color:#444">{{ $periode->label }}</strong><br>
      {{ $periode->tanggal_mulai?->format('d M Y') }} – {{ $periode->tanggal_selesai?->format('d M Y') }}
    </div>
    @endif
  </div>
</div>

{{-- Tabel peserta --}}
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>NRP</th>
          <th>Pangkat</th>
          <th style="text-align:center">Nilai Kepribadian</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($peserta as $i => $p)
        <tr>
          <td>{{ $i+1 }}</td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:30px;height:30px;border-radius:50%;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;flex-shrink:0">
                {{ strtoupper(collect(explode(' ',$p->nama))->slice(1)->map(fn($w)=>$w[0]??'')->join('')) }}
              </div>
              <span style="font-weight:500">{{ $p->nama }}</span>
            </div>
          </td>
          <td style="color:#aaa">{{ $p->nrp }}</td>
          <td><span class="badge badge-blue">{{ $p->pangkat }}</span></td>
          <td style="text-align:center">
            @if($p->kep)
              @php
                $val = $p->kep->nilai_akhir;
                $color = $val >= 78 ? '#059669' : ($val >= 76 ? '#4f46e5' : ($val >= 75 ? '#d97706' : '#dc2626'));
                $bg    = $val >= 78 ? '#ecfdf5' : ($val >= 76 ? '#eef2ff' : ($val >= 75 ? '#fffbeb' : '#fee2e2'));
              @endphp
              <span style="display:inline-block;padding:3px 12px;border-radius:99px;font-size:13px;font-weight:600;background:{{ $bg }};color:{{ $color }}">
                {{ $val }}
              </span>
            @else
              <span style="color:#ddd;font-size:12px">—</span>
            @endif
          </td>
          <td style="text-align:center">
            @if($p->kep)
              <span class="badge badge-green">✓ Sudah diinput</span>
            @else
              <span class="badge badge-red">Belum</span>
            @endif
          </td>
          <td style="text-align:center">
            <div style="display:flex;gap:6px;justify-content:center">
              @if(auth()->user()->isAdminKepribadian() || auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                <a href="{{ route('kepribadian.form', ['peserta'=>$p->id,'periode_id'=>$periodeId]) }}"
                   class="btn btn-sm {{ $p->kep ? 'btn-outline' : 'btn-primary' }}">
                  {{ $p->kep ? '✏ Edit' : '+ Input' }}
                </a>
              @endif
              @if($p->kep)
                <a href="{{ route('kepribadian.show', ['peserta'=>$p->id,'periode_id'=>$periodeId]) }}"
                   class="btn btn-outline btn-sm">Lihat</a>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty-state">Tidak ada peserta.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endif
@endsection
