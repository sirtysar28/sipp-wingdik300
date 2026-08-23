@extends('layouts.app')
@section('title','Input Kepribadian')
@section('page-title','Input Nilai Kepribadian')

@section('topbar-actions')
<a href="{{ route('kepribadian.index',['periode_id'=>$periodeId,'angkatan_id'=>$peserta->angkatan_id,'cari'=>1]) }}"
   class="btn btn-outline btn-sm">← Kembali</a>
@endsection

@section('content')
<div style="max-width:820px">

  {{-- Info peserta --}}
  <div class="card" style="margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:16px">
      <div style="width:52px;height:52px;border-radius:50%;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700;flex-shrink:0">
        {{ strtoupper(collect(explode(' ',$peserta->nama))->slice(1)->map(fn($w)=>$w[0]??'')->join('')) }}
      </div>
      <div>
        <div style="font-size:16px;font-weight:600">{{ $peserta->nama }}</div>
        <div style="font-size:12px;color:#aaa">{{ $peserta->pangkat }} · {{ $peserta->nrp }} · Nosis: {{ $peserta->nosis }}</div>
      </div>
      <div style="margin-left:auto;text-align:right">
        <div style="font-size:12px;color:#aaa">Periode</div>
        <div style="font-size:14px;font-weight:500">{{ $periode?->label }}</div>
      </div>
    </div>
  </div>

  {{-- Keterangan skala --}}
  <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <span style="font-size:12px;color:#4f46e5;font-weight:600">Skala penilaian:</span>
    @foreach(['BS'=>['Baik Sekali','+0.5','#059669','#ecfdf5'],'B'=>['Baik','+0.25','#4f46e5','#eef2ff'],'C'=>['Cukup','0','#888','#f4f5f7'],'K'=>['Kurang','-0.25','#b45309','#fffbeb'],'KS'=>['Kurang Sekali','-0.5','#b91c1c','#fee2e2']] as $k=>[$label,$poin,$color,$bg])
    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:500;background:{{ $bg }};color:{{ $color }}">
      {{ $k }} — {{ $label }}
      <span style="font-size:10px;opacity:.7">({{ $poin }})</span>
    </span>
    @endforeach
    <span style="margin-left:auto;font-size:12px;color:#888">Nilai awal: <strong>75</strong></span>
  </div>

  <form method="POST" action="{{ route('kepribadian.store', $peserta) }}" id="form-kep">
    @csrf
    <input type="hidden" name="periode_nilai_id" value="{{ $periodeId }}">

    <div class="card" style="margin-bottom:16px">
      <div class="card-title">10 Aspek Kepribadian</div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th style="width:36px">No</th>
              <th>Aspek</th>
              @foreach(['KS','K','C','B','BS'] as $k)
              <th style="text-align:center;width:70px;font-size:11px">
                {{ $k }}<br>
                <span style="font-weight:400;color:#aaa">{{ ['KS'=>'-0.5','K'=>'-0.25','C'=>'0','B'=>'+0.25','BS'=>'+0.5'][$k] }}</span>
              </th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach($aspekList as $aspek)
            @php $selected = $existingDetail->get($aspek->id)?->kriteria ?? null; @endphp
            <tr>
              <td style="text-align:center;color:#aaa;font-size:12px">{{ $aspek->nomor }}</td>
              <td>
                <div style="font-size:13px;font-weight:500">{{ $aspek->nama }}</div>
                @if($aspek->deskripsi)
                <div style="font-size:11px;color:#aaa;margin-top:2px;line-height:1.5">{{ $aspek->deskripsi }}</div>
                @endif
              </td>
              @foreach(['KS','K','C','B','BS'] as $k)
              @php $radioId = "aspek_{$aspek->id}_{$k}"; @endphp
              <td style="text-align:center;padding:10px 4px">
                <!-- Radio asli disembunyikan -->
                <input type="radio"
                       name="kriteria[{{ $aspek->id }}]"
                       value="{{ $k }}"
                       id="{{ $radioId }}"
                       class="krit-radio-hidden"
                       data-aspek="{{ $aspek->id }}"
                       {{ $selected===$k ? 'checked' : '' }}
                       style="display:none;">
                <!-- Label custom dengan tampilan centang -->
                <label for="{{ $radioId }}"
                       class="custom-check-radio"
                       data-value="{{ $k }}"
                       style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;border:2px solid #d0d0d8;background:#fff;cursor:pointer;transition:all 0.1s;font-size:18px;font-weight:bold;color:#4f46e5;">
                       <!-- Icon centang akan muncul via CSS atau JS, kita gunakan teks -->
                </label>
              </td>
              @endforeach
            </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr style="background:#f5f5ff">
              <td colspan="2" style="padding:10px 12px;font-weight:600;color:#4f46e5">
                Nilai Akhir = 75 + total poin
              </td>
              <td colspan="5" style="text-align:right;padding:10px 16px">
                <span style="font-size:22px;font-weight:700;color:#4f46e5" id="preview-nilai">
                  {{ $existing ? $existing->nilai_akhir : '75.00' }}
                </span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    {{-- Narasi --}}
    <div class="card" style="margin-bottom:16px">
      <div class="card-title">Narasi & Rekomendasi</div>
      <div class="form-group">
        <label>Penjelasan Penilaian Kepribadian</label>
        <textarea name="penjelasan" rows="4"
          placeholder="Deskripsikan perilaku dan kepribadian siswa secara singkat...">{{ old('penjelasan', $existing?->penjelasan) }}</textarea>
      </div>
      <div class="form-group">
        <label>Rekomendasi Pengembangan</label>
        <textarea name="rekomendasi" rows="2"
          placeholder="Saran pengembangan untuk siswa...">{{ old('rekomendasi', $existing?->rekomendasi) }}</textarea>
      </div>
    </div>

    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">
        {{ $existing ? '💾 Perbarui Nilai' : '💾 Simpan Nilai' }}
      </button>
      <a href="{{ route('kepribadian.index',['periode_id'=>$periodeId,'angkatan_id'=>$peserta->angkatan_id,'cari'=>1]) }}"
         class="btn btn-outline">Batal</a>
    </div>
  </form>
</div>

@push('scripts')
<style>
  /* custom style untuk radio yang tampil seperti checkbox centang */
  .custom-check-radio {
    background-color: #fff;
    border: 2px solid #d0d0d8;
    transition: all 0.1s;
  }
  /* ketika radio di-check, label menampilkan centang dan berubah warna border/bg */
  .krit-radio-hidden:checked + .custom-check-radio,
  .custom-check-radio:has(~ .krit-radio-hidden:checked) {
    /* karena label setelah radio, kita gunakan general sibling selector atau ubah posisi.
       Lebih mudah: gunakan JS untuk update style, atau kita atur dengan class.
       Kita akan tambahkan class 'checked' pada label via JS. */
  }
  /* Untuk mendukung tanpa JS yang rumit, kita gunakan sedikit JS untuk sinkronisasi */
</style>
<script>
const poin = { KS: -0.5, K: -0.25, C: 0, B: 0.25, BS: 0.5 };

// Fungsi update tampilan label (centang)
function updateCheckDisplay() {
  document.querySelectorAll('.krit-radio-hidden').forEach(radio => {
    const label = radio.nextElementSibling;
    if (radio.checked) {
      label.textContent = '✓';
      label.style.borderColor = '#4f46e5';
      label.style.backgroundColor = '#eef2ff';
    } else {
      label.textContent = '';
      label.style.borderColor = '#d0d0d8';
      label.style.backgroundColor = '#fff';
    }
  });
}

// Hitung total nilai
function hitungNilai() {
  let total = 0;
  document.querySelectorAll('.krit-radio-hidden:checked').forEach(r => {
    total += poin[r.value] || 0;
  });
  document.getElementById('preview-nilai').textContent = (75 + total).toFixed(2);
}

// Event listener untuk semua radio
document.querySelectorAll('.krit-radio-hidden').forEach(radio => {
  radio.addEventListener('change', function() {
    // Satu aspek hanya boleh satu pilihan, radio sudah handle otomatis
    updateCheckDisplay();
    hitungNilai();
  });
});

// Untuk label yang diklik secara langsung, kita trigger radio-nya (tapi label sudah punya for)
// Tidak perlu tambahan, karena klik label akan memeriksa radio asli.

// Inisialisasi tampilan
updateCheckDisplay();
hitungNilai();

// Validasi semua aspek terpilih
document.getElementById('form-kep').addEventListener('submit', function(e) {
  const aspekIds = [...new Set([...document.querySelectorAll('.krit-radio-hidden')].map(r => r.dataset.aspek))];
  const checked  = [...document.querySelectorAll('.krit-radio-hidden:checked')].map(r => r.dataset.aspek);
  const missing  = aspekIds.filter(id => !checked.includes(id));
  if (missing.length > 0) {
    e.preventDefault();
    alert(`Masih ada ${missing.length} aspek yang belum dipilih. Harap lengkapi semua aspek.`);
  }
});
</script>
@endpush
@endsection