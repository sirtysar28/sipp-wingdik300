<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
<title>Login — SIPP - Sistem Informasi Penilaian Prestasi</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#fff;border:1px solid #e8e8ed;border-radius:16px;padding:36px 32px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.logo{text-align:center;margin-bottom:24px}
.logo h1{font-size:24px;font-weight:700;color:#1a1a2e;letter-spacing:1px}
.logo p{font-size:12px;color:#6b7280;margin-top:4px}
.badge-smart{display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;font-size:10px;font-weight:600;padding:2px 10px;border-radius:99px;margin-top:6px;letter-spacing:1px}
label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
input{width:100%;padding:10px 14px;border:1px solid #d0d0d8;border-radius:8px;font-size:14px;font-family:inherit;transition:border .15s;margin-bottom:14px}
input:focus{outline:none;border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.pw-wrap{position:relative;margin-bottom:14px}
.pw-wrap input{margin-bottom:0;padding-right:40px}
.pw-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#888;font-size:18px;line-height:1}
.pw-toggle:hover{color:#4f46e5}
.btn{width:100%;padding:11px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;margin-top:4px}
.btn:hover{background:linear-gradient(135deg,#4338ca,#6d28d9);transform:translateY(-1px);box-shadow:0 4px 12px rgba(79,70,229,.3)}
.error{font-size:12px;color:#b91c1c;background:#fee2e2;padding:10px 12px;border-radius:7px;margin-bottom:12px}
.form-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px}
.remember{display:flex;align-items:center;gap:6px;font-size:12px;color:#666;margin-bottom:0;cursor:pointer}
.lupa-link{font-size:12px;color:#4f46e5;text-decoration:none;font-weight:500;white-space:nowrap}
.lupa-link:hover{text-decoration:underline;color:#4338ca}
.footer-text{text-align:center;font-size:11px;color:#9ca3af;margin-top:20px}
.login-success{font-size:12px;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:10px 12px;border-radius:7px;margin-bottom:12px;line-height:1.5}
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <h1><span style="color:#4f46e5">SIPP</span></h1>
    <p>Sistem Informasi Penilaian Prestasi</p>
  </div>

  @if(session('status'))
    <div class="login-success">{{ session('status') }} Silakan login dengan password baru Anda.</div>
  @endif

  @if($errors->any())
    <div class="error">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="/login">
    @csrf
    <label>Email</label>
    <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="email@sipp.id" required autofocus>
    <label>Password</label>
    <div class="pw-wrap">
      <input type="password" name="password" id="loginPw" autocomplete="current-password" placeholder="••••••••" required>
      <button type="button" class="pw-toggle" onclick="togglePw('loginPw',this)">👁️</button>
    </div>
    <div class="form-row">
      <label class="remember">
        <input type="checkbox" name="remember" style="width:auto;margin:0"> Ingat saya
      </label>
      <a href="{{ url('/forgot-password') }}" class="lupa-link">🔑 Lupa Password?</a>
    </div>
    <button type="submit" class="btn">Masuk</button>
  </form>
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
