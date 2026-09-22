<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
<title>@yield('title', 'SIPP') — Sistem Informasi Penilaian Prestasi</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:14px;background:#f4f5f7;color:#1a1a2e;min-height:100vh}
a{color:inherit;text-decoration:none}

/* --- SIDEBAR RESPONSIVE --- */
.sidebar{
  position:fixed;top:0;left:0;width:260px;height:100vh;
  background:#fff;border-right:1px solid #e8e8ed;
  display:flex;flex-direction:column;z-index:1000;
  transform: translateX(0);transition: transform 0.2s ease;
}
.sidebar-logo{padding:16px 20px;border-bottom:1px solid #e8e8ed;display:flex;align-items:center;gap:10px}
.sidebar-logo img{width:36px;height:36px;object-fit:contain;flex-shrink:0}
.sidebar-logo .app-name{font-size:15px;font-weight:700;color:#1a1a2e}
.sidebar-logo .app-sub{font-size:10px;color:#888;margin-top:1px}
.sidebar-logo .smart-badge{display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;font-size:8px;font-weight:700;padding:1px 6px;border-radius:99px;margin-left:4px;letter-spacing:0.5px}
.sidebar-nav{flex:1;padding:8px 0;overflow-y:auto}
.nav-section{font-size:10px;font-weight:600;color:#aaa;letter-spacing:.08em;text-transform:uppercase;padding:10px 20px 4px}
.nav-link{display:flex;align-items:center;gap:10px;padding:8px 20px;color:#555;font-size:13px;border-radius:0;transition:background .15s,color .15s;cursor:pointer}
.nav-link:hover{background:#f4f5f7;color:#1a1a2e}
.nav-link.active{background:#eef2ff;color:#4f46e5;font-weight:500}
.nav-link svg{width:16px;height:16px;flex-shrink:0}
.sidebar-user{padding:14px 20px;border-top:1px solid #e8e8ed;display:flex;align-items:center;gap:10px}
.avatar-sm{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;flex-shrink:0}
.avatar-admin{background:#eef2ff;color:#4f46e5}
.avatar-super{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff}
.avatar-akademik{background:#ecfdf5;color:#059669}
.avatar-kepribadian{background:#eff6ff;color:#2563eb}
.avatar-samapta{background:#fff7ed;color:#ea580c}

/* overlay */
.sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.3);z-index:999;backdrop-filter:blur(2px)}

.main{margin-left:260px;min-height:100vh;display:flex;flex-direction:column;transition:margin-left 0.2s ease}

.topbar{
  background:#fff;border-bottom:1px solid #e8e8ed;padding:0 28px;min-height:50px;
  display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:50;
  flex-wrap:wrap;
}
.hamburger{display:none;background:none;border:none;cursor:pointer;padding:6px;border-radius:6px;color:#4f46e5}
.hamburger svg{width:22px;height:22px;stroke:currentColor;stroke-width:2;fill:none}
.hamburger:hover{background:#f0f0f5}
.topbar-title{font-size:19px;font-weight:500}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.content{padding:28px;flex:1}

/* card & components */
.card{background:#fff;border:1px solid #e8e8ed;border-radius:10px;padding:20px}
.card-title{font-size:13px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-bottom:14px}
.metric-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.metric-card{background:#fff;border:1px solid #e8e8ed;border-radius:10px;padding:16px}
.metric-label{font-size:12px;color:#888;margin-bottom:6px}
.metric-value{font-size:24px;font-weight:600;color:#1a1a2e}
.metric-sub{font-size:11px;color:#aaa;margin-top:2px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;border:1px solid transparent;transition:all .15s}
.btn-primary{background:#4f46e5;color:#fff;border-color:#4f46e5}
.btn-primary:hover{background:#4338ca}
.btn-success{background:#059669;color:#fff;border-color:#059669}
.btn-success:hover{background:#047857}
.btn-warning{background:#d97706;color:#fff;border-color:#d97706}
.btn-warning:hover{background:#b45309}
.btn-outline{background:#fff;color:#444;border-color:#d0d0d8}
.btn-outline:hover{background:#f4f5f7}
.btn-sm{padding:5px 10px;font-size:12px}
.btn-danger{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}
.btn-smart{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none}
.btn-smart:hover{background:linear-gradient(135deg,#4338ca,#6d28d9)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th{font-size:11px;font-weight:600;color:#888;text-align:left;padding:8px 12px;border-bottom:1px solid #e8e8ed;white-space:nowrap;text-transform:uppercase;letter-spacing:.04em}
td{font-size:13px;padding:10px 12px;border-bottom:1px solid #f0f0f5;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafafe}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500}
.badge-blue{background:#eef2ff;color:#4f46e5}
.badge-green{background:#ecfdf5;color:#059669}
.badge-amber{background:#fffbeb;color:#b45309}
.badge-red{background:#fee2e2;color:#b91c1c}
.badge-purple{background:#f5f3ff;color:#7c3aed}
.badge-orange{background:#fff7ed;color:#ea580c}
.form-group{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=date],select,textarea{width:100%;padding:8px 10px;border:1px solid #d0d0d8;border-radius:7px;font-size:13px;font-family:inherit;color:#1a1a2e;background:#fff;transition:border .15s}
input:focus,select:focus,textarea:focus{outline:none;border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px}
.alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.empty-state{text-align:center;padding:48px 20px;color:#aaa}
.empty-state svg{width:40px;height:40px;margin:0 auto 12px;opacity:.4}
.progress-bar{background:#e8e8ed;border-radius:4px;height:5px;overflow:hidden}
.progress-fill{height:100%;border-radius:4px;background:#4f46e5;transition:width .3s}
.pw-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#888;font-size:18px;line-height:1}
.pw-toggle:hover{color:#4f46e5}

/* Status cards for module admins */
.module-status{border-left:4px solid #4f46e5;padding:12px 16px;background:#fff;border-radius:0 8px 8px 0;margin-bottom:8px}
.module-status.akademik{border-left-color:#059669}
.module-status.kepribadian{border-left-color:#2563eb}
.module-status.samapta{border-left-color:#ea580c}

/* ========== PAGINATION (SIPP) ========== */
.sipp-pagination{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.sipp-page-info{font-size:12px;color:#888}
.sipp-page-info strong{color:#1a1a2e;font-weight:600}
.sipp-page-links{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap}
.sipp-page{display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 8px;border:1px solid #d0d0d8;border-radius:7px;font-size:12px;font-weight:500;color:#555;background:#fff;cursor:pointer;transition:all .15s;line-height:1;text-decoration:none}
.sipp-page:hover{background:#f4f5f7;color:#1a1a2e;border-color:#c0c0c8}
.sipp-page.sipp-active{background:#4f46e5;color:#fff;border-color:#4f46e5;font-weight:600}
.sipp-page.sipp-disabled{color:#bbb;background:#f9f9fb;cursor:not-allowed;border-color:#e8e8ed}
.sipp-page.sipp-dots{border:none;background:transparent;cursor:default;color:#aaa;padding:0 2px}

/* Filter bar */
.filter-bar{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:end}
.filter-bar .form-group{margin-bottom:0;flex:1;min-width:180px}

/* ========== MEDIA QUERY MOBILE ========== */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);box-shadow:none}
  .sidebar.open{transform:translateX(0);box-shadow:2px 0 12px rgba(0,0,0,0.1)}
  .sidebar-overlay.active{display:block}
  .main{margin-left:0!important}
  .hamburger{display:block}
  .topbar{padding:0 16px}
  .content{padding:20px 16px}
  .metric-grid{grid-template-columns:repeat(2,1fr);gap:10px}
  .filter-bar{flex-direction:column}
  .filter-bar .form-group{min-width:100%}
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="{{ asset('images/Logo_Wingdik_300_Tek.png') }}" alt="Logo" onerror="this.style.display='none'">
    <div>
      <div class="app-name">SIPP</div>
      <div class="app-sub">Sistem Informasi Penilaian Prestasi</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    @php
      $showReportNPA = auth()->user()->isAdminAkademik() || auth()->user()->canSeeAll();
      $showReportNPK = auth()->user()->isAdminKepribadian() || auth()->user()->canSeeAll();
      $showReportNPS = auth()->user()->isAdminSamapta() || auth()->user()->canSeeAll();
      $showReportNPP = auth()->user()->isAdminAkademik() || auth()->user()->canSeeAll();
      // Mata Pelajaran: super_admin, admin, admin_akademik (admin NPA)
      $showMataPelajaran = auth()->user()->isAdminAkademik() || auth()->user()->canSeeAll();
      $showAnyReport = $showReportNPA || $showReportNPK || $showReportNPS || $showReportNPP;
      // Report Individual: hanya super_admin, admin, admin_akademik.
      // Danflight (admin_kepribadian) & BinJas (admin_samapta) TIDAK boleh
      // melihat hasil individu akhir pendidikan (revisi 13 Agustus 2026).
      $showReportIndividu = auth()->user()->isAdminAkademik() || auth()->user()->canSeeAll();
      // Penandatanganan: semua role modul + super_admin/admin (revisi 13 Agustus 2026).
      $showPenandatangan = auth()->user()->canSeeAll()
          || auth()->user()->isAdminAkademik()
          || auth()->user()->isAdminKepribadian()
          || auth()->user()->isAdminSamapta();
    @endphp

    {{-- ═══ MONITORING ═══ --}}
    <div class="nav-section">Monitoring</div>
    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    @if(auth()->user()->canSeeAll())
    <a href="{{ route('leaderboard') }}" class="nav-link {{ request()->routeIs('leaderboard*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      Leaderboard
    </a>
    @endif

    {{-- ═══ REPORT ═══ --}}
    @if($showAnyReport)
    <div class="nav-section">Report</div>
    @if($showReportNPA)
    <a href="{{ route('report.npa') }}" class="nav-link {{ request()->routeIs('report.npa*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Report NPA
    </a>
    @endif
    @if($showMataPelajaran)
    <a href="{{ route('mata-pelajaran.index') }}" class="nav-link {{ request()->routeIs('mata-pelajaran*') ? 'active' : '' }}" style="padding-left:46px">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      Mata Pelajaran
    </a>
    @endif
    @if($showReportNPK)
    <a href="{{ route('report.npk') }}" class="nav-link {{ (request()->routeIs('report.npk*') || request()->routeIs('rekap*')) ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Report NPK
    </a>
    @if(auth()->user()->isAdminKepribadian() || auth()->user()->canSeeAll())
    <a href="{{ route('kepribadian.periode.create') }}" class="nav-link {{ request()->routeIs('kepribadian.periode.create') ? 'active' : '' }}" style="padding-left:46px">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Tambah Periode
    </a>
    @endif
    @endif
    @if($showReportNPS)
    <a href="{{ route('report.nps') }}" class="nav-link {{ (request()->routeIs('report.nps*') || request()->routeIs('nilai-samapta*')) ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Report NPS
    </a>
    <a href="{{ route('nilai-samapta.putaran.create') }}" class="nav-link {{ request()->routeIs('nilai-samapta.putaran.create') ? 'active' : '' }}" style="padding-left:46px">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Tambah Putaran
    </a>
    @endif
    @if($showReportNPP)
    <a href="{{ route('report.npp') }}" class="nav-link {{ request()->routeIs('report.npp*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Report NPP (Angkatan)
    </a>
    @endif
    @if($showReportIndividu)
    <a href="{{ route('report.individu') }}" class="nav-link {{ request()->routeIs('report.individu') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Report Individual
    </a>
    @endif
    @endif

    {{-- ═══ CETAK LAPORAN (super_admin, admin) ═══ --}}
    @if(auth()->user()->canSeeAll())
    <div class="nav-section">Cetak Laporan</div>
    <a href="{{ route('laporan.index') }}" class="nav-link {{ request()->routeIs('laporan*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Cetak Laporan
    </a>
    @endif

    {{-- ═══ MASTER DATA (super_admin SAJA) ═══ --}}
    {{-- Revisi 13 Agustus 2026: modul Master Data di-takeout dari role
         Opsdik/admin. Hanya WingDik (super_admin) yang mengelola data
         struktur & user. --}}
    @if(auth()->user()->isSuperAdmin())
    <div class="nav-section">Master Data</div>
    <a href="{{ route('lemdik.index') }}" class="nav-link {{ request()->routeIs('lemdik*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
      Lembaga Pendidikan
    </a>
    <a href="{{ route('skadik.index') }}" class="nav-link {{ request()->routeIs('skadik*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
      Sekolah
    </a>
    <a href="{{ route('angkatan.index') }}" class="nav-link {{ request()->routeIs('angkatan*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Angkatan
    </a>
    <a href="{{ route('peserta.index') }}" class="nav-link {{ request()->routeIs('peserta.index') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Peserta Didik
    </a>
    @if(auth()->user()->isAdminKepribadian() || auth()->user()->canSeeAll())
    <a href="{{ route('kepribadian.aspek') }}" class="nav-link {{ request()->routeIs('kepribadian.aspek*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
      Kelola Aspek Kepribadian
    </a>
    @endif
    <a href="{{ route('manage-user.index') }}" class="nav-link {{ request()->routeIs('manage-user*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
      Manage User
    </a>
    <div class="nav-section">Pengaturan</div>
    <a href="{{ route('smtp-settings.index') }}" class="nav-link {{ request()->routeIs('smtp-settings*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l9 6 9-6M3 8v8a2 2 0 002 2h14a2 2 0 002-2V8M3 8a2 2 0 012-2h14a2 2 0 012 2"/></svg>
      Pengaturan SMTP
    </a>
    @endif

    {{-- ═══ PENANDATANGANAN (semua role modul + super_admin/admin) ═══ --}}
    {{-- Revisi 13 Agustus 2026: fitur penandatanganan ditambahkan ke
         Kepala Sekolah, Danflight & BinJas. --}}
    @if($showPenandatangan)
    <div class="nav-section">Penandatanganan</div>
    <a href="{{ route('penandatangan.index') }}" class="nav-link {{ request()->routeIs('penandatangan*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
      Penandatangan
    </a>
    @endif

    <div class="nav-section">Bantuan</div>
    <a href="/manual-book.html" target="_blank" class="nav-link">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      Manual Book
    </a>
  </nav>

  <div class="sidebar-user">
    @php
      $avatarClass = 'avatar-admin';
      if(auth()->user()->isSuperAdmin()) $avatarClass = 'avatar-super';
      elseif(auth()->user()->isAdminAkademik()) $avatarClass = 'avatar-akademik';
      elseif(auth()->user()->isAdminKepribadian()) $avatarClass = 'avatar-kepribadian';
      elseif(auth()->user()->isAdminSamapta()) $avatarClass = 'avatar-samapta';
    @endphp
    <div class="avatar-sm {{ $avatarClass }}">{{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 2)) }}</div>
    <div style="flex:1;min-width:0">
      <div style="font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ auth()->user()?->name }}</div>
      <div style="font-size:11px;color:#aaa">{{ auth()->user()->module_label }}@if(auth()->user()->skadik) — {{ auth()->user()->skadik->nama }}@endif</div>
    </div>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" style="background:none;border:none;cursor:pointer;color:#aaa;font-size:11px">Keluar</button>
    </form>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <img src="{{ asset('images/Logo_Wingdik_300_Tek.png') }}" alt="Logo SIPP" class="topbar-logo"
         style="height:100px;width:auto;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
    <span class="topbar-title">@yield('page-title', 'Dashboard')</span>
    <div class="topbar-right">
      @yield('topbar-actions')
      <!-- Logo kanan dihapus -->
    </div>
  </div>

  <div class="content">
    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('info'))
      <div class="alert" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8">{{ session('info') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif
    @yield('content')
  </div>
</div>

<script>
const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),hamburger=document.getElementById('hamburgerBtn');
function openSidebar(){sidebar.classList.add('open');overlay.classList.add('active');document.body.style.overflow='hidden'}
function closeSidebar(){sidebar.classList.remove('open');overlay.classList.remove('active');document.body.style.overflow=''}
hamburger.addEventListener('click',e=>{e.stopPropagation();sidebar.classList.contains('open')?closeSidebar():openSidebar()});
overlay.addEventListener('click',closeSidebar);
document.querySelectorAll('.sidebar .nav-link').forEach(l=>l.addEventListener('click',()=>{if(window.innerWidth<=768)closeSidebar()}));
window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSidebar()});
</script>

@stack("scripts")
</body>
</html>
