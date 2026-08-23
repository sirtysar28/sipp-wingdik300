@extends('layouts.app')
@section('title', 'Angkatan')
@section('page-title', 'Data Angkatan')
@section('topbar-actions')
<a href="{{ route('angkatan.create') }}" class="btn btn-primary btn-sm">+ Tambah Angkatan</a>
@endsection
@section('content')
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>No. Angkatan</th>
          <th>Sekolah</th>
          <th>Tahun Masuk</th>
          <th>Lembaga Pendidikan</th>
          <th>Peserta</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($angkatan as $i => $a)
        <tr>
          <td>{{ $angkatan->firstItem() + $i }}</td>
          <td style="font-weight:600">{{ $a->nomor_angkatan }}</td>
          <td>{{ $a->skadik->nama }}</td>
          <td>{{ $a->tahun_masuk }}</td>
          <td style="color:#aaa">{{ $a->skadik->lemdik->nama }}</td>
          <td><span class="badge badge-blue">{{ $a->peserta()->where('aktif',true)->count() }}</span></td>
          <td><span class="badge {{ $a->aktif ? 'badge-green' : 'badge-red' }}">{{ $a->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="{{ route('leaderboard', ['angkatan_id'=>$a->id]) }}" class="btn btn-outline btn-sm">Leaderboard</a>
            <a href="{{ route('angkatan.edit', $a) }}" class="btn btn-outline btn-sm">✏ Edit</a>
            @if($a->aktif)
            <form method="POST" action="{{ route('angkatan.destroy', $a) }}"
              onsubmit="return confirm('Nonaktifkan angkatan ini?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger">🚫 Nonaktifkan</button>
            </form>
            @else
            <form method="POST" action="{{ route('angkatan.destroy', $a) }}"
              onsubmit="return confirm('Hapus PERMANEN angkatan ini beserta semua data terkait?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-warning">🗑 Hapus Permanen</button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="8"><div class="empty-state">Belum ada angkatan.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px">{{ $angkatan->links() }}</div>
</div>
@endsection
