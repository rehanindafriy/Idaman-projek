<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>efidi. – Evolving Framework for Integrated Data</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
   <!-- FAVICON / LOGO PALING ATAS -->
  <link rel="icon" type="image/png" sizes="32x32" href="images/icon/logoo.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/icon/logoo.png">
  <!-- optional: untuk compatibility lama -->
  <link rel="shortcut icon" href="images/logoo.png">


  <!-- Font mirip BUMN -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
   <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">


  <style>
    :root {
      --efidi-teal: #00a3ad;
      --efidi-teal-soft: #e5f7f87b;
      --navy: #182958;
      --text-main: #2a2a2a;
      --text-muted: #404040;
      --border-soft: #e5e7eb;
      --bg-body: #ffffff;

      --radius-lg: 28px;
      --radius-pill: 9px;
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      padding: 0;
      font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--text-main);
      background-color: var(--bg-body);

      /* "f" dan "i" tidak menyatu */
      font-variant-ligatures: none;
      font-feature-settings: "liga" 0, "clig" 0;
    }

    body {
      line-height: 1.6;
      font-size: 16px;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    .efidi-container {
      max-width: 1360px;
      margin: 0 auto;
      padding: 0 40px;
    }

    /* NAVBAR */
    .efidi-navbar {
      position: sticky;
      top: 0;
      z-index: 40;
      background: #ffffff;
      border-bottom: 1px solid rgba(229, 231, 235, 0.9);
    }

    .efidi-navbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 80px;
    }

    .efidi-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 700;
      font-size: 22px;
      color: var(--navy);
    }

   /* container logo di navbar */
/* container logo di navbar */
.efidi-brand {
  display: flex;
  align-items: center;   /* semua logo sejajar vertikal */
  gap: 20px;             /* jarak antar logo */
}

/* base semua logo */
.efidi-brand-logo {
  display: block;
  object-fit: contain;
}

/* ukuran masing-masing logo */
.efidi-brand-logo--efidi {
  height: 24px;      /* ukuran logo efidi */
  margin-top: 8px;   /* atur tinggi vertikal (coba 2–3px) */
  margin-right: -11px;
}


.efidi-brand-logo--telkom {
  height: 34px;
}



    .efidi-brand span:last-child {
      color: var(--efidi-teal);
    }

    .efidi-nav-links {
      display: flex;
      align-items: center;
      gap: 40px;
      font-size: 15px;
      font-weight: 400;
    }

    .efidi-nav-links a {
      position: relative;
      color: var(--text-main);
      padding-bottom: 4px;
    }

    .efidi-nav-links a::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      width: 0;
      height: 1px;
      border-radius: 999px;
      background-color: var(--navy);
      transition: width 0.2s ease;
    }

    .efidi-nav-links a.active::after,
    .efidi-nav-links a:hover::after {
      width: 26px;
    }

    /* NAV BUTTONS */
    .efidi-nav-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 15px;
    }

    .btn-nav-outline,
    .btn-nav-primary,
    .btn-hero-primary {
      border-radius: var(--radius-pill);
      padding: 7px 25px;
      font-size: 13px;
      font-weight: 500;
      border: 1px solid transparent;
      background: transparent;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-nav-outline {
      border-color: var(--efidi-teal);
      background: var(--efidi-teal-soft);
      color: var(--efidi-teal);
    }

    .btn-nav-primary {
      border: none;
      background: var(--efidi-teal);
      color: #ffffff;
    }

    .btn-nav-primary:hover {
      background: #008996;
    }

    .btn-nav-outline:hover {
      background: #d3eef0;
    }

    .btn-hero-primary {
      background: var(--navy);
      color: #ffffff;
      border-color: var(--navy);
    }

    .btn-hero-primary:hover {
      background: #111a3d;
      border-color: #111a3d;
    }

    /* HERO */
    .hero-efidi {
      position: relative;
      background: #ffffff;
      overflow: hidden;
      border-bottom: 1px solid rgba(229, 231, 235, 0.9);
    }

.hero-inner {
  position: relative;
  z-index: 1;

  /* tadinya: min-height: calc(100vh - 80px); */
  /* sekarang: cukup 1/2–3/4 layar, tidak ada ruang kosong besar di bawah */
  min-height: min(650px, calc(100vh - 80px));

  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 48px;

  /* tadinya: padding: 72px 0 96px; (bawahnya tinggi banget) */
  padding: 56px 0 36px;
}

.hero-left {
  flex: 0.9;
}

/* HERO RIGHT – DOT MAP LEBIH BESAR & NGEJATUH KE BAWAH */
.hero-right {
  flex: 1.1;
  min-height: 520px;
  display: flex;
  align-items: flex-end;      /* logo-logo ikut duduk di bawah */
  justify-content: center;
  position: relative;
  z-index: 1;
  margin-right: -40px;
}

/* DOT MAP */
.hero-map-bg {
  position: absolute;
  inset: 0;
  background-image: url("images/hero-dotmappp.png");
  background-repeat: no-repeat;

  /* DIBESARKAN dan di-anchor ke kiri-bawah,
     supaya area kosong bawah kepakai gambar */
  background-size: 120% auto;
  background-position: left bottom;

  pointer-events: none;
  opacity: 1;
   z-index: 0; 
}

@media (min-width: 1200px) {
  .hero-right {
    flex: 1.15;
    margin-right: -20px;
    align-items: flex-end;    /* logo tetap duduk di bawah */
  }

  .hero-map-bg {
    position: absolute;

    /* "margin" versi absolute – diperlebar keluar */
    top: -40px;     /* seperti margin-top */
    bottom: -40px;  /* seperti margin-bottom */
    left: -110px;    /* seperti margin-left */
    right: -50px;   /* opsional, boleh 0 kalau mau */

    /* gambar membesar */
    background-size: 97% auto;  /* boleh 110–130% sesuai selera */
    background-position: left bottom;
  }

  /* biar logo sedikit turun mengikuti map */
  .hero-map-logos {
    transform: translateY(10px);
  }
}

    .hero-pretitle {
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--navy);
      margin-bottom: 9px;
    }

    .hero-title {
      font-size: 43px;
      line-height: 1.2;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 20px;
    }

    .hero-title span.highlight {
      color: var(--efidi-teal);
    }

    .hero-subtitle {
      font-size: 14px;
      color: var(--text-main);
      max-width: 620px;
      margin-bottom: 28px;
    }

    .hero-cta {
      display: flex;
      gap: 16px;
      align-items: center;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .hero-meta {
      font-size: 14px;
      color: var(--text-main);
      max-width: 640px;
    }

/* =========================
   HERO RIGHT – DOT MAP + LOGO
   ========================= */

.hero-right {
  flex: 1;
  min-height: 450px;
  display: flex;
  align-items: flex-end;       /* isi (logo-logo) ikut turun ke bawah */
  justify-content: center;
  position: relative;
  z-index: 1;
}

/* layer animasi titik di atas dot-map */
.hero-map-overlay{
  position:absolute;
  inset:0;
  pointer-events:none;
  z-index:1;          /* di atas PNG map, di bawah logo-logo */
  overflow:hidden;
}

/* TITIK-TITIK “JARINGAN” PUTIH – KEDIP PUTUS-PUTUS */
.map-node{
  position:absolute;
  width:9px;
  height:9px;
  border-radius:999px;
  background: #fdfdfdff;  /* PUTIH, bukan tosca */
  box-shadow:
    0 0 0 0 rgba(255,255,255,0.25),
    0 0 10px rgba(255,255,255,0.85);
  opacity:.18;

  /* kedip ala jaringan ngelag (loncat, bukan halus) */
  animation-name: nodeBlink;
  animation-duration:2.7s;
  animation-iteration-count: infinite;
  animation-timing-function: steps(4,end);
}

.map-node::after{
  content:"";
  position:absolute;
  inset:1px;
  border-radius:inherit;
  border:1px solid rgba(255,255,255,0.9);
  opacity:.75;
}

/* variasi timing supaya kedipnya gantian */
.map-node--slow   { animation-duration:3.5s; }
.map-node--fast   { animation-duration:2.1s; }
.map-node--delay1 { animation-delay:.6s; }
.map-node--delay2 { animation-delay:1.2s; }
.map-node--delay3 { animation-delay:1.8s; }

@keyframes nodeBlink{
  0%   { opacity:.15; transform:scale(.9); }
  18%  { opacity:1;   transform:scale(1); }
  22%  { opacity:.2;  transform:scale(.96); }
  50%  { opacity:.8;  transform:scale(1); }
  60%  { opacity:.1;  transform:scale(.9); }
  100% { opacity:.25; transform:scale(.95); }
}

/* ======================================
   POSISI TITIK-TITIK
   (DIBANYAKIN DI SEKITAR TITIK ASAL)
   ====================================== */

/* --- CLUSTER INDONESIA (PULAU UTAMA) --- */
.map-node--id-main      { bottom:70%; right:44%; }
.map-node--id-main-1    { bottom:72%; right:45%; }
.map-node--id-main-2    { bottom:68%; right:43%; }
.map-node--id-main-3    { bottom:71%; right:42%; }
.map-node--id-main-4    { bottom:69%; right:46%; }
.map-node--id-main-5    { bottom:73%; right:43%; }

/* --- CLUSTER INDONESIA TIMUR --- */
.map-node--id-east      { bottom:64%; right:52%; }
.map-node--id-east-1    { bottom:66%; right:51%; }
.map-node--id-east-2    { bottom:62%; right:53%; }
.map-node--id-east-3    { bottom:63%; right:50%; }
.map-node--id-east-4    { bottom:65%; right:54%; }

/* --- CLUSTER INDONESIA BARAT / SUMATRA --- */
.map-node--id-west      { bottom:79%; right:50%; }
.map-node--id-west-1    { bottom:77%; right:49%; }
.map-node--id-west-2    { bottom:81%; right:51%; }
.map-node--id-west-3    { bottom:78%; right:52%; }
.map-node--id-west-4    { bottom:80%; right:48%; }

/* --- CLUSTER LAUT SEKITAR (SEA) --- */
.map-node--sea1         { bottom:19%; right:26%; }
.map-node--sea1-1       { bottom:21%; right:27%; }
.map-node--sea1-2       { bottom:17%; right:25%; }
.map-node--sea1-3       { bottom:20%; right:24%; }

.map-node--sea2         { bottom:18%; right:19%; }
.map-node--sea2-1       { bottom:20%; right:18%; }
.map-node--sea2-2       { bottom:16%; right:21%; }
.map-node--sea2-3       { bottom:19%; right:22%; }

/* --- CLUSTER ASIA ATAS --- */
.map-node--asia1        { bottom:90%; right:60%; }
.map-node--asia1-1      { bottom:88%; right:59%; }
.map-node--asia1-2      { bottom:92%; right:61%; }
.map-node--asia1-3      { bottom:89%; right:62%; }

.map-node--asia2        { bottom:75%; right:70%; }
.map-node--asia2-1      { bottom:77%; right:69%; }
.map-node--asia2-2      { bottom:73%; right:71%; }
.map-node--asia2-3      { bottom:76%; right:72%; }

.map-node--asia3        { bottom:85%; right:39%; }
.map-node--asia3-1      { bottom:83%; right:38%; }
.map-node--asia3-2      { bottom:87%; right:40%; }
.map-node--asia3-3      { bottom:84%; right:41%; }

/* --- CLUSTER EROPA --- */
.map-node--eu1          { top:25%;  left:80%; }
.map-node--eu1-1        { top:23%;  left:79%; }
.map-node--eu1-2        { top:27%;  left:82%; }

.map-node--eu2          { top:2%;   left:51%; }
.map-node--eu2-1        { top:4%;   left:52%; }
.map-node--eu2-2        { top:0%;   left:49%; }

.map-node--eu3          { top:10%;  left:20%; }
.map-node--eu3-1        { top:8%;   left:21%; }
.map-node--eu3-2        { top:12%;  left:19%; }
.map-node--eu3-3        { top:11%;  left:23%; }

/* --- CLUSTER AMERIKA UTARA --- */
.map-node--na1          { top:38%;  left:20%; }
.map-node--na1-1        { top:36%;  left:19%; }
.map-node--na1-2        { top:40%;  left:22%; }

.map-node--na2          { top:46%;  left:4%; }
.map-node--na2-1        { top:44%;  left:6%; }
.map-node--na2-2        { top:48%;  left:3%; }
.map-node--na2-3        { top:45%;  left:1%; }

/* --- CLUSTER AMERIKA SELATAN --- */
.map-node--sa1          { bottom:46%; left:21%; }
.map-node--sa1-1        { bottom:44%; left:22%; }
.map-node--sa1-2        { bottom:48%; left:19%; }

.map-node--sa2          { bottom:23%; left:16%; }
.map-node--sa2-1        { bottom:21%; left:17%; }
.map-node--sa2-2        { bottom:25%; left:15%; }
.map-node--sa2-3        { bottom:22%; left:18%; }

/* --- BEBERAPA TITIK GLOBAL TAMBAHAN (BACKGROUND) --- */
.map-node--bg1          { top:18%;  right:30%; }
.map-node--bg2          { top:32%;  right:36%; }
.map-node--bg3          { top:58%;  right:62%; }
.map-node--bg4          { top:66%;  left:32%; }
.map-node--bg5          { bottom:32%; right:42%; }
.map-node--bg6          { bottom:12%; right:34%; }
.map-node--bg7          { bottom:32%; left:8%;  }
.map-node--bg8          { bottom:12%; left:28%; }

/* logo-logo efidi tetap di layer paling atas */
.hero-map-logos {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 2;
}

/* base semua logo */
.hero-map-logo {
  position: absolute;
  object-fit: contain;
  filter: drop-shadow(0 2px 6px rgba(0, 217, 255, 0.2));
}
/* ===== POSISI & UKURAN PER LOGO (BISA DISESUAIKAN) ===== */
/* 1. efidi utama */
.hero-map-logo--efidi {
  width: 80px;
  top: 37%;   /* geser vertikal di sini */
  left: 53%;  /* geser horizontal di sini */
  animation: mapLogoFloat1 6s ease-in-out infinite;
}

/* 2. Archi efidi */
.hero-map-logo--archi {
  width: 50px;
  top: 7%;
  left: 84%;
  animation: mapLogoFloat2 7s ease-in-out infinite;
}

/* 3. Announc efidi */
.hero-map-logo--announc {
  width: 54px;
  top: -12%;
  left: 27%;
  animation: mapLogoFloat3 5.5s ease-in-out infinite;
}

/* 4. efidi.ai */
.hero-map-logo--ai {
  width: 34px;
  top: 2%;
  left: 4%;
  animation: mapLogoFloat4 7.5s ease-in-out infinite;
}

/* 5. Admin efidi */
.hero-map-logo--admin {
  width: 39px;
  top: 22%;
  left: 37%;
  animation: mapLogoFloat5 6.5s ease-in-out infinite;
  z-index: 2;
}

/* LINGKARAN PUTIH DI BELAKANG ADMIN (TANPA WRAPPER) */
.hero-map-logo--admin-circle {
  position: absolute;
  /* ikut gerakan ADMIN: pakai animasi yang sama */
  animation: mapLogoFloat5 6.5s ease-in-out infinite;

  /* ukuran lingkaran (boleh kamu ubah) */
  width: 26px;
  height: 26px;

  /* peletakan: dibuat sedikit geser supaya logo 39px
     berada di tengah lingkaran 60px (boleh kamu geser lagi) */
  top: calc(25% - 10px);
  left: calc(39% - 10px);

  background: #ffffff;
  border-radius: 50%;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.22);

  /* tidak perlu filter drop-shadow untuk lingkaran */
  filter: none;
  object-fit: initial;
  pointer-events: none; /* biar tidak mengganggu klik di area ini */
  z-index: 1;           /* di bawah logo admin */
}

/* 6. Finder efidi */
.hero-map-logo--finder {
  width: 51px;
  top: 56%;
  left: 23%;
  animation: mapLogoFloat6 8s ease-in-out infinite;
}

/* ===== ANIMASI NAIK-TURUN, BERBEDA SETIAP LOGO ===== */

@keyframes mapLogoFloat1 {
  0%   { transform: translateY(0); }
  50%  { transform: translateY(-10px); }
  100% { transform: translateY(0); }
}

@keyframes mapLogoFloat2 {
  0%   { transform: translateY(0); }
  50%  { transform: translateY(-14px); }
  100% { transform: translateY(0); }
}

@keyframes mapLogoFloat3 {
  0%   { transform: translateY(0); }
  50%  { transform: translateY(-8px); }
  100% { transform: translateY(0); }
}

@keyframes mapLogoFloat4 {
  0%   { transform: translateY(0); }
  50%  { transform: translateY(-16px); }
  100% { transform: translateY(0); }
}

@keyframes mapLogoFloat5 {
  0%   { transform: translateY(0); }
  50%  { transform: translateY(-11px); }
  100% { transform: translateY(0); }
}

@keyframes mapLogoFloat6 {
  0%   { transform: translateY(0); }
  50%  { transform: translateY(-18px); }
  100% { transform: translateY(0); }
}

/* RESPONSIVE – di layar sempit dot-map tetap rapi */
@media (max-width: 1024px) {
  .hero-right {
    width: 100%;
  }
  .hero-map-bg {
    background-position: center top;
    background-size: contain;
  }
}

@media (max-width: 640px) {
  .hero-right {
    min-height: 360px;
  }
}




    /* SECTION 2 – MODUL EFIDI (tetap) */
    .section-efidi-modul {
      background: #ffffff;
      position: relative;
      overflow: hidden;
      border-bottom: 1px solid rgba(229, 231, 235, 0.9);
    }

    .section-efidi-modul-inner {
      position: relative;
      z-index: 1;
      padding: 80px 0 88px;
    }

    .section-title {
      text-align: center;
      font-size: 32px;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 12px;
    }

    .section-subtitle {
      text-align: center;
      font-size: 15px;
      color: var(--text-main);
      max-width: 760px;
      margin: 0 auto 36px;
    }

    .modul-row {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 28px;
      margin-bottom: 24px;
    }

.modul-card {
  border-radius: 28px;
  padding: 24px 26px 22px;
  font-size: 15px;
  line-height: 1.6;
  position: relative;
  overflow: hidden;
}

/* hanya anak langsung card (judul, isi, dll), bukan dekorasi */
.modul-card > * {
  position: relative;
  z-index: 1;
}
/* dekorasi lingkaran (mirip BUMN) */
.modul-ring {
  position: absolute;
  pointer-events: none;
  z-index: 0;          /* SELALU di belakang teks */
}

.modul-ring--top {
  top: 0;
  right: 0;
}

.modul-ring--bottom {
  bottom: 0;
  left: 0;
  transform: rotate(180deg);
}


    .modul-header {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .modul-header span.dot {
      color: var(--efidi-teal);
    }

    .modul-body {
      color: var(--text-main);
      font-size: 15px;
    }

    .modul-body strong {
      color: var(--text-main);
    }

 /* VARIAN WARNA KARTU */
.modul-card--primary {
  background-color: #00a3ad;
  color: #ffffff;
}
.modul-card--primary .modul-header,
.modul-card--primary .modul-body,
.modul-card--primary .modul-body strong {
  color: #ffffff;
}

.modul-card--light {
  background-color: #ffffff;
  border: 1px solid rgba(148,163,184,0.35);
}

.modul-card--dark {
  background-color: #182958;
  color: #ffffff;
}
.modul-card--dark .modul-header,
.modul-card--dark .modul-body,
.modul-card--dark .modul-body strong {
  color: #ffffff;
}

/* TEKSTUR LINGKARAN – PERSIS SEPERTI BUMN (PAKAI SVG) */
.modul-card {
  position: relative;
  overflow: hidden;
}
.modul-card * {
  position: relative;
  z-index: 1;
}

/* SVG lingkaran */
.modul-ring {
  position: absolute;
  width: 133px;   /* sama persis dengan width SVG */
  height: 117px;  /* sama persis dengan height SVG */
  pointer-events: none;
  fill: none;
  z-index: 0;
}

/* posisi seperti contoh BUMN */
.modul-ring--tr {
  top: -24px;
  right: -6px;
}
.modul-ring--bl {
  bottom: -40px;
  left: -12px;
}

/* warna stroke per tema */
.modul-ring circle {
  stroke-width: 2.24388;
}
.modul-ring--teal  circle { stroke: #04AFCD; }                     /* kartu teal */
.modul-ring--gray  circle { stroke: #D2DEEB; }                     /* kartu putih */
.modul-ring--white circle { stroke: rgba(255,255,255,0.80); }      /* kartu navy */

/* TITIK SUDUT BERBENTUK "V" (STATIS, DI ATAS ANIMASI TITIK) */
.hero-corner-nodes{
  position:absolute;
  inset:0;
  pointer-events:none;
  z-index:2; /* di atas .hero-map-overlay (z-index:1), di bawah .hero-map-logos */
}

/* TITIK V – LEBIH KECIL & BLUR, TANPA BORDER / GLOW PUTIH */
.corner-node {
  position: absolute;
  width: 5px;               /* ukuran inti titik */
  height: 5px;
  border-radius: 50%;
  background: #2e8389;      /* warna titik */
  filter: blur(0.6px);      /* inti titik ikut blur */
  opacity: 0.95;
  pointer-events: none;
  box-shadow: 0 0 6px rgba(34,132,147,0.55); /* glow lembut warna tosca */
}

/* pastikan tidak ada pseudo-element dari versi sebelumnya */
.corner-node::before,
.corner-node::after {
  content: none !important;
}


/* ===== 7 SUDUT – MASING-MASING 3 TITIK V ===== */
/* 1) Top-left */
.corner-node--v1-1{ top:15.1%;  left:7.0%;  }
.corner-node--v1-2{ top:12.9%;  left:7.3%;  }  /* turun kiri */
.corner-node--v1-3{ top:14.8%;  left:8.4%; }  /* turun kanan */

/* 2) Top-center */
.corner-node--v2-1{ top:5.1%;  left:33.5%; }
.corner-node--v2-2{ top:3.1%;  left:32.9%; }
.corner-node--v2-3{ top:5.1%;  left:32.4%; }

/* 3) Top-right */
.corner-node--v3-1{ top:23.2%;  right:15.6%; }
.corner-node--v3-2{ top:21.3%; right:16.1%; }
.corner-node--v3-3{ top:23.2%; right:14.1%; }

/* 4) Middle-right */
.corner-node--v4-1{ top:9.1%; right:47.9%; }
.corner-node--v4-2{ top:9.1%; right:46.8%; }
.corner-node--v4-3{ top:10.9%; right:48.5%; }

/* 5) Bottom-right */
.corner-node--v5-1{ bottom:11.8%; right:8.6%; }
.corner-node--v5-2{ bottom:9.8%; right:9.3%; }
.corner-node--v5-3{ bottom:11.7%; right:7.3%; }

/* 6) Bottom-center */
.corner-node--v6-1{ bottom:77.8%; left:42.0%; }
.corner-node--v6-2{ bottom:79.7%; left:42.7%; }
.corner-node--v6-3{ bottom:79.7%; left:43.8%; }

/* 7) Bottom-left */
.corner-node--v7-1{ bottom:42.7%; left:21.4%; }
.corner-node--v7-2{ bottom:40.9%; left:20.8%;  }
.corner-node--v7-3{ bottom:42.7%; left:22.7%; }

/* Link khusus montaklo.id */
.efidi-nav-links a.nav-montaklo {
  color: #1d4ed8;          /* teks biru */
}

/* garis bawah khusus montaklo.id (default: tidak kelihatan) */
.efidi-nav-links a.nav-montaklo::after {
  background-color: #1d4ed8;  /* garis biru */
  width: 0;                   /* tidak ada garis saat normal */
}

/* saat diarahkan (hover) baru keluar garis biru penuh */
.efidi-nav-links a.nav-montaklo:hover::after {
  width: 100%;                /* sepanjang teks "montaklo.id" */
}

/* LABEL NAMA DI SETIAP LOGO (KANAN ATAS) */
.hero-map-label {
  position: absolute;
  display: inline-flex;
  align-items: center;
  padding: 0px 7px;
  border-radius: 9px;
  background: #ffffffff;              /* putih */
  color: #082e32ff;                   /* navy */
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 0.03em;
  border: 1px solid rgba(0, 0, 0, 0.07);
  box-shadow: none;                 /* TANPA shadow */
  z-index: 3;                       /* di atas logo */
}

/* posisi masing-masing label: anchor = titik logo, lalu digeser ke kanan-atas */
.hero-map-label--efidi {
  top: 37%;
  left: 53%;
  transform: translate(50px, -18px);
}

.hero-map-label--archi {
  top: 7%;
  left: 84%;
  transform: translate(40px, -18px);
}

.hero-map-label--announc {
  top: -12%;
  left: 27%;
  transform: translate(44px, -12px);
}

.hero-map-label--ai {
  top: 2%;
  left: 4%;
  transform: translate(40px, -14px);
}

.hero-map-label--admin {
  top: 22%;
  left: 37%;
  transform: translate(44px, -18px);
}

.hero-map-label--finder {
  top: 56%;
  left: 23%;
  transform: translate(44px, -18px);
}

/* BAGIAN NAMA BRAND DI DALAM LABEL (efidi.) */
.hero-map-label-efidi {
  display: inline-flex;
  align-items: baseline;
  font-weight: 700;           /* biar efidi sedikit lebih tegas */
}

/* TITIK SETELAH efidi – LEBIH BESAR & WARNA TOSCA */
.hero-map-label-efidi-dot {
  color: var(--efidi-teal);   /* #00a3ad */
  font-size: 2.0em;           /* titik lebih besar dari teks label */
  line-height: 0;
  margin-left: 0.1px;
}



    /* SECTION 3 – PANDUAN REGISTRASI (tetap) */
    .section-registrasi {
      position: relative;
      padding: 80px 0 92px;
      background: #ffffff;
      overflow: hidden;
    }

    .section-registrasi::before {
      content: "";
      position: absolute;
      left: 50%;
      bottom: -55%;
      transform: translateX(-50%);
      width: 180%;
      height: 180%;
      border-radius: 50%;
      background: #ffffff;
      pointer-events: none;
      z-index: 0;
    }

    .section-registrasi .efidi-container {
      position: relative;
      z-index: 1;
    }

    .timeline-wrapper {
      margin-top: 36px;
      overflow-x: auto;
      padding-bottom: 12px;
    }

    .timeline-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 32px;
    }

    .timeline-row + .timeline-row {
      margin-top: 40px;
    }

    .timeline-step {
      text-align: center;
      position: relative;
    }

    .timeline-number {
      width: 44px;
      height: 44px;
      border-radius: 999px;
      background: var(--navy);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 17px;
      font-weight: 600;
      margin: 0 auto 12px;
      position: relative;
      z-index: 2;
    }

    .timeline-step::after {
      content: "";
      position: absolute;
      top: 22px;
      left: 50%;
      width: 100%;
      border-top: 2px dashed rgba(148, 163, 184, 0.85);
      transform: translateX(0);
      z-index: 1;
    }

    .timeline-step:last-child::after {
      display: none;
    }

    .timeline-card {
      margin: 6px auto 0;
      background: #f5f7fb;
      border-radius: 20px;
      border: 1px solid var(--border-soft);
      padding: 16px 18px 14px;
      min-height: 100px;
    }

    .timeline-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 4px;
    }

    .timeline-body {
      font-size: 12px;
      color: var(--text-main);
    }

    .timeline-body strong {
      color: var(--text-main);
    }

    .registrasi-detail {
      margin-top: 36px;
      background: #f5fafb;
      border-radius: 18px;
      border: 1px solid var(--border-soft);
      padding: 20px 22px;
      font-size: 13px;
      color: var(--text-main);
    }

    .registrasi-detail strong {
      color: var(--text-main);
    }

    .registrasi-detail ol {
      margin: 10px 0 0 18px;
      padding: 0;
    }

    .registrasi-detail li + li {
      margin-top: 6px;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
      .efidi-container {
        padding: 0 28px;
      }
    }

    @media (max-width: 1024px) {
      .hero-inner {
        flex-direction: column;
        text-align: center;
        padding-top: 56px;
      }
      .hero-left {
        order: 1;
      }
      .hero-right {
        order: 2;
      }
      .hero-cta {
        justify-content: center;
      }
      .hero-subtitle {
        margin-left: auto;
        margin-right: auto;
      }
    }

    @media (max-width: 900px) {
      .modul-row {
        grid-template-columns: 1fr 1fr;
      }
      .timeline-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 640px) {
      .efidi-container {
        padding: 0 20px;
      }
      .efidi-nav-links {
        display: none;
      }
      .hero-inner {
        padding-top: 40px;
      }
      .hero-title {
        font-size: 32px;
      }
      .hero-network {
        width: 340px;
        height: 340px;
      }
      .modul-row {
        grid-template-columns: 1fr;
      }
      .timeline-grid {
        grid-template-columns: 1fr;
        gap: 24px;
      }
      .timeline-step::after {
        display: none;
      }
      .registrasi-detail {
        padding: 16px 14px;
      }
    }
    
  </style>
</head>
<body>

  <header class="efidi-navbar">
    <div class="efidi-container efidi-navbar-inner">
    <div class="efidi-brand">
  <!-- Logo efidi utama -->
  <img src="images/icon/logoo.png"
       alt="Logo efidi"
       class="efidi-brand-logo efidi-brand-logo--efidi">

  <!-- Logo Danantara Indonesia -->
  <img src="images/icon/logo.png"
      alt="Telkom Akses"
       class="efidi-brand-logo efidi-brand-logo--telkom"">

  <!-- Logo Telkom Akses -->
 
</div>



      <nav class="efidi-nav-links">
        <a href="#beranda" class="active">Beranda</a>
        <a href="#modul">Modul efidi</a>
        <a href="#registrasi">Panduan Registrasi</a>
        <a href="https://montaklo.id/" class="nav-montaklo" target="_blank" rel="noopener">
    montaklo.id
  </a>
      </nav>

      <div class="efidi-nav-actions">
        <a href="login.php" class="btn-nav-primary">Masuk</a>
        <a href="qrcode.php" class="btn-nav-outline">Daftar</a>
      </div>
    </div>
  </header>

  <main id="beranda">
    <!-- HERO -->
    <section class="hero-efidi">
      <div class="efidi-container hero-inner">
        <div class="hero-left">
          <div class="hero-pretitle">EKOSISTEM DATA TERINTEGRASI</div>
          <h1 class="hero-title">
            Saatnya berkolaborasi dengan <span class="highlight">efidi</span>
            dan membangun ekosistem data yang terukur.
          </h1>
          <p class="hero-subtitle">
            <strong>efidi.</strong> adalah kerangka kerja utama:
            pusat semua data Laporan Orders, Berita Acara Instalasi, Evidensi Lapangan, status pekerjaan, laporan Harian, bot &amp; AI
            dalam satu ekosistem yang terintegrasi &amp; efektif.
          </p>
          <div class="hero-cta">
            <a href="#modul" class="btn-hero-primary">Get Started</a>
          </div>
        </div>
<!-- HERO RIGHT – DOT MAP + LOGO -->
<div class="hero-right">
  <!-- background dot-map PNG (tetap diam) -->
  <div class="hero-map-bg"></div>

  <!-- LAPISAN ANIMASI CAHAYA DI ATAS DOT MAP -->
   <!-- LAPISAN ANIMASI TITIK PUTIH DI ATAS DOT MAP -->
  <!-- LAPISAN ANIMASI TITIK PUTIH DI ATAS DOT MAP -->
  <div class="hero-map-overlay">
    <!-- === CLUSTER INDONESIA (PADAT) === -->
    <div class="map-node map-node--id-main   map-node--fast"></div>
    <div class="map-node map-node--id-main-1 map-node--delay1"></div>
    <div class="map-node map-node--id-main-2 map-node--slow  map-node--delay2"></div>
    <div class="map-node map-node--id-main-3 map-node--fast  map-node--delay3"></div>
    <div class="map-node map-node--id-main-4 map-node--slow"></div>
    <div class="map-node map-node--id-main-5 map-node--delay1"></div>

    <!-- Indonesia timur -->
    <div class="map-node map-node--id-east   map-node--fast"></div>
    <div class="map-node map-node--id-east-1 map-node--delay2"></div>
    <div class="map-node map-node--id-east-2 map-node--slow  map-node--delay3"></div>
    <div class="map-node map-node--id-east-3 map-node--fast  map-node--delay1"></div>
    <div class="map-node map-node--id-east-4 map-node--slow"></div>

    <!-- Indonesia barat / Sumatra -->
    <div class="map-node map-node--id-west   map-node--slow"></div>
    <div class="map-node map-node--id-west-1 map-node--fast  map-node--delay1"></div>
    <div class="map-node map-node--id-west-2 map-node--delay2"></div>
    <div class="map-node map-node--id-west-3 map-node--fast  map-node--delay3"></div>
    <div class="map-node map-node--id-west-4 map-node--slow  map-node--delay1"></div>

    <!-- === LAUT SEKITAR (SEA) === -->
    <div class="map-node map-node--sea1   map-node--fast  map-node--delay3"></div>
    <div class="map-node map-node--sea1-1 map-node--slow  map-node--delay1"></div>
    <div class="map-node map-node--sea1-2 map-node--delay2"></div>
    <div class="map-node map-node--sea1-3 map-node--fast"></div>

    <div class="map-node map-node--sea2   map-node--delay1"></div>
    <div class="map-node map-node--sea2-1 map-node--fast  map-node--delay2"></div>
    <div class="map-node map-node--sea2-2 map-node--slow  map-node--delay3"></div>
    <div class="map-node map-node--sea2-3 map-node--fast"></div>

    <!-- === ASIA ATAS === -->
    <div class="map-node map-node--asia1   map-node--slow"></div>
    <div class="map-node map-node--asia1-1 map-node--fast  map-node--delay1"></div>
    <div class="map-node map-node--asia1-2 map-node--delay2"></div>
    <div class="map-node map-node--asia1-3 map-node--fast  map-node--delay3"></div>

    <div class="map-node map-node--asia2   map-node--fast  map-node--delay2"></div>
    <div class="map-node map-node--asia2-1 map-node--slow"></div>
    <div class="map-node map-node--asia2-2 map-node--delay1"></div>
    <div class="map-node map-node--asia2-3 map-node--fast  map-node--delay3"></div>

    <div class="map-node map-node--asia3   map-node--delay3"></div>
    <div class="map-node map-node--asia3-1 map-node--fast  map-node--delay1"></div>
    <div class="map-node map-node--asia3-2 map-node--slow  map-node--delay2"></div>
    <div class="map-node map-node--asia3-3 map-node--fast"></div>

    <!-- === EROPA === -->
    <div class="map-node map-node--eu1   map-node--fast"></div>
    <div class="map-node map-node--eu1-1 map-node--slow  map-node--delay1"></div>
    <div class="map-node map-node--eu1-2 map-node--delay2"></div>

    <div class="map-node map-node--eu2   map-node--slow  map-node--delay2"></div>
    <div class="map-node map-node--eu2-1 map-node--fast  map-node--delay1"></div>
    <div class="map-node map-node--eu2-2 map-node--delay3"></div>

    <div class="map-node map-node--eu3   map-node--delay1"></div>
    <div class="map-node map-node--eu3-1 map-node--fast  map-node--delay3"></div>
    <div class="map-node map-node--eu3-2 map-node--slow"></div>
    <div class="map-node map-node--eu3-3 map-node--fast  map-node--delay2"></div>

    <!-- === AMERIKA UTARA === -->
    <div class="map-node map-node--na1   map-node--fast  map-node--delay3"></div>
    <div class="map-node map-node--na1-1 map-node--slow  map-node--delay1"></div>
    <div class="map-node map-node--na1-2 map-node--delay2"></div>

    <div class="map-node map-node--na2   map-node--slow"></div>
    <div class="map-node map-node--na2-1 map-node--fast  map-node--delay1"></div>
    <div class="map-node map-node--na2-2 map-node--delay3"></div>
    <div class="map-node map-node--na2-3 map-node--fast  map-node--delay2"></div>

    <!-- === AMERIKA SELATAN === -->
    <div class="map-node map-node--sa1   map-node--fast  map-node--delay2"></div>
    <div class="map-node map-node--sa1-1 map-node--slow  map-node--delay3"></div>
    <div class="map-node map-node--sa1-2 map-node--delay1"></div>

    <div class="map-node map-node--sa2   map-node--delay1"></div>
    <div class="map-node map-node--sa2-1 map-node--fast  map-node--delay2"></div>
    <div class="map-node map-node--sa2-2 map-node--slow  map-node--delay3"></div>
    <div class="map-node map-node--sa2-3 map-node--fast"></div>

    <!-- === TITIK GLOBAL TAMBAHAN (BACKGROUND) === -->
    <div class="map-node map-node--bg1   map-node--slow  map-node--delay1"></div>
    <div class="map-node map-node--bg2   map-node--fast  map-node--delay3"></div>
    <div class="map-node map-node--bg3   map-node--delay2"></div>
    <div class="map-node map-node--bg4   map-node--fast"></div>
    <div class="map-node map-node--bg5   map-node--slow  map-node--delay1"></div>
    <div class="map-node map-node--bg6   map-node--delay3"></div>
    <div class="map-node map-node--bg7   map-node--fast  map-node--delay2"></div>
    <div class="map-node map-node--bg8   map-node--slow"></div>
  </div>

  <div class="hero-corner-nodes">
    <!-- 1) Top-left -->
    <span class="corner-node corner-node--v1-1"></span>
    <span class="corner-node corner-node--v1-2"></span>
    <span class="corner-node corner-node--v1-3"></span>

    <!-- 2) Top-center -->
    <span class="corner-node corner-node--v2-1"></span>
    <span class="corner-node corner-node--v2-2"></span>
    <span class="corner-node corner-node--v2-3"></span>

    <!-- 3) Top-right -->
    <span class="corner-node corner-node--v3-1"></span>
    <span class="corner-node corner-node--v3-2"></span>
    <span class="corner-node corner-node--v3-3"></span>

    <!-- 4) Middle-right -->
    <span class="corner-node corner-node--v4-1"></span>
    <span class="corner-node corner-node--v4-2"></span>
    <span class="corner-node corner-node--v4-3"></span>

    <!-- 5) Bottom-right -->
    <span class="corner-node corner-node--v5-1"></span>
    <span class="corner-node corner-node--v5-2"></span>
    <span class="corner-node corner-node--v5-3"></span>

    <!-- 6) Bottom-center -->
    <span class="corner-node corner-node--v6-1"></span>
    <span class="corner-node corner-node--v6-2"></span>
    <span class="corner-node corner-node--v6-3"></span>

    <!-- 7) Bottom-left -->
    <span class="corner-node corner-node--v7-1"></span>
    <span class="corner-node corner-node--v7-2"></span>
    <span class="corner-node corner-node--v7-3"></span>
  </div>

  <!-- logo-logo efidi di atas semua animasi -->
 <div class="hero-map-logos">
  <img src="images/icon/efidi.png"
       alt="efidi"
       class="hero-map-logo hero-map-logo--efidi">
  <div class="hero-map-label hero-map-label--efidi">
  <span class="hero-map-label-efidi">
    efidi<span class="hero-map-label-efidi-dot">.</span>
  </span>
</div>


  <img src="images/icon/archi.png"
       alt="Archi efidi"
       class="hero-map-logo hero-map-logo--archi">
  <div class="hero-map-label hero-map-label--archi">
  Archi&nbsp;
  <span class="hero-map-label-efidi">
    efidi<span class="hero-map-label-efidi-dot">.</span>
  </span>
</div>

  <img src="images/icon/announc.png"
       alt="Announc efidi"
       class="hero-map-logo hero-map-logo--announc">
  <div class="hero-map-label hero-map-label--announc">
  Announc&nbsp;
  <span class="hero-map-label-efidi">
    efidi<span class="hero-map-label-efidi-dot">.</span>
  </span>
</div>

  <img src="images/icon/ai.png"
       alt="efidi.ai"
       class="hero-map-logo hero-map-logo--ai">
  <div class="hero-map-label hero-map-label--ai">efidi.ai</div>

  <span class="hero-map-logo hero-map-logo--admin-circle"></span>

  <img src="images/icon/admin.png"
       alt="Admin efidi"
       class="hero-map-logo hero-map-logo--admin">
  <div class="hero-map-label hero-map-label--admin">
  Admin&nbsp;
  <span class="hero-map-label-efidi">
    efidi<span class="hero-map-label-efidi-dot">.</span>
  </span>
</div>
  <img src="images/icon/finder.png"
       alt="Finder efidi"
       class="hero-map-logo hero-map-logo--finder">
  <div class="hero-map-label hero-map-label--finder">
  Finder&nbsp;
  <span class="hero-map-label-efidi">
    efidi<span class="hero-map-label-efidi-dot">.</span>
  </span>
</div>
</div>




      </div>
    </section>

   <section class="section-efidi-modul" id="modul">
  <div class="efidi-container section-efidi-modul-inner">
    <h2 class="section-title">Apa saja modul di dalam ekosistem efidi?</h2>
    <p class="section-subtitle">
Modul efidi disusun tersegmentasi:
      ada pusat kerangka kerja, modul arsip &amp; pengumuman, mesin pencari,
      panel admin, hingga asisten AI yang saling terhubung.
    </p>

    <!-- ========== ROW 1 (TEAL / PUTIH / NAVY) ========== -->
    <div class="modul-row">
      <!-- CARD 1: efidi (TEAL) -->
      <article class="modul-card modul-card--primary">
  <!-- lingkaran atas-kanan & bawah-kiri -->
  <svg class="modul-ring modul-ring--tr modul-ring--teal"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>
  <svg class="modul-ring modul-ring--bl modul-ring--teal"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>

  <div class="modul-header">efidi<span class="dot">.</span></div>
  <div class="modul-body">
          efidi adalah <strong>kerangka kerja utama</strong> &mdash; pusat semua data:
          orders, BAI, foto, koordinat, status pekerjaan, laporan, hingga integrasi
          bot &amp; AI yang terhubung menggunakan situs <strong>efidi.montaklo.id</strong>.<br>
          Semua modul lain (Archi, Announc, Finder, Admin, efidi.ai) merupakan
          “cabang” dari efidi ini.
        </div>
      </article>

      <!-- CARD 2: Archi (PUTIH) -->
     <article class="modul-card modul-card--light">
  <svg class="modul-ring modul-ring--tr modul-ring--gray"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>
  <svg class="modul-ring modul-ring--bl modul-ring--gray"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>

  <div class="modul-header">Archi efidi<span class="dot">.</span></div>
  <div class="modul-body">

          Archi efidi adalah sisi <strong>arsitektur &amp; peta besar</strong> efidi:
          menggambarkan alur data dari Telegram &rarr; database &rarr; dashboard
          &rarr; laporan, termasuk dokumentasi struktur data dan relasi antar modul.
        </div>
      </article>

      <!-- CARD 3: Announc (NAVY) -->
     <article class="modul-card modul-card--dark">
  <svg class="modul-ring modul-ring--tr modul-ring--white"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>
  <svg class="modul-ring modul-ring--bl modul-ring--white"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>

  <div class="modul-header">Announc efidi<span class="dot">.</span></div>
  <div class="modul-body">
          Announc efidi adalah <strong>mesin pengumuman &amp; laporan otomatis</strong>.
          Modul ini mengirim ringkasan harian, laporan performa, notifikasi aktivitas,
          hingga OTP ke grup Telegram maupun akun personal admin, TA, dan Tech.
        </div>
      </article>
    </div>

    <!-- ========== ROW 2 (NAVY / TEAL / PUTIH – DIACAK) ========== -->
    <div class="modul-row">
      <!-- CARD 4: Finder (NAVY) -->
    <article class="modul-card modul-card--dark">
  <svg class="modul-ring modul-ring--tr modul-ring--white"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>
  <svg class="modul-ring modul-ring--bl modul-ring--white"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>

  <div class="modul-header">Finder efidi<span class="dot">.</span></div>
  <div class="modul-body">

          Finder efidi adalah <strong>mesin pencari</strong> di ekosistem efidi:
          form pencarian di web dan Telegram WebApp Finder yang bisa mencari berdasarkan
          <strong>no SC, no internet, nama pelanggan, HP, tag, dll</strong>
          dengan cepat serta terintegrasi langsung ke data efidi.
        </div>
      </article>

      <!-- CARD 5: Admin (TEAL) -->
      <article class="modul-card modul-card--primary">
  <svg class="modul-ring modul-ring--tr modul-ring--teal"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>
  <svg class="modul-ring modul-ring--bl modul-ring--teal"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>

  <div class="modul-header">Admin efidi<span class="dot">.</span></div>
  <div class="modul-body">
          Admin efidi adalah <strong>panel kontrol</strong> yang mengatur akses akun efidi
          dan hak akses Bot Finder efidi. Sederhananya, ruang kendali utama
          bagi orang yang mengelola keseluruhan ekosistem efidi.
        </div>
      </article>

      <!-- CARD 6: efidi.ai (PUTIH) -->
      <article class="modul-card modul-card--light">
  <svg class="modul-ring modul-ring--tr modul-ring--gray"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>
  <svg class="modul-ring modul-ring--bl modul-ring--gray"
       width="133" height="117" viewBox="0 0 133 117"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="120.657" cy="-3.59205" r="118.926"></circle>
  </svg>

  <div class="modul-header">efidi.ai<span class="dot">.</span></div>
  <div class="modul-body">

          efidi.ai adalah <strong>Artificial Intelligence</strong> di dalam ekosistem efidi:
          berfungsi sebagai asisten AI yang membantu menjawab pertanyaan, debugging,
          pembuatan query, penyusunan teks, sampai ide pengembangan fitur baru,
          dengan konsistensi terhadap brand efidi.
        </div>
      </article>
    </div>
  </div>
</section>



    <!-- SECTION 3: PANDUAN REGISTRASI -->
    <section class="section-registrasi" id="registrasi">
      <div class="efidi-container">
        <h2 class="section-title">Panduan Registrasi Akun efidi</h2>
        <p class="section-subtitle">
          Registrasi efidi terhubung dengan Telegram untuk keamanan OTP.
          Ikuti langkah berikut mulai dari halaman login sampai akun aktif.
        </p>

        <div class="timeline-wrapper">
          <div class="timeline-row">
            <div class="timeline-grid">
              <div class="timeline-step">
                <div class="timeline-number">01</div>
                <div class="timeline-card">
                  <div class="timeline-title">Mulai dari halaman Login</div>
                  <div class="timeline-body">
                    Dari halaman <strong>Login</strong>, klik tombol
                    <strong>Register</strong> untuk memulai proses registrasi akun efidi.
                  </div>
                </div>
              </div>
              <div class="timeline-step">
                <div class="timeline-number">02</div>
                <div class="timeline-card">
                  <div class="timeline-title">Hubungkan dengan Telegram</div>
                  <div class="timeline-body">
                    Sistem membuka halaman <strong>Hubungkan Telegram</strong>.
                    Klik <strong>Open in Telegram</strong> untuk
                    membuka bot <strong>@Announc efidi</strong>.
                  </div>
                </div>
              </div>
              <div class="timeline-step">
                <div class="timeline-number">03</div>
                <div class="timeline-card">
                  <div class="timeline-title">Start bot &amp; LANJUT</div>
                  <div class="timeline-body">
                    Di bot, tekan <strong>Start</strong> (atau ketik <strong>/start</strong>),
                    lalu pilih <strong>LANJUT</strong>. Pastikan username aktif dan unik.
                  </div>
                </div>
              </div>
              <div class="timeline-step">
                <div class="timeline-number">04</div>
                <div class="timeline-card">
                  <div class="timeline-title">Kembali ke Website</div>
                  <div class="timeline-body">
                    Bot menampilkan tombol <strong>KEMBALI KE HALAMAN WEBSITE</strong>
                    untuk melanjutkan ke form registrasi efidi.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="timeline-row" style="margin-top:40px;">
            <div class="timeline-grid">
              <div class="timeline-step">
                <div class="timeline-number">05</div>
                <div class="timeline-card">
                  <div class="timeline-title">Form Registrasi</div>
                  <div class="timeline-body">
                    Jika username valid, website otomatis menuju <strong>Form Registrasi</strong>
                    dan kolom Username terisi dari Telegram.
                  </div>
                </div>
              </div>
              <div class="timeline-step">
                <div class="timeline-number">06</div>
                <div class="timeline-card">
                  <div class="timeline-title">Kode Verifikasi</div>
                  <div class="timeline-body">
                    Lengkapi form, kirim, lalu masukkan <strong>kode verifikasi</strong>
                    yang dikirimkan via bot. Kode ini bersifat sementara.
                  </div>
                </div>
              </div>
              <div class="timeline-step">
                <div class="timeline-number">07</div>
                <div class="timeline-card">
                  <div class="timeline-title">Menunggu Aktivasi Admin</div>
                  <div class="timeline-body">
                    Setelah verifikasi, kembali ke <strong>Login</strong>. Akun belum bisa
                    dipakai sampai Admin memberikan <strong>hak akses/role</strong>.
                  </div>
                </div>
              </div>
              <div class="timeline-step">
                <div class="timeline-number">08</div>
                <div class="timeline-card">
                  <div class="timeline-title">Login dengan OTP</div>
                  <div class="timeline-body">
                    Setelah role diaktifkan, login ke efidi. Demi keamanan,
                    setiap login akan diminta <strong>OTP</strong> yang dikirim ke Telegram.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="registrasi-detail">
          <strong>Ringkasan lengkap langkah registrasi:</strong>
          <ol>
            <li>Dari halaman <strong>Login</strong>, klik <strong>Register</strong> untuk memulai.</li>
            <li>Masuk ke halaman <strong>Hubungkan Telegram</strong>. Klik <strong>Open in Telegram</strong> untuk membuka bot <strong>@Announc efidi</strong>.</li>
            <li>Di bot, tekan <strong>Start</strong> atau <strong>/start</strong> lalu tekan <strong>LANJUT</strong>. Username harus aktif dan unik.</li>
            <li>Bot menampilkan tombol <strong>KEMBALI KE HALAMAN WEBSITE</strong> untuk kembali ke website efidi.</li>
            <li>Jika username valid, website otomatis menuju <strong>Form Registrasi</strong> dengan kolom Username terisi otomatis.</li>
            <li>Lengkapi form, kirim, lalu masukkan <strong>kode verifikasi</strong> yang dikirim via bot.</li>
            <li>Setelah verifikasi, kembali ke Login. Admin akan memberikan <strong>hak akses/role</strong> melalui Admin efidi.</li>
            <li>Setelah role aktif, setiap login efidi akan meminta <strong>OTP</strong> yang dikirim ke Telegram demi keamanan.</li>
          </ol>
        </div>
      </div>
    </section>
  </main>

<style>
  :root{
    --kodee2-bg:#fff;
    --kodee2-ink:#0f172a;
    --kodee2-line:#e5e7eb;
    --kodee2-primary:var(--es-primary,#fff); /* brand teal */
    --kodee2-accent:#07d9e6;  /* border input */
    --kodee2-acce:#06c5d1;

    /* ========= PALET "COFFEE SLATE" UNTUK CODE BLOCK ========= */
    --k2-ink:#e5e7eb;
    --k2-dim:#9ca3af;
    --k2-bg:#18181b;
    --k2-hl:#27272a;

    --k2-key:#fb923c;
    --k2-str:#4ade80;
    --k2-num:#facc15;
    --k2-op:#e5e7eb;
    --k2-cmt:#9ca3af;

    --k2-tag:#38bdf8;
    --k2-attr:#eab308;
    --k2-val:#a5b4fc;

    --k2-const:#fb7185;
    --k2-bool:#f97373;
    --k2-builtin:#2dd4bf;
    --k2-type:#a855f7;
    --k2-fn:#06b6d4;
    --k2-var:#e5e7eb;
    --k2-class:#facc15;
    --k2-annot:#f472b6;
    --k2-reg:#22c55e;

    --k2-sel:#7dd3fc;
    --k2-prop:#e5e7eb;
    --k2-cssval:#fbbf24;

    --k2-ns:#2dd4bf;
    --k2-punct:#cbd5e1;
    --k2-angle:#e5e7eb;
    --k2-eq:#f97316;

    --k2-addbg:#064e3b;
    --k2-addfg:#6ee7b7;
    --k2-delbg:#7f1d1d;
    --k2-delfg:#fecaca;
    --k2-hunkbg:#1f2937;
    --k2-hunkfg:#bfdbfe;
  }

  /* ========== FAB LAUNCHER (KIRI-BAWAH) ========== */
  .kodee2-launcher{
    position:fixed;
    left:18px;
    bottom:18px;
    z-index:9999;
    display:inline-flex;
    align-items:center;
    gap:8px;
    height:44px;
    padding:0 16px;
    border:1px solid var(--kodee2-accent);
    border-radius:999px;
    background:linear-gradient(90deg,var(--kodee2-primary),#fff);
    color:#06c5d1;
    font-weight:600;
    cursor:pointer;
    box-shadow:0 1px 3px rgba(2,8,23,.25);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
  }
  .kodee2-logo{
    width:23px;
    height:23px;
    object-fit:contain;
    display:block;
  }

  /* ========== PANEL UTAMA (FLOATING) ========== */
  .kodee2-panel{
    position:fixed;
    left:18px;
    bottom:74px;
    z-index:9999;
    width:400px; /* ukuran kecil TETAP */
    max-width:calc(100vw - 36px);
    height:590px; /* tinggi kecil TETAP */
    background:var(--kodee2-bg);
    color:var(--kodee2-ink);
    border:1px solid var(--kodee2-line);
    border-radius:12px;
    box-shadow:0 18px 48px rgba(2,8,23,.28);
    overflow:hidden;
    display:none;
    flex-direction:column;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
  }
  .kodee2-panel.is-open{
    display:flex;
  }

  /* MODE BESAR – FULL LAYAR (DESKTOP + MOBILE) */
.kodee2-panel.is-expanded{
  width:100vw !important;
  height:100vh !important;
  left:0 !important;
  bottom:0 !important;
  right:0 !important;
  top:0 !important;
  max-width:none !important;
  border-radius:0 !important;
  transform:none !important;
}

/* --------- LAYOUT SAAT FULL SCREEN --------- */

/* header sedikit lega */
.kodee2-panel.is-expanded .kodee2-head{
  padding:14px 32px;
}

/* hero / empty state isi lebar, tidak cuma di tengah */
.kodee2-panel.is-expanded .k2-empty{
  align-items:flex-start;
  justify-content:flex-start;
  text-align:left;
  padding:40px 72px 24px;
}

/* tombol chip + bullet ikut melebar */
.kodee2-panel.is-expanded .k2-empty-actions{
  align-items:flex-start;
  max-width:none;
  width:100%;
}
.kodee2-panel.is-expanded .k2-empty-chips{
  justify-content:flex-start;
}
.kodee2-panel.is-expanded .k2-empty-hints{
  max-width:none;
}

/* area chat & intro lebar kiri-kanan */
.kodee2-panel.is-expanded .kodee2-chat{
  padding:24px 72px 0;
}
.kodee2-panel.is-expanded .k2-chat-intro{
  margin-bottom:12px;
}

/* bubble & teks sedikit lebih besar di mode besar */
.kodee2-panel.is-expanded .k2-msg .k2-bubble{
  max-width:100%;
  font-size:14px;
}

/* input & footer lebar penuh */
.kodee2-panel.is-expanded .kodee2-ask{
  padding:14px 72px 20px;
}
.kodee2-panel.is-expanded .k2-input{
  font-size:14px;
  min-height:64px;
}
.kodee2-panel.is-expanded .k2-disclaimer{
  font-size:12px;
}

/* saat full di mobile tetap full layar, tapi padding disesuaikan */
@media (max-width:768px){
  .kodee2-panel.is-expanded .k2-empty{
    padding:28px 20px 16px;
  }
  .kodee2-panel.is-expanded .kodee2-chat{
    padding:16px 20px 0;
  }
  .kodee2-panel.is-expanded .kodee2-ask{
    padding:12px 20px 16px;
  }
}


  /* HEADER */
  .kodee2-head{
    display:flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-bottom:1px solid var(--kodee2-line);
    background:#f8fafc;
    flex-shrink:0;
  }
  .kodee2-title{
    display:flex;
    align-items:center;
    gap:6px;
    font-weight:700;
    font-size:13px;
  }
  .kodee2-head-spacer{
    flex:1;
  }
  .kodee2-head-actions{
    display:flex;
    gap:4px;
    align-items:center;
  }
  .kodee2-x{
    background:transparent;
    border:0;
    width:28px;
    height:28px;
    border-radius:8px;
    cursor:pointer;
    font-size:12px;
  }

  /* TOMBOL RESIZE (1 tombol 2 icon) */
  .k2-resize{
    background:transparent;
    border:0;
    width:28px;
    height:28px;
    border-radius:8px;
    cursor:pointer;
    display:grid;
    place-items:center;
    color:#64748b;
    transition:all .2s;
  }
  .k2-resize:hover{
    background:#f1f5f9;
    color:#0f172a;
  }
  .k2-resize .expand-icon{display:block;}
  .k2-resize .minimize-icon{display:none;}
  .kodee2-panel.is-expanded .k2-resize .expand-icon{display:none;}
  .kodee2-panel.is-expanded .k2-resize .minimize-icon{display:block;}

  /* TOMBOL NEW CHAT – hanya muncul saat has-chat & besar */
  .kodee2-reset{
    display:none;
    align-items:center;
    gap:6px;
    border-radius:999px;
    border:1px solid #e2e8f0;
    background:#ffffff;
    color:#0f172a;
    font-size:11px;
    font-weight:500;
    padding:4px 10px;
    cursor:pointer;
    box-shadow:0 1px 3px rgba(15,23,42,.06);
    outline:none;
    transition:background .16s,box-shadow .16s,transform .06s;
  }
  .kodee2-reset i{
    font-size:14px;
    color:#06c5d1;
  }
  .kodee2-reset:hover{
    background:#f9fafb;
    box-shadow:0 2px 5px rgba(15,23,42,.12);
    transform:translateY(-0.5px);
  }
  .kodee2-reset:active{
    transform:translateY(0);
    box-shadow:0 1px 3px rgba(15,23,42,.06);
  }
  .kodee2-panel.is-expanded.has-chat .kodee2-reset{
    display:inline-flex;
  }

  /* BODY & CARD */
  .kodee2-body{
    padding:0;
    background:#ffffff;
    flex:1;
    display:flex;
    flex-direction:column;
    overflow:hidden;
  }
  .kodee2-card{
    background:#ffffff;
    border-radius:0;
    border:0;
    box-shadow:none;
    padding:0;
    flex:1;
    display:flex;
    flex-direction:column;
    overflow:hidden;
  }

  /* CHAT AREA */
  .kodee2-chat{
    padding:10px 12px 0;
    border-top:1px dashed var(--kodee2-line);
    background:#ffffff;
    flex:1;
    display:flex;
    flex-direction:column;
    overflow:hidden;
  }
  .kodee2-panel.has-chat #k2Empty{
    display:none !important;
  }
  .kodee2-panel.has-chat .kodee2-chat{
    border-top:none;
    padding-top:10px;
  }
  .kodee2-list{
    max-height:none;
    height:auto;
    overflow:auto;
    display:flex;
    flex-direction:column;
    gap:8px;
    flex:1;
  }
  .k2-msg{
    display:flex;
    gap:6px;
  }
  .k2-msg .k2-bubble{
    max-width:85%;
    white-space:normal;
    line-height:1.4;
    padding:8px 10px;
    border:1px solid var(--kodee2-line);
    border-radius:12px;
    box-shadow:0 2px 6px rgba(2,8,23,.06);
    background:#fff;
    font-size:13px;
  }
  .k2-user{
    justify-content:flex-end;
  }
  .k2-user .k2-bubble{
    background:linear-gradient(180deg,#eef6ff,#fff);
    border-color:#cfe1ff;
  }
  .k2-bot .k2-ava{
    width:22px;
    height:22px;
    border-radius:8px;
    display:grid;
    place-items:center;
    border:1px solid var(--kodee2-line);
    background:#fff;
    overflow:hidden;
    flex-shrink:0;
  }
  .k2-ava img{
    width:22px;
    height:22px;
    object-fit:contain;
    display:block;
  }

  /* INPUT AREA */
  .kodee2-ask{
    padding:10px 12px 12px;
    border-top:1px solid var(--kodee2-line);
    background:#ffffff;
    flex-shrink:0;
    margin-top:auto;
  }
  .k2-ask-wrap{
    position:relative;
  }
  .k2-input{
    box-sizing:border-box;
    width:100%;
    min-height:60px;
    max-height:120px;
    border:2px solid var(--kodee2-accent);
    border-radius:10px;
    padding:8px 40px 8px 10px;
    resize:vertical;
    outline:none;
    background:#fff;
    color:#0f172a;
    font:400 13px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Arial;
  }
  .k2-input::placeholder{
    color:#94a3b8;
    font-size:13px;
  }
  .k2-send,.k2-stop{
    position:absolute;
    right:11px;
    bottom:11px;
    width:28px;
    height:28px;
    display:grid;
    place-items:center;
    border:0;
    border-radius:8px;
    cursor:pointer;
    transition:all .3s ease;
    z-index:10;
  }
  .k2-send{
    background:#06c5d1;
  }
  .k2-send:disabled{
    opacity:.6;
    cursor:not-allowed;
  }
  .k2-send svg{
    width:16px;
    height:16px;
  }
  .k2-stop{
    background:#ef4444;
    color:#fff;
    display:none;
  }
  .k2-stop:hover{
    background:#dc2626;
  }
  .k2-stop svg{
    width:12px;
    height:12px;
  }
  .k2-disclaimer{
    font-size:11px;
    color:#64748b;
    margin-top:4px;
    text-align:center;
  }

  /* HERO / INTRO */
  .k2-empty{
    display:flex;
    flex-direction:column;
    align-items:center;
    text-align:center;
    gap:4px;
    padding:16px 8px 8px;
    animation:k2pop .35s ease-out both;
    flex:1;
    justify-content:center;
  }
  .k2-empty-logo{
    width:56px;
    height:56px;
    object-fit:contain;
    display:block;
    background:#fff;
    border-radius:16px;
    padding:8px;
    box-shadow:0 4px 14px rgba(2,8,23,.12);
  }
  .k2-empty-title{
    font-weight:800;
    font-size:16px;
    color:#0f172a;
    margin-top:8px;
  }
  .k2-empty-sub{
    font-size:12px;
    color:#64748b;
    margin-bottom:4px;
  }
  .k2-empty-actions{
    margin-top:12px;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:10px;
    max-width:360px;
    width:100%;
  }
  .k2-empty-chips{
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:8px;
  }
  .k2-empty-chip{
    border-radius:999px;
    border:1px solid rgba(6,197,209,0.9);
    background:rgba(6,197,209,0.04);
    color:#0f172a;
    padding:6px 10px;
    font-size:11px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    cursor:pointer;
    outline:none;
    transition:background .18s,box-shadow .18s,transform .1s;
    white-space:nowrap;
  }
  .k2-empty-chip i{
    font-size:14px;
    color:#06c5d1;
  }
  .k2-empty-chip:hover{
    background:rgba(6,197,209,0.08);
    box-shadow:0 1px 4px rgba(15,23,42,.14);
    transform:translateY(-0.5px);
  }
  .k2-empty-chip:active{
    transform:translateY(0);
    box-shadow:none;
  }
  .k2-empty-hints{
    display:flex;
    flex-direction:column;
    gap:4px;
    font-size:11px;
    color:#94a3b8;
    text-align:left;
    width:100%;
    max-width:320px;
  }
  .k2-hint-row{
    display:flex;
    align-items:flex-start;
    gap:6px;
  }
  .k2-hint-dot{
    width:6px;
    height:6px;
    margin-top:4px;
    border-radius:999px;
    background:rgba(6,197,209,0.7);
  }

  /* CHAT INTRO BAR */
  .k2-chat-intro{
    padding:8px 10px 10px;
    margin-bottom:6px;
    border-radius:10px;
    border:1px dashed rgba(6,197,209,0.45);
    background:linear-gradient(135deg,rgba(6,197,209,.05),rgba(6,197,209,.02));
    font-size:11px;
    color:#64748b;
    display:flex;
    align-items:flex-start;
    gap:8px;
  }
  .k2-chat-intro-icon{
    width:20px;
    height:20px;
    border-radius:999px;
    border:1px solid rgba(6,197,209,0.6);
    display:grid;
    place-items:center;
    font-size:13px;
    color:#06c5d1;
    background:#ffffff;
  }
  .k2-chat-intro-main{
    display:flex;
    flex-direction:column;
    gap:2px;
  }
  .k2-chat-intro-main strong{
    color:#0f172a;
    font-size:11px;
  }

  /* TYPING */
  .k2-typing .k2-bubble{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
  }
  .k2-spin{
    width:14px;
    height:14px;
    border-radius:50%;
    border:2px solid #e2e8f0;
    border-top-color:var(--kodee2-acce);
    animation:k2spin 1s linear infinite;
  }
  .k2-dots{
    display:inline-flex;
    gap:3px;
  }
  .k2-dots i{
    width:5px;
    height:5px;
    border-radius:999px;
    background:var(--kodee2-acce);
    opacity:.8;
    animation:k2b .9s ease-in-out infinite;
    will-change:transform;
  }

  /* CODE BLOCK (COFFEE SLATE) */
  .k2-code-block{
    margin:8px 0;
    border-radius:10px;
    overflow:hidden;
    background:var(--k2-bg);
    border:1px solid #4b5563;
    box-shadow:0 0 0 1px rgba(15,23,42,0.5);
  }
  .k2-code-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:7px 12px;
    background:linear-gradient(135deg,#3f3f46,#292524);
    color:#e5e7eb;
    font-size:11px;
    font-weight:600;
    letter-spacing:.02em;
    border-bottom:1px solid rgba(15,23,42,0.75);
  }
  .k2-code-header span{
    display:inline-flex;
    align-items:center;
    gap:6px;
  }
  .k2-code-header span::before{
    content:"";
    width:8px;
    height:8px;
    border-radius:999px;
    background:radial-gradient(circle at 30% 30%,#cceff4,#49b4bd);
    box-shadow:0 0 0 3px rgba(248,250,252,0.08);
  }
  .k2-code-copy{
    background:rgba(15,23,42,0.25);
    border:1px solid rgba(148,163,184,0.7);
    color:#e5e7eb;
    padding:3px 8px;
    border-radius:999px;
    cursor:pointer;
    font-size:10px;
    font-weight:500;
    display:inline-flex;
    align-items:center;
    gap:4px;
    transition:all .18s ease-out;
  }
  .k2-code-copy::before{
    content:"⧉";
    font-size:10px;
  }
  .k2-code-copy:hover{
    background:rgba(15,23,42,0.6);
    border-color:#e5e7eb;
    transform:translateY(-0.5px);
    box-shadow:0 6px 14px rgba(15,23,42,0.8);
  }
  .k2-code-copy:active{
    transform:translateY(0);
    box-shadow:0 2px 6px rgba(15,23,42,0.7);
  }
  .k2-code-content{
    padding:10px 12px;
    color:var(--k2-ink);
    background:radial-gradient(circle at 0 0,#27272a 0,var(--k2-bg) 45%);
    font-family:'Monaco','Menlo','Ubuntu Mono',monospace;
    font-size:12px;
    line-height:1.35;
    overflow-x:auto;
    white-space:pre;
  }

  /* TOKEN WARNA */
  .k2-code-content .keyword{color:var(--k2-key);font-weight:600}
  .k2-code-content .string{color:var(--k2-str)}
  .k2-code-content .number{color:var(--k2-num)}
  .k2-code-content .boolean{color:var(--k2-bool);font-weight:600}
  .k2-code-content .constant{color:var(--k2-const)}
  .k2-code-content .type{color:var(--k2-type);font-weight:600}
  .k2-code-content .builtin{color:var(--k2-builtin);font-weight:600}
  .k2-code-content .function{color:var(--k2-fn);font-weight:600}
  .k2-code-content .variable{color:var(--k2-var)}
  .k2-code-content .class{color:var(--k2-class);font-weight:600}
  .k2-code-content .operator{color:var(--k2-op)}
  .k2-code-content .annot{color:var(--k2-annot);font-weight:600}
  .k2-code-content .comment,
  .k2-code-content .html-comment{
    color:var(--k2-cmt);
    font-style:italic;
  }
  .k2-code-content .tag{color:var(--k2-tag);font-weight:600}
  .k2-code-content .attribute{color:var(--k2-attr)}
  .k2-code-content .value{color:var(--k2-val)}
  .k2-code-content .html-doctype{color:var(--k2-ns);font-weight:700}
  .k2-code-content .entity{color:#7dd3fc}
  .k2-code-content .angle{color:var(--k2-angle)}
  .k2-code-content .equals{color:var(--k2-eq)}
  .k2-code-content .selector{color:var(--k2-sel);font-weight:600}
  .k2-code-content .property{color:var(--k2-prop)}
  .k2-code-content .css-value{color:var(--k2-cssval)}
  .k2-code-content .unit{color:var(--k2-num);opacity:.95}
  .k2-code-content .paren{color:#9db2ff}
  .k2-code-content .brace{color:#ffb4e6}
  .k2-code-content .bracket{color:#8df4d3}
  .k2-code-content .punct{color:var(--k2-punct)}
  .k2-code-content .comma{color:#cbd5e1}
  .k2-code-content .semicolon{color:#ffd28f}
  .k2-code-content .colon{color:#a5f3fc}
  .k2-code-content .dot{color:#d8b4fe}
  .k2-code-content .pipe{color:#fca5a5}
  .k2-code-content .amp{color:#67e8f9}
  .k2-code-content .url{
    color:#38bdf8;
    text-decoration:underline;
    text-underline-offset:2px;
  }
  .k2-code-content .email{color:#86efac}
  .k2-code-content .diff-add{
    background:var(--k2-addbg);
    color:var(--k2-addfg);
    padding:0 2px;
    border-radius:3px;
  }
  .k2-code-content .diff-del{
    background:var(--k2-delbg);
    color:var(--k2-delfg);
    padding:0 2px;
    border-radius:3px;
  }
  .k2-code-content .hl-match{
    background:var(--k2-hl);
    border-radius:3px;
    padding:0 2px;
  }
  .k2-code-content .color-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:0 4px;
    border-radius:4px;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(148,163,184,.6);
  }
  .k2-code-content .color-chip i{
    display:inline-block;
    width:10px;
    height:10px;
    border-radius:2px;
    background:var(--chip);
    box-shadow:0 0 0 1px rgba(0,0,0,.4) inset;
  }
  .k2-code-content ::selection{
    background:#0ea5e9;
    color:#0b1220;
  }

  /* LINK DI DALAM BUBBLE */
  .k2-bubble a{
    color:#3b82f6;
    text-decoration:underline;
    text-underline-offset:2px;
    font-weight:500;
  }
  .k2-bubble a:hover{
    text-decoration-thickness:2px;
    filter:brightness(1.1);
  }

  /* NOTE LANJUTKAN */
  .k2-cont-note{
    display:inline-block;
    margin-top:6px;
    font-size:11px;
    color:#94a3b8;
  }
  .k2-cont-note a{
    color:#06c5d1;
    font-weight:600;
    text-decoration:underline;
    text-underline-offset:2px;
    cursor:pointer;
  }
  .k2-cont-note a:hover{
    filter:brightness(1.08);
  }

  /* ANIMASI & MISC */
  .wave{
    display:inline-block;
    transform-origin:70% 70%;
    animation:k2wave 1.6s ease-in-out infinite;
  }
  @keyframes k2wave{
    0%,60%,100%{transform:rotate(0)}
    30%{transform:rotate(20deg)}
  }
  @keyframes k2pop{
    from{opacity:0;transform:translateY(6px)}
    to{opacity:1;transform:none}
  }
  @keyframes k2spin{to{transform:rotate(1turn)}}
  @keyframes k2b{
    0%,80%,100%{transform:translateY(0);opacity:.35}
    40%{transform:translateY(-2px);opacity:1}
  }
  .k2-cursor{
    animation:k2blink 1s infinite;
    color:var(--kodee2-acce);
    font-weight:bold;
  }
  @keyframes k2blink{
    0%,50%{opacity:1}
    51%,100%{opacity:0}
  }
  .k2-stop-pulse{
    animation:stopPulse 1.5s ease-in-out infinite;
  }
  @keyframes stopPulse{
    0%,100%{
      transform:scale(1);
      box-shadow:0 0 0 0 rgba(239,68,68,0.7);
    }
    50%{
      transform:scale(1.05);
      box-shadow:0 0 0 3px rgba(239,68,68,0);
    }
  }

  /* ========= MODE COMPACT (PANEL KECIL) ========= */
  .kodee2-panel:not(.is-expanded) .kodee2-head{
    padding:6px 10px;
  }
  .kodee2-panel:not(.is-expanded) .kodee2-title{
    font-size:13px;
  }
  .kodee2-panel:not(.is-expanded) .k2-empty{
    padding:8px 8px 4px;
    gap:4px;
  }
  .kodee2-panel:not(.is-expanded) .k2-empty-logo{
    width:43px;
    height:43px;
    border-radius:12px;
    padding:6px;
  }
  .kodee2-panel:not(.is-expanded) .k2-empty-title{
    font-size:14px;
    margin-top:3px;
  }
  .kodee2-panel:not(.is-expanded) .k2-empty-sub{
    font-size:12px;
  }
  .kodee2-panel:not(.is-expanded) .k2-empty-actions{
    margin-top:3px;
    gap:5px;
  }
  .kodee2-panel:not(.is-expanded) .k2-empty-chip{
    padding:4px 8px;
    font-size:11px;
  }
  .kodee2-panel:not(.is-expanded) .k2-empty-hints{
    font-size:11px;
    max-width:350px;
  }
  .kodee2-panel:not(.is-expanded) .k2-chat-intro{
    padding:6px 8px 8px;
    font-size:11px;
  }
  .kodee2-panel:not(.is-expanded) .k2-chat-intro-main strong{
    font-size:11px;
  }
  .kodee2-panel:not(.is-expanded) .k2-msg .k2-bubble{
    font-size:12px;
    padding:7px 9px;
  }
  .kodee2-panel:not(.is-expanded) .k2-input{
    min-height:52px;
    font-size:12px;
    padding:6px 38px 6px 8px;
  }
  .kodee2-panel:not(.is-expanded) .k2-input::placeholder{
    font-size:12px;
  }
  .kodee2-panel:not(.is-expanded) .k2-disclaimer{
    font-size:11px;
  }

  /* ========= RESPONSIVE MOBILE ========= */
  @media (max-width:768px){
    .kodee2-panel{
      width:calc(100vw - 36px) !important;
      left:18px !important;
      right:18px !important;
      bottom:74px !important;
      height:450px;
    }
    .kodee2-panel.is-expanded{
      width:100vw !important;
      height:100vh !important;
      left:0 !important;
      bottom:0 !important;
      border-radius:0 !important;
      transform:none !important;
    }
    .kodee2-panel.is-expanded .kodee2-head{
      padding:10px 14px;
    }
  }
  @media (max-width:480px){
    .kodee2-panel{
      width:calc(100vw - 24px) !important;
      left:12px !important;
      right:12px !important;
      height:400px;
    }
    .k2-bubble{
      max-width:80% !important;
      font-size:12px;
    }
    .k2-input{
      font-size:14px;
      min-height:55px;
    }
  }
</style>

<?php
  // URL dinamis ke /ai/ask.php (ikut subfolder apa pun)
  $aiLogo = 'images/10.png';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');   // contoh: "" atau "/e-siklo"
  $aiUrl  = $scheme.'://'.$_SERVER['HTTP_HOST'].($base ? $base.'/' : '/').'ai/ask.php';
?>

<!-- FAB -->
<button id="aiFab" class="kodee2-launcher" aria-expanded="false">
  <img src="<?= htmlspecialchars($aiLogo,ENT_QUOTES) ?>" alt="" class="kodee2-logo">
  <span>efidi.ai</span>
</button>

<!-- PANEL CHAT -->
<div id="aiPanel" class="kodee2-panel" role="dialog" aria-label="efidi.AI"
     data-action="<?= htmlspecialchars($aiUrl,ENT_QUOTES) ?>"
     data-logo="<?= htmlspecialchars($aiLogo,ENT_QUOTES) ?>">

  <div class="kodee2-head">
    <div class="kodee2-title">
      <img src="<?= htmlspecialchars($aiLogo,ENT_QUOTES) ?>" alt="" class="kodee2-logo">
      <span>efidi.ai</span>
    </div>

    <div class="kodee2-head-spacer"></div>

    <!-- New chat (hanya muncul saat has-chat & besar) -->
    <button type="button" id="aiReset" class="kodee2-reset" title="Reset percakapan" aria-label="Reset percakapan">
      <i class="mdi mdi-square-edit-outline"></i>
      <span>New Chat</span>
    </button>

    <div class="kodee2-head-actions">
      <button class="k2-resize" id="aiResize" aria-label="Perbesar atau perkecil jendela">
        <svg class="expand-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
        </svg>
        <svg class="minimize-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
        </svg>
      </button>
      <button class="kodee2-x" id="aiClose" aria-label="Tutup">✕</button>
    </div>
  </div>

  <div class="kodee2-body">
    <div class="kodee2-card">

      <!-- HERO / EMPTY STATE -->
      <div id="k2Empty" class="k2-empty">
        <img src="<?= htmlspecialchars($aiLogo,ENT_QUOTES) ?>" alt="" class="k2-empty-logo">
        <div class="k2-empty-title">Hello, I'm efidi.ai <span class="wave" aria-hidden="true">👋</span></div>
        <div class="k2-empty-sub">What can I help you with today?</div>

        <div class="k2-empty-actions">
          <div class="k2-empty-chips">
            <button type="button" class="k2-empty-chip" data-q="Jelaskan sebuah topik dengan bahasa yang paling sederhana dan mudah saya pahami, lengkap dengan contoh.">
              <i class="mdi mdi-file-search-outline"></i>
              <span>Explain a topic</span>
            </button>
            <button type="button" class="k2-empty-chip" data-q="Bantu rapikan dan susun teks atau pesan saya agar lebih jelas, rapi, dan enak dibaca.">
              <i class="mdi mdi-image-multiple-outline"></i>
              <span>Help with writing</span>
            </button>
            <button type="button" class="k2-empty-chip" data-q="Saya punya sebuah masalah, jelaskan situasinya dan bantu carikan ide solusi yang realistis.">
              <i class="mdi mdi-lan-connect"></i>
              <span>Find ideas &amp; solutions</span>
            </button>
          </div>

          <div class="k2-empty-hints">
            <div class="k2-hint-row">
              <span class="k2-hint-dot"></span>
              <span>Anda dapat mengajukan pertanyaan tentang beragam topik tanpa batasan kategori: mulai dari konsep, langkah praktis, sampai analisis untuk aktivitas sehari-hari maupun pekerjaan.</span>
            </div>
            <div class="k2-hint-row">
              <span class="k2-hint-dot"></span>
              <span>Anda bisa memasukkan kata kunci, melampirkan potongan teks, atau mengirimkan informasi lain yang perlu dibaca dan dianalisis lebih dalam.</span>
            </div>
            <div class="k2-hint-row">
              <span class="k2-hint-dot"></span>
              <span>efidi.ai dirancang sebagai asisten cerdas yang memberi jawaban jelas, relevan, dan mudah dipahami, menyesuaikan dengan kebutuhan dan gaya bahasa Anda.</span>
            </div>
          </div>
        </div>
      </div>

      <!-- CHAT AREA -->
      <div class="kodee2-chat" id="aiChat" hidden>
        <div id="k2ChatIntro" class="k2-chat-intro" hidden>
          <div class="k2-chat-intro-icon">
            <i class="mdi mdi-flash"></i>
          </div>
          <div class="k2-chat-intro-main">
            <strong>Session started with efidi.ai</strong>
            <span>Tip: kamu bisa bertanya apa saja lalu minta efidi.ai menjelaskan, meringkas, membuat contoh, atau menyusun langkah solusi.</span>
          </div>
        </div>

        <div id="aiList" class="kodee2-list" aria-live="polite" aria-relevant="additions text"></div>
      </div>

      <!-- INPUT -->
      <div class="kodee2-ask">
        <div class="k2-ask-wrap">
          <textarea id="aiMsg" class="k2-input" placeholder="Write your question or instruction here" spellcheck="true"></textarea>
          <button id="aiSend" class="k2-send" type="button" title="Send" aria-label="Send">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true" focusable="false">
              <path d="M22 2L11 13"></path>
              <path d="M22 2l-7 20-4-9-9-4 20-7z"></path>
            </svg>
          </button>
          <button id="aiStop" class="k2-stop" type="button" title="Stop" aria-label="Stop generating" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true" focusable="false">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            </svg>
          </button>
        </div>
        <div class="k2-disclaimer">efidi.ai – Artificial intelligence assistant</div>
      </div>

    </div>
  </div>
</div>

<script>
(() => {
  const $ = s => document.querySelector(s);
  const esc = s => String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));

  const panel    = $('#aiPanel');
  const list     = $('#aiList');
  const msg      = $('#aiMsg');
  const btn      = $('#aiSend');
  const chat     = $('#aiChat');
  const empty    = $('#k2Empty');
  const resizeBtn= $('#aiResize');
  const stopBtn  = $('#aiStop');
  const chatIntro= $('#k2ChatIntro');
  const resetBtn = $('#aiReset');
  const fab      = $('#aiFab');
  const closeBtn = $('#aiClose');

  let isAIProcessing        = false;
  let currentAbortController= null;
  let isUserScrolling       = false;
  let scrollBottomTimer     = null;
  let autoScrollEnabled     = true;
  let typingBubble          = null;
  let stopRequested         = false;

  function getActionUrl(){
    const d = panel?.dataset.action?.trim();
    if (!d) return `${location.origin}/ai/ask.php`;
    if (/^https?:\/\//i.test(d)) return d;
    if (d.startsWith('/'))       return `${location.origin}${d}`;
    return `${location.origin}/${d}`;
  }

  function scrollBottom(force=false){
    if (!list) return;
    if (!force && !autoScrollEnabled) return;
    if (scrollBottomTimer) clearTimeout(scrollBottomTimer);
    scrollBottomTimer = setTimeout(()=>{ list.scrollTop = list.scrollHeight; },50);
  }

  function fallbackCopy(text){
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position='fixed';
    ta.style.left='-9999px';
    document.body.appendChild(ta);
    ta.select();
    try{ document.execCommand('copy'); }catch(_){}
    document.body.removeChild(ta);
  }

  // COPY CODE & QUICK CHIP (delegasi)
  document.addEventListener('click', async (e) => {
    const copyBtn = e.target.closest('.k2-code-copy');
    if (copyBtn){
      const id = copyBtn.getAttribute('data-code-id');
      const codeEl = document.getElementById(id);
      if (codeEl){
        const text = codeEl.textContent || '';
        try{ await navigator.clipboard.writeText(text); }
        catch(_){ fallbackCopy(text); }
        const old = copyBtn.textContent;
        copyBtn.textContent = 'Copied!';
        copyBtn.disabled = true;
        setTimeout(()=>{ copyBtn.textContent = old; copyBtn.disabled = false; },1200);
      }
    }

    const chip = e.target.closest('.k2-empty-chip');
    if (chip){
      const q = chip.getAttribute('data-q') || '';
      if (msg){
        msg.value = q;
        msg.focus();
      }
    }
  });

  function setupScrollDetection(){
    if (!list) return;
    let t;
    list.addEventListener('scroll', () => {
      const threshold = 100;
      const pos = list.scrollTop + list.clientHeight;
      const h   = list.scrollHeight;
      const nearBottom = h - pos <= threshold;

      if (!nearBottom){
        autoScrollEnabled = false;
        isUserScrolling = true;
      } else {
        autoScrollEnabled = true;
        isUserScrolling = false;
      }

      clearTimeout(t);
      t = setTimeout(() => {
        const pos2 = list.scrollTop + list.clientHeight;
        const h2   = list.scrollHeight;
        if (h2 - pos2 <= threshold){
          autoScrollEnabled = true;
          isUserScrolling = false;
        }
      },1500);
    });
  }

  function addUserBubble(t){
    const el = document.createElement('div');
    el.className='k2-msg k2-user';
    el.innerHTML=`<div class="k2-bubble">${esc(t)}</div>`;
    list.appendChild(el);
    scrollBottom(true);
  }

  function addTyping(){
    const el = document.createElement('div');
    el.id='aiTyping';
    el.className='k2-msg k2-bot k2-typing';
    const logo = panel?.dataset.logo || '';
    el.innerHTML =
      `<div class="k2-ava">${logo ? `<img src="${esc(logo)}" alt="">` : ''}</div>
       <div class="k2-bubble">
         <span class="k2-spin"></span>
         <strong style="color:#334155">Thinking</strong>
         <span class="k2-dots"><i></i><i></i><i></i></span>
       </div>`;
    list.appendChild(el);
    scrollBottom(true);
  }

  function removeTyping(){
    document.getElementById('aiTyping')?.remove();
  }

  function createCodeBlock(code, language){
    const codeId = 'code-' + Date.now() + '-' + Math.random().toString(36).slice(2,7);
    const lang   = (language || 'text').toLowerCase();
    const label  = (language || 'text').toUpperCase();
    return `
      <div class="k2-code-block">
        <div class="k2-code-header">
          <span>${esc(label)}</span>
          <button class="k2-code-copy" type="button" data-code-id="${codeId}">Copy</button>
        </div>
        <div class="k2-code-content" id="${codeId}" data-lang="${esc(lang)}">${esc(code)}</div>
      </div>
    `;
  }

  function addBotBubble(t){
    const el = document.createElement('div');
    el.className='k2-msg k2-bot';
    const bubbleId='bubble-' + Date.now();
    const logo = panel?.dataset.logo || '';
    el.innerHTML =
      `<div class="k2-ava">${logo ? `<img src="${esc(logo)}" alt="">` : ''}</div>
       <div class="k2-bubble" id="${bubbleId}">
         <span class="k2-cursor">|</span>
       </div>`;
    list.appendChild(el);
    scrollBottom(true);
    const bubble = document.getElementById(bubbleId);
    typingBubble = bubble;
    bubble.typingDone = typeWriterWithCursor(bubble, t, 15);
    bubble.typingDone.finally(()=>{ if (typingBubble === bubble) typingBubble = null; });
    return bubble;
  }

  function attachContinueLink(bubble, noteText){
    if (!bubble || bubble.dataset.k2Continue === '1') return;
    bubble.dataset.k2Continue='1';
    const note = document.createElement('div');
    note.className='k2-cont-note';
    const label = (noteText && String(noteText).trim())
      ? String(noteText).trim()
      : 'Jawaban cukup panjang dan mungkin masih bisa dilanjutkan.';
    note.innerHTML = esc(label) + ' <a href="#" class="k2-cont-link">LANJUTKAN</a>';
    bubble.appendChild(note);
    const link = note.querySelector('.k2-cont-link');
    if (link){
      link.addEventListener('click', ev => {
        ev.preventDefault();
        if (isAIProcessing) return;
        sendNow({isContinue:true,text:'Lanjutkan'});
      });
    }
    scrollBottom(true);
  }

  function toggleSendStopButtons(showStop=false){
    if (!btn || !stopBtn) return;
    if (showStop){
      btn.style.display='none';
      stopBtn.style.display='grid';
      isAIProcessing=true;
      stopBtn.classList.add('k2-stop-pulse');
    } else {
      btn.style.display='grid';
      stopBtn.style.display='none';
      isAIProcessing=false;
      stopBtn.classList.remove('k2-stop-pulse');
      if (currentAbortController) currentAbortController=null;
    }
  }

  function stopAIGeneration(){
    stopRequested=true;
    if (currentAbortController) currentAbortController.abort();
    if (typingBubble && typeof typingBubble.stopTyping === 'function'){
      typingBubble.stopTyping();
    }
    removeTyping();
    toggleSendStopButtons(false);
    if (btn) btn.disabled=false;
  }

  stopBtn?.addEventListener('click', ev => {
    ev.preventDefault();
    stopAIGeneration();
  });

  function resetChat(){
    stopAIGeneration();
    if (list) list.innerHTML='';
    if (chatIntro) chatIntro.setAttribute('hidden','');
    if (chat) chat.setAttribute('hidden','');
    if (empty) empty.removeAttribute('hidden');
    if (panel) panel.classList.remove('has-chat');
    if (msg){
      msg.value='';
      msg.focus();
    }
    autoScrollEnabled=true;
    scrollBottom(true);
  }

  resetBtn?.addEventListener('click', e => {
    e.preventDefault();
    resetChat();
  });

  function typeWriterWithCursor(element, text, speed=15){
    let resolveFn;
    const p = new Promise(r => { resolveFn = r; });
    let i=0;
    let isTyping=true;
    const cursor = element.querySelector('.k2-cursor');

    function finish(appendTruncatedNote=false){
      if (cursor) cursor.remove();
      applySyntaxHighlighting(element);
      if (appendTruncatedNote){
        element.insertAdjacentHTML('beforeend','<br><em style="color:#94a3b8;font-size:12px">[Chat dihentikan]</em>');
      }
      isTyping=false;
      autoScrollEnabled=true;
      resolveFn();
    }

    function type(){
      if (!isTyping) return;
      if (i < text.length){
        const char = text.charAt(i);

        if (char === '`' && text.substring(i,i+3)==='```'){
          const endIndex = text.indexOf('```',i+3);
          if (endIndex !== -1){
            const codeContent = text.substring(i+3,endIndex);
            const languageMatch = codeContent.match(/^(\w+)\n/);
            const language = languageMatch ? languageMatch[1] : 'text';
            const code = languageMatch ? codeContent.substring(languageMatch[0].length) : codeContent;
            const block = createCodeBlock(code, language);
            cursor.insertAdjacentHTML('beforebegin', block);
            i = endIndex + 3;
            if (autoScrollEnabled) scrollBottom(true);
            return setTimeout(type,speed);
          }
        }

        if (char === '\n'){
          cursor.insertAdjacentHTML('beforebegin','<br>');
        } else {
          cursor.insertAdjacentHTML('beforebegin',esc(char));
        }
        i++;
        if (autoScrollEnabled) scrollBottom();
        setTimeout(type,speed);
      } else {
        finish(false);
      }
    }

    element.stopTyping = function(){
      isTyping=false;
      finish(true);
    };

    setTimeout(type,100);
    return p;
  }

  /* HIGHLIGHTER + LINKIFY ==================== */

  function highlightCode(code, lang){
    lang = (lang || '').toLowerCase();
    if (lang === 'html' || lang === 'xml' || lang === 'xhtml'){
      return highlightHtml(code);
    }
    const cfg = {
      allowDollarVars: lang === 'php',
      noBacktickStrings:false,
      keywords:[],
      types:[],
      builtins:[],
      constants:[],
      booleans:['true','false']
    };

    if (lang === 'php'){
      cfg.keywords = [
        'if','else','elseif','endif','for','foreach','endforeach','while','endwhile',
        'do','switch','case','default','break','continue','return','function','fn',
        'namespace','use','as','try','catch','finally','throw','class','interface',
        'trait','extends','implements','new','clone','var','public','protected',
        'private','static','global','echo','print','isset','empty','unset','include',
        'require','include_once','require_once','yield','from','instanceof'
      ];
      cfg.types = [
        'int','float','string','bool','array','object','iterable','mixed','void',
        'self','parent','static','callable','resource','true','false','null'
      ];
      cfg.builtins=[
        'var_dump','print_r','count','strlen','substr','array_merge','in_array',
        'json_encode','json_decode','explode','implode','trim','strtoupper',
        'strtolower','header','time','date','sprintf'
      ];
      cfg.constants=['null'];
    } else if (['js','javascript','ts','typescript','node'].includes(lang)){
      cfg.keywords=[
        'if','else','for','while','do','switch','case','default','break','continue',
        'return','function','const','let','var','new','in','of','instanceof','try',
        'catch','finally','throw','class','extends','super','import','export',
        'default','await','async','yield','this','delete','typeof','void'
      ];
      cfg.types=[
        'string','number','boolean','object','symbol','bigint','any','unknown',
        'never','undefined','null'
      ];
      cfg.builtins=[
        'console','window','document','Array','Object','String','Number','Boolean',
        'Promise','Math','Date','JSON','Map','Set','WeakMap','WeakSet','RegExp',
        'Error','TypeError','SyntaxError','ReferenceError'
      ];
      cfg.constants=['NaN','Infinity','undefined','null'];
    } else if (lang === 'sql'){
      cfg.keywords=[
        'select','from','where','group','by','order','limit','insert','into',
        'update','delete','join','left','right','inner','outer','on','create',
        'table','alter','drop','index','view','having','distinct','as','and',
        'or','not','like','in','between','is','null','primary','key','foreign',
        'default','auto_increment','values'
      ];
    } else if (['bash','sh','shell','zsh'].includes(lang)){
      cfg.keywords=[
        'if','then','else','elif','fi','for','in','do','done','case','esac',
        'while','until','function','select','time'
      ];
      cfg.builtins=[
        'echo','cd','ls','cat','grep','awk','sed','find','export','readonly',
        'local','read','printf'
      ];
    } else if (['py','python'].includes(lang)){
      cfg.keywords=[
        'if','elif','else','for','while','break','continue','return','def','class',
        'try','except','finally','raise','import','from','as','pass','yield',
        'with','lambda','global','nonlocal','assert','del','in','is','and','or','not'
      ];
      cfg.types=[
        'int','float','str','bool','list','dict','set','tuple','object','None'
      ];
      cfg.builtins=[
        'print','len','range','enumerate','zip','map','filter','open','sorted',
        'sum','min','max','any','all','isinstance','type','dir','vars'
      ];
      cfg.constants=['None'];
      cfg.booleans=['True','False'];
    } else {
      cfg.keywords=[
        'if','else','for','while','do','switch','case','break','continue','return',
        'struct','enum','union','typedef','sizeof','volatile','const','static','goto'
      ];
      cfg.types=[
        'int','long','short','char','float','double','void','signed','unsigned',
        'bool','size_t'
      ];
    }
    return genericHighlight(code, cfg);
  }

  function genericHighlight(code, cfg){
    const kwSet      = new Set((cfg.keywords || []).map(s => s.toLowerCase()));
    const typeSet    = new Set((cfg.types || []).map(s => s.toLowerCase()));
    const builtinSet = new Set((cfg.builtins || []).map(s => s.toLowerCase()));
    const constSet   = new Set((cfg.constants || []).map(s => s.toLowerCase()));
    const boolWords  = (cfg.booleans || ['true','false']).map(s => s.toLowerCase());

    const isIdentStart = ch => /[A-Za-z_$]/.test(ch);
    const isIdentPart  = ch => /[A-Za-z0-9_$]/.test(ch);
    const isDigit      = ch => /[0-9]/.test(ch);

    function formatTagSegment(tagText){
      let res='';
      const tagMatch = /^<\/?([a-zA-Z0-9:-]+)/.exec(tagText);
      let tagName = tagMatch ? tagMatch[1] : '';
      let head='';
      let rest=tagText;

      if (tagMatch){
        head = tagText.slice(0,tagMatch.index+1);
        rest = tagText.slice(tagMatch.index+1);
        rest = rest.slice(tagName.length);
      }

      if (head) res += '<span class="angle">' + esc(head) + '</span>';
      if (tagName) res += '<span class="tag">' + esc(tagName) + '</span>';

      let i=0, n=rest.length;
      while(i<n){
        const ch = rest[i];

        if (ch === '"' || ch === "'"){
          const q = ch;
          let j=i+1;
          while(j<n && rest[j]!==q) j++;
          j++;
          const val = rest.slice(i,j);
          res += '<span class="string">' + esc(val) + '</span>';
          i=j; continue;
        }

        if (/\s/.test(ch)){
          let j=i+1;
          while(j<n && /\s/.test(rest[j])) j++;
          res += esc(rest.slice(i,j));
          i=j; continue;
        }

        if (ch === '='){
          res += '<span class="equals">' + esc(ch) + '</span>';
          i++; continue;
        }

        if (ch === '>'){
          res += '<span class="angle">' + esc(ch) + '</span>';
          i++; continue;
        }

        let j=i+1;
        while(j<n && !/[\s=>]/.test(rest[j])) j++;
        const name = rest.slice(i,j);
        res += '<span class="attribute">' + esc(name) + '</span>';
        i=j;
      }
      return res;
    }

    let out='';
    const n=code.length;
    let i=0;

    while(i<n){
      const ch = code[i];

      if (ch === '\n'){ out+='\n'; i++; continue; }

      if (ch === '/' && code[i+1] === '/'){
        let j=i+2;
        while(j<n && code[j] !== '\n') j++;
        const c = code.slice(i,j);
        out += '<span class="comment">' + esc(c) + '</span>';
        i=j; continue;
      }

      if (ch === '/' && code[i+1] === '*'){
        let j = code.indexOf('*/',i+2);
        if (j === -1) j=n; else j+=2;
        const c = code.slice(i,j);
        out += '<span class="comment">' + esc(c) + '</span>';
        i=j; continue;
      }

      if (ch === '#'){
        let j=i+1;
        while(j<n && code[j] !== '\n') j++;
        const c = code.slice(i,j);
        out += '<span class="comment">' + esc(c) + '</span>';
        i=j; continue;
      }

      if (ch === '<'){
        let j=i+1;
        if (code[j] === '/') j++;
        if (code[j] && /[A-Za-z]/.test(code[j])){
          const end = code.indexOf('>',j);
          if (end !== -1){
            const tagText = code.slice(i,end+1);
            out += formatTagSegment(tagText);
            i = end+1;
            continue;
          }
        }
      }

      if (ch === '"' || ch === "'" || (ch === '`' && !cfg.noBacktickStrings)){
        const quote = ch;
        let j=i+1;
        while(j<n){
          const cj = code[j];
          if (cj === '\\'){ j+=2; continue; }
          if (cj === quote){ j++; break; }
          j++;
        }
        const s = code.slice(i,j);
        out += '<span class="string">' + esc(s) + '</span>';
        i=j; continue;
      }

      if (isDigit(ch)){
        let j=i+1;
        while(j<n && /[0-9_.xXa-fA-F]/.test(code[j])) j++;
        const num = code.slice(i,j);
        out += '<span class="number">' + esc(num) + '</span>';
        i=j; continue;
      }

      if (ch === '@'){
        let j=i+1;
        while(j<n && /[A-Za-z0-9_.]/.test(code[j])) j++;
        const ann = code.slice(i,j);
        out += '<span class="annot">' + esc(ann) + '</span>';
        i=j; continue;
      }

      if (ch === '$' && cfg.allowDollarVars){
        let j=i+1;
        while(j<n && isIdentPart(code[j])) j++;
        const v = code.slice(i,j);
        out += '<span class="variable">' + esc(v) + '</span>';
        i=j; continue;
      }

      if (isIdentStart(ch)){
        let j=i+1;
        while(j<n && isIdentPart(code[j])) j++;
        const id = code.slice(i,j);
        const low = id.toLowerCase();
        let cls='';
        if (boolWords.includes(low)) cls='boolean';
        else if (kwSet.has(low)) cls='keyword';
        else if (builtinSet.has(low)) cls='builtin';
        else if (typeSet.has(low)) cls='type';
        else if (constSet.has(low)) cls='constant';
        else{
          let k=j;
          while(k<n && /\s/.test(code[k])) k++;
          if (code[k] === '(') cls='function';
        }
        if (!cls) cls='variable';
        out += '<span class="'+cls+'">'+esc(id)+'</span>';
        i=j; continue;
      }

      if ('(){}[]'.includes(ch)){
        const cls = (ch==='('||ch===')') ? 'paren' : (ch==='{'||ch==='}') ? 'brace' : 'bracket';
        out += '<span class="'+cls+'">'+esc(ch)+'</span>';
        i++; continue;
      }

      if (',;:.'.includes(ch)){
        const cls = (ch===',')?'comma':(ch===';')?'semicolon':(ch===':')?'colon':'dot';
        out += '<span class="punct '+cls+'">'+esc(ch)+'</span>';
        i++; continue;
      }

      if ('+-*/%=&|!<>^~?'.includes(ch)){
        let j=i+1;
        while(j<n && '+-*/%=&|!<>^~?'.includes(code[j])) j++;
        const op = code.slice(i,j);
        out += '<span class="operator">'+esc(op)+'</span>';
        i=j; continue;
      }

      out += esc(ch);
      i++;
    }

    return out;
  }

  function highlightHtml(code){
    const commentRe = /<!--[\s\S]*?-->/g;
    let out='', last=0, m;

    function processSegment(seg){
      const entityRe=/&[a-zA-Z0-9#]+;/g;
      let result='', idx=0, tm;
      const tagRe=/<\/?[a-zA-Z][^>]*>/g;

      while((tm = tagRe.exec(seg)) !== null){
        if (tm.index > idx){
          let plain = seg.slice(idx, tm.index);
          plain = plain.replace(entityRe, ent => '<span class="entity">'+esc(ent)+'</span>');
          result += plain ? plain.split('').map(ch=>esc(ch)).join('') : '';
        }
        result += genericHighlight(tm[0], {keywords:[],types:[],builtins:[],constants:[],booleans:[]});
        idx = tagRe.lastIndex;
      }

      if (idx < seg.length){
        let plain = seg.slice(idx);
        plain = plain.replace(entityRe, ent => '<span class="entity">'+esc(ent)+'</span>');
        result += plain ? plain.split('').map(ch=>esc(ch)).join('') : '';
      }
      return result;
    }

    while((m = commentRe.exec(code)) !== null){
      if (m.index > last){
        out += processSegment(code.slice(last, m.index));
      }
      out += '<span class="comment">' + esc(m[0]) + '</span>';
      last = commentRe.lastIndex;
    }
    if (last < code.length){
      out += processSegment(code.slice(last));
    }
    return out;
  }

  function linkifyText(text){
    const pattern = /((?:https?:\/\/|www\.)\S+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/g;
    let result='', last=0, m;
    while((m = pattern.exec(text)) !== null){
      if (m.index > last){
        result += esc(text.slice(last, m.index));
      }
      const token = m[0];
      if (token.includes('@') && !/^https?:\/\//i.test(token)){
        result += '<a href="mailto:'+esc(token)+'" class="k2-link">'+esc(token)+'</a>';
      } else {
        const href = /^https?:\/\//i.test(token) ? token : 'https://'+token;
        result += '<a href="'+esc(href)+'" class="k2-link" target="_blank" rel="noopener noreferrer">'+esc(token)+'</a>';
      }
      last = m.index + token.length;
    }
    if (last < text.length){
      result += esc(text.slice(last));
    }
    return result;
  }

  function linkifyElement(rootEl){
    if (!rootEl) return;
    const walker = document.createTreeWalker(rootEl,NodeFilter.SHOW_TEXT,null,false);
    const pattern = /((?:https?:\/\/|www\.)\S+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/;
    const nodes=[];
    let node;
    while((node = walker.nextNode())){
      if (!node.parentElement) continue;
      if (node.parentElement.closest('.k2-code-content')) continue;
      const val = node.nodeValue || '';
      if (!pattern.test(val)) continue;
      nodes.push(node);
    }
    nodes.forEach(n => {
      const html = linkifyText(n.nodeValue || '');
      const span = document.createElement('span');
      span.innerHTML = html;
      const frag = document.createDocumentFragment();
      while(span.firstChild) frag.appendChild(span.firstChild);
      if (n.parentNode) n.parentNode.replaceChild(frag,n);
    });
  }

  function applySyntaxHighlighting(root=document){
    const blocks = root.querySelectorAll('.k2-code-content');
    blocks.forEach(block => {
      if (block.dataset.k2Hl === '1') return;
      block.dataset.k2Hl='1';
      const lang = (block.dataset.lang || '').toLowerCase();
      const raw  = block.textContent || '';
      const html = highlightCode(raw, lang);
      block.innerHTML = html;
    });

    const bubbles = root.querySelectorAll('.k2-bubble');
    bubbles.forEach(bubble => {
      if (bubble.dataset.k2Link === '1') return;
      linkifyElement(bubble);
      bubble.dataset.k2Link='1';
    });
  }

  applySyntaxHighlighting(document);
  new MutationObserver(muts => {
    let need=false;
    muts.forEach(m => m.addedNodes && m.addedNodes.forEach(n => {
      if (n.nodeType===1 && (
        n.matches?.('.k2-code-content, .k2-bubble') ||
        n.querySelector?.('.k2-code-content, .k2-bubble')
      )) need=true;
    }));
    if (need) applySyntaxHighlighting(document);
  }).observe(document.body,{childList:true,subtree:true});

  async function sendNow(opts={}){
    const isContinue = !!opts.isContinue;
    const manualText = typeof opts.text === 'string' ? opts.text : null;

    let q;
    let userBubbleText;

    if (isContinue){
      q = manualText || 'Lanjutkan';
      userBubbleText = q;
    } else {
      q = (msg?.value || '').trim();
      userBubbleText = q;
    }

    if (!q || isAIProcessing){
      if (!isContinue) msg?.focus();
      return;
    }

    stopRequested=false;

    if (panel && !panel.classList.contains('has-chat')){
      panel.classList.add('has-chat');
    }
    if (empty) empty.setAttribute('hidden','');
    if (chat)  chat.removeAttribute('hidden');
    if (chatIntro) chatIntro.removeAttribute('hidden');

    addUserBubble(userBubbleText);
    addTyping();

    currentAbortController = new AbortController();
    toggleSendStopButtons(true);

    let reply='', finishReason='', noteText='';

    try{
      const params = new URLSearchParams();
      params.set('q', q);
      if (isContinue) params.set('continue','1');

      const res = await fetch(getActionUrl(),{
        method:'POST',
        headers:{
          'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With':'XMLHttpRequest'
        },
        body:params.toString(),
        credentials:'same-origin',
        signal:currentAbortController.signal
      });

      if (res.ok){
        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')){
          const data = await res.json();
          reply        = (data.reply ?? '').toString();
          finishReason = data.finish || '';
          noteText     = data.note   || '';
        } else {
          reply = await res.text();
        }
      } else {
        reply = (await res.text().catch(()=>'')) || ('HTTP ' + res.status);
      }
    } catch(e){
      if (e.name === 'AbortError') return;
      console.error(e);
      reply = 'Maaf, terjadi gangguan jaringan. Coba lagi ya.';
    }

    removeTyping();

    if (stopRequested){
      toggleSendStopButtons(false);
      return;
    }

    if (reply){
      const bubble = addBotBubble(reply);
      try{ await bubble.typingDone; }
      finally{ toggleSendStopButtons(false); }
      if (finishReason === 'length'){
        attachContinueLink(bubble, noteText);
      }
    } else {
      toggleSendStopButtons(false);
    }

    if (!isContinue && msg){
      msg.value='';
      setTimeout(()=>msg.focus(),100);
    }
    setTimeout(scrollBottom,150);
  }

  btn?.addEventListener('click', () => {
    if (!isAIProcessing) sendNow();
  });

  msg?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey){
      e.preventDefault();
      if (!isAIProcessing) sendNow();
    }
  });

  /* ========== PANEL OPEN/CLOSE & RESIZE ========== */

  function openPanel(){
    if (!panel) return;
    if (!panel.classList.contains('is-open')){
      panel.classList.add('is-open');
      fab?.setAttribute('aria-expanded','true');
      setTimeout(()=>msg?.focus(),50);
    }
  }
  function closePanel(){
    if (!panel) return;
    panel.classList.remove('is-open');
    panel.classList.remove('is-expanded');
    document.body.style.overflow='';
    fab?.setAttribute('aria-expanded','false');
    if (isAIProcessing) stopAIGeneration();
  }

  function toggleExpand(){
    if (!panel) return;
    const isExpanded = panel.classList.contains('is-expanded');
    if (!isExpanded){
      panel.classList.add('is-expanded');
      document.body.style.overflow='hidden';
    } else {
      panel.classList.remove('is-expanded');
      document.body.style.overflow='';
    }
    setTimeout(scrollBottom,100);
  }

  fab?.addEventListener('click', ev => {
    ev.preventDefault();
    if (panel?.classList.contains('is-open')){
      closePanel();
    } else {
      openPanel();
    }
  });

  closeBtn?.addEventListener('click', ev => {
    ev.preventDefault();
    closePanel();
  });

  resizeBtn?.addEventListener('click', ev => {
    ev.preventDefault();
    if (!panel?.classList.contains('is-open')) openPanel();
    toggleExpand();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && panel?.classList.contains('is-open') && panel.classList.contains('is-expanded')){
      toggleExpand();
    }
  });

  window.addEventListener('resize', () => { scrollBottom(); });

  setTimeout(()=>{ setupScrollDetection(); },1000);
})();
</script>

</body>
</html>
