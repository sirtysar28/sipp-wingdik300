@extends('layouts.app')
@section('page-title', 'Manage Mata Pelajaran')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">📚 Manage Mata Pelajaran</h2>
    <p style="font-size:12px;color:#888">Petakan mata pelajaran ke sekolah. Satu matpel bisa dipakai banyak sekolah.</p>
  </div>
</div>

{{-- Info Card --}}
<div class="card" style="margin-bottom:16px;background:linear-gradient(135deg,#eef2ff,#f5f3ff);border-color:#c7d2fe">
  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div>
      <div style="font-size:13px;font-weight:600;color:#4f46e5">💡 Cara Kerja</div>
      <div style="font-size:12px;color:#555;margin-top:4px">
        Pilih sekolah, lalu tambah matpel baru <strong>atau</strong> petakan matpel yang sudah ada dari sekolah lain.
        JP, Bobot &amp; Harga Nilai milik matpel (dipakai bersama antar sekolah). Urutan &amp; status aktif diatur per-sekolah.
      </div>
    </div>
  </div>
</div>

{{-- Filter Skadik --}}
<div class="filter-bar">
  <div class="form-group">
    <label>Pilih Sekolah</label>
    <form id="filterForm" method="GET" action="{{ route('mata-pelajaran.index') }}" style="display:flex;gap:8px;align-items:end">
      <select name="skadik_id" onchange="this.form.submit()" style="min-width:300px">
        @foreach($allSkadik as $s)
          <option value="{{ $s->id }}" {{ $s->id == $skadikId ? 'selected' : '' }}>
            {{ $s->nama }} — {{ $s->lemdik?->wingdik ?? '-' }}
            ({{ $s->mata_pelajaran_count ?? '-' }} mapel)
          </option>
        @endforeach
      </select>
    </form>
  </div>
</div>

@if(!$skadikId)
<div class="empty-state">
  <p>Pilih sekolah untuk mengatur mata pelajaran.</p>
</div>
@else

{{-- Statistik --}}
@if($mapel->count() > 0)
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card">
    <div class="metric-label">Total Mata Pelajaran</div>
    <div class="metric-value">{{ $mapel->count() }}</div>
    <div class="metric-sub">{{ $mapel->filter(fn($m)=>$m->pivot->aktif)->count() }} aktif</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Total JP (aktif)</div>
    <div class="metric-value" style="color:#4f46e5">{{ $totalJP }}</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Total Bobot (aktif)</div>
    <div class="metric-value" style="color:#7c3aed">{{ $totalBobot }}</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Σ Harga Nilai (aktif)</div>
    <div class="metric-value" style="color:#059669">{{ $totalHargaNilai }}</div>
    <div class="metric-sub">Digunakan untuk rumus NPA</div>
  </div>
</div>
@endif

{{-- Tambah Baru + Pilih dari Lain --}}
<div class="card" style="margin-bottom:16px">
  <div class="card-title">➕ Tambah / Petakan Mata Pelajaran</div>

  {{-- Tambah matpel BARU --}}
  <form method="POST" action="{{ route('mata-pelajaran.store') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:14px">
    @csrf
    <input type="hidden" name="skadik_id" value="{{ $skadikId }}">
    <div class="form-group" style="flex:1;min-width:220px;margin:0">
      <label>Nama Mata Pelajaran (baru)</label>
      <input type="text" name="nama" placeholder="cth: Pengetahuan VMT" required>
    </div>
    <div class="form-group" style="width:80px;margin:0">
      <label>Kode</label>
      <input type="text" name="kode" placeholder="1.1">
    </div>
    <div class="form-group" style="width:60px;margin:0">
      <label>JP</label>
      <input type="number" name="jp" value="0" min="0">
    </div>
    <div class="form-group" style="width:70px;margin:0">
      <label>Bobot</label>
      <input type="number" name="bobot" value="6" min="0">
    </div>
    <div class="form-group" style="width:90px;margin:0">
      <label>Harga Nilai</label>
      <input type="number" name="harga_nilai" value="0" min="0" placeholder="0=auto">
    </div>
    <button type="submit" class="btn btn-primary" style="height:36px">Tambah Baru</button>
  </form>

  {{-- Petakan matpel yang SUDAH ADA --}}
  @if($availableToMap->count() > 0)
  <form method="POST" action="{{ route('mata-pelajaran.map-existing') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;border-top:1px dashed #e8e8ed;padding-top:14px">
    @csrf
    <input type="hidden" name="skadik_id" value="{{ $skadikId }}">
    <div class="form-group" style="flex:1;min-width:300px;margin:0">
      <label>Petakan Matpel yang Sudah Ada (dari sekolah lain)</label>
      <select name="mata_pelajaran_id" required>
        <option value="">-- Pilih Matpel --</option>
        @foreach($availableToMap as $m)
          <option value="{{ $m->id }}">
            {{ $m->nama }} ({{ $m->kode ?: '-' }}) — JP {{ $m->jp }} | B {{ $m->bobot }} | HN {{ $m->harga_nilai_calc }}
          </option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="btn btn-success" style="height:36px">🔗 Petakan ke Sekolah Ini</button>
  </form>
  @else
  <div style="font-size:12px;color:#aaa;border-top:1px dashed #e8e8ed;padding-top:14px">
    Semua mata pelajaran sudah dipetakan ke sekolah ini.
  </div>
  @endif
</div>

{{-- Template & Duplikasi --}}
<div class="card" style="margin-bottom:16px">
  <div class="card-title">🔧 Template & Duplikasi</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <form method="POST" action="{{ route('mata-pelajaran.load-template') }}" style="display:flex;gap:6px;align-items:end"
          onsubmit="return confirm('Ini akan MENGHAPUS semua pemetaan matpel lama sekolah ini dan menggantinya dengan template. Lanjutkan?')">
      @csrf
      <input type="hidden" name="skadik_id" value="{{ $skadikId }}">
      <div class="form-group" style="margin:0">
        <select name="template" style="min-width:200px">
          <option value="">Pilih Template...</option>
          <option value="sbs-c130-fuel">SBS C-130 Fuel System (18 subjek)</option>
          <option value="template-kosong">Template Kosong (Hapus Semua)</option>
        </select>
      </div>
      <button type="submit" class="btn btn-warning btn-sm" disabled id="btnTemplate">📋 Muat Template</button>
    </form>

    <form method="POST" action="{{ route('mata-pelajaran.duplikasi') }}" style="display:flex;gap:6px;align-items:end"
          onsubmit="return confirm('Ganti semua matpel sekolah ini dengan salinan dari sekolah sumber. Lanjutkan?')">
      @csrf
      <input type="hidden" name="to_skadik_id" value="{{ $skadikId }}">
      <div class="form-group" style="margin:0">
        <select name="from_skadik_id" style="min-width:200px">
          <option value="">Duplikasi dari sekolah...</option>
          @foreach($allSkadik as $s)
            @if($s->id != $skadikId)
              <option value="{{ $s->id }}">{{ $s->nama }} ({{ $s->mata_pelajaran_count ?? '-' }} mapel)</option>
            @endif
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-outline btn-sm" disabled id="btnDuplikasi">📋 Duplikasi</button>
    </form>
  </div>
</div>

{{-- Tabel Mata Pelajaran --}}
@if($mapel->count() === 0)
<div class="empty-state card">
  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
  <p>Belum ada mata pelajaran dipetakan ke sekolah ini.</p>
  <p style="font-size:12px;margin-top:4px;color:#aaa">Tambahkan baru, petakan dari sekolah lain, atau gunakan template.</p>
</div>
@else
<div class="card">
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th style="text-align:center;width:40px">No</th>
          <th>Kode</th>
          <th>Nama Mata Pelajaran</th>
          <th style="text-align:center">JP</th>
          <th style="text-align:center">Bobot</th>
          <th style="text-align:center">Harga Nilai</th>
          <th style="text-align:center">Urutan</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center">Jml Sekolah</th>
          <th style="width:120px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($mapel as $idx => $m)
        <tr id="row-{{ $m->id }}" style="{{ !$m->pivot->aktif ? 'opacity:0.5;background:#fef2f2' : '' }}">
          <td style="text-align:center">{{ $idx + 1 }}</td>
          <td>
            @if($m->kode)
              <span class="badge badge-blue">{{ $m->kode }}</span>
            @else
              <span style="color:#ccc">—</span>
            @endif
          </td>
          <td style="font-weight:500">{{ $m->nama }}</td>
          <td style="text-align:center">{{ $m->jp }}</td>
          <td style="text-align:center">{{ $m->bobot }}</td>
          <td style="text-align:center;font-weight:600;color:#059669">{{ $m->harga_nilai_calc }}</td>
          <td style="text-align:center">
            <input type="number" name="urutan_{{ $m->id }}" value="{{ $m->pivot->urutan }}" min="0"
                   style="width:50px;text-align:center;padding:2px 4px" class="inline-urutan"
                   data-id="{{ $m->id }}">
          </td>
          <td style="text-align:center">
            @if($m->pivot->aktif)
              <span class="badge badge-green">Aktif</span>
            @else
              <span class="badge badge-red">Nonaktif</span>
            @endif
          </td>
          <td style="text-align:center">
            <span class="badge badge-purple">{{ $m->skadiks()->count() }}</span>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button onclick="toggleStatus({{ $m->id }})" class="btn btn-sm btn-outline" title="Toggle Aktif">
                {{ $m->pivot->aktif ? '🚫' : '✅' }}
              </button>
              <button onclick="editMapel({{ $m->id }})" class="btn btn-sm btn-outline" title="Edit">✏️</button>
              <form method="POST" action="{{ route('mata-pelajaran.destroy', $m->id) }}?skadik_id={{ $skadikId }}"
                    style="display:inline" onsubmit="return confirm('Lepas matpel ini dari sekolah ini?')">
                @csrf @method('DELETE')
                <input type="hidden" name="skadik_id" value="{{ $skadikId }}">
                <button type="submit" class="btn btn-sm btn-danger" title="Lepas dari sekolah">🔓</button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
        <tr style="background:#ffffff;font-weight:700">
          <td colspan="3" style="text-align:center">TOTAL (aktif)</td>
          <td style="text-align:center">{{ $totalJP }}</td>
          <td style="text-align:center">{{ $totalBobot }}</td>
          <td style="text-align:center">{{ $totalHargaNilai }}</td>
          <td colspan="4"></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endif

@endif {{-- end skadikId check --}}

{{-- Modal Edit --}}
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:2000;justify-content:center;align-items:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:90%;max-width:500px;position:relative">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:4px">✏️ Edit Mata Pelajaran</h3>
    <div style="font-size:11px;color:#888;margin-bottom:16px">JP, Bobot &amp; Harga Nilai berlaku untuk SEMUA sekolah yang memetakan matpel ini. Urutan &amp; Aktif hanya untuk sekolah ini.</div>
    <form method="POST" action="" id="editForm">
      @csrf @method('PUT')
      <input type="hidden" name="skadik_id" value="{{ $skadikId }}">
      <div class="form-group">
        <label>Nama</label>
        <input type="text" name="nama" id="editNama" required>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <div class="form-group" style="flex:1;min-width:100px">
          <label>Kode</label>
          <input type="text" name="kode" id="editKode">
        </div>
        <div class="form-group" style="width:80px">
          <label>JP</label>
          <input type="number" name="jp" id="editJP" min="0">
        </div>
        <div class="form-group" style="width:80px">
          <label>Bobot</label>
          <input type="number" name="bobot" id="editBobot" min="0">
        </div>
        <div class="form-group" style="width:90px">
          <label>Harga Nilai</label>
          <input type="number" name="harga_nilai" id="editHargaNilai" min="0" placeholder="0=auto">
        </div>
        <div class="form-group" style="width:80px">
          <label>Urutan</label>
          <input type="number" name="urutan" id="editUrutan" min="0">
        </div>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="aktif" id="editAktif" value="1" style="width:auto">
        <label style="margin:0">Aktif di sekolah ini</label>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-outline" onclick="closeEditModal()">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
    <button onclick="closeEditModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:#aaa">&times;</button>
  </div>
</div>

@endsection

@push('scripts')
<script>
const SKADIK_ID = {{ $skadikId ?: 'null' }};

// Enable/disable buttons based on select
document.querySelectorAll('select[name="template"]').forEach(el => {
  el.addEventListener('change', () => { document.getElementById('btnTemplate').disabled = !el.value; });
});
document.querySelectorAll('select[name="from_skadik_id"]').forEach(el => {
  el.addEventListener('change', () => { document.getElementById('btnDuplikasi').disabled = !el.value; });
});

// Toggle status (per-sekolah via pivot)
function toggleStatus(id) {
  const row = document.getElementById('row-' + id);
  const isActive = row.querySelector('.badge-green') !== null;
  const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  fetch('/mata-pelajaran/' + id + '?skadik_id=' + SKADIK_ID, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
    body: JSON.stringify({ _method: 'PUT', skadik_id: SKADIK_ID, aktif: isActive ? 0 : 1 })
  }).then(r => { if (r.ok) window.location.reload(); else alert('Gagal mengubah status.'); });
}

// Edit modal — ambil data dari atribut data-* (lebih andal daripada textContent)
function editMapel(id) {
  const row = document.getElementById('row-' + id);
  const kodeEl = row.querySelector('.badge-blue');
  document.getElementById('editNama').value = row.cells[2].textContent.trim();
  document.getElementById('editKode').value = kodeEl ? kodeEl.textContent.trim() : '';
  document.getElementById('editJP').value = row.cells[3].textContent.trim();
  document.getElementById('editBobot').value = row.cells[4].textContent.trim();
  document.getElementById('editHargaNilai').value = row.cells[5].textContent.trim();
  document.getElementById('editUrutan').value = row.querySelector('.inline-urutan').value;
  document.getElementById('editAktif').checked = row.querySelector('.badge-green') !== null;
  document.getElementById('editForm').action = '/mata-pelajaran/' + id + '?skadik_id=' + SKADIK_ID;
  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});
</script>
@endpush
