@extends('layouts.app')

@section('page-title', 'Report NPP — Nilai Prestasi Pendidikan (Angkatan)')

@section('topbar-actions')
@if($data->count() > 0)
<a href="{{ route('report.npp.ekspor', array_filter(['angkatan_id' => $angkatanId, 'nps_putaran' => $npsPutaran, 'npk_periode' => $npkPeriode])) }}" class="btn btn-success btn-sm">📥 Ekspor NPP</a>
{{-- Revisi 29 Agustus 2026: cetak PDF NPP HANYA super_admin + Opsdik --}}
@if(auth()->user()->canSeeAll())
<a href="{{ route('laporan.cetak.semua', array_filter(['angkatan_id' => $angkatanId, 'nps_putaran' => $npsPutaran, 'npk_periode' => $npkPeriode])) }}" class="btn btn-smart btn-sm" target="_blank">🖨️ Cetak NPP Angkatan</a>
@endif
@endif
@endsection

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">📊 Report NPP — Nilai Prestasi Pendidikan (Angkatan)</h2>
    <p style="font-size:12px;color:#888">Kompilasi nilai akademik, kepribadian, dan samapta per angkatan</p>
  </div>
</div>

{{-- Filter --}}
<div class="card" style="margin-bottom:14px">
  <form method="GET" action="{{ route('report.npp') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
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
    {{-- Revisi 30 Sept 2026: pilihan SUMBER nilai NPP —
         NPS dari putaran mana & NPK dari periode mana.
         Default = TERAKHIR (putaran/periode terbaru). --}}
    <div>
      <label style="font-size:11px;margin-bottom:3px">Sumber NPS (Samapta)</label>
      <select name="nps_putaran" onchange="this.form.submit()">
        <option value="" {{ !$npsPutaran ? 'selected' : '' }}>🔄 Putaran Terakhir</option>
        @foreach($putaranList as $pl)
          <option value="{{ $pl }}" {{ $npsPutaran == $pl ? 'selected' : '' }}>{{ $pl }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:11px;margin-bottom:3px">Sumber NPK (Kepribadian)</label>
      <select name="npk_periode" onchange="this.form.submit()">
        <option value="" {{ !$npkPeriode ? 'selected' : '' }}>🔄 Periode Terakhir</option>
        @foreach($periodeList as $per)
          <option value="{{ $per->id }}" {{ $npkPeriode == $per->id ? 'selected' : '' }}>{{ $per->label }} ({{ optional($per->tanggal_mulai)->format('d M Y') }})</option>
        @endforeach
      </select>
    </div>
  </form>
</div>

@if(!$angkatan)
  <div class="empty-state">Pilih angkatan untuk melihat report NPP.</div>
@else

  {{-- ── Form Proses Kompilasi (admin/super_admin only) ── --}}
  <div class="card" style="margin-bottom:20px;border-left:4px solid #7c3aed">
    <div class="card-title">⚙️ Proses Kompilasi Nilai</div>
    <form method="POST" action="{{ route('kompilasi.proses') }}" id="formKompilasi">
      @csrf
      <input type="hidden" name="angkatan_id" value="{{ $angkatanId }}">
      {{-- Revisi 30 Sept 2026: pilihan sumber NPS & NPK utk proses kompilasi
           (default "Putaran/Periode Terakhir" — konsisten dgn filter di atas). --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin:0">
          <label>Sumber NPS (Samapta)</label>
          <select name="nps_putaran">
            <option value="">🔄 Putaran Terakhir (otomatis)</option>
            @foreach($putaranList as $pl)
              <option value="{{ $pl }}" {{ $npsPutaran == $pl ? 'selected' : '' }}>{{ $pl }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label>Sumber NPK (Kepribadian)</label>
          <select name="npk_periode">
            <option value="">🔄 Periode Terakhir (otomatis)</option>
            @foreach($periodeList as $per)
              <option value="{{ $per->id }}" {{ $npkPeriode == $per->id ? 'selected' : '' }}>{{ $per->label }} ({{ optional($per->tanggal_mulai)->format('d M Y') }})</option>
            @endforeach
          </select>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end">
        <div class="form-group" style="margin:0">
          <label>Bobot Akademik (%)</label>
          <input type="number" name="bobot_akademik" value="70" min="0" max="100" step="1" id="ba">
        </div>
        <div class="form-group" style="margin:0">
          <label>Bobot Kepribadian (%)</label>
          <input type="number" name="bobot_kepribadian" value="20" min="0" max="100" step="1" id="bk">
        </div>
        <div class="form-group" style="margin:0">
          <label>Bobot Samapta (%)</label>
          <input type="number" name="bobot_samapta" value="10" min="0" max="100" step="1" id="bs">
        </div>
        <button type="submit" class="btn btn-smart" onclick="return confirm('Proses kompilasi nilai? Data sebelumnya akan diperbarui.')">
          🔄 Proses Kompilasi
        </button>
      </div>
      <div id="bobotWarning" style="display:none;margin-top:8px;color:#dc2626;font-size:12px;font-weight:600">
        ⚠️ Total bobot harus 100%!
      </div>
      <div style="margin-top:8px;color:#666;font-size:11px">
        ℹ️ Default: NPS dari <strong>putaran terakhir</strong> & NPK dari <strong>periode terakhir</strong>.
      </div>
    </form>
  </div>

  {{-- ── Metrik ── --}}
  @if(isset($stats) && $stats)
  <div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card">
      <div class="metric-label">Total Peserta</div>
      <div class="metric-value">{{ $stats['total_peserta'] }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Sudah Kompilasi</div>
      <div class="metric-value" style="color:#059669">{{ $stats['sudah_kompilasi'] }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Rata-rata NPP</div>
      <div class="metric-value" style="color:#4f46e5">{{ $stats['rata_akhir'] }}</div>
    </div>
    {{-- Revisi 22 Sept 2026: rata-rata angkatan per komponen NPA/NPK/NPS --}}
    <div class="metric-card">
      <div class="metric-label">Rata-rata Angkatan NPA · NPK · NPS</div>
      <div class="metric-value" style="font-size:16px">
        <span style="color:#0e7490">{{ $stats['rata_npa'] }}</span>
        <span style="color:#aaa;font-size:12px"> · </span>
        <span style="color:#b45309">{{ $stats['rata_npk'] }}</span>
        <span style="color:#aaa;font-size:12px"> · </span>
        <span style="color:#047857">{{ $stats['rata_nps'] }}</span>
      </div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Tertinggi / Terendah</div>
      <div class="metric-value" style="font-size:18px">
        <span style="color:#059669">{{ $stats['tertinggi'] }}</span>
        <span style="color:#aaa;font-size:12px"> / </span>
        <span style="color:#dc2626">{{ $stats['terendah'] }}</span>
      </div>
    </div>
  </div>

  {{-- ── Warning jika data belum lengkap ── --}}
  @if($stats['belum_akademik'] > 0 || $stats['belum_kepribadian'] > 0 || $stats['belum_samapta'] > 0)
  <div class="card" style="margin-bottom:16px;background:#fffbeb;border-color:#fde68a">
    <div style="font-size:12px;color:#92400e">
      ⚠️ <strong>Ketersediaan Data:</strong>
      @if($stats['belum_kompilasi'] > 0)
        <span style="color:#b45309;font-weight:600">{{ $stats['belum_kompilasi'] }} peserta belum dikompilasi.</span>
      @endif
      @if($stats['belum_akademik'] > 0)
        · {{ $stats['belum_akademik'] }} peserta belum punya <strong>NPA (Akademik)</strong>.
      @endif
      @if($stats['belum_kepribadian'] > 0)
        · {{ $stats['belum_kepribadian'] }} peserta belum punya <strong>NPK (Kepribadian)</strong>.
      @endif
      @if($stats['belum_samapta'] > 0)
        · {{ $stats['belum_samapta'] }} peserta belum punya <strong>NPS (Samapta)</strong>.
      @endif
      <br>Pastikan semua data sudah diinput sebelum memproses kompilasi.
    </div>
  </div>
  @endif
  @endif

  {{-- ── Tabel NPP ── --}}
  @if($data->count() > 0)
  <div class="card" style="padding:0;overflow:hidden">
    {{-- Revisi 30 Sept 2026: info sumber nilai yang sedang dipakai --}}
    @if($data->first()->sumber_nps || $data->first()->sumber_npk)
    <div style="padding:10px 14px;font-size:11px;color:#666;border-bottom:1px solid #eee">
      📌 Sumber nilai — NPS: <strong style="color:#047857">{{ $data->first()->sumber_nps }}</strong>
      · NPK: <strong style="color:#b45309">{{ $data->first()->sumber_npk }}</strong>
    </div>
    @endif
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="text-align:center;width:50px">Rank</th>
            <th>Nama</th>
            <th>Pangkat</th>
            <th>NRP</th>
            <th style="text-align:right">N. Akademik<br><span style="font-weight:400;font-size:9px">({{ $data->first()->bobot_akademik ?? 70 }}%)</span></th>
            <th style="text-align:right">N. Kepribadian<br><span style="font-weight:400;font-size:9px">({{ $data->first()->bobot_kepribadian ?? 20 }}%)</span></th>
            <th style="text-align:right">N. Samapta<br><span style="font-weight:400;font-size:9px">({{ $data->first()->bobot_samapta ?? 10 }}%)</span></th>
            <th style="text-align:right">NPP</th>
            <th style="text-align:center">Predikat</th>
            <th style="text-align:center;width:40px">Laporan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($data as $d)
            @php
              // Tabel polos — tanpa warna rank (revisi 21 Sept 2026)
              $bgRow = '';
            @endphp
            <tr style="{{ $bgRow }}">
              <td style="text-align:center;font-weight:600">
                @if($d->rank === 1) 🥇 @elseif($d->rank === 2) 🥈 @elseif($d->rank === 3) 🥉 @else {{ $d->rank }} @endif
              </td>
              <td><strong>{{ $d->peserta->nama }}</strong></td>
              <td>{{ $d->peserta->pangkat }}</td>
              <td>{{ $d->peserta->nrp }}</td>
              <td style="text-align:right">{{ $d->nilai_akademik }}</td>
              <td style="text-align:right">{{ $d->nilai_kepribadian }}</td>
              <td style="text-align:right">{{ $d->nilai_samapta }}</td>
              <td style="text-align:right">
                <span style="font-weight:700;font-size:16px;color:#4f46e5">{{ $d->nilai_akhir }}</span>
              </td>
              <td style="text-align:center">
                <span class="badge badge-{{ $d->nilai_akhir >= 85 ? 'green' : ($d->nilai_akhir >= 75 ? 'blue' : ($d->nilai_akhir >= 65 ? 'amber' : 'red')) }}">
                  {{ $d->predikat_huruf }}
                </span>
              </td>
              <td style="text-align:center">
                @if(auth()->user()->canSeeAll())
                <a href="{{ route('laporan.cetak.individu', array_filter(['angkatan_id' => $angkatanId, 'peserta_id' => $d->peserta_didik_id, 'nps_putaran' => $npsPutaran, 'npk_periode' => $npkPeriode])) }}" class="btn btn-sm btn-outline" title="Cetak Laporan Individu" target="_blank">🖨️</a>
                @else
                <span style="color:#bbb" title="Cetak hanya oleh Super Admin / Opsdik">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
        {{-- Revisi 22 Sept 2026: footer rata-rata angkatan per komponen —
            N. Akademik (NPA), N. Kepribadian (NPK), N. Samapta (NPS) + NPP.
            Peserta tanpa nilai (0) tidak ikut dihitung per komponen. --}}
        @if(isset($stats['rata_npa']))
        <tfoot>
          <tr style="font-weight:700;background:#f8fafc">
            <td colspan="4" style="text-align:right">Rata-rata Angkatan</td>
            <td style="text-align:right">{{ $stats['rata_npa'] }}</td>
            <td style="text-align:right">{{ $stats['rata_npk'] }}</td>
            <td style="text-align:right">{{ $stats['rata_nps'] }}</td>
            <td style="text-align:right;color:#4f46e5">{{ $stats['rata_akhir'] }}</td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
  @else
    <div class="empty-state card">
      <p>Belum ada data kompilasi NPP. Klik <strong>"Proses Kompilasi"</strong> untuk memulai.</p>
    </div>
  @endif

@endif

@section('scripts')
<script>
const ba=document.getElementById('ba'), bk=document.getElementById('bk'), bs=document.getElementById('bs'), warn=document.getElementById('bobotWarning');
function checkBobot(){
  const total=parseFloat(ba.value||0)+parseFloat(bk.value||0)+parseFloat(bs.value||0);
  warn.style.display=(Math.abs(total-100)>0.01)?'block':'none';
}
ba.addEventListener('input',checkBobot);
bk.addEventListener('input',checkBobot);
bs.addEventListener('input',checkBobot);
</script>
@endsection

@endsection
