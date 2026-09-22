@extends('layouts.app')
@section('title', 'Edit NPS')
@section('page-title', 'Edit NPS')

@section('topbar-actions')
<a href="{{ route('nilai-samapta.index', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">✏️ Edit Nilai NPS</h2>
    <p style="font-size:12px;color:#888">
      @if($nilai)
        {{ $nilai->peserta?->pangkat }} {{ $nilai->peserta?->nama }} — NRP: {{ $nilai->peserta?->nrp }}
        | Putaran: <strong>{{ $nilai->putaran_label }}</strong>
      @else
        Peserta belum punya nilai NPS untuk Putaran {{ $putaranLabel }}.
      @endif
    </p>
  </div>
</div>

<div style="max-width:620px">
  <div class="card">
    <div class="card-title">⚡ Input NPS (5 Field Manual — Tanpa Rumus)</div>
    <div style="font-size:11px;color:#888;margin-bottom:14px">Isi kelima nilai sesuai data Anda. Tidak ada perhitungan otomatis.</div>

    <form method="POST" action="{{ $nilai ? route('nilai-samapta.update') : route('nilai-samapta.manual.store') }}">
      @csrf
      @if($nilai)
        <input type="hidden" name="nilai_id" value="{{ $nilai->id }}">
      @else
        <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
        <input type="hidden" name="peserta_didik_id" value="{{ $pesertaId }}">
        <input type="hidden" name="putaran_label" value="{{ $putaranLabel }}">
      @endif

      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px">
        <div class="form-group">
          <label>Jarak Lari (meter)</label>
          <input type="number" name="jarak_lari" value="{{ old('jarak_lari', $nilai?->jarak_lari) }}" min="0" step="0.01" placeholder="2800">
        </div>
        <div class="form-group">
          <label>Nilai Lari (Garjas A)</label>
          <input type="number" name="nilai_lari" value="{{ old('nilai_lari', $nilai?->nilai_lari) }}" min="0" max="100" step="0.01" placeholder="80.50">
        </div>
        <div class="form-group">
          <label>Garjas B</label>
          <input type="number" name="garjas_b_nilai" value="{{ old('garjas_b_nilai', $nilai?->garjas_b_nilai) }}" min="0" max="100" step="0.01" placeholder="75.00">
        </div>
        <div class="form-group">
          <label>Nilai Akhir</label>
          <input type="number" name="nilai_akhir" value="{{ old('nilai_akhir', $nilai?->nilai_akhir) }}" min="0" max="100" step="0.01" placeholder="77.75">
        </div>
        <div class="form-group" style="grid-column:1 / -1">
          <label>Nilai Konversi</label>
          <input type="number" name="nilai_konversi" value="{{ old('nilai_konversi', $nilai?->nilai_konversi) }}" min="0" max="100" step="0.01" placeholder="78.00">
        </div>
      </div>

      @if($nilai)
        <div style="background:#f5f3ff;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px">
          <strong style="color:#7c3aed">Kategori:</strong>
          <span style="color:{{ $nilai->predikat['color'] }};font-weight:600">{{ $nilai->predikat['icon'] }} {{ $nilai->predikat['label'] }}</span>
          <span style="color:#aaa"> (otomatis dari Nilai Konversi / NPS)</span>
        </div>
      @endif

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">💾 Simpan</button>
        <a href="{{ route('nilai-samapta.index', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" class="btn btn-outline">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
