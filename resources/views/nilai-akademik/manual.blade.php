@extends('layouts.app')
@section('page-title', 'Input Manual NPA')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">📝 Input Manual Nilai Akademik (NPA)</h2>
    <p style="font-size:12px;color:#888">Input nilai per mata pelajaran sesuai konfigurasi sekolah</p>
  </div>
  <a href="{{ route('nilai-akademik.index', ['angkatan_id' => $angkatanId]) }}" class="btn btn-outline">← Kembali</a>
</div>

{{-- Pilih Angkatan --}}
<div class="card" style="margin-bottom:16px">
  <form method="GET" action="{{ route('nilai-akademik.manual.form') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <div class="form-group" style="flex:1;min-width:200px;margin:0">
      <label>Pilih Angkatan</label>
      <select name="angkatan_id" onchange="this.form.submit()">
        @foreach($allAngkatan as $a)
          <option value="{{ $a->id }}" {{ $a->id == $angkatanId ? 'selected' : '' }}>
            Angkatan {{ $a->nomor_angkatan }} — {{ $a->skadik?->nama_singkat ?? $a->skadik?->nama }}{{ $a->jurusan ? ' — ' . $a->jurusan : '' }}
          </option>
        @endforeach
      </select>
    </div>
  </form>
</div>

@if(!$angkatanId)
<div class="empty-state">
  <p>Pilih angkatan terlebih dahulu untuk mulai input nilai.</p>
</div>
@else

{{-- Warning: belum ada mapel --}}
@if($subjek->isEmpty())
<div class="card" style="background:#fef2f2;border-color:#fecaca;margin-bottom:16px">
  <div style="display:flex;gap:12px;align-items:center">
    <span style="font-size:24px">⚠️</span>
    <div>
      <div style="font-size:14px;font-weight:600;color:#991b1b">Mata Pelajaran Belum Diatur</div>
      <div style="font-size:12px;color:#b91c1c;margin-top:4px">
        Sekolah <strong>{{ $angkatan?->skadik?->nama_singkat ?? $angkatan?->skadik?->nama }}</strong> belum memiliki konfigurasi mata pelajaran.
        Silakan atur terlebih dahulu agar bisa menginput nilai.
      </div>
      <a href="{{ route('mata-pelajaran.index', ['skadik_id' => $angkatan?->skadik_id]) }}" class="btn btn-danger btn-sm" style="margin-top:8px">
        📚 Atur Mata Pelajaran
      </a>
    </div>
  </div>
</div>
@else

{{-- Info sekolah & mapel --}}
<div class="card" style="margin-bottom:16px;background:#f0fdf4;border-color:#bbf7d0">
  <div style="font-size:12px;color:#065f46">
    🏫 Sekolah: <strong>{{ $angkatan?->skadik?->nama_singkat ?? $angkatan?->skadik?->nama }}</strong> —
    {{ $subjek->count() }} mata pelajaran aktif |
    Total Bobot: <strong>{{ $totalBobot }}</strong> |
    Total Harga Nilai (Σ HN): <strong>{{ $totalHN ?? 0 }}</strong>
  </div>
</div>

{{-- Pilih Peserta --}}
<div class="card" style="margin-bottom:16px">
  <div class="card-title">Pilih Peserta Didik <span style="font-weight:400;color:#aaa;font-size:11px">({{ $pesertaList->count() }} peserta)</span></div>
  @if($pesertaList->isEmpty())
    <p style="font-size:13px;color:#888">Belum ada peserta pada angkatan ini. Tambahkan peserta terlebih dahulu di menu Peserta Didik.</p>
  @else
  <form method="POST" action="{{ route('nilai-akademik.manual.store') }}" id="formNPA">
    @csrf
    <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
    <div class="form-group">
      <label>Peserta</label>
      <select name="peserta_didik_id" required id="pesertaSelect" onchange="loadExistingNilai()">
        <option value="">— Pilih Peserta —</option>
        @foreach($pesertaList as $p)
          @php $sudah = $p->nilaiAkademik; @endphp
          <option value="{{ $p->id }}" data-detail="{{ json_encode($sudah?->detail_nilai ?? []) }}">
            {{ $p->pangkat }} {{ $p->nama }} (NRP: {{ $p->nrp }})
            @if($sudah) — ✓ NPA: {{ $sudah->npa }} (klik untuk perbarui)
            @else — belum input
            @endif
          </option>
        @endforeach
      </select>
    </div>

    {{-- Tabel Input Nilai --}}
    <div class="card-title" style="margin-top:20px">📊 Input Nilai per Mata Pelajaran</div>
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
          @foreach($subjek as $idx => $s)
          @php $hn = $s->harga_nilai_calc; @endphp
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
                     class="sbs-input" data-bobot="{{ $s->bobot }}" data-hn="{{ $hn }}" placeholder="0"
                     style="width:80px;text-align:center;padding:4px 6px">
            </td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr style="background:#ffffff;font-weight:600">
            <td colspan="2" style="text-align:center">TOTAL</td>
            <td>{{ $subjek->count() }} Subjek</td>
            <td style="text-align:center">{{ $subjek->sum('jp') }}</td>
            <td style="text-align:center">{{ $totalBobot }}</td>
            <td style="text-align:center;background:#eef2ff;color:#4f46e5">{{ $totalHN ?? 0 }}</td>
            <td style="text-align:center" id="totalNilai">0</td>
          </tr>
        </tfoot>
      </table>
    </div>

    {{-- Info Rumus --}}
    <div style="background:#eef2ff;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#4f46e5">
      <strong>📖 Rumus NPA:</strong> NPA = Σ(Nilai MP × HN) / Σ(HN)<br>
      Dimana <strong>HN (Harga Nilai)</strong> diambil dari konfigurasi mata pelajaran<br>
      Total Bobot = <strong>{{ $totalBobot }}</strong> | Σ HN = <strong>{{ $totalHN ?? 0 }}</strong>
      <br><span id="npaPreview" style="font-size:16px;font-weight:700;color:#1a1a2e"></span>
    </div>

    <button type="submit" class="btn btn-smart">💾 Simpan Nilai Akademik</button>
  </form>
  @endif
</div>

@endif {{-- end subjek empty --}}
@endif {{-- end angkatanId check --}}

@endsection

@push('scripts')
<script>
const totalHN = {{ $totalHN ?? 0 }};

document.querySelectorAll('.sbs-input').forEach(input => {
  input.addEventListener('input', hitungNPA);
});

// Isi otomatis nilai yg sudah tersimpan ketika memilih peserta yg sudah punya NPA
function loadExistingNilai() {
  const sel = document.getElementById('pesertaSelect');
  const opt = sel.options[sel.selectedIndex];
  let detail = [];
  try { detail = JSON.parse(opt.getAttribute('data-detail') || '[]'); } catch (e) {}
  const inputs = document.querySelectorAll('.sbs-input');
  inputs.forEach((input, i) => { input.value = (detail[i] !== undefined && detail[i] !== 0) ? detail[i] : ''; });
  hitungNPA();
}

function hitungNPA() {
  let totalHarga = 0;
  let filled = 0;

  document.querySelectorAll('.sbs-input').forEach((input) => {
    const val = parseFloat(input.value) || 0;
    const hn = parseFloat(input.dataset.hn) || 0;
    if (val > 0) filled++;
    totalHarga += val * hn;
  });

  // NPA = Σ(nilai × HN) / Σ(HN)
  const npa = totalHN > 0 ? Math.min((totalHarga / totalHN), 100).toFixed(2) : 0;
  const totalSubjek = document.querySelectorAll('.sbs-input').length;

  document.getElementById('totalNilai').textContent = filled + '/' + totalSubjek + ' subjek';
  document.getElementById('npaPreview').textContent = 'NPA = ' + npa;
}
</script>
@endpush
