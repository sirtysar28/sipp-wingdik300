@extends('layouts.app')
@section('page-title', 'Edit NPA')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">✏️ Edit Nilai Akademik</h2>
    <p style="font-size:12px;color:#888">{{ $nilai?->peserta?->pangkat }} {{ $nilai?->peserta?->nama }} — NRP: {{ $nilai?->peserta?->nrp }}</p>
  </div>
  <a href="{{ route('report.npa', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline">← Kembali ke Report NPA</a>
</div>

@if(!$nilai)
<div class="empty-state"><p>Data nilai tidak ditemukan.</p></div>
@else

@if($subjek->isEmpty())
<div class="card" style="background:#fef2f2;border-color:#fecaca;margin-bottom:16px">
  <div style="display:flex;gap:12px;align-items:center">
    <span style="font-size:24px">⚠️</span>
    <div>
      <div style="font-size:14px;font-weight:600;color:#991b1b">Mata Pelajaran Tidak Ditemukan</div>
      <div style="font-size:12px;color:#b91c1c;margin-top:4px">
        Sekolah ini belum memiliki konfigurasi mata pelajaran. Data nilai sudah ada tapi tidak bisa diedit.
        Silakan atur mata pelajaran terlebih dahulu.
      </div>
      <a href="{{ route('mata-pelajaran.index') }}" class="btn btn-danger btn-sm" style="margin-top:8px">
        📚 Atur Mata Pelajaran
      </a>
    </div>
  </div>
</div>
@else

<div class="card" style="margin-bottom:16px;background:#f0fdf4;border-color:#bbf7d0">
  <div style="font-size:12px;color:#065f46">
    📊 Total Bobot: <strong>{{ $totalBobot }}</strong> |
    Σ Harga Nilai (HN): <strong>{{ $totalHN ?? 0 }}</strong> |
    {{ $subjek->count() }} mata pelajaran
  </div>
</div>

<div class="card">
  <form method="POST" action="{{ route('nilai-akademik.update') }}" id="formEdit">
    @csrf
    <input type="hidden" name="nilai_id" value="{{ $nilai->id }}">
    <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">

    <div class="card-title">📊 Nilai per Mata Pelajaran</div>
    <div style="overflow-x:auto;margin-bottom:16px">
      <table style="min-width:900px">
        <thead>
          <tr style="background:#4f46e5;color:#fff">
            <th style="text-align:center;width:40px">No</th>
            <th>Kode</th>
            <th>Mata Pelajaran</th>
            <th style="text-align:center">JP</th>
            <th style="text-align:center">Bobot</th>
            <th style="text-align:center;background:#3730a3">Harga Nilai<br><span style="font-weight:400;font-size:8px">(HN)</span></th>
            <th style="text-align:center">Nilai<br><span style="font-weight:400;font-size:8px">(0-100)</span></th>
          </tr>
        </thead>
        <tbody>
          @php $existingNilai = is_array($nilai->detail_nilai) ? $nilai->detail_nilai : (json_decode($nilai->detail_nilai, true) ?: []); @endphp
          @foreach($subjek as $idx => $s)
          @php $hn = $s->harga_nilai_calc; $existing = $existingNilai[$idx] ?? null; @endphp
          <tr>
            <td style="text-align:center">{{ $idx + 1 }}</td>
            <td>
              @if($s->kode)
                <span class="badge badge-blue">{{ $s->kode }}</span>
              @else
                <span style="color:#ccc">—</span>
              @endif
            </td>
            <td style="font-size:12px">{{ $s->nama }}</td>
            <td style="text-align:center;background:#f9fafb">{{ $s->jp }}</td>
            <td style="text-align:center;background:#f9fafb">{{ $s->bobot }}</td>
            <td style="text-align:center;background:#eef2ff;font-weight:600;color:#4f46e5">{{ $hn }}</td>
            <td style="text-align:center">
              <input type="number" name="nilai[{{ $idx }}]" min="0" max="100" step="0.01"
                     class="sbs-input" data-hn="{{ $hn }}" value="{{ $existing !== null ? $existing : '' }}"
                     style="width:80px;text-align:center;padding:4px 6px">
            </td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr style="background:#f0f0ff;font-weight:600">
            <td colspan="2" style="text-align:center">TOTAL</td>
            <td>{{ $subjek->count() }} Subjek</td>
            <td style="text-align:center">{{ $subjek->sum('jp') }}</td>
            <td style="text-align:center">{{ $totalBobot }}</td>
            <td style="text-align:center;background:#eef2ff;color:#4f46e5">{{ $totalHN ?? 0 }}</td>
            <td style="text-align:center" id="totalNilai">-</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div style="background:#eef2ff;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#4f46e5">
      <strong>Rumus NPA:</strong> NPA = Σ(Nilai MP × HN) / Σ(HN) | HN dari konfigurasi mata pelajaran<br>
      <strong>NPA saat ini:</strong> {{ $nilai->npa }}
      <span id="npaPreview" style="font-size:16px;font-weight:700;color:#1a1a2e;display:block;margin-top:4px"></span>
    </div>

    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
      <a href="{{ route('report.npa', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline">Batal</a>
    </div>
  </form>
</div>

@endif {{-- end subjek empty --}}
@endif {{-- end nilai check --}}

@endsection

@push('scripts')
<script>
const totalHN = {{ $totalHN ?? 0 }};

document.querySelectorAll('.sbs-input').forEach(input => {
  input.addEventListener('input', hitungNPA);
});

function hitungNPA() {
  let totalHarga = 0;
  document.querySelectorAll('.sbs-input').forEach((input) => {
    const val = parseFloat(input.value) || 0;
    const hn = parseFloat(input.dataset.hn) || 0;
    totalHarga += val * hn;
  });
  // NPA = Σ(nilai × HN) / Σ(HN)
  const npa = totalHN > 0 ? Math.min((totalHarga / totalHN), 100).toFixed(2) : 0;
  document.getElementById('npaPreview').textContent = 'NPA baru = ' + npa;
}
// Trigger on load
hitungNPA();
</script>
@endpush
