@extends('layouts.app')
@section('title', 'Lembaga Pendidikan')
@section('page-title', 'Lembaga Pendidikan')

@section('topbar-actions')
<a href="{{ route('lemdik.create') }}" class="btn btn-primary btn-sm">+ Tambah Lembaga Pendidikan</a>
@endsection

@section('content')
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>Nama Lembaga Pendidikan</th>
          <th>Kode</th>
          <th>Wingdik</th>
          <th>Kota</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($lemdik as $i => $l)
        <tr>
          <td>{{ $lemdik->firstItem() + $i }}</td>
          <td style="font-weight:500">{{ $l->nama }}</td>
          <td><span class="badge badge-blue">{{ $l->kode }}</span></td>
          <td style="color:#888">{{ $l->wingdik ?: '—' }}</td>
          <td><span class="badge badge-purple">{{ $l->kota ?: '—' }}</span></td>
          <td><span class="badge {{ $l->aktif ? 'badge-green' : 'badge-red' }}">{{ $l->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="{{ route('lemdik.edit', $l) }}" class="btn btn-outline btn-sm">✏ Edit</a>
            @if($l->skadik->isEmpty())
            <form method="POST" action="{{ route('lemdik.destroy', $l) }}" style="display:inline"
              onsubmit="return confirm('Hapus lembaga pendidikan ini?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-danger btn-sm">🗑 Hapus</button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty-state">Belum ada data Lembaga Pendidikan.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px">{{ $lemdik->links() }}</div>
</div>
@endsection
