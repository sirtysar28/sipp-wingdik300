@extends('layouts.app')
@section('title', 'NPS — Nilai Prestasi Samapta')
@section('page-title', 'NPS — Nilai Prestasi Samapta')
@section('topbar-actions')
<a href="{{ route('nilai-samapta.manual.form', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" class="btn btn-outline btn-sm">✏️ Input Manual</a>
@endsection

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">⚡ NPS — Nilai Prestasi Samapta</h2>
    <p style="font-size:12px;color:#888">Input sederhana 5 field manual (tanpa rumus): Jarak Lari, Nilai Lari (Garjas A), Garjas B, Nilai Akhir, Nilai Konversi.</p>
  </div>
</div>

{{-- Filter --}}
<div class="card" style="margin-bottom:14px">
  <form method="GET" action="{{ route('nilai-samapta.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label style="font-size:11px;margin-bottom:3px">Sekolah</label>
      <select name="skadik_id" onchange="this.form.submit()">
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $sk->id == $skadikId ? 'selected' : '' }}>{{ $sk->nama }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Angkatan</label>
      <select name="angkatan_id" onchange="this.form.submit()">
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id == $angkatanId ? 'selected' : '' }}>Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Putaran</label>
      <select name="putaran_label" onchange="this.form.submit()">
        @foreach($putaranList as $pl)
          <option value="{{ $pl }}" {{ $putaranLabel == $pl ? 'selected' : '' }}>{{ $pl }}</option>
        @endforeach
      </select>
    </div>
  </form>
</div>

@if(!$angkatan)
  <div class="empty-state"><p>Pilih angkatan untuk melihat data NPS.</p></div>
@else
  {{-- Info angkatan --}}
  <div class="card" style="margin-bottom:16px;border-left:4px solid #ea580c;padding:12px 16px">
    <div style="font-size:12px;color:#888">
      <strong>{{ $angkatan->skadik->lemdik?->wingdik ?? '' }}</strong> — {{ $angkatan->skadik->nama ?? '' }} |
      Angkatan {{ $angkatan->nomor_angkatan }} ({{ $angkatan->tahun_masuk }})
      | <strong style="color:#ea580c">Putaran: {{ $putaranLabel }}</strong>
    </div>
  </div>

  {{-- Upload Area --}}
  <div class="card" style="margin-bottom:16px;border:2px dashed #d0d0d8">
    <div class="card-title">📄 Upload Data NPS (Bulk Import)</div>
    <div style="font-size:12px;color:#888;margin-bottom:12px">
      1. <a href="{{ route('nilai-samapta.template', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" style="color:#059669;font-weight:600">Unduh Template</a>
      &nbsp;→&nbsp; 2. Copy data Anda ke kolom D–H &nbsp;→&nbsp; 3. Upload file yang sudah diisi
    </div>
    <form method="POST" action="{{ route('nilai-samapta.import') }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      <div class="form-group" style="margin-bottom:0;min-width:220px">
        <label style="font-size:11px">File Excel (.xlsx)</label>
        <input type="file" name="file" accept=".xlsx,.xls" required>
      </div>
      <div class="form-group" style="margin-bottom:0;min-width:170px">
        <label style="font-size:11px">Simpan ke Putaran</label>
        <input type="text" name="putaran_label" value="{{ $putaranLabel }}" placeholder="Putaran 1">
        <span style="font-size:10px;color:#aaa">Ubah nama putaran (opsional)</span>
      </div>
      <button type="submit" class="btn btn-success btn-sm">📤 Upload</button>
    </form>
    <div style="font-size:11px;color:#aaa;margin-top:8px">
      Catatan: semua nilai diisi manual. Tidak ada perhitungan otomatis. Data untuk putaran yang sama akan menimpa yang lama.
    </div>
  </div>

  @if($data->count() === 0)
    <div class="empty-state card">
      <p>Belum ada data NPS untuk <strong>Putaran {{ $putaranLabel }}</strong>.</p>
      <p style="font-size:12px;margin-top:4px">Unduh template, isi, lalu upload.</p>
    </div>
  @else

  {{-- Metrics --}}
  <div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card">
      <div class="metric-label">Total Peserta</div>
      <div class="metric-value">{{ $data->count() }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Rata-rata NPS</div>
      <div class="metric-value" style="color:#059669">{{ round($data->avg('nilai_konversi') ?? 0, 2) }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Tertinggi</div>
      <div class="metric-value" style="color:#4f46e5">{{ round($data->max('nilai_konversi') ?? 0, 2) }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Terendah</div>
      <div class="metric-value" style="color:#dc2626">{{ round($data->min('nilai_konversi') ?? 0, 2) }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">NPS Putaran Terakhir (dipakai NPP)</div>
      <div class="metric-value" style="color:#d97706">{{ round(count($rataNps) ? array_sum($rataNps) / count($rataNps) : 0, 2) }}</div>
    </div>
  </div>

  {{-- Tabel --}}
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr style="background:#ffffff;color:#000">
            <th>No</th>
            <th>Nama</th>
            <th>Pangkat</th>
            <th>NRP</th>
            <th>Jarak Lari (m)</th>
            <th>Nilai Lari (Garjas A)</th>
            <th>Garjas B</th>
            <th>Nilai Akhir</th>
            <th>Nilai Konversi (NPS)</th>
            <th>NPS Putaran Terakhir<br><span style="font-weight:400;font-size:10px">(dipakai untuk NPP)</span></th>
            <th>Kategori</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($data as $idx => $d)
          <tr>
            <td style="text-align:center;color:#888">{{ $idx + 1 }}</td>
            <td><strong>{{ $d->peserta->nama }}</strong></td>
            <td>{{ $d->peserta->pangkat }}</td>
            <td style="font-family:Arial,Helvetica,sans-serif;font-size:12px">{{ $d->peserta->nrp }}</td>
            <td style="text-align:center">{{ $d->jarak_lari ?? '-' }}</td>
            <td style="text-align:center;font-weight:600">{{ $d->nilai_lari ?? '-' }}</td>
            <td style="text-align:center;font-weight:600">{{ $d->garjas_b_nilai ?? '-' }}</td>
            <td style="text-align:center;background:#ffffff">
              <span style="font-weight:700;font-size:15px">{{ $d->nilai_akhir ?? '-' }}</span>
            </td>
            {{-- Urutan hasil NPS: jarak lari → nilai lari (Garjas A) → Garjas B → nilai akhir → nilai konversi (NPS) → kategori --}}
            {{-- Revisi 26 Agustus 2026: kategori Baik/Cukup/Kurang berbasis NILAI KONVERSI --}}
            <td style="text-align:center">
              <span style="font-weight:700;font-size:14px;color:{{ $d->predikat['color'] }}">{{ $d->nilai_konversi ?? '-' }}</span>
            </td>
            <td style="text-align:center;background:#fff7ed">
              <span style="font-weight:700;font-size:14px;color:#d97706">{{ isset($rataNps[$d->peserta_didik_id]) ? round($rataNps[$d->peserta_didik_id], 2) : '-' }}</span>
            </td>
            <td style="text-align:center">
              @if($d->nilai_konversi !== null)
                <span style="font-size:11px;font-weight:700;color:{{ $d->predikat['color'] }}">{{ $d->predikat['icon'] }} {{ $d->predikat['label'] }}</span>
              @else
                <span style="color:#888">-</span>
              @endif
            </td>
            <td>
              <a href="{{ route('nilai-samapta.edit', ['angkatan_id' => $angkatanId, 'peserta_id' => $d->peserta_didik_id, 'putaran_label' => $putaranLabel]) }}" class="btn btn-sm btn-outline" title="Edit">✏️</a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
      <form method="POST" action="{{ route('nilai-samapta.destroy') }}" onsubmit="return confirm('Hapus SEMUA data NPS Putaran {{ $putaranLabel }} pada angkatan ini?')">
        @csrf @method('DELETE')
        <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
        <input type="hidden" name="putaran_label" value="{{ $putaranLabel }}">
        <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus Putaran Ini</button>
      </form>
      <form method="POST" action="{{ route('nilai-samapta.destroy') }}" onsubmit="return confirm('Hapus SEMUA data NPS angkatan ini (SEMUA putaran)?')">
        @csrf @method('DELETE')
        <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
        <input type="hidden" name="putaran_label" value="all">
        <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus Semua Putaran</button>
      </form>
      <a href="{{ route('nilai-samapta.ekspor', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" class="btn btn-outline btn-sm">📥 Ekspor</a>
      <a href="{{ route('nilai-samapta.cetak', ['angkatan_id' => $angkatanId, 'putaran_label' => $putaranLabel]) }}" class="btn btn-primary btn-sm" target="_blank">🖨️ Cetak</a>
    </div>
  </div>
  @endif
@endif
@endsection
