@extends('layouts.app')
@section('page-title', 'Report NPK — Rekap & Analisis Kepribadian')

@section('topbar-actions')
<a href="{{ route('kepribadian.index') }}" class="btn btn-primary btn-sm">✏️ Input Manual</a>
<a href="{{ route('kepribadian.import.form') }}" class="btn btn-outline btn-sm">📥 Bulk Upload</a>
<a href="{{ route('report.npk.ekspor', ['angkatan_id'=>$angkatanId]) }}" class="btn btn-success btn-sm">⬇ Ekspor NPK</a>
{{-- Revisi 29 Agustus 2026: cetak PDF NPK hanya super_admin/Opsdik + Danflight --}}
@if(auth()->user()->canSeeAll() || auth()->user()->isAdminKepribadian())
<a href="{{ route('report.npk.cetak', ['angkatan_id'=>$angkatanId]) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak Laporan</a>
@endif
@endsection

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">📊 Report NPK — Rekap & Analisis Kepribadian</h2>
    <p style="font-size:12px;color:#888">Rekap nilai kepribadian semua periode per angkatan</p>
  </div>
</div>

{{-- Filter --}}
<div class="card" style="margin-bottom:14px">
  <form method="GET" action="{{ route('report.npk') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label style="font-size:11px;margin-bottom:3px">Sekolah</label>
      <select name="skadik_id">
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $sk->id==$skadikId?'selected':'' }}>{{ $sk->nama }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Angkatan</label>
      <select name="angkatan_id">
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id==$angkatanId?'selected':'' }}>Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})</option>
        @endforeach
      </select>
    </div>
    {{-- Revisi 2 September 2026: tombol "Tampilkan" — hasil baru dimuat setelah
         tombol ini ditekan, memastikan sekolah-angkatan yg tampil sesuai pilihan
         user (selaras dengan Report NPA & NPS). --}}
    <div>
      <button type="submit" name="tampilkan" value="1" class="btn btn-primary btn-sm" style="height:38px">🔍 Tampilkan</button>
    </div>
  </form>
</div>

@if(!$angkatan)
  <div class="empty-state">Pilih angkatan untuk melihat report.</div>
@elseif(!($submitted ?? false))
  {{-- Belum klik Tampilkan --}}
  <div class="card">
    <div class="empty-state" style="padding:48px">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:40px;height:40px;margin:0 auto 12px;opacity:.4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <div style="font-size:15px;font-weight:500;color:#888;margin-top:8px">
        Pilih <strong>Sekolah</strong> &amp; <strong>Angkatan</strong>, lalu klik tombol
        <strong style="color:#4f46e5">🔍 Tampilkan</strong> untuk memuat hasil Report NPK.
      </div>
    </div>
  </div>
@else

{{-- KEOLOLA PERIODE (edit superadmin + hard delete)
     Revisi 26 Agustus 2026: periode uji coba (mis. Periode 1 dan 6) bisa
     dihapus permanen beserta seluruh data nilainya langsung dari sini,
     sehingga tidak tampil lagi di tabel report.
     Revisi 2 Oktober 2026: SUPERADMIN dapat MENGEDIT periode (label & rentang
     tanggal) tanpa menghapus data nilai di dalamnya. --}}
@if((auth()->user()->isAdminKepribadian() || auth()->user()->canSeeAll()) && $periodeKelola->count() > 0)
<details class="card" style="margin-bottom:14px;border:1px solid #fecaca;background:#fff7f7">
  <summary style="cursor:pointer;font-weight:600;font-size:13px;color:#b91c1c;padding:4px 0">
    🧹 Kelola Periode ({{ auth()->user()->isSuperAdmin() ? 'Edit + ' : '' }}Hapus Permanen) — {{$periodeKelola->count()}} periode terdaftar
  </summary>
  <div style="margin-top:12px;font-size:12px;color:#888;line-height:1.5">
    Menghapus periode akan <strong style="color:#dc2626">menghapus permanen (hard delete)</strong>
    periode beserta seluruh data nilai kepribadian &amp; detail aspek di dalamnya.
    Gunakan untuk membersihkan periode uji coba / yang salah input.
    @if(auth()->user()->isSuperAdmin())
    <br><strong style="color:#4f46e5">✏️ Edit</strong> hanya mengubah label &amp; rentang tanggal periode —
    seluruh data nilai di dalamnya tetap utuh (khusus Superadmin).
    @endif
  </div>
  <div class="table-wrap" style="margin-top:10px">
    <table>
      <thead>
        <tr>
          <th style="text-align:center">No</th>
          <th>Label Periode</th>
          <th style="text-align:center">Rentang Tanggal</th>
          <th style="text-align:center">Jumlah Data Nilai</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($periodeKelola as $i => $pk)
        <tr>
          <td style="text-align:center">{{ $i + 1 }}</td>
          <td><strong>{{ $pk->label }}</strong></td>
          <td style="text-align:center;font-size:12px">
            {{ $pk->tanggal_mulai?->format('d M Y') }} – {{ $pk->tanggal_selesai?->format('d M Y') }}
          </td>
          <td style="text-align:center">{{ $pk->jumlah_nilai }} nilai</td>
          <td style="text-align:center">
            @if($pk->aktif)
              <span class="badge badge-green">Aktif</span>
            @else
              <span class="badge" style="background:#f3f4f6;color:#666">Non-aktif</span>
            @endif
          </td>
          <td style="text-align:center">
            <div style="display:flex;gap:6px;justify-content:center;align-items:center">
              {{-- Revisi 2 Oktober 2026: edit periode NPK — khusus SUPERADMIN --}}
              @if(auth()->user()->isSuperAdmin())
                <a href="{{ route('kepribadian.periode.edit', ['periode' => $pk->id, 'back' => request()->fullUrl()]) }}" class="btn btn-sm btn-outline" title="Edit Periode (Superadmin)">✏️ Edit</a>
              @endif
              <form method="POST" action="{{ route('kepribadian.periode.destroy', $pk->id) }}"
                    onsubmit="return confirm('HAPUS PERMANEN periode {{ $pk->label }} beserta {{ $pk->jumlah_nilai }} data nilai kepribadian?\nTindakan ini TIDAK bisa dibatalkan.')">
                @csrf @method('DELETE')
                <input type="hidden" name="redirect" value="{{ request()->fullUrlWithQuery([]) }}">
                <button type="submit" class="btn btn-danger btn-sm">🗑️ Hard Delete</button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</details>
@endif


{{-- Metrik — Revisi 2 Oktober 2026: acuan NPK kini AKUMULATIF (Σ) bukan rata-rata --}}
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-label">Total Peserta</div><div class="metric-value">{{ $data->count() }}</div></div>
  <div class="metric-card"><div class="metric-label">Total Periode</div><div class="metric-value">{{ $periodes->count() }}</div></div>
  <div class="metric-card"><div class="metric-label">Total Akumulatif Angkatan</div><div class="metric-value" style="color:#2563eb">{{ $data->filter(fn($b) => $b['akumulatif'] !== null)->count() > 0 ? number_format($data->sum(fn($b) => $b['akumulatif'] ?? 0), 2) : '—' }}</div></div>
  <div class="metric-card"><div class="metric-label">Akumulatif Tertinggi</div><div class="metric-value" style="color:#059669">{{ $data->max('akumulatif') ? round($data->max('akumulatif'), 2) : '—' }}</div></div>
</div>

{{-- Tabel --}}
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Nama</th>
          <th>Pangkat</th>
          <th>NRP</th>
          @foreach($periodes as $per)
            <th style="text-align:center;min-width:80px">{{ $per->label }}</th>
          @endforeach
          <th style="text-align:center;background:#ffffff;min-width:80px">Akumulatif</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($data as $i => $row)
        <tr>
          <td style="text-align:center;font-weight:600">{{ $i + 1 }}</td>
          <td><strong>{{ $row['peserta']->nama }}</strong></td>
          <td>{{ $row['peserta']->pangkat }}</td>
          <td style="font-size:12px;color:#888">{{ $row['peserta']->nrp }}</td>
          @foreach($periodes as $per)
            @php $n = $row['nilai_per_periode'][$per->id]['nilai'] ?? null; @endphp
            <td style="text-align:center">
              @if($n !== null)
                @php
                  $color = $n >= 78 ? '#059669' : ($n >= 76 ? '#2563eb' : ($n >= 75 ? '#d97706' : '#dc2626'));
                  $bg = $n >= 78 ? '#ecfdf5' : ($n >= 76 ? '#eff6ff' : ($n >= 75 ? '#fffbeb' : '#fef2f2'));
                @endphp
                <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:12px;font-weight:500;background:{{ $bg }};color:{{ $color }}">{{ round($n,2) }}</span>
              @else
                <span style="color:#999">—</span>
              @endif
            </td>
          @endforeach
          <td style="text-align:center;background:#ffffff">
            {{-- Revisi 2 Oktober 2026: AKUMULATIF (Σ seluruh periode) — pengganti
                 rata-rata sebagai acuan urutan ranking NPK. --}}
            <strong style="font-size:14px;color:#2563eb">{{ $row['akumulatif'] ?? '—' }}</strong>
          </td>
          <td>
            {{-- Revisi 30 Sept 2026: aksi utama di Report NPK = DETAIL NILAI
                 KEPRIBADIAN per siswa (kepribadian.show), bukan laporan
                 individual (nilai akhir). Tombol 📋 laporan individual tetap
                 disediakan sesuai hak akses. --}}
            @php
              $u = auth()->user();
            @endphp
            @if($u->isSuperAdmin() || $u->isAdmin() || $u->isAdminKepribadian())
              <a href="{{ route('kepribadian.show', $row['peserta']->id) }}" class="btn btn-sm btn-outline" title="Detail Nilai Kepribadian">🧠</a>
            @endif
            @if($u->canSeeAll() || $u->isAdminAkademik())
              <a href="{{ route('report.individu', ['angkatan_id'=>$angkatanId,'peserta_id'=>$row['peserta']->id]) }}" class="btn btn-sm btn-outline" title="Laporan Individual (NPP)">📋</a>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
      @if($data->count() > 1)
      <tfoot>
        <tr style="background:#ffffff">
          {{-- Revisi 2 Oktober 2026: footer kini AKUMULATIF (Σ) — kolom periode
               berisi jumlah nilai seluruh peserta pada periode tsb, kolom akhir
               = total akumulatif angkatan. --}}
          <td colspan="3" style="font-weight:600;font-size:12px;color:#2563eb">Akumulatif Angkatan</td>
          @foreach($periodes as $per)
            @php
              $sumPer = $data->map(fn($b) => $b['nilai_per_periode'][$per->id]['nilai'] ?? null)->filter()->sum();
            @endphp
            <td style="text-align:center;font-weight:600;color:#2563eb">{{ $sumPer > 0 ? number_format($sumPer, 2) : '—' }}</td>
          @endforeach
          <td style="text-align:center;font-weight:700;color:#2563eb;font-size:14px">{{ $data->filter(fn($b) => $b['akumulatif'] !== null)->count() > 0 ? number_format($data->sum(fn($b) => $b['akumulatif'] ?? 0), 2) : '—' }}</td>
          <td></td>
        </tr>
      </tfoot>
      @endif
    </table>
  </div>
</div>
@endif
@endsection
