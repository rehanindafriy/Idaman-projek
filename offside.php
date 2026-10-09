<?php
declare(strict_types=1);

// bangun link home dinamis (misal ke halaman login / dashboard utama)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$home   = $scheme . '://' . $host . '/';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>efidi · Link sudah kedaluwarsa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#000000">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
  <style>
    :root{
      --es-teal:      #00a3ad;
      --es-teal-soft: #00a3ad;
      --es-teal-dark: #e5f6f7;
      --es-bg:        #000000;   /* full hitam */
      --es-muted:     #9ca3af;
    }

    *{box-sizing:border-box;margin:0;padding:0;}

    body{
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:20px;
      font-family: system-ui, -apple-system, BlinkMacSystemFont,
                   "Segoe UI", Roboto, "Plus Jakarta Sans", sans-serif;
      background:
        var(--es-bg);
      color:#e5e7eb;
    }

    .card{
      width:min(420px,100%);
      border-radius:20px;
      background:#000000; /* card juga hitam */
      box-shadow:
        0 0px 0px rgba(0,0,0,0.9),
        0 0 0 1px #262626;
      overflow:hidden;
      position:relative;
    }

    .card::before{
      content:"";
      position:absolute;
      inset:-1px;
      border-radius:22px;
      background:
        linear-gradient(135deg,rgba(34,211,238,0.20),transparent 40%,rgba(34,211,238,0.18));
      pointer-events:none;
      z-index:-1;
    }

    .card-header{
      padding:14px 18px 10px;
      border-bottom:1px solid #262626;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
    }

    .card-brand{
      display:flex;
      align-items:center;
      gap:8px;
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.18em;
      color:#f9fafb;
      font-weight:600;
    }

    .card-brand-logo{
      width:26px;
      height:26px;
      border-radius:999px;
      background:#00a3ad;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:18px;
      color:#ecfeff;
      box-shadow:0 0 0px rgba(56,189,248,0.6);
    }

    .card-tag{
      padding:3px 9px;
      border-radius:999px;
      border:1px solid #4b5563;
      font-size:10px;
      text-transform:uppercase;
      letter-spacing:.12em;
      color:#e5e7eb;
      background:#000000;
      white-space:nowrap;
    }

    .card-body{
      padding:16px 18px 18px;
    }

    .title{
      font-size:18px;
      font-weight:700;
      margin-bottom:4px;
      color:#f9fafb;
    }

    .subtitle{
      font-size:13px;
      color:var(--es-muted);
      margin-bottom:14px;
      line-height:1.5;
    }

    .badge-info{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 10px;
      border-radius:999px;
      background:#000000;
      border:1px solid rgba(0, 163, 173, 1);
      font-size:11px;
      color:#e0f2fe;
      margin-bottom:10px;
    }
    .badge-info .mdi{
      font-size:16px;
      color:var(--es-teal-soft);
    }

    .hint{
      font-size:11px;
      color:#9ca3af;
      line-height:1.5;
      margin-bottom:14px;
    }

    .actions{
      display:flex;
      justify-content:flex-start;
      gap:10px;
      flex-wrap:wrap;
    }

    .btn-main{
      border:none;
      border-radius:999px;
      padding:7px 14px;
      font-size:12px;
      font-weight:600;
      display:inline-flex;
      align-items:center;
      gap:6px;
      cursor:pointer;
      background:linear-gradient(135deg,var(--es-teal-soft),var(--es-teal-dark));
      color:#022733;
      box-shadow:0 0px 0px rgba(56,189,248,0.5);
      text-decoration:none;
    }
    .btn-main .mdi{
      font-size:16px;
    }

    .btn-main:active{
      transform:translateY(1px);
      box-shadow:0 0px 0px rgba(56,189,248,0.4);
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="card-header">
      <div class="card-brand">
        <div class="card-brand-logo">
          <i class="mdi mdi-shield-lock-outline"></i>
        </div>
        <span>efidi secure link</span>
      </div>
      <div class="card-tag">LINK EXPIRED</div>
    </div>
    <div class="card-body">
      <div class="title">Link berbagi sudah kedaluwarsa</div>
      <div class="subtitle">
        Demi keamanan data, setiap link berbagi detail order hanya berlaku
        selama <b>1 jam</b> sejak dibuat.
      </div>

      <div class="badge-info">
        <i class="mdi mdi-timer-off-outline"></i>
        <span>Link yang kamu buka sudah lewat batas waktunya.</span>
      </div>

      <div class="hint">
        Minta pengirim untuk membuat link baru dari fitur
        <b>Export detail order</b> di aplikasi efidi, lalu gunakan
        link terbaru tersebut.
      </div>

   
    </div>
  </div>
</body>
</html>
