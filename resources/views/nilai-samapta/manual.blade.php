@extends('layouts.app')
@section('title', 'Input Manual NPS')
@section('page-title', 'Input Manual NPS')
@section('topbar-actions')
<a href="{{ route('nilai-samapta.index', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">✏️ Input Manual NPS</h2>
    <p style="font-size:12px;color:#888">Isi nilai NPS untuk satu peserta. Untuk input banyak peserta sekaligus, gunakan <a href="{{ route('nilai-samapta.index', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" style="color:#4f46e5">Upload Template</a>.</p>
  </div>
</div>

{{-- Filter angkatan (GET, terpisah dari form input) --}}
<div class="card" style="margin-bottom:14px">
  <form method="GET" action="{{ route('nilai-samapta.manual.form') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <div class="form-group" style="flex:1;min-width:240px;margin:0">
      <label style="font-size:11px;margin-bottom:3px">Angkatan</label>
      <select name="angkatan_id" onchange="this.form.submit()">
        <option value="">-- Pilih Angkatan --</option>
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id == $angkatanId ? 'selected' : '' }}>
            {{ $ang->skadik->nama ?? '' }} — Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group" style="flex:1;min-width:180px;margin:0">
      <label style="font-size:11px;margin-bottom:3px">Putaran</label>
      <input type="text" name="putaran_label" value="{{ $putaranLabel }}" placeholder="Putaran 1">
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Terapkan</button>
  </form>
</div>

@if(!$angkatanId)
  <div class="empty-state"><p>Pilih angkatan terlebih dahulu untuk mulai input nilai NPS.</p></div>
@else
<div style="max-width:620px">
  <div class="card">
    <div class="card-title">⚡ Input NPS (5 Field Manual — Tanpa Rumus)</div>

    <form method="POST" action="{{ route('nilai-samapta.manual.store') }}">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">

      <div class="form-group">
        <label>Peserta Didik <span style="color:#dc2626">*</span></label>
        <select name="peserta_didik_id" required>
          <option value="">-- Pilih Peserta --</option>
          @foreach($pesertaList as $p)
            <option value="{{ $p->id }}" {{ old('peserta_didik_id') == $p->id ? 'selected' : '' }}>{{ $p->nama }} ({{ $p->nrp }})</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label>Putaran</label>
        <input type="text" name="putaran_label" value="{{ old('putaran_label', $putaranLabel) }}" list="putaran-list" placeholder="Putaran 1">
        <datalist id="putaran-list">
          @foreach($putaranList as $pl)
            <option value="{{ $pl }}">
          @endforeach
        </datalist>
        <span style="font-size:10px;color:#aaa">Kosongkan untuk default (Putaran 1). Isi nama lain untuk membuat putaran baru.</span>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px">
        <div class="form-group">
          <label>Jarak Lari (meter)</label>
          <input type="number" name="jarak_lari" value="{{ old('jarak_lari') }}" min="0" step="0.01" placeholder="2800">
        </div>
        <div class="form-group">
          <label>Nilai Lari (Garjas A)</label>
          <input type="number" name="nilai_lari" value="{{ old('nilai_lari') }}" min="0" max="100" step="0.01" placeholder="80.50">
        </div>
        <div class="form-group">
          <label>Garjas B</label>
          <input type="number" name="garjas_b_nilai" value="{{ old('garjas_b_nilai') }}" min="0" max="100" step="0.01" placeholder="75.00">
        </div>
        <div class="form-group">
          <label>Nilai Akhir</label>
          <input type="number" name="nilai_akhir" value="{{ old('nilai_akhir') }}" min="0" max="100" step="0.01" placeholder="77.75">
        </div>
        <div class="form-group" style="grid-column:1 / -1">
          <label>Nilai Konversi</label>
          <input type="number" name="nilai_konversi" value="{{ old('nilai_konversi') }}" min="0" max="100" step="0.01" placeholder="78.00">
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">💾 Simpan</button>
        <a href="{{ route('nilai-samapta.index', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>
@endif
@endsection
