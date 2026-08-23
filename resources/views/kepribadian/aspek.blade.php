@extends('layouts.app')
@section('title','Kelola Aspek Kepribadian')
@section('page-title','Kelola Aspek Kepribadian')

@section('topbar-actions')
<a href="{{ route('kepribadian.index') }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start">

  {{-- Daftar aspek --}}
  <div class="card">
    <div class="card-title">Daftar Aspek ({{ $aspek->count() }})</div>
    <div style="font-size:12px;color:#888;margin-bottom:14px">
      Aspek yang dinonaktifkan tidak akan muncul di form input, tapi data lama tetap tersimpan.
    </div>

    <div style="display:grid;gap:8px">
      @foreach($aspek as $a)
      <div style="border:1px solid {{ $a->aktif?'#e8e8ed':'#fee2e2' }};border-radius:10px;padding:12px 14px;background:{{ $a->aktif?'#fff':'#fef2f2' }}">
        <div style="display:flex;align-items:flex-start;gap:10px">
          <span style="width:28px;height:28px;border-radius:50%;background:{{ $a->aktif?'#eef2ff':'#fee2e2' }};color:{{ $a->aktif?'#4f46e5':'#b91c1c' }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">
            {{ $a->nomor }}
          </span>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:600;color:{{ $a->aktif?'#1a1a2e':'#b91c1c' }}">{{ $a->nama }}</div>
            @if($a->deskripsi)
            <div style="font-size:11px;color:#aaa;margin-top:2px;line-height:1.5">{{ $a->deskripsi }}</div>
            @endif
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <button onclick="editAspek({{ $a->id }},'{{ addslashes($a->nama) }}','{{ addslashes($a->deskripsi) }}')"
                    class="btn btn-outline btn-sm">Edit</button>
            <form method="POST" action="{{ route('kepribadian.aspek.destroy',$a) }}"
                  onsubmit="return confirm('{{ $a->aktif?'Nonaktifkan':'Aktifkan' }} aspek ini?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm {{ $a->aktif?'btn-danger':'btn-outline' }}">
                {{ $a->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>
            </form>
          </div>
        </div>

        {{-- Form edit inline --}}
        <div id="edit-{{ $a->id }}" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f5">
          <form method="POST" action="{{ route('kepribadian.aspek.update',$a) }}">
            @csrf @method('PUT')
            <div class="form-group">
              <label>Nama Aspek</label>
              <input type="text" name="nama" value="{{ $a->nama }}" required>
            </div>
            <div class="form-group">
              <label>Deskripsi</label>
              <textarea name="deskripsi" rows="2">{{ $a->deskripsi }}</textarea>
            </div>
            <div style="display:flex;gap:8px">
              <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
              <button type="button" onclick="document.getElementById('edit-{{ $a->id }}').style.display='none'"
                      class="btn btn-outline btn-sm">Batal</button>
            </div>
          </form>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- Form tambah aspek baru --}}
  <div class="card" style="position:sticky;top:80px">
    <div class="card-title">Tambah Aspek Baru</div>
    <div style="font-size:12px;color:#888;margin-bottom:14px;line-height:1.6">
      Aspek baru akan ditambahkan di urutan paling bawah. Nomor urut otomatis.
    </div>
    <form method="POST" action="{{ route('kepribadian.aspek.store') }}">
      @csrf
      <div class="form-group">
        <label>Nama Aspek <span style="color:#dc2626">*</span></label>
        <input type="text" name="nama" placeholder="Contoh: Kreativitas" required value="{{ old('nama') }}">
      </div>
      <div class="form-group">
        <label>Deskripsi / Sub-aspek</label>
        <textarea name="deskripsi" rows="3"
          placeholder="Jelaskan indikator penilaian aspek ini...">{{ old('deskripsi') }}</textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">+ Tambah Aspek</button>
    </form>

    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f0f0f5">
      <div style="font-size:11px;color:#aaa;margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Keterangan Skala</div>
      @foreach(['BS'=>['+0.5','Baik Sekali','#059669','#ecfdf5'],'B'=>['+0.25','Baik','#4f46e5','#eef2ff'],'C'=>['0','Cukup','#888','#f4f5f7'],'K'=>['-0.25','Kurang','#b45309','#fffbeb'],'KS'=>['-0.5','Kurang Sekali','#b91c1c','#fee2e2']] as $k=>[$poin,$label,$color,$bg])
      <div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #f4f5f7">
        <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $bg }};color:{{ $color }};min-width:36px;text-align:center">{{ $k }}</span>
        <span style="font-size:12px;color:#555;flex:1">{{ $label }}</span>
        <span style="font-size:12px;font-weight:600;color:{{ $color }}">{{ $poin }}</span>
      </div>
      @endforeach
      <div style="font-size:11px;color:#888;margin-top:8px">Nilai awal: <strong>75</strong> | Maks: 80 | Min: 70</div>
    </div>
  </div>
</div>

@push('scripts')
<script>
function editAspek(id, nama, deskripsi) {
  // Sembunyikan semua form edit lain
  document.querySelectorAll('[id^="edit-"]').forEach(el => el.style.display = 'none');
  document.getElementById('edit-' + id).style.display = 'block';
}
</script>
@endpush
@endsection
