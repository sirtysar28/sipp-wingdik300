@extends('layouts.app')
@section('title', 'Tambah Angkatan')
@section('page-title', 'Tambah Angkatan Baru')
@section('topbar-actions')
<a href="{{ route('angkatan.index') }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@php
  // Susun opsi sekolah untuk combobox searchable di sisi JS.
  // Dipecah ke variabel sederhana agar @json() di Blade tidak salah-parser
  // (ekspresi kompleks dgn tanda kurung di dalam string bikin regex Blade tertukar).
  $oldSkadik = old('skadik_id');
  $selectedSkadik = $oldSkadik ? $skadik->firstWhere('id', $oldSkadik) : null;
  $skadikOptions = $skadik->map(function ($s) {
      return [
          'id'    => (string) $s->id,
          'nama'  => $s->nama,
          'lemdik'=> $s->lemdik?->nama ?? '',
          'label' => $s->nama . ($s->lemdik ? ' (' . $s->lemdik->nama . ')' : ''),
      ];
  })->values();
@endphp

@section('content')
<div style="max-width:500px">
  <div class="card">
    <form method="POST" action="{{ route('angkatan.store') }}">
      @csrf
      <div class="form-group">
        <label>Sekolah <span style="color:#dc2626">*</span></label>
        {{-- Combobox searchable: nilai terpilih dikirim via hidden input,
             tampilan teks bisa diketik untuk memfilter daftar sekolah. --}}
        <div class="sipp-combo" id="skadikCombo">
          <input type="hidden" name="skadik_id" id="skadikIdInput"
                 value="{{ $oldSkadik ?? '' }}" required>
          <input type="text" id="skadikSearchInput"
                 value="{{ $selectedSkadik?->nama }}{{ $selectedSkadik && $selectedSkadik->lemdik ? ' (' . $selectedSkadik->lemdik->nama . ')' : '' }}"
                 placeholder="Ketik nama sekolah…" autocomplete="off"
                 aria-label="Cari sekolah">
          <div class="sipp-combo-list" id="skadikComboList" role="listbox"></div>
        </div>
        <div class="sipp-combo-hint">Ketik untuk mencari, gunakan panah ↑ ↓ lalu Enter untuk memilih.</div>
        @error('skadik_id')<div style="color:#b91c1c;font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
      </div>
      <div class="form-group">
        <label>Nomor Angkatan <span style="color:#dc2626">*</span></label>
        <input type="text" name="nomor_angkatan" value="{{ old('nomor_angkatan') }}" placeholder="22" required>
      </div>
      <div class="form-group">
        <label>Tahun Masuk <span style="color:#dc2626">*</span></label>
        <input type="number" name="tahun_masuk" value="{{ old('tahun_masuk', date('Y')) }}" min="2000" max="2099" required>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Simpan Angkatan</button>
        <a href="{{ route('angkatan.index') }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>

<style>
/* Combobox searchable SIPP */
.sipp-combo{position:relative}
.sipp-combo-list{
  position:absolute;top:100%;left:0;right:0;z-index:50;
  max-height:240px;overflow-y:auto;
  background:#fff;border:1px solid #d0d0d8;border-top:none;
  border-radius:0 0 7px 7px;display:none;
  box-shadow:0 6px 14px rgba(0,0,0,.08);
}
.sipp-combo.open .sipp-combo-list{display:block}
.sipp-combo.open input#skadikSearchInput{border-radius:7px 7px 0 0;border-color:#4f46e5}
.sipp-combo-item{padding:8px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid #f4f5f7;line-height:1.4}
.sipp-combo-item:last-child{border-bottom:none}
.sipp-combo-item.active,.sipp-combo-item:hover{background:#eef2ff;color:#4f46e5}
.sipp-combo-item .sipp-combo-sub{font-size:11px;color:#999}
.sipp-combo-empty{padding:10px 12px;font-size:12px;color:#aaa;text-align:center}
.sipp-combo-hint{font-size:11px;color:#aaa;margin-top:4px}
</style>

@push('scripts')
<script>
(function () {
  const data = @json($skadikOptions);

  const combo     = document.getElementById('skadikCombo');
  const idInput   = document.getElementById('skadikIdInput');
  const search    = document.getElementById('skadikSearchInput');
  const list      = document.getElementById('skadikComboList');
  let items = [];      // rendered DOM nodes
  let activeIdx = -1;  // highlighted index

  function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

  function render(query) {
    const q = (query || '').trim().toLowerCase();
    const pool = q === ''
      ? data
      : data.filter(d => d.nama.toLowerCase().indexOf(q) !== -1 || d.lemdik.toLowerCase().indexOf(q) !== -1);

    list.innerHTML = '';
    items = [];

    if (pool.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'sipp-combo-empty';
      empty.textContent = 'Sekolah tidak ditemukan.';
      list.appendChild(empty);
      activeIdx = -1;
      return;
    }

    pool.forEach((d, i) => {
      const el = document.createElement('div');
      el.className = 'sipp-combo-item';
      el.setAttribute('role', 'option');
      el.dataset.id = d.id;
      el.innerHTML = '<div>' + escapeHtml(d.nama) + '</div>' +
                     (d.lemdik ? '<div class="sipp-combo-sub">' + escapeHtml(d.lemdik) + '</div>' : '');
      el.addEventListener('mousedown', e => { e.preventDefault(); selectItem(d); });
      list.appendChild(el);
      items.push(el);
    });
    activeIdx = q === '' ? -1 : 0;
    highlight();
  }

  function highlight() {
    items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
    const cur = items[activeIdx];
    if (cur) cur.scrollIntoView({ block: 'nearest' });
  }

  function selectItem(d) {
    idInput.value = d.id;
    search.value = d.label;
    close();
  }

  function open() {
    combo.classList.add('open');
    render(search.value);
  }

  function close() {
    combo.classList.remove('open');
    list.innerHTML = '';
    items = [];
    activeIdx = -1;
  }

  // Jika teks diubah manual, pastikan id terpilih di-reset agar tidak
  // ikut menyekolah yg salah.
  search.addEventListener('input', () => {
    if (idInput.value !== '') {
      const cur = data.find(d => d.id === idInput.value);
      if (!cur || search.value !== cur.label) idInput.value = '';
    }
    open();
  });

  search.addEventListener('focus', open);

  search.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!combo.classList.contains('open')) { open(); return; }
      if (items.length) { activeIdx = (activeIdx + 1) % items.length; highlight(); }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (!combo.classList.contains('open')) { open(); return; }
      if (items.length) { activeIdx = (activeIdx - 1 + items.length) % items.length; highlight(); }
    } else if (e.key === 'Enter') {
      if (combo.classList.contains('open') && activeIdx >= 0 && items[activeIdx]) {
        e.preventDefault();
        const d = data.find(x => x.id === items[activeIdx].dataset.id);
        if (d) selectItem(d);
      }
    } else if (e.key === 'Escape') {
      close();
    }
  });

  document.addEventListener('click', e => {
    if (!combo.contains(e.target)) close();
  });
})();
</script>
@endpush
@endsection
