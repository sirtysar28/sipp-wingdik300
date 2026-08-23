@extends('layouts.app')
@section('page-title', 'Manage User & Role')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">👥 Manage User & Role Management</h2>
    <p style="font-size:12px;color:#888">Kelola akun pengguna & hak akses modul per Sekolah</p>
  </div>
  <button type="button" class="btn btn-smart" onclick="document.getElementById('modalTambah').style.display='flex'">➕ Tambah User</button>
</div>

{{-- Info Role --}}
<div class="card" style="margin-bottom:16px;border-left:4px solid #7c3aed">
  <div class="card-title" style="margin-bottom:8px">Struktur Role Management</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:8px;font-size:12px">
    <div style="padding:8px;background:#f5f3ff;border-radius:6px"><strong style="color:#7c3aed">Super Admin (WingDik)</strong><br><span style="color:#888">Akses penuh seluruh modul & sekolah</span></div>
    <div style="padding:8px;background:#eef2ff;border-radius:6px"><strong style="color:#4f46e5">Opsdik</strong><br><span style="color:#888">Admin per Skadron Pendidikan (boleh >1)</span></div>
    <div style="padding:8px;background:#ecfdf5;border-radius:6px"><strong style="color:#059669">Kepala Sekolah</strong><br><span style="color:#888">Input & kelola NPA (boleh >1 sekolah)</span></div>
    <div style="padding:8px;background:#eff6ff;border-radius:6px"><strong style="color:#2563eb">Danflight</strong><br><span style="color:#888">Input & kelola NPK (boleh >1 sekolah)</span></div>
    <div style="padding:8px;background:#fff7ed;border-radius:6px"><strong style="color:#ea580c">Binjaswing</strong><br><span style="color:#888">Input & kelola NPS (boleh >1 sekolah)</span></div>
  </div>
  <div style="margin-top:8px;font-size:11px;color:#7c3aed;background:#f5f3ff;padding:6px 10px;border-radius:6px">
    💡 <strong>Tips:</strong> Admin modul & Opsdik dapat diberi akses <strong>lebih dari 1 sekolah</strong> — tahan <strong>Ctrl/Cmd</strong> saat memilih. Contoh: Binjaswing bisa diberi akses 4 skadik (301–304).
  </div>
</div>

{{-- Modal Tambah User --}}
<div id="modalTambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:2000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-size:16px;font-weight:600">Tambah User Baru</h3>
      <button onclick="document.getElementById('modalTambah').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888">&times;</button>
    </div>
    <form method="POST" action="{{ route('manage-user.store') }}">
      @csrf
      <div class="form-group">
        <label>Nama</label>
        <input type="text" name="name" required placeholder="Nama Lengkap">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required placeholder="email@siak-dirgantara.id">
      </div>
      <div class="form-group">
        <label>Password</label>
        <div style="position:relative">
          <input type="password" name="password" id="addPw" required minlength="6" autocomplete="new-password" placeholder="Minimal 6 karakter">
          <button type="button" class="pw-toggle" onclick="togglePw('addPw',this)">👁️</button>
        </div>
      </div>
      <div class="form-group">
        <label>Role / Akses Modul</label>
        <select name="role" required id="addRole" onchange="toggleSkadikField('add')">
          <option value="">— Pilih Role —</option>
          @foreach($roles as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group" id="skadikFieldAdd">
        <label>Sekolah <span style="color:red">*</span> <span id="skadikHintAdd" style="font-size:10px;color:#7c3aed;font-weight:600"></span></label>
        @if($lemdikList->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;align-items:center">
          <span style="font-size:10px;color:#888;font-weight:600;white-space:nowrap">Pilih per Skadron:</span>
          @foreach($lemdikList as $l)
            @php $lIds = $lemdikSkadikIds[$l->id] ?? []; @endphp
            @if(!empty($lIds))
            <button type="button" class="btn btn-sm btn-outline" data-lemdik-add="{{ $l->id }}"
                    onclick="toggleSkadikByLemdik('add', {{ $l->id }})"
                    style="font-size:11px;padding:3px 12px;border-radius:999px">
              {{ $l->singkat }}
            </button>
            @endif
          @endforeach
        </div>
        @endif
        <select name="skadik_ids[]" id="addSkadik" multiple size="5" style="min-height:120px">
          @foreach($skadikList as $s)
            <option value="{{ $s->id }}" data-lemdik="{{ $s->lemdik_id }}">{{ $s->nama }} ({{ $s->lemdik?->nama ?? '-' }})</option>
          @endforeach
        </select>
        <input type="hidden" name="skadik_id" id="addSkadikPrimary" value="">
        <div style="font-size:10px;color:#aaa;margin-top:3px">Klik <strong>Skadik XXX</strong> untuk pilih seluruh sekolah di skadron itu, atau tahan <strong>Ctrl/Cmd</strong> untuk pilih manual.</div>
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
  <form method="GET" action="{{ route('manage-user.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;width:100%">
    <div class="form-group" style="flex:2;min-width:200px;margin:0">
      <label>Cari Nama / Email</label>
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Ketik nama atau email...">
    </div>
    <div class="form-group" style="flex:1;min-width:180px;margin:0">
      <label>Filter Role</label>
      <select name="role">
        <option value="">Semua Role</option>
        @foreach($roles as $val => $label)
          <option value="{{ $val }}" {{ request('role') == $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group" style="flex:1;min-width:180px;margin:0">
      <label>Filter Sekolah</label>
      <select name="skadik_id">
        <option value="">Semua Sekolah</option>
        @foreach($skadikList as $s)
          <option value="{{ $s->id }}" {{ request('skadik_id') == $s->id ? 'selected' : '' }}>{{ $s->nama }}</option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="btn btn-outline" style="margin:0;height:36px">🔍 Filter</button>
  </form>
</div>

{{-- Tabel User --}}
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="text-align:center;width:40px">#</th>
          <th>Nama</th>
          <th>Email</th>
          <th style="text-align:center">Role</th>
          <th style="text-align:center">Sekolah</th>
          <th style="text-align:center;width:120px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($users as $idx => $u)
          @php
            $roleColor = match($u->role) {
              'super_admin' => '#7c3aed',
              'admin_akademik' => '#059669',
              'admin_kepribadian' => '#2563eb',
              'admin_samapta' => '#ea580c',
              'admin' => '#4f46e5',
              default => '#6b7280',
            };
          @endphp
          <tr>
            <td>{{ $users->firstItem() + $idx }}</td>
            <td>
              <strong>{{ $u->name }}</strong>
              @if($u->id === auth()->id())
                <span class="badge badge-blue" style="font-size:9px">Anda</span>
              @endif
            </td>
            <td style="font-size:12px;color:#666">{{ $u->email }}</td>
            <td style="text-align:center">
              <span class="badge" style="background:{{ $roleColor }}15;color:{{ $roleColor }};font-weight:600">
                {{ $u->module_label }}
              </span>
            </td>
            <td style="text-align:center;font-size:12px">
              @php
                // Gabungkan pivot skadiks + kolom tunggal skadik_id (anti duplikat).
                $assigned = $u->relationLoaded('skadiks') ? $u->skadiks : collect();
                if ($u->skadik_id && $u->skadik && !$assigned->contains('id', $u->skadik_id)) {
                    $assigned->push($u->skadik);
                }
                // Ringkas: bila user mencakup SEMUA sekolah di sebuah skadron
                // (Lemdik 301/302/303/304), tampilkan satu label "Skadik XXX"
                // alih-alih daftar nama sekolah individual.
                $lemdikCounts = $lemdikSkadikIds->map(fn ($ids) => count($ids))->toArray();
                $labels = \App\Models\Skadik::compactLabels($assigned, $lemdikCounts);
              @endphp
              @if($u->role === 'super_admin')
                <span class="badge badge-purple">Semua Sekolah</span>
              @elseif(!empty($labels))
                @foreach($labels as $lbl)
                  <span class="badge badge-blue" style="margin:1px;display:inline-block">{{ $lbl }}</span>
                @endforeach
              @else
                <span style="color:red;font-weight:600">⚠ Belum ditetapkan</span>
              @endif
            </td>
            <td style="text-align:center">
              @php
                $editIds = $u->skadiks->pluck('id')->map(fn($i)=>(int)$i)->values()->all();
                if (!$editIds && $u->skadik_id) $editIds = [(int)$u->skadik_id];
              @endphp
              <button type="button" onclick='openEdit({{ $u->id }}, {{ json_encode($u->name, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) }}, {{ json_encode($u->email, JSON_HEX_APOS|JSON_HEX_QUOT) }}, "{{ $u->role }}", {{ json_encode($editIds) }})' class="btn btn-sm btn-outline" title="Edit">✏️</button>
              @if($u->id !== auth()->id() && $u->role !== 'super_admin')
                <form method="POST" action="{{ route('manage-user.destroy', $u->id) }}" style="display:inline" onsubmit="return confirm('Hapus user {{ $u->name }}?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-danger" title="Hapus">🗑️</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px;display:flex;justify-content:center">
    {{ $users->withQueryString()->links() }}
  </div>
</div>

{{-- Modal Edit User --}}
<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:2000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-size:16px;font-weight:600">✏️ Edit User</h3>
      <button onclick="document.getElementById('modalEdit').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888">&times;</button>
    </div>
    <form method="POST" id="formEdit">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <input type="hidden" name="id" id="editId">
      <div class="form-group">
        <label>Nama</label>
        <input type="text" name="name" id="editName" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" id="editEmail" required>
      </div>
      <div class="form-group">
        <label>Password (kosongkan jika tidak diubah)</label>
        <div style="position:relative">
          <input type="password" name="password" id="editPw" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah">
          <button type="button" class="pw-toggle" onclick="togglePw('editPw',this)">👁️</button>
        </div>
      </div>
      <div class="form-group">
        <label>Role / Akses Modul</label>
        <select name="role" id="editRole" required onchange="toggleSkadikField('edit')">
          @foreach($roles as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group" id="skadikFieldEdit">
        <label>Sekolah <span style="color:red">*</span> <span id="skadikHintEdit" style="font-size:10px;color:#7c3aed;font-weight:600"></span></label>
        @if($lemdikList->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;align-items:center">
          <span style="font-size:10px;color:#888;font-weight:600;white-space:nowrap">Pilih per Skadron:</span>
          @foreach($lemdikList as $l)
            @php $lIds = $lemdikSkadikIds[$l->id] ?? []; @endphp
            @if(!empty($lIds))
            <button type="button" class="btn btn-sm btn-outline" data-lemdik-edit="{{ $l->id }}"
                    onclick="toggleSkadikByLemdik('edit', {{ $l->id }})"
                    style="font-size:11px;padding:3px 12px;border-radius:999px">
              {{ $l->singkat }}
            </button>
            @endif
          @endforeach
        </div>
        @endif
        <select name="skadik_ids[]" id="editSkadik" multiple size="5" style="min-height:120px">
          @foreach($skadikList as $s)
            <option value="{{ $s->id }}" data-lemdik="{{ $s->lemdik_id }}">{{ $s->nama }} ({{ $s->lemdik?->nama ?? '-' }})</option>
          @endforeach
        </select>
        <input type="hidden" name="skadik_id" id="editSkadikPrimary" value="">
        <div style="font-size:10px;color:#aaa;margin-top:3px">Klik <strong>Skadik XXX</strong> untuk pilih seluruh sekolah di skadron itu, atau tahan <strong>Ctrl/Cmd</strong> untuk pilih manual.</div>
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
// Toggle skadik field visibility & label hint based on role
function toggleSkadikField(prefix) {
  const roleId = prefix === 'add' ? 'addRole' : 'editRole';
  const fieldId = prefix === 'add' ? 'skadikFieldAdd' : 'skadikFieldEdit';
  const skadikId = prefix === 'add' ? 'addSkadik' : 'editSkadik';
  const hintId = prefix === 'add' ? 'skadikHintAdd' : 'skadikHintEdit';
  const role = document.getElementById(roleId).value;
  const field = document.getElementById(fieldId);
  const skadikSelect = document.getElementById(skadikId);
  const hint = document.getElementById(hintId);

  if (role === 'super_admin') {
    field.style.display = 'none';
    skadikSelect.removeAttribute('required');
  } else {
    field.style.display = 'block';
    skadikSelect.setAttribute('required', 'required');
    // Pesan hint dinamis sesuai role modul
    const multi = ['admin_akademik','admin_kepribadian','admin_samapta','admin'].includes(role);
    if (hint) hint.textContent = multi ? '(boleh >1 sekolah)' : '';
  }
}

// ═════════════════════════════════════════════════════════════
//  QUICK-SELECT "Skadik 301/302/303/304"
//  Klik chip skadron → pilih SEMUA sekolah di skadron itu sekaligus,
//  sehingga form Manage User lebih ringkas. Dipakai juga untuk menandai
//  status chip (aktif/belum) sinkron dgn isi <select>.
// ═════════════════════════════════════════════════════════════
window.__lemdikSkadikIds = @json($lemdikSkadikIds);
window.__lemdikLabels    = @json($lemdikList->mapWithKeys(fn($l) => [$l->id => $l->singkat]));

function toggleSkadikByLemdik(prefix, lemdikId) {
  const selId = prefix === 'add' ? 'addSkadik' : 'editSkadik';
  const sel = document.getElementById(selId);
  const ids = (window.__lemdikSkadikIds[lemdikId] || []).map(String);
  if (!ids.length) return;

  // Toggle: jika semua sdh terpilih → kosongkan; bila belum → pilih semua.
  const allSelected = ids.every(id => {
    const opt = sel.querySelector('option[value="' + id + '"]');
    return opt && opt.selected;
  });
  ids.forEach(id => {
    const opt = sel.querySelector('option[value="' + id + '"]');
    if (opt) opt.selected = !allSelected;
  });

  refreshSkadikChips(prefix);
}

// Sinkronkan tampilan chip (aktif = semua sekolah skadron terpilih).
function refreshSkadikChips(prefix) {
  const selId = prefix === 'add' ? 'addSkadik' : 'editSkadik';
  const sel = document.getElementById(selId);
  if (!sel) return;
  document.querySelectorAll('[data-lemdik-' + prefix + ']').forEach(chip => {
    const lemdikId = chip.getAttribute('data-lemdik-' + prefix);
    const ids = (window.__lemdikSkadikIds[lemdikId] || []).map(String);
    const allSelected = ids.length > 0 && ids.every(id => {
      const opt = sel.querySelector('option[value="' + id + '"]');
      return opt && opt.selected;
    });
    chip.classList.toggle('btn-primary', allSelected);
    chip.classList.toggle('btn-outline', !allSelected);
    const label = window.__lemdikLabels[lemdikId] || ('Skadik ' + lemdikId);
    chip.textContent = (allSelected ? '✓ ' : '+ ') + label;
  });
}

function openEdit(id, name, email, role, skadikIds) {
  document.getElementById('editId').value = id;
  document.getElementById('editName').value = name;
  document.getElementById('editEmail').value = email;
  document.getElementById('editRole').value = role;

  // ── PENTING (revisi 13 Agustus 2026) ───────────────────────
  // Selalu kosongkan field password saat membuka modal edit.
  // Tanpa ini, password bisa ter-autofill oleh browser (password
  // manager) atau menyisakan nilai dari edit user sebelumnya, lalu
  // ikut tersimpan saat admin hanya bermaksud mengubah role/skadik
  // → password user diam-diam berubah. Inilah akar bug "password
  // berubah sendiri keesokan harinya".
  var editPwField = document.getElementById('editPw');
  if (editPwField) editPwField.value = '';

  // Pilih (multi-select) skadik yang sudah ditugaskan ke user.
  const sel = document.getElementById('editSkadik');
  const ids = Array.isArray(skadikIds) ? skadikIds.map(String) : (skadikIds ? [String(skadikIds)] : []);
  Array.from(sel.options).forEach(opt => { opt.selected = ids.includes(String(opt.value)); });

  document.getElementById('formEdit').action = '/manage-user/' + id;
  document.getElementById('modalEdit').style.display = 'flex';
  toggleSkadikField('edit');
  refreshSkadikChips('edit');
}

function togglePw(id, btn) {
  const i = document.getElementById(id);
  if (i.type === 'password') { i.type = 'text'; btn.textContent = '🙈'; }
  else { i.type = 'password'; btn.textContent = '👁️'; }
}

// Close modal on backdrop click
document.querySelectorAll('#modalTambah, #modalEdit').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});

// Sinkron status chip saat user memilih sekolah manual (Ctrl/Cmd-click).
['addSkadik', 'editSkadik'].forEach(selId => {
  const sel = document.getElementById(selId);
  if (sel) sel.addEventListener('change', () => refreshSkadikChips(selId === 'addSkadik' ? 'add' : 'edit'));
});

// Init: hide skadik if super_admin is selected by default
toggleSkadikField('add');
refreshSkadikChips('add');
refreshSkadikChips('edit');
</script>
@endpush
