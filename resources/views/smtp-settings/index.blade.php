@extends('layouts.app')
@section('page-title', 'Pengaturan SMTP')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px">
  <div>
    <h2 style="font-size:18px;font-weight:600">⚙️ Pengaturan SMTP (Email Pengirim)</h2>
    <p style="font-size:12px;color:#888">Konfigurasi email aktif untuk mengirim email ke user — termasuk link reset password (Lupa Password)</p>
  </div>
  @if($setting && $setting->is_active)
    <span class="badge badge-green">● SMTP Aktif</span>
  @elseif($setting)
    <span class="badge badge-amber">● SMTP Nonaktif</span>
  @else
    <span class="badge badge-red">● Belum dikonfigurasi</span>
  @endif
</div>

{{-- Info / panduan singkat --}}
<div class="card" style="margin-bottom:16px;border-left:4px solid #7c3aed">
  <div class="card-title" style="margin-bottom:8px">Cara Kerja</div>
  <div style="font-size:12px;color:#555;line-height:1.7">
    Email yang dikonfigurasi di halaman ini akan dipakai SIPP untuk mengirim <strong>link reset password</strong> ke user yang menekan tombol
    <em>"Lupa Password?"</em> di halaman login. Contoh konfigurasi Gmail:
    <span style="background:#f5f3ff;color:#7c3aed;padding:2px 8px;border-radius:6px;font-size:11px">Host: smtp.gmail.com • Port: 587 • Enkripsi: TLS • Password: <strong>App Password</strong> (bukan password biasa)</span>
  </div>
</div>

{{-- ── FORM KONFIGURASI ── --}}
<div class="card">
  <div class="card-title">Konfigurasi SMTP</div>

  @if($errors->any())
    <div class="alert alert-error">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('smtp-settings.save') }}">
    @csrf
    {{-- Mailer selalu smtp (satu-satunya opsi) — dikirim via hidden input --}}
    <input type="hidden" name="mailer" value="smtp">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
      <div class="form-group">
        <label>Host SMTP</label>
        <input type="text" name="host" value="{{ old('host', $setting->host ?? '') }}" placeholder="smtp.gmail.com" required>
      </div>
      <div class="form-group">
        <label>Port</label>
        <input type="number" name="port" value="{{ old('port', $setting->port ?? 587) }}" placeholder="587" required>
      </div>
      <div class="form-group">
        <label>Enkripsi</label>
        <select name="encryption" required>
          <option value="tls" {{ old('encryption', ($setting->encryption ?? 'tls') === 'tls') ? 'selected' : '' }}>TLS (Port 587 — umum)</option>
          <option value="ssl" {{ old('encryption', ($setting->encryption ?? '') === 'ssl') ? 'selected' : '' }}>SSL (Port 465)</option>
          <option value="none" {{ old('encryption', ($setting->encryption ?? '') === null) ? 'selected' : '' }}>Tanpa Enkripsi (Port 25)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Email Aktif (Username SMTP)</label>
        <input type="email" name="username" value="{{ old('username', $setting->username ?? '') }}" placeholder="email.aktif@gmail.com" required>
      </div>
      <div class="form-group">
        <label>Password SMTP {{ $passwordSet ? '(biarkan kosong jika tidak diubah)' : '' }}</label>
        <input type="password" name="password" placeholder="{{ $passwordSet ? '•••••••• (sudah tersimpan)' : 'App Password / Password SMTP' }}" {{ $passwordSet ? '' : 'required' }} autocomplete="new-password">
      </div>
      <div class="form-group">
        <label>Email Pengirim (From)</label>
        <input type="email" name="from_address" value="{{ old('from_address', $setting->from_address ?? '') }}" placeholder="email.aktif@gmail.com" required>
      </div>
      <div class="form-group">
        <label>Nama Pengirim</label>
        <input type="text" name="from_name" value="{{ old('from_name', $setting->from_name ?? 'SIPP') }}" placeholder="SIPP" required>
      </div>
    </div>

    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#555;font-weight:500;margin-bottom:16px">
      <input type="checkbox" name="is_active" style="width:auto" {{ old('is_active', !$setting || $setting->is_active) ? 'checked' : '' }}>
      Aktifkan pengiriman email dengan konfigurasi ini
    </label>

    <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
  </form>
</div>

{{-- ── TEST EMAIL ── --}}
<div class="card" style="margin-top:16px">
  <div class="card-title">Test Kirim Email</div>
  <p style="font-size:12px;color:#888;margin-bottom:12px">Kirim email percobaan untuk memastikan konfigurasi SMTP berfungsi sebelum dipakai untuk reset password.</p>
  <form method="POST" action="{{ route('smtp-settings.test') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    @csrf
    <div class="form-group" style="margin-bottom:0;flex:1;min-width:220px">
      <label>Tujuan Email Test</label>
      <input type="email" name="test_email" value="{{ old('test_email', auth()->user()->email) }}" placeholder="tujuan@test.com" required>
    </div>
    <button type="submit" class="btn btn-success" style="white-space:nowrap">📤 Kirim Email Test</button>
  </form>
</div>
@endsection
