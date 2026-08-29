@extends('layouts.app')
@section('page-title', 'Report Individual — Akhir Pendidikan')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">📋 Report Individual — Akhir Pendidikan</h2>
    <p style="font-size:12px;color:#888">Laporan lengkap NPA, NPK, NPS per peserta didik</p>
  </div>
</div>

{{-- Filter --}}
<div class="card" style="margin-bottom:14px">
  <form method="GET" action="{{ route('report.individu') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label style="font-size:11px;margin-bottom:3px">Sekolah</label>
      <select name="skadik_id" onchange="this.form.submit()">
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $sk->id==$skadikId?'selected':'' }}>{{ $sk->nama }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Angkatan</label>
      <select name="angkatan_id" onchange="this.form.submit()">
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id==$angkatanId?'selected':'' }}>Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Peserta</label>
      <select name="peserta_id" onchange="this.form.submit()">
        <option value="">— Pilih Peserta —</option>
        @foreach($pesertaList as $p)
          <option value="{{ $p->id }}" {{ $p->id==$pesertaId?'selected':'' }}>{{ $p->nama }} ({{ $p->nrp }})</option>
        @endforeach
      </select>
    </div>
  </form>
</div>

@if(!$peserta)
  <div class="empty-state">Pilih angkatan dan peserta untuk melihat laporan individual.</div>
@else

{{-- Tombol Cetak — Revisi 29 Agustus 2026: cetak PDF dari fitur Report Individual
     HANYA berlaku untuk super_admin + Opsdik (admin). Kepala Sekolah
     (admin_akademik) bisa melihat report, tapi TIDAK bisa mencetak NPP. --}}
@if(auth()->user()->canSeeAll())
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <a href="{{ route('laporan.cetak.individu', ['angkatan_id' => $angkatanId, 'peserta_id' => $pesertaId]) }}" target="_blank" class="btn btn-smart btn-sm">🖨️ Cetak Laporan (PDF)</a>
</div>
@endif

{{-- Info Peserta --}}
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="width:48px;height:48px;border-radius:50%;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0">
      {{ strtoupper(substr($peserta->nama,0,2)) }}
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-size:18px;font-weight:600">{{ $peserta->nama }}</div>
      <div style="font-size:13px;color:#888">{{ $peserta->pangkat }} · NRP: {{ $peserta->nrp }} · Nosis: {{ $peserta->nosis ?? '-' }}</div>
      <div style="font-size:12px;color:#aaa">{{ $angkatan?->skadik?->nama }} — Angkatan {{ $angkatan?->nomor_angkatan }} ({{ $angkatan?->tahun_masuk }})</div>
    </div>
    @if($kompilasi)
    @php
      $npaCard = $akademik ? round($akademik->npa, 2) : round($kompilasi->nilai_akademik, 2);
      $npkCard = round($kepribadianAvg, 2);
      $npsCard = $samapta ? round($samapta->nilai_akhir, 2) : round($kompilasi->nilai_samapta, 2);
      $nppCard = round(($npaCard * $kompilasi->bobot_akademik / 100) + ($npkCard * $kompilasi->bobot_kepribadian / 100) + ($npsCard * $kompilasi->bobot_samapta / 100), 2);
    @endphp
    <div style="text-align:center;background:linear-gradient(135deg,#eef2ff,#f5f3ff);padding:12px 20px;border-radius:10px;border:1px solid #c7d2fe">
      <div style="font-size:10px;color:#888;text-transform:uppercase">NPP</div>
      <div style="font-size:24px;font-weight:700;color:#4f46e5">{{ $nppCard }}</div>
      <div style="font-size:12px;font-weight:600;color:#4f46e5">{{ $kompilasi->predikat_huruf }}</div>
    </div>
    @endif
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px">

  {{-- NPA --}}
  <div class="card" style="border-top:4px solid #059669">
    <div class="card-title" style="color:#059669">NPA — Nilai Prestasi Akademik</div>
    @if($akademik)
      <div style="font-size:28px;font-weight:700;color:#059669;margin-bottom:8px">{{ $akademik->npa }}</div>
      <div style="font-size:12px;color:#888">Jumlah Nilai: {{ $akademik->jumlah_nilai }}</div>
      @if($akademik->detail_nilai)
        <div style="margin-top:12px">
          @if($mataPelajaran->count() > 0)
            @foreach($akademik->detail_nilai as $i => $val)
            <div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #f0f0f5;font-size:11px">
              <span style="color:#888">{{ $mataPelajaran[$i]->nama ?? 'Mata Pelajaran ' . ($i+1) }}</span>
              <span style="font-weight:500">{{ $val }}</span>
            </div>
            @endforeach
          @else
            @foreach($akademik->detail_nilai as $i => $val)
            <div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #f0f0f5;font-size:11px">
              <span style="color:#888">Mata Pelajaran {{ $i+1 }}</span>
              <span style="font-weight:500">{{ $val }}</span>
            </div>
            @endforeach
          @endif
        </div>
      @endif
    @else
      <div style="color:#aaa;font-size:13px">Belum ada data NPA</div>
    @endif
  </div>

  {{-- NPK --}}
  <div class="card" style="border-top:4px solid #2563eb">
    <div class="card-title" style="color:#2563eb">NPK — Nilai Prestasi Kepribadian</div>
    @if($kepribadianList && $kepribadianList->count() > 0)
      <div style="font-size:28px;font-weight:700;color:#2563eb;margin-bottom:8px">{{ round($kepribadianAvg,2) }}</div>
      <div style="font-size:12px;color:#888">Rata-rata {{ $kepribadianList->count() }} periode</div>
      <div style="margin-top:12px">
        @foreach($kepribadianList as $nk)
        <div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #f0f0f5;font-size:11px">
          <span style="color:#888">{{ $nk->periode?->label ?? '-' }}</span>
          <span style="font-weight:500">{{ round($nk->nilai_akhir,2) }}</span>
        </div>
        @endforeach
      </div>
    @else
      <div style="color:#aaa;font-size:13px">Belum ada data NPK</div>
    @endif
  </div>

  {{-- NPS --}}
  <div class="card" style="border-top:4px solid #ea580c">
    <div class="card-title" style="color:#ea580c">NPS — Nilai Prestasi Samapta</div>
    @if($samapta)
      <div style="font-size:28px;font-weight:700;color:#ea580c;margin-bottom:8px">{{ $samapta->nilai_akhir }}</div>
      @if($samapta->putaran_label)
      <div style="font-size:11px;color:#888;margin-bottom:6px">Putaran: <strong>{{ $samapta->putaran_label }}</strong></div>
      @endif
      <div style="margin-top:12px">
        <div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #f0f0f5;font-size:11px">
          <span style="color:#888">Jarak Lari (m)</span><span>{{ $samapta->jarak_lari ?? '-' }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #f0f0f5;font-size:11px">
          <span style="color:#888">Nilai Lari (Garjas A)</span><span>{{ $samapta->nilai_lari ?? '-' }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #f0f0f5;font-size:11px">
          <span style="color:#888">Garjas B</span><span>{{ $samapta->garjas_b_nilai ?? '-' }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #f0f0f5;font-size:11px">
          <span style="color:#888">Nilai Konversi</span><span>{{ $samapta->nilai_konversi ?? '-' }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:2px 0;font-size:11px">
          <span style="color:#888;font-weight:600">Nilai Akhir NPS</span><span style="font-weight:700;color:#ea580c">{{ $samapta->nilai_akhir }}</span>
        </div>
      </div>
    @else
      <div style="color:#aaa;font-size:13px">Belum ada data NPS</div>
    @endif
  </div>

</div>

{{-- Kompilasi Detail --}}
@if($kompilasi)
@php
  // Gunakan NPA asli dari nilai_akademik (bukan nilai_kompilasi yang mungkin salah)
  $npaAsli = $akademik ? round($akademik->npa, 2) : round($kompilasi->nilai_akademik, 2);
  $npkAsli = round($kepribadianAvg, 2);
  $npsAsli = $samapta ? round($samapta->nilai_akhir, 2) : round($kompilasi->nilai_samapta, 2);
  $nppFix = round(($npaAsli * $kompilasi->bobot_akademik / 100) + ($npkAsli * $kompilasi->bobot_kepribadian / 100) + ($npsAsli * $kompilasi->bobot_samapta / 100), 2);
@endphp
<div class="card">
  <div class="card-title">Kompilasi Nilai Prestasi Pendidikan (NPP)</div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;text-align:center">
    <div>
      <div style="font-size:12px;color:#888">NPA ({{ $kompilasi->bobot_akademik }}%)</div>
      <div style="font-size:20px;font-weight:600;color:#059669">{{ $npaAsli }}</div>
      <div style="font-size:12px;color:#aaa">{{ round($npaAsli * $kompilasi->bobot_akademik / 100, 2) }}</div>
    </div>
    <div>
      <div style="font-size:12px;color:#888">NPK ({{ $kompilasi->bobot_kepribadian }}%)</div>
      <div style="font-size:20px;font-weight:600;color:#2563eb">{{ $npkAsli }}</div>
      <div style="font-size:12px;color:#aaa">{{ round($npkAsli * $kompilasi->bobot_kepribadian / 100, 2) }}</div>
    </div>
    <div>
      <div style="font-size:12px;color:#888">NPS ({{ $kompilasi->bobot_samapta }}%)</div>
      <div style="font-size:20px;font-weight:600;color:#ea580c">{{ $npsAsli }}</div>
      <div style="font-size:12px;color:#aaa">{{ round($npsAsli * $kompilasi->bobot_samapta / 100, 2) }}</div>
    </div>
    <div style="background:#eef2ff;padding:12px;border-radius:8px">
      <div style="font-size:12px;color:#4f46e5;font-weight:600">NPP</div>
      <div style="font-size:24px;font-weight:700;color:#4f46e5">{{ $nppFix }}</div>
      <div style="font-size:12px;color:#4f46e5">{{ $kompilasi->predikat_huruf }} ({{ $kompilasi->predikat_angka }})</div>
    </div>
  </div>
</div>
@endif

@endif
@endsection
