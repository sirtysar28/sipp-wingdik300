<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
<title>Reset Password — SIPP - Sistem Informasi Penilaian Prestasi</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#fff;border:1px solid #e8e8ed;border-radius:16px;padding:36px 32px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.logo{text-align:center;margin-bottom:24px}
.logo h1{font-size:24px;font-weight:700;color:#1a1a2e;letter-spacing:1px}
.logo p{font-size:12px;color:#6b7280;margin-top:4px}
.icon-circle{width:52px;height:52px;margin:0 auto 14px;border-radius:50%;background:#ecfdf5;display:flex;align-items:center;justify-content:center;font-size:24px}
label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
input{width:100%;padding:10px 14px;border:1px solid #d0d0d8;border-radius:8px;font-size:14px;font-family:inherit;transition:border .15s;margin-bottom:14px}
input:focus{outline:none;border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.pw-wrap{position:relative;margin-bottom:14px}
.pw-wrap input{margin-bottom:0;padding-right:40px}
.pw-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#888;font-size:18px;line-height:1}
.pw-toggle:hover{color:#4f46e5}
.btn{width:100%;padding:11px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;margin-top:4px}
.btn:hover{background:linear-gradient(135deg,#4338ca,#6d28d9);transform:translateY(-1px);box-shadow:0 4px 12px rgba(79,70,229,.3)}
.btn-outline{width:100%;padding:10px;background:#fff;color:#555;border:1px solid #d0d0d8;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s;margin-top:10px;display:inline-block;text-align:center;text-decoration:none}
.btn-outline:hover{background:#f4f5f7;color:#1a1a2e}
.error{font-size:12px;color:#b91c1c;background:#fee2e2;padding:10px 12px;border-radius:7px;margin-bottom:12px}
.success{font-size:12px;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:10px 12px;border-radius:7px;margin-bottom:12px;line-height:1.5}
.hint{font-size:12px;color:#6b7280;line-height:1.6;margin-bottom:18px;text-align:center}
.footer-text{text-align:center;font-size:11px;color:#9ca3af;margin-top:20px}
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <div class="icon-circle">🔐</div>
    <h1><span style="color:#4f46e5">SIPP</span></h1>
    <p>Sistem Informasi Penilaian Prestasi</p>
  </div>

  @if($errors->any())
    <div class="error">{{ $errors->first() }}</div>
  @endif

  <p class="hint">Buat <strong>password baru</strong> untuk akun Anda.<br>Email: <strong>{{ $email }}</strong></p>

  <form method="POST" action="{{ url('/reset-password') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label>Email</label>
    <input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" placeholder="email@sipp.id" required>
    <label>Password Baru</label>
    <div class="pw-wrap">
      <input type="password" name="password" id="newPw" autocomplete="new-password" placeholder="Minimal 6 karakter" required>
      <button type="button" class="pw-toggle" onclick="togglePw('newPw',this)">👁️</button>
    </div>
    <label>Konfirmasi Password Baru</label>
    <div class="pw-wrap">
      <input type="password" name="password_confirmation" id="confirmPw" autocomplete="new-password" placeholder="Ulangi password baru" required>
      <button type="button" class="pw-toggle" onclick="togglePw('confirmPw',this)">👁️</button>
    </div>
    <button type="submit" class="btn">Simpan Password Baru</button>
  </form>

  <a href="{{ url('/login') }}" class="btn-outline">← Kembali ke Login</a>
  <div class="footer-text">© 2026 SIPP, All Right Reserved.</div>
</div>
<script>
function togglePw(id,btn){
  const i=document.getElementById(id);
  if(i.type==='password'){i.type='text';btn.textContent='🙈'}else{i.type='password';btn.textContent='👁️'}
}
</script>
</body>
</html>
