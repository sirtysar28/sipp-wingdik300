@extends('layouts.app')
@section('page-title', 'Penandatangan')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">✍️ Penandatangan</h2>
    <p style="font-size:12px;color:#888">Kelola data penandatangan laporan. 1 orang bisa ttd di banyak sekolah, tapi 1 jenis per sekolah hanya boleh 1.</p>
  </div>
  <button type="button" class="btn btn-smart" onclick="document.getElementById('modalTambah').style.display='flex'">➕ Tambah Penandatangan</button>
</div>

{{-- Modal Tambah --}}
<div id="modalTambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:2000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-size:16px;font-weight:600">Tambah Penandatangan</h3>
      <button onclick="document.getElementById('modalTambah').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888">&times;</button>
    </div>
    <form method="POST" action="{{ route('penandatangan.store') }}">
      @csrf

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Sekolah <span style="color:#dc2626">*</span></label>
          <select name="skadik_id" id="tambahSkadik" required>
            <option value="">— Global (Semua Sekolah) —</option>
            @foreach($allSkadik as $sk)
              <option value="{{ $sk->id }}">{{ $sk->nama }}</option>
            @endforeach
          </select>
          <div style="font-size:10px;color:#999;margin-top:2px">Global = dipakai semua sekolah jika tidak ada ttd khusus sekolah</div>
        </div>
        <div class="form-group">
          <label>Jenis / Kategori <span style="color:#dc2626">*</span></label>
          <select name="jenis" id="tambahJenis" required>
            @foreach($jenisList as $val => $label)
              <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Nama Lengkap <span style="color:#dc2626">*</span></label>
          <input type="text" name="nama" required placeholder="Nama Lengkap">
        </div>
        <div class="form-group">
          <label>NRP <span style="color:#dc2626">*</span></label>
          <input type="text" name="nrp" required placeholder="NRP">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Pangkat</label>
          <input type="text" name="pangkat" placeholder="cth: Kaptes Kes">
        </div>
        <div class="form-group">
          <label>Jabatan <span style="color:#dc2626">*</span></label>
          <input type="text" name="jabatan" required placeholder="Jabatan">
        </div>
      </div>

      <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary">💾 Simpan</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalTambah').style.display='none'">Batal</button>
      </div>
    </form>
  </div>
</div>

{{-- Filter --}}
<div class="filter-bar">
  <form method="GET" action="{{ route('penandatangan.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;min-width:180px">
      <label>Filter Sekolah</label>
      <select name="skadik_id" onchange="this.form.submit()">
        <option value="semua" {{ $skadikId=='semua'?'selected':'' }}>Semua Sekolah</option>
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $skadikId==$sk->id?'selected':'' }}>{{ $sk->nama }}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:200px">
      <label>Filter Jenis</label>
      <select name="jenis" onchange="this.form.submit()">
        <option value="semua" {{ $jenis=='semua'?'selected':'' }}>Semua Jenis</option>
        @foreach($jenisList as $val => $label)
          <option value="{{ $val }}" {{ $jenis==$val?'selected':'' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </form>
</div>

{{-- Tabel --}}
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Sekolah</th>
          <th>Nama</th>
          <th>Pangkat</th>
          <th>NRP</th>
          <th>Jabatan</th>
          <th>Jenis</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center;width:120px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($penandatangan as $idx => $p)
        <tr>
          <td>{{ $idx + 1 }}</td>
          <td>
            @if($p->skadik_id)
              <span class="badge badge-purple">{{ $p->skadik->nama }}</span>
            @else
              <span class="badge" style="background:#f3f4f6;color:#666">🌍 Global</span>
            @endif
          </td>
          <td><strong>{{ $p->nama }}</strong></td>
          <td style="font-size:12px;color:#666">{{ $p->pangkat ?? '-' }}</td>
          <td style="font-family:monospace;color:#666">{{ $p->nrp }}</td>
          <td>{{ $p->jabatan }}</td>
          <td>
            <span class="badge badge-{{ $p->jenis=='umum'?'purple':($p->jenis=='akademik'?'green':($p->jenis=='kepribadian'?'blue':($p->jenis=='samapta'?'orange':($p->jenis=='danskadik'?'amber':'blue')))) }}">
              {{ $jenisList[$p->jenis] ?? $p->jenis }}
            </span>
          </td>
          <td style="text-align:center">
            @if($p->aktif)
              <span class="badge badge-green">Aktif</span>
            @else
              <span class="badge badge-amber">Nonaktif</span>
            @endif
          </td>
          <td style="text-align:center">
            <button type="button" onclick="openEdit({{ $p->id }},'{{ addslashes($p->nama) }}','{{ $p->nrp }}','{{ addslashes($p->pangkat ?? '') }}','{{ addslashes($p->jabatan) }}','{{ $p->jenis }}',{{ $p->skadik_id ?? 'null' }},{{ $p->aktif?'true':'false' }})" class="btn btn-sm btn-outline" title="Edit">✏️</button>
            <form method="POST" action="{{ route('penandatangan.destroy', $p->id) }}" style="display:inline" onsubmit="return confirm('Hapus penandatangan ini?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger" title="Hapus">🗑️</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="9"><div class="empty-state">Belum ada data penandatangan.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Info --}}
<div style="margin-top:12px;font-size:11px;color:#999;display:flex;gap:20px;flex-wrap:wrap">
  <span>💡 <strong>Global</strong> = ttd default dipakai semua sekolah jika tidak ada ttd khusus</span>
  <span>🔒 <strong>Aturan:</strong> 1 jenis + 1 sekolah hanya boleh 1 penandatangan aktif</span>
</div>

{{-- Modal Edit --}}
<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:2000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-size:16px;font-weight:600">✏️ Edit Penandatangan</h3>
      <button onclick="document.getElementById('modalEdit').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888">&times;</button>
    </div>
    <form method="POST" id="formEdit">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <input type="hidden" name="id" id="editId">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Sekolah</label>
          <select name="skadik_id" id="editSkadik">
            <option value="">— Global (Semua Sekolah) —</option>
            @foreach($allSkadik as $sk)
              <option value="{{ $sk->id }}">{{ $sk->nama }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label>Jenis</label>
          <select name="jenis" id="editJenis" required>
            @foreach($jenisList as $val => $label)
              <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Nama</label>
          <input type="text" name="nama" id="editNama" required>
        </div>
        <div class="form-group">
          <label>NRP</label>
          <input type="text" name="nrp" id="editNrp" required>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Pangkat</label>
          <input type="text" name="pangkat" id="editPangkat">
        </div>
        <div class="form-group">
          <label>Jabatan</label>
          <input type="text" name="jabatan" id="editJabatan" required>
        </div>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="aktif" id="editAktif" value="1" checked>
          Aktif
        </label>
      </div>

      <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary">💾 Simpan</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalEdit').style.display='none'">Batal</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
function openEdit(id, nama, nrp, pangkat, jabatan, jenis, skadikId, aktif) {
  document.getElementById('editId').value = id;
  document.getElementById('editNama').value = nama;
  document.getElementById('editNrp').value = nrp;
  document.getElementById('editPangkat').value = pangkat;
  document.getElementById('editJabatan').value = jabatan;
  document.getElementById('editJenis').value = jenis;
  document.getElementById('editSkadik').value = skadikId || '';
  document.getElementById('editAktif').checked = aktif;
  document.getElementById('formEdit').action = '/penandatangan/' + id;
  document.getElementById('modalEdit').style.display = 'flex';
}

document.querySelectorAll('#modalTambah, #modalEdit').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});
</script>
@endpush
