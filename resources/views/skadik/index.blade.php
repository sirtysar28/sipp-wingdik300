@extends('layouts.app')
@section('title', 'Sekolah')
@section('page-title', 'Data Sekolah')

@section('topbar-actions')
<a href="{{ route('skadik.create') }}" class="btn btn-primary btn-sm">+ Tambah Sekolah</a>
@endsection

@section('content')
@if(session('error'))
  <div class="alert alert-error">{{ session('error') }}</div>
@endif
<div class="card">
  {{-- Filter pencarian nama sekolah --}}
  <div style="display:flex;gap:10px;margin-bottom:16px;align-items:flex-end;flex-wrap:wrap">
    <form method="GET" action="{{ route('skadik.index') }}" style="display:flex;gap:8px;flex:1;align-items:flex-end;flex-wrap:wrap">
      <div style="flex:1;min-width:220px">
        <label style="font-size:11px;margin-bottom:3px;display:block">Cari Sekolah</label>
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Ketik nama sekolah, kode, atau lembaga pendidikan…" autofocus />
      </div>
      <button type="submit" class="btn btn-primary btn-sm">🔍 Tampilkan</button>
      @if(($search ?? '') !== '')
        <a href="{{ route('skadik.index') }}" class="btn btn-outline btn-sm">Reset</a>
      @endif
    </form>
    <span style="font-size:12px;color:#aaa">{{ $skadik->total() }} sekolah</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>Nama Sekolah</th>
          <th>Kode</th>
          <th>Lembaga Pendidikan</th>
          <th>Jenjang</th>
          <th>Jenis Pendidikan</th>
          <th>Keterangan</th>
          <th>Jumlah Angkatan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($skadik as $i => $s)
        <tr>
          <td>{{ $skadik->firstItem() + $i }}</td>
          <td style="font-weight:500">{{ $s->nama }}</td>
          <td><span class="badge badge-blue">{{ $s->kode }}</span></td>
          <td style="color:#888">{{ $s->lemdik->nama ?? '—' }}</td>
          <td><span class="badge {{ $s->jenjang === 'perwira' ? 'badge-blue' : 'badge-amber' }}">{{ $s->jenjang_label }}</span></td>
          <td><span class="badge badge-blue">{{ $s->jenis_pendidikan_label }}</span></td>
          <td style="color:#888;max-width:200px">{{ $s->keterangan ?? '—' }}</td>
          <td><span class="badge badge-blue">{{ $s->angkatan()->count() }}</span></td>
          <td style="display:flex;gap:6px">
            <a href="{{ route('skadik.edit', $s) }}" class="btn btn-outline btn-sm">Edit</a>
            <form method="POST" action="{{ route('skadik.destroy', $s) }}"
                  onsubmit="return confirm('Hapus sekolah ini? Pastikan tidak ada angkatan terkait.')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="9"><div class="empty-state">@if(($search ?? '') !== '') Sekolah dengan kata kunci "{{ $search }}" tidak ditemukan. <a href="{{ route('skadik.index') }}">Reset filter</a>. @else Belum ada data Sekolah. @endif</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px">{{ $skadik->links() }}</div>
</div>
@endsection
