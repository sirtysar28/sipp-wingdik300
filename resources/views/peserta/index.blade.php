@extends('layouts.app')
@section('title', 'Peserta Didik')
@php $curAng = $allAngkatan->firstWhere('id', $angkatanId); @endphp
@section('page-title', 'Peserta Didik — ' . ($curAng?->skadik->nama ?? '') . ' Angkt ' . ($curAng?->nomor_angkatan ?? ''))

@section('topbar-actions')
<a href="{{ route('peserta.create') }}" class="btn btn-primary btn-sm">+ Tambah Peserta</a>
<a href="{{ route('peserta.import.form', ['skadik_id'=>$skadikId, 'angkatan_id'=>$angkatanId]) }}" class="btn btn-outline btn-sm">📥 Impor Peserta</a>
@endsection

@section('content')
<div class="card">
  {{-- Filter sejajar ke samping --}}
  <div style="display:flex;gap:10px;margin-bottom:16px;align-items:flex-end;flex-wrap:wrap">
    <form method="GET" action="{{ route('peserta.index') }}" id="filterForm" style="display:flex;gap:8px;flex:1;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label style="font-size:11px;margin-bottom:3px;display:block">Sekolah</label>
        <select name="skadik_id" style="font-size:12px;padding:5px 8px;border:1px solid #d0d0d8;border-radius:7px;min-width:140px">
          @foreach($allSkadik as $sk)
            <option value="{{ $sk->id }}" {{ $sk->id == $skadikId ? 'selected' : '' }}>
              {{ $sk->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label style="font-size:11px;margin-bottom:3px;display:block">Angkatan</label>
        <select name="angkatan_id" style="font-size:12px;padding:5px 8px;border:1px solid #d0d0d8;border-radius:7px;min-width:150px">
          @foreach($allAngkatan as $ang)
            <option value="{{ $ang->id }}" {{ $ang->id == $angkatanId ? 'selected' : '' }}>
              Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label style="font-size:11px;margin-bottom:3px;display:block">Cari Nama / NRP</label>
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Ketik nama atau NRP…" style="font-size:12px;padding:5px 8px;border:1px solid #d0d0d8;border-radius:7px;min-width:200px" />
      </div>
      <button type="submit" class="btn btn-primary btn-sm">🔍 Tampilkan</button>
    </form>
    <span style="font-size:12px;color:#aaa">{{ $peserta->total() }} peserta</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>Pangkat</th>
          <th>NRP</th>
          <th>Nosis</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($peserta as $i => $p)
        <tr style="{{ !$p->aktif ? 'opacity:0.6' : '' }}">
          <td>{{ $peserta->firstItem() + $i }}</td>
          <td>
            <a href="{{ route('peserta.show', $p) }}" style="color:#4f46e5;font-weight:500">
              {{ $p->nama }}
            </a>
            @if(!$p->aktif)
              <span class="badge badge-red" style="margin-left:6px">Nonaktif</span>
            @endif
          </td>
          <td><span class="badge badge-blue">{{ $p->pangkat }}</span></td>
          <td style="color:#888">{{ $p->nrp }}</td>
          <td>{{ $p->nosis }}</td>
          <td>
            <span class="badge {{ $p->aktif ? 'badge-green' : 'badge-red' }}">
              {{ $p->aktif ? 'Aktif' : 'Nonaktif' }}
            </span>
          </td>
          <td style="display:flex;gap:4px;flex-wrap:wrap">
            <a href="{{ route('peserta.show', $p) }}" class="btn btn-outline btn-sm">Profil</a>
            @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin() || auth()->user()->isAdminAkademik() || auth()->user()->isAdminKepribadian() || auth()->user()->isAdminSamapta())
            <a href="{{ route('peserta.edit', $p) }}" class="btn btn-outline btn-sm" title="Edit">✏</a>
            @endif
            <form method="POST" action="{{ route('peserta.destroy', $p) }}"
              onsubmit="return confirm('{{ $p->aktif ? 'Nonaktifkan' : 'Aktifkan kembali' }} peserta ini?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm {{ $p->aktif ? 'btn-danger' : 'btn-outline' }}" title="{{ $p->aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                {{ $p->aktif ? '⏻' : '✓' }}
              </button>
            </form>
            @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
            <form method="POST" action="{{ route('peserta.force-delete', $p) }}"
              onsubmit="return confirm('HAPUS PERMANEN {{ $p->nama }}? Data tidak bisa dikembalikan.')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger" title="Hapus Permanen">🗑</button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty-state">Belum ada peserta di angkatan ini.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{-- Pagination --}}
  <div style="margin-top:16px">{{ $peserta->withQueryString()->links() }}</div>
</div>
@endsection
