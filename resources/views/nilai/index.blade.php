@extends('layouts.app')
@section('title', 'Input Nilai')
@php $curAng = $allAngkatan->firstWhere('id', $angkatanId); @endphp
@section('page-title', 'Input Nilai — ' . ($curAng?->skadik->nama ?? '') . ' Angkt ' . ($curAng?->nomor_angkatan ?? ''))

@section('topbar-actions')
<a href="{{ route('nilai.periode.create') }}" class="btn btn-outline btn-sm">+ Periode Baru</a>
@endsection

@section('content')

{{-- Filter --}}
<div class="card" style="margin-bottom:16px">
  <form method="GET" action="{{ route('nilai.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <div>
      <label style="font-size:11px;margin-bottom:3px">Sekolah</label>
      <select name="skadik_id" onchange="this.form.submit()" style="font-size:12px;padding:5px 8px;border:1px solid #d0d0d8;border-radius:7px">
        @foreach($allSkadik as $sk)
          <option value="{{ $sk->id }}" {{ $sk->id == $skadikId ? 'selected' : '' }}>
            {{ $sk->nama }} ({{ $sk->lemdik->nama }})
          </option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Angkatan</label>
      <select name="angkatan_id" onchange="this.form.submit()" style="font-size:12px;padding:5px 8px;border:1px solid #d0d0d8;border-radius:7px">
        @foreach($allAngkatan as $ang)
          <option value="{{ $ang->id }}" {{ $ang->id == $angkatanId ? 'selected' : '' }}>
            Angkatan {{ $ang->nomor_angkatan }} ({{ $ang->tahun_masuk }})
          </option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Periode</label>
      <select name="periode_id" onchange="this.form.submit()" style="font-size:12px;padding:5px 8px;border:1px solid #d0d0d8;border-radius:7px">
        @foreach($periodes as $per)
          <option value="{{ $per->id }}" {{ $per->id == $periodeId ? 'selected' : '' }}>
            {{ $per->label }} {{ $per->aktif ? '(aktif)' : '' }}
          </option>
        @endforeach
      </select>
    </div>
    @if($periode)
    <div style="margin-left:auto;font-size:12px;color:#888">
      {{ $periode->tanggal_mulai?->format('d M Y') }} – {{ $periode->tanggal_selesai?->format('d M Y') }}
    </div>
    @endif
  </form>
</div>

@if(!$periode)
  <div class="empty-state">
    Belum ada periode. <a href="{{ route('nilai.periode.create') }}" style="color:#4f46e5">Buat periode baru</a>
  </div>
@else

{{-- Form bulk input --}}
<div class="card">
  <div style="display:flex;align-items:center;margin-bottom:14px">
    <span class="card-title" style="margin:0">Input nilai — {{ $periode->label }}</span>
    <span style="margin-left:auto;font-size:12px;color:#aaa">Semua aspek bobot sama rata (0–100)</span>
  </div>

  <form method="POST" action="{{ route('nilai.bulk') }}">
    @csrf
    <input type="hidden" name="periode_nilai_id" value="{{ $periodeId }}">

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Nama</th>
            <th>NRP</th>
            <th style="text-align:center;width:110px">Akademik</th>
            <th style="text-align:center;width:110px">Fisik</th>
            <th style="text-align:center;width:110px">Sikap</th>
            <th style="text-align:center;width:110px">Kepemimpinan</th>
            <th style="text-align:right;width:70px">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($peserta as $i => $p)
          @php $n = $p->nilai->first(); @endphp
          <tr>
            <td>{{ $i + 1 }}</td>
            <td style="font-weight:500">{{ $p->nama }}</td>
            <td style="color:#aaa">{{ $p->nrp }}</td>
            <input type="hidden" name="nilai[{{ $i }}][peserta_id]" value="{{ $p->id }}">
            @foreach(['akademik','fisik','sikap','kepemimpinan'] as $aspek)
            <td style="text-align:center;padding:6px">
              <input type="number" name="nilai[{{ $i }}][{{ $aspek }}]"
                value="{{ old("nilai.$i.$aspek", $n?->$aspek ?? '') }}"
                min="0" max="100" step="0.01"
                class="nilai-input"
                data-row="{{ $i }}"
                style="width:88px;text-align:center;padding:5px 6px"
                oninput="hitungTotal({{ $i }})"
                placeholder="0">
            </td>
            @endforeach
            <td style="text-align:right;font-weight:600" id="total-{{ $i }}">
              {{ $n ? round($n->total,1) : '—' }}
            </td>
          </tr>
          @empty
          <tr><td colspan="8"><div class="empty-state">Tidak ada peserta aktif.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($peserta->count() > 0)
    <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
      <button type="submit" class="btn btn-primary">Simpan Semua Nilai</button>
      <span style="font-size:12px;color:#aaa">Nilai yang sudah ada akan diperbarui</span>
    </div>
    @endif
  </form>
</div>
@endif

@endsection

@push('scripts')
<script>
function hitungTotal(row) {
  const inputs = document.querySelectorAll(`input[data-row="${row}"]`);
  let sum = 0, count = 0;
  inputs.forEach(inp => {
    const v = parseFloat(inp.value);
    if (!isNaN(v)) { sum += v; count++; }
  });
  const totalEl = document.getElementById(`total-${row}`);
  if (totalEl) totalEl.textContent = count === 4 ? (sum / 4).toFixed(1) : '—';
}
</script>
@endpush
