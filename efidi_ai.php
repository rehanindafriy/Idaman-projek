<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// URL dinamis ke /ai/ask.php (ikut subfolder apa pun)
$aiLogo = 'images/10.png';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');   // contoh: "" atau "/e-siklo"
$aiUrl  = $scheme.'://'.$_SERVER['HTTP_HOST'].($base ? $base.'/' : '/').'ai/ask.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>efidi.ai — Assistant</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- (opsional) MDI kalau mau pakai ikon lain2 -->
  <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

  <style>
    /* ====== BACKGROUND PAGE ====== */
    body{
      margin:0;
      min-height:100vh;
      font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
      background:#0f172a; /* tidak kelihatan karena panel putih menutup full */
    }

    :root{
      --kodee2-bg:#fff;
      --kodee2-ink:#0f172a;
      --kodee2-line:#e5e7eb;
      --kodee2-primary:var(--es-primary,#fff); /* brand teal */
      --kodee2-accent:#07d9e6; /* border input */
      --kodee2-acce:#06c5d1;

      /* ========= PALET "COFFEE SLATE" UNTUK CODE BLOCK ========= */
      /* dasar teks di atas background gelap */
      --k2-ink:#e5e7eb;   /* teks utama */
      --k2-dim:#9ca3af;   /* teks redup */

      /* background utama & highlight baris */
      --k2-bg:#18181b;    /* abu-abu gelap netral (zinc-900) */
      --k2-hl:#27272a;    /* highlight baris (zinc-800, sedikit lebih terang) */

      /* keyword / control flow (if, for, return, class, etc.) */
      --k2-key:#fb923c;   /* oranye terang, eye-catching */

      /* string & template literal */
      --k2-str:#4ade80;   /* hijau emerald cerah */

      /* angka / literal numeric / unit */
      --k2-num:#facc15;   /* kuning keemasan */

      /* operator (+, =, &&, dll) */
      --k2-op:#e5e7eb;

      /* komentar */
      --k2-cmt:#9ca3af;   /* abu-abu lembut */

      /* tag HTML / nama elemen */
      --k2-tag:#38bdf8;   /* biru langit */

      /* atribut (class, id, href, dll.) */
      --k2-attr:#eab308;  /* kuning emas */

      /* value atribut (misal class="btn") */
      --k2-val:#a5b4fc;   /* ungu indigo lembut */

      /* konstanta & nilai khusus (NULL, NaN) */
      --k2-const:#fb7185; /* merah muda coral */

      /* boolean true/false */
      --k2-bool:#f97373;  /* merah lembut tapi jelas */

      /* builtins (console, Array, window, var_dump, dll.) */
      --k2-builtin:#2dd4bf; /* teal cerah */

      /* tipe data (int, string, bool, interface…) */
      --k2-type:#a855f7;    /* ungu terang */

      /* nama function */
      --k2-fn:#06b6d4;      /* cyan */

      /* nama variabel biasa */
      --k2-var:#e5e7eb;     /* putih keabu, netral */

      /* nama class / interface */
      --k2-class:#facc15;   /* kuning dominan */

      /* anotasi / decorator (@Route, @Test) */
      --k2-annot:#f472b6;   /* pink */

      /* regex / pola */
      --k2-reg:#22c55e;     /* hijau terang */

      /* selector CSS */
      --k2-sel:#7dd3fc;     /* biru muda */

      /* properti CSS */
      --k2-prop:#e5e7eb;    /* netral terang */

      /* nilai CSS (px, rem, warna, dll.) */
      --k2-cssval:#fbbf24;  /* amber */

      /* namespace / module */
      --k2-ns:#2dd4bf;      /* teal */

      /* tanda baca umum (.,;: dsb) */
      --k2-punct:#cbd5e1;
      --k2-angle:#e5e7eb;   /* < dan > */
      --k2-eq:#f97316;      /* tanda = */

      /* diff + (baris ditambah) */
      --k2-addbg:#064e3b;
      --k2-addfg:#6ee7b7;

      /* diff - (baris dihapus) */
      --k2-delbg:#7f1d1d;
      --k2-delfg:#fecaca;

      /* header hunk (@@ -1,3 +1,9 @@) */
      --k2-hunkbg:#1f2937;
      --k2-hunkfg:#bfdbfe;
    }

    /* Launcher disembunyikan (tidak dipakai di halaman khusus ini) */
    .kodee2-launcher{
      display:none !important;
    }
    .kodee2-logo{
      width:23px;
      height:23px;
      object-fit:contain;
      display:block;
    }

    /* ========= PANEL UTAMA efidi.ai — FULLSCREEN ========= */
    .kodee2-panel{
      position:fixed;
      inset:0;
      z-index:9999;
      width:100vw;
      height:100vh;
      max-width:100vw;
      max-height:100vh;
      background:var(--kodee2-bg);
      color:var(--kodee2-ink);
      border:0;
      border-radius:0;
      box-shadow:none;
      overflow:hidden;
      display:flex;
      flex-direction: column;
    }

    /* Header (logo + teks) */
    .kodee2-head{
      display:flex;
      align-items:center;
      justify-content:flex-start;
      gap:8px;
      padding:10px 14px;
      border-bottom:1px solid var(--kodee2-line);
      flex-shrink: 0;
      background:#f8fafc;
    }

    .kodee2-title{
      display:flex;
      align-items:center;
      gap:6px;
      font-weight:700;
      font-size:14px;
    }

    /* spacer supaya tombol reset nempel kanan */
    .kodee2-head-spacer{
      flex:1;
    }

    /* tombol X & resize DISEMBUNYIKAN total */
    .kodee2-head-actions{
      display:none !important;
    }
    .kodee2-x,
    .k2-resize{
      display:none !important;
    }

    /* ====== BUTTON RESET (pojok kanan atas, muncul setelah chat mulai) ====== */
    .kodee2-reset{
      display:none; /* default: tidak muncul */
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
      transition:background .16s ease, box-shadow .16s ease, transform .06s ease;
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
    /* Muncul hanya setelah chat dimulai (class .has-chat) */
    .kodee2-panel.has-chat .kodee2-reset{
      display:inline-flex;
    }

    /* ====== BODY: CARD PUTIH = SATU HALAMAN ====== */
    .kodee2-body{
      padding:0;
      background:#ffffff;
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .kodee2-card{
      background:#ffffff;
      border-radius:0;
      border:0;
      box-shadow:none;
      padding:0;
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* Area chat */
    .kodee2-chat{
      padding:12px 16px 0;
      border-top:1px dashed var(--kodee2-line);
      background:#ffffff;
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* Saat chat sudah dimulai → hero hilang, chat nempel ke atas */
    .kodee2-panel.has-chat #k2Empty{
      display:none !important;
    }
    .kodee2-panel.has-chat .kodee2-chat{
      border-top:none;
      padding-top:12px;
    }

    .kodee2-list{
      max-height: none;
      height: auto;
      overflow:auto;
      display:flex;
      flex-direction:column;
      gap:8px;
      flex: 1;
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
      flex-shrink: 0;
    }
    .k2-ava img{
      width:22px;
      height:22px;
      object-fit:contain;
      display:block;
    }

    .kodee2-ask{
      padding:12px 16px 16px;
      border-top: 1px solid var(--kodee2-line);
      background: #ffffff;
      flex-shrink: 0;
      margin-top: auto;
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
      font:400 13px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial;
    }

    .k2-input::placeholder{
      color:#94a3b8;
      font-size:13px;
    }

    .k2-send, .k2-stop{
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
      transition:all 0.3s ease;
      z-index:10;
    }

    .k2-send{
      background:#06c5d1;
    }
    .k2-send:disabled{
      opacity:0.6;
      cursor:not-allowed;
    }
    .k2-send svg{
      width:16px;
      height:16px;
    }

    .k2-stop{
      background:#ef4444;
      color:white;
      display: none;
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

    /* ========= HERO / EMPTY STATE ========= */
    .k2-empty{
      display:flex;
      flex-direction:column;
      align-items:center;
      text-align:center;
      gap:4px;
      padding:16px 8px 8px;
      animation:k2pop .35s ease-out both;
      flex: 1;
      justify-content: center;
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
      transition:background .18s ease, box-shadow .18s ease, transform .1s ease;
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
      box-shadow:0 0 0 rgba(15,23,42,0);
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

    /* ========= CHAT INTRO (SETELAH PESAN PERTAMA) ========= */
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

    .k2-typing .k2-bubble{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding: 6px 10px;
    }
    .k2-spin{
      width:14px;
      height:14px;
      border-radius:50%;
      border:2px solid #e2e8f0;
      border-top-color: var(--kodee2-acce);
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
      background: var(--kodee2-acce);
      opacity:.8;
      animation:k2b .9s ease-in-out infinite;
      will-change: transform;
    }

    @media (max-width: 480px) {
      .k2-bubble {
        max-width: 80% !important;
        font-size: 12px;
      }
      .k2-input {
        font-size: 14px;
        min-height: 55px;
      }
    }

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
      from{opacity:0; transform:translateY(6px)}
      to{opacity:1; transform:none}
    }
    @keyframes k2spin{ to{transform:rotate(1turn)} }
    @keyframes k2b{
      0%,80%,100%{transform:translateY(0);opacity:.35}
      40%{transform:translateY(-2px);opacity:1}
    }

    .k2-cursor {
      animation: k2blink 1s infinite;
      color: var(--kodee2-acce);
      font-weight: bold;
    }
    @keyframes k2blink {
      0%, 50% { opacity: 1; }
      51%, 100% { opacity: 0; }
    }

    .k2-stop-pulse {
      animation: stopPulse 1.5s ease-in-out infinite;
    }
    @keyframes stopPulse {
      0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
      }
      50% {
        transform: scale(1.05);
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0);
      }
    }

    /* ====== Code block & highlighting (tema Coffee Slate) ====== */
    .k2-code-block {
      margin: 8px 0;
      border-radius: 10px;
      overflow: hidden;
      background: var(--k2-bg);
      border: 1px solid #4b5563; /* abu-abu gelap */
      box-shadow:
        0 0 0 1px rgba(15,23,42,0.5);
    }

    /* header pakai palet abu-abu + cokelat */
    .k2-code-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 7px 12px;
      background: linear-gradient(135deg,#3f3f46,#292524); /* abu-abu → cokelat */
      color: #e5e7eb;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .02em;
      border-bottom: 1px solid rgba(15,23,42,0.75);
    }

    .k2-code-header span {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .k2-code-header span::before{
      content:"";
      width:8px;
      height:8px;
      border-radius:999px;
      background: radial-gradient(circle at 30% 30%,#cceff4,#49b4bd);
      box-shadow: 0 0 0 3px rgba(248,250,252,0.08);
    }

    /* tombol copy tampil minimalis */
    .k2-code-copy {
      background: rgba(15,23,42,0.25);
      border: 1px solid rgba(148,163,184,0.7);
      color: #e5e7eb;
      padding: 3px 8px;
      border-radius: 999px;
      cursor: pointer;
      font-size: 10px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.18s ease-out;
    }
    .k2-code-copy::before{
      content:"⧉";
      font-size: 10px;
    }
    .k2-code-copy:hover{
      background: rgba(15,23,42,0.6);
      border-color:#e5e7eb;
      transform: translateY(-0.5px);
      box-shadow: 0 6px 14px rgba(15,23,42,0.8);
    }
    .k2-code-copy:active{
      transform: translateY(0);
      box-shadow: 0 2px 6px rgba(15,23,42,0.7);
    }

    /* area isi kode */
    .k2-code-content {
      padding: 10px 12px;
      color: var(--k2-ink);
      background: radial-gradient(circle at 0 0,#27272a 0, var(--k2-bg) 45%);
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
      font-size: 12px;
      line-height: 1.35;
      overflow-x: auto;
      white-space: pre;
    }

    /* ========== MAPPING WARNA TOKEN ========== */
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

    .k2-code-content .html-doctype{
      color:var(--k2-ns);
      font-weight:700;
    }

    /* entity & literal spesial */
    .k2-code-content .entity{color:#7dd3fc}

    /* tanda < > = */
    .k2-code-content .angle{color:var(--k2-angle)}
    .k2-code-content .equals{color:var(--k2-eq)}

    /* CSS */
    .k2-code-content .selector{color:var(--k2-sel);font-weight:600}
    .k2-code-content .property{color:var(--k2-prop)}
    .k2-code-content .css-value{color:var(--k2-cssval)}
    .k2-code-content .unit{color:var(--k2-num);opacity:.95}

    /* grouping karakter */
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

    /* URL & email di dalam code (kalau suatu saat dipakai) */
    .k2-code-content .url{
      color:#38bdf8;
      text-decoration:underline;
      text-underline-offset:2px;
    }
    .k2-code-content .email{
      color:#86efac;
    }

    /* diff view */
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

    /* highlight match (misal hasil search) */
    .k2-code-content .hl-match{
      background:var(--k2-hl);
      border-radius:3px;
      padding:0 2px;
    }

    /* warna dinamis opsional */
    .k2-code-content .dyn{
      color:hsl(var(--k2h,210) 78% 70%);
    }
    .k2-code-content .dyn.bg{
      background:hsl(var(--k2h,210) 30% 20% / .28);
      padding:0 2px;
      border-radius:3px;
    }

    /* chip warna kode (untuk preview warna) */
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

    /* selection di dalam code block */
    .k2-code-content ::selection{
      background:#0ea5e9;
      color:#0b1220;
    }

    /* ====== LINK DI DALAM BUBBLE JAWABAN ====== */
    .k2-bubble a{
      color:#3b82f6;  /* biru jelas, clickable */
      text-decoration:underline;
      text-underline-offset:2px;
      font-weight:500;
    }
    .k2-bubble a:hover{
      text-decoration-thickness:2px;
      filter:brightness(1.1);
    }
        /* ====== CATATAN LANJUTKAN SAAT JAWABAN TERPOTONG ====== */
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

  </style>
</head>
<body>

  <!-- LANGSUNG PANEL UTAMA efidi.ai, TANPA FAB / MODAL LAIN -->
  <div id="aiPanel" class="kodee2-panel is-open" role="dialog" aria-label="efidi.AI"
       data-action="<?= htmlspecialchars($aiUrl,ENT_QUOTES) ?>"
       data-logo="<?= htmlspecialchars($aiLogo,ENT_QUOTES) ?>">

    <div class="kodee2-head">
      <div class="kodee2-title">
        <img src="<?= htmlspecialchars($aiLogo,ENT_QUOTES) ?>" alt="" class="kodee2-logo">
        <span>efidi.ai</span>
      </div>

      <!-- spacer supaya tombol reset lari ke kanan -->
      <div class="kodee2-head-spacer"></div>

      <!-- BUTTON RESET: muncul setelah chat pertama (via .has-chat) -->
      <button type="button" id="aiReset" class="kodee2-reset" title="Reset percakapan" aria-label="Reset percakapan">
        <i class="mdi mdi-square-edit-outline"></i>
        <span>New Chat</span>
      </button>

      <!-- head-actions dibiarkan ada, tapi DISABLE via CSS -->
      <div class="kodee2-head-actions">
        <button class="k2-resize" id="aiResize" aria-label="Perbesar atau perkecil jendela"></button>
        <button class="kodee2-x" id="aiClose" aria-label="Tutup">✕</button>
      </div>
    </div>

    <div class="kodee2-body">
      <div class="kodee2-card">
        <!-- HERO / INTRO: akan hilang setelah pesan pertama terkirim -->
        <div id="k2Empty" class="k2-empty">
          <img src="<?= htmlspecialchars($aiLogo,ENT_QUOTES) ?>" alt="" class="k2-empty-logo">
          <div class="k2-empty-title">Hello, I'm efidi.ai <span class="wave" aria-hidden="true">👋</span></div>
          <div class="k2-empty-sub">What can I help you with today?</div>

          <!-- QUICK ACTIONS -->
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

        <!-- AREA CHAT: awalnya hidden, akan muncul setelah user kirim pesan -->
        <div class="kodee2-chat" id="aiChat" hidden>
          <!-- INTRO BAR YANG MUNCUL SETELAH PESAN PERTAMA -->
          <div id="k2ChatIntro" class="k2-chat-intro" hidden>
            <div class="k2-chat-intro-icon">
              <i class="mdi mdi-flash"></i>
            </div>
            <div class="k2-chat-intro-main">
              <strong>Session started with efidi.ai</strong>
              <span>Tip: kamu bisa bertanya apa saja lalu minta efidi.ai menjelaskan, meringkas, membuat contoh, atau membantu menyusun langkah solusi.</span>
            </div>
          </div>

          <div id="aiList" class="kodee2-list" aria-live="polite" aria-relevant="additions text"></div>
        </div>

        <!-- INPUT AREA -->
        <div class="kodee2-ask">
          <div class="k2-ask-wrap">
            <textarea id="aiMsg" class="k2-input" placeholder="Write your question or instruction here" spellcheck="true"></textarea>
            <button id="aiSend" class="k2-send" type="button" title="Send" aria-label="Send">
              <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true" focusable="false">
                <path d="M22 2L11 13"></path>
                <path d="M22 2l-7 20-4-9-9-4 20-7z"></path>
              </svg>
            </button>
            <button id="aiStop" class="k2-stop" type="button" title="Stop" aria-label="Stop generating" style="display: none;">
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

  const panel = $('#aiPanel');
  const list  = $('#aiList');
  const msg   = $('#aiMsg');
  const btn   = $('#aiSend');
  const chat  = $('#aiChat');
  const empty = $('#k2Empty');
  const resizeBtn = $('#aiResize');
  const stopBtn   = $('#aiStop');
  const chatIntro = $('#k2ChatIntro');
  const resetBtn  = $('#aiReset');

  let isAIProcessing = false;
  let currentAbortController = null;
  let isUserScrolling = false;
  let scrollBottomTimer = null;
  let autoScrollEnabled = true;
  let typingBubble = null;
  let stopRequested = false;

  function getActionUrl(){
    const d = panel?.dataset.action?.trim();
    if (!d) return `${location.origin}/ai/ask.php`;
    if (/^https?:\/\//i.test(d)) return d;
    if (d.startsWith('/'))        return `${location.origin}${d}`;
    return `${location.origin}/${d}`;
  }

  function scrollBottom(force=false){
    if (!list) return;
    if (!force && !autoScrollEnabled) return;
    if (scrollBottomTimer) clearTimeout(scrollBottomTimer);
    scrollBottomTimer = setTimeout(()=>{
      list.scrollTop = list.scrollHeight;
    },50);
  }

  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (_) {}
    document.body.removeChild(ta);
  }

  document.addEventListener('click', async (e) => {
    const b = e.target.closest('.k2-code-copy');
    if (!b) return;
    const id = b.getAttribute('data-code-id');
    const codeEl = document.getElementById(id);
    if (!codeEl) return;
    const text = codeEl.textContent || '';
    try { await navigator.clipboard.writeText(text); }
    catch(_) { fallbackCopy(text); }
    const old = b.textContent;
    b.textContent = 'Copied!';
    b.disabled = true;
    setTimeout(()=>{ b.textContent = old; b.disabled = false; }, 1200);
  });

  /* QUICK CHIP: klik isi textarea + fokus */
  document.addEventListener('click', (e) => {
    const chip = e.target.closest('.k2-empty-chip');
    if (!chip) return;
    const q = chip.getAttribute('data-q') || '';
    if (msg) {
      msg.value = q;
      msg.focus();
    }
  });

  function setupScrollDetection(){
    if (!list) return;
    let t;
    list.addEventListener('scroll', ()=>{
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
      t = setTimeout(()=>{
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
    el.className = 'k2-msg k2-user';
    el.innerHTML = `<div class="k2-bubble">${esc(t)}</div>`;
    list.appendChild(el);
    scrollBottom(true);
  }

  function addTyping(){
    const el = document.createElement('div');
    el.id = 'aiTyping';
    el.className = 'k2-msg k2-bot k2-typing';
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

  function createCodeBlock(code, language) {
    const codeId  = 'code-' + Date.now() + '-' + Math.random().toString(36).slice(2,7);
    const lang    = (language || 'text').toLowerCase();
    const label   = (language || 'text').toUpperCase();

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
    el.className = 'k2-msg k2-bot';
    const bubbleId = 'bubble-' + Date.now();
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
    bubble.dataset.k2Continue = '1';

    const note = document.createElement('div');
    note.className = 'k2-cont-note';
    const label = (noteText && String(noteText).trim())
      ? String(noteText).trim()
      : 'Jawaban cukup panjang dan mungkin masih bisa dilanjutkan.';
    note.innerHTML =
      esc(label) + ' ' +
      '<a href="#" class="k2-cont-link">LANJUTKAN</a>';

    bubble.appendChild(note);

    const link = note.querySelector('.k2-cont-link');
    if (link){
      link.addEventListener('click', (ev)=>{
        ev.preventDefault();
        if (isAIProcessing) return;
        // kirim request lanjutan pakai mode continue
        sendNow({ isContinue:true, text:'Lanjutkan' });
      });
    }
    scrollBottom(true);
  }
  function toggleSendStopButtons(showStop=false){
    if (!btn || !stopBtn) return;
    if (showStop){
      btn.style.display = 'none';
      stopBtn.style.display = 'grid';
      isAIProcessing = true;
      stopBtn.classList.add('k2-stop-pulse');
    } else {
      btn.style.display = 'grid';
      stopBtn.style.display = 'none';
      isAIProcessing = false;
      stopBtn.classList.remove('k2-stop-pulse');
      if (currentAbortController) currentAbortController = null;
    }
  }

  function stopAIGeneration(){
    stopRequested = true;
    if (currentAbortController) currentAbortController.abort();
    if (typingBubble && typeof typingBubble.stopTyping === 'function'){
      typingBubble.stopTyping();
    }
    removeTyping();
    toggleSendStopButtons(false);
    if (btn) btn.disabled = false;
  }

  stopBtn?.addEventListener('click', ev => {
    ev.preventDefault();
    stopAIGeneration();
  });

  /* === RESET CHAT: balik ke halaman pertama Hello, I'm efidi.ai === */
  function resetChat(){
    // hentikan proses AI bila sedang jalan
    stopAIGeneration();

    // bersihkan daftar pesan
    if (list) {
      list.innerHTML = '';
    }

    // sembunyikan intro bar & area chat
    if (chatIntro) chatIntro.setAttribute('hidden','');
    if (chat)      chat.setAttribute('hidden','');

    // tampilkan kembali hero / empty state
    if (empty) empty.removeAttribute('hidden');

    // hapus status has-chat → tombol reset ikut hilang
    if (panel) panel.classList.remove('has-chat');

    // kosongkan input
    if (msg) {
      msg.value = '';
      msg.focus();
    }

    // reset scroll
    autoScrollEnabled = true;
    scrollBottom(true);
  }

  resetBtn?.addEventListener('click', e => {
    e.preventDefault();
    resetChat();
  });

  function typeWriterWithCursor(element, text, speed=15){
    let resolveFn;
    const p = new Promise(r=>{ resolveFn = r; });
    let i = 0;
    let isTyping = true;
    const cursor = element.querySelector('.k2-cursor');

    function finish(appendTruncatedNote=false){
      if (cursor) cursor.remove();
      applySyntaxHighlighting(element);
      if (appendTruncatedNote){
        element.insertAdjacentHTML(
          'beforeend',
          '<br><em style="color:#94a3b8; font-size:12px">[Chat dihentikan]</em>'
        );
      }
      isTyping = false;
      autoScrollEnabled = true;
      resolveFn();
    }

    function type(){
      if (!isTyping) return;
      if (i < text.length){
        const char = text.charAt(i);

        // Deteksi blok ```code```
        if (char === '`' && text.substring(i, i+3) === '```'){
          const endIndex = text.indexOf('```', i+3);
          if (endIndex !== -1){
            const codeContent = text.substring(i+3, endIndex);
            const languageMatch = codeContent.match(/^(\w+)\n/);
            const language = languageMatch ? languageMatch[1] : 'text';
            const code = languageMatch ? codeContent.substring(languageMatch[0].length) : codeContent;
            const block = createCodeBlock(code, language);
            cursor.insertAdjacentHTML('beforebegin', block);
            i = endIndex + 3;
            if (autoScrollEnabled) scrollBottom(true);
            return setTimeout(type, speed);
          }
        }

        if (char === '\n'){
          cursor.insertAdjacentHTML('beforebegin', '<br>');
        } else {
          cursor.insertAdjacentHTML('beforebegin', esc(char));
        }
        i++;
        if (autoScrollEnabled) scrollBottom();
        setTimeout(type, speed);
      } else {
        finish(false);
      }
    }

    element.stopTyping = function(){
      isTyping = false;
      finish(true);
    };

    setTimeout(type, 100);
    return p;
  }

  /* ====== HIGHLIGHTER & LINKIFY UTAMA (multi-bahasa kode) ====== */

  function highlightCode(code, lang) {
    lang = (lang || '').toLowerCase();

    if (lang === 'html' || lang === 'xml' || lang === 'xhtml') {
      return highlightHtml(code);
    }

    const cfg = {
      allowDollarVars: lang === 'php',
      noBacktickStrings: false,
      keywords: [],
      types: [],
      builtins: [],
      constants: [],
      booleans: ['true', 'false']
    };

    if (lang === 'php') {
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
      cfg.builtins = [
        'var_dump','print_r','count','strlen','substr','array_merge','in_array',
        'json_encode','json_decode','explode','implode','trim','strtoupper',
        'strtolower','header','time','date'
      ];
      cfg.constants = ['null'];
    } else if (['js','javascript','ts','typescript','node'].includes(lang)) {
      cfg.keywords = [
        'if','else','for','while','do','switch','case','default','break','continue',
        'return','function','const','let','var','new','in','of','instanceof','try',
        'catch','finally','throw','class','extends','super','import','export',
        'default','await','async','yield','this','delete','typeof','void'
      ];
      cfg.types = [
        'string','number','boolean','object','symbol','bigint','any','unknown',
        'never','undefined','null'
      ];
      cfg.builtins = [
        'console','window','document','Array','Object','String','Number','Boolean',
        'Promise','Math','Date','JSON','Map','Set','WeakMap','WeakSet','RegExp',
        'Error','TypeError','SyntaxError','ReferenceError'
      ];
      cfg.constants = ['NaN','Infinity','undefined','null'];
    } else if (lang === 'sql') {
      cfg.keywords = [
        'select','from','where','group','by','order','limit','insert','into',
        'update','delete','join','left','right','inner','outer','on','create',
        'table','alter','drop','index','view','having','distinct','as','and',
        'or','not','like','in','between','is','null','primary','key','foreign',
        'default','auto_increment','values'
      ];
    } else if (['bash','sh','shell','zsh'].includes(lang)) {
      cfg.keywords = [
        'if','then','else','elif','fi','for','in','do','done','case','esac',
        'while','until','function','select','time'
      ];
      cfg.builtins = [
        'echo','cd','ls','cat','grep','awk','sed','find','export','readonly',
        'local','read','printf'
      ];
    } else if (['py','python'].includes(lang)) {
      cfg.keywords = [
        'if','elif','else','for','while','break','continue','return','def','class',
        'try','except','finally','raise','import','from','as','pass','yield',
        'with','lambda','global','nonlocal','assert','del','in','is','and','or','not'
      ];
      cfg.types = [
        'int','float','str','bool','list','dict','set','tuple','object','None'
      ];
      cfg.builtins = [
        'print','len','range','enumerate','zip','map','filter','open','sorted',
        'sum','min','max','any','all','isinstance','type','dir','vars'
      ];
      cfg.constants = ['None'];
      cfg.booleans = ['True','False'];
    } else {
      // generic C-like fallback
      cfg.keywords = [
        'if','else','for','while','do','switch','case','break','continue','return',
        'struct','enum','union','typedef','sizeof','volatile','const','static','goto'
      ];
      cfg.types = [
        'int','long','short','char','float','double','void','signed','unsigned',
        'bool','size_t'
      ];
    }

    return genericHighlight(code, cfg);
  }

  function genericHighlight(code, cfg) {
    const kwSet       = new Set((cfg.keywords  || []).map(s => s.toLowerCase()));
    const typeSet     = new Set((cfg.types     || []).map(s => s.toLowerCase()));
    const builtinSet  = new Set((cfg.builtins  || []).map(s => s.toLowerCase()));
    const constSet    = new Set((cfg.constants || []).map(s => s.toLowerCase()));
    const boolWords   = (cfg.booleans || ['true','false']).map(s => s.toLowerCase());

    const isIdentStart = ch => /[A-Za-z_$]/.test(ch);
    const isIdentPart  = ch => /[A-Za-z0-9_$]/.test(ch);
    const isDigit      = ch => /[0-9]/.test(ch);

    function formatTagSegment(tagText) {
      let res = '';
      const tagMatch = /^<\/?([a-zA-Z0-9:-]+)/.exec(tagText);
      let tagName = tagMatch ? tagMatch[1] : '';
      let head = '';
      let rest = tagText;

      if (tagMatch) {
        head = tagText.slice(0, tagMatch.index + 1); // "<" atau "</"
        rest = tagText.slice(tagMatch.index + 1);
        rest = rest.slice(tagName.length); // sisakan setelah nama tag
      }

      // head ("<" / "</")
      if (head) res += '<span class="angle">' + esc(head) + '</span>';
      // nama tag
      if (tagName) res += '<span class="tag">' + esc(tagName) + '</span>';

      // parse atribut + sisa hingga '>'
      let i = 0;
      const n = rest.length;
      while (i < n) {
        const ch = rest[i];

        if (ch === '"' || ch === "'") {
          const quote = ch;
          let j = i + 1;
          while (j < n && rest[j] !== quote) j++;
          j++;
          const val = rest.slice(i, j);
          res += '<span class="string">' + esc(val) + '</span>';
          i = j;
          continue;
        }

        if (/\s/.test(ch)) {
          let j = i + 1;
          while (j < n && /\s/.test(rest[j])) j++;
          res += esc(rest.slice(i, j));
          i = j;
          continue;
        }

        if (ch === '=') {
          res += '<span class="equals">' + esc(ch) + '</span>';
          i++;
          continue;
        }

        if (ch === '>') {
          res += '<span class="angle">' + esc(ch) + '</span>';
          i++;
          continue;
        }

        // nama atribut atau karakter lain
        let j = i + 1;
        while (j < n && !/[\s=>]/.test(rest[j])) j++;
        const name = rest.slice(i, j);
        res += '<span class="attribute">' + esc(name) + '</span>';
        i = j;
      }

      return res;
    }

    let out = '';
    const n = code.length;
    let i = 0;

    while (i < n) {
      const ch = code[i];

      // newline
      if (ch === '\n') {
        out += '\n';
        i++;
        continue;
      }

      // single-line comment //...
      if (ch === '/' && code[i+1] === '/') {
        let j = i + 2;
        while (j < n && code[j] !== '\n') j++;
        const c = code.slice(i, j);
        out += '<span class="comment">' + esc(c) + '</span>';
        i = j;
        continue;
      }

      // multi-line comment /* ... */
      if (ch === '/' && code[i+1] === '*') {
        let j = code.indexOf('*/', i + 2);
        if (j === -1) j = n;
        else j += 2;
        const c = code.slice(i, j);
        out += '<span class="comment">' + esc(c) + '</span>';
        i = j;
        continue;
      }

      // hash comment (#) – shell / php / python
      if (ch === '#') {
        let j = i + 1;
        while (j < n && code[j] !== '\n') j++;
        const c = code.slice(i, j);
        out += '<span class="comment">' + esc(c) + '</span>';
        i = j;
        continue;
      }

      // HTML-like tag (untuk <html>, <div>, dll di dalam PHP/JS)
      if (ch === '<') {
        let j = i + 1;
        // skip closing slash
        if (code[j] === '/') j++;
        // jangan treat <!DOCTYPE / <!-- dsb sebagai tag biasa di sini
        if (code[j] && /[A-Za-z]/.test(code[j])) {
          const end = code.indexOf('>', j);
          if (end !== -1) {
            const tagText = code.slice(i, end + 1);
            out += formatTagSegment(tagText);
            i = end + 1;
            continue;
          }
        }
      }

      // strings "..." '...' `...`
      if (ch === '"' || ch === "'" || (ch === '`' && !cfg.noBacktickStrings)) {
        const quote = ch;
        let j = i + 1;
        while (j < n) {
          const cj = code[j];
          if (cj === '\\') { j += 2; continue; }
          if (cj === quote) { j++; break; }
          j++;
        }
        const s = code.slice(i, j);
        out += '<span class="string">' + esc(s) + '</span>';
        i = j;
        continue;
      }

      // number
      if (isDigit(ch)) {
        let j = i + 1;
        while (j < n && /[0-9_.xXa-fA-F]/.test(code[j])) j++;
        const num = code.slice(i, j);
        out += '<span class="number">' + esc(num) + '</span>';
        i = j;
        continue;
      }

      // annotation / decorator @Route, @Test, dll.
      if (ch === '@') {
        let j = i + 1;
        while (j < n && /[A-Za-z0-9_.]/.test(code[j])) j++;
        const ann = code.slice(i, j);
        out += '<span class="annot">' + esc(ann) + '</span>';
        i = j;
        continue;
      }

      // PHP variable: $var
      if (ch === '$' && cfg.allowDollarVars) {
        let j = i + 1;
        while (j < n && isIdentPart(code[j])) j++;
        const v = code.slice(i, j);
        out += '<span class="variable">' + esc(v) + '</span>';
        i = j;
        continue;
      }

      // identifier / keyword / builtin / type / bool / function / variable
      if (isIdentStart(ch)) {
        let j = i + 1;
        while (j < n && isIdentPart(code[j])) j++;
        const id = code.slice(i, j);
        const low = id.toLowerCase();

        let cls = '';

        if (boolWords.includes(low)) {
          cls = 'boolean';
        } else if (kwSet.has(low)) {
          cls = 'keyword';
        } else if (builtinSet.has(low)) {
          cls = 'builtin';
        } else if (typeSet.has(low)) {
          cls = 'type';
        } else if (constSet.has(low)) {
          cls = 'constant';
        } else {
          // deteksi function: nama diikuti "("
          let k = j;
          while (k < n && /\s/.test(code[k])) k++;
          if (code[k] === '(') {
            cls = 'function';
          }
        }

        if (!cls) cls = 'variable';
        out += '<span class="' + cls + '">' + esc(id) + '</span>';

        i = j;
        continue;
      }

      // braces / parens / brackets
      if ('(){}[]'.includes(ch)) {
        const cls =
          (ch === '(' || ch === ')') ? 'paren'   :
          (ch === '{' || ch === '}') ? 'brace'   :
                                       'bracket';
        out += '<span class="' + cls + '">' + esc(ch) + '</span>';
        i++;
        continue;
      }

      // punctuations
      if (',;:.'.includes(ch)) {
        const cls =
          (ch === ',') ? 'comma'     :
          (ch === ';') ? 'semicolon' :
          (ch === ':') ? 'colon'     :
                         'dot';
        out += '<span class="punct ' + cls + '">' + esc(ch) + '</span>';
        i++;
        continue;
      }

      // operators
      if ('+-*/%=&|!<>^~?'.includes(ch)) {
        let j = i + 1;
        while (j < n && '+-*/%=&|!<>^~?'.includes(code[j])) j++;
        const op = code.slice(i, j);
        out += '<span class="operator">' + esc(op) + '</span>';
        i = j;
        continue;
      }

      // default (spasi, tab, dll)
      out += esc(ch);
      i++;
    }

    return out;
  }

  function highlightHtml(code) {
    const commentRe = /<!--[\s\S]*?-->/g;
    let out = '';
    let last = 0;
    let m;

    function formatTag(tagText) {
      let res = '';
      const tagMatch = /^<\/?([a-zA-Z0-9:-]+)/.exec(tagText);
      let tagName = tagMatch ? tagMatch[1] : '';
      let head = '';
      let rest = tagText;

      if (tagMatch) {
        head = tagText.slice(0, tagMatch.index + 1); // "<" atau "</"
        rest = tagText.slice(tagMatch.index + 1);
        rest = rest.slice(tagName.length); // sisakan setelah nama tag
      }

      // head ("<" / "</")
      if (head) res += '<span class="angle">' + esc(head) + '</span>';
      // nama tag
      if (tagName) res += '<span class="tag">' + esc(tagName) + '</span>';

      // parse atribut + sisa hingga '>'
      let i = 0;
      const n = rest.length;
      while (i < n) {
        const ch = rest[i];

        if (ch === '"' || ch === "'") {
          const quote = ch;
          let j = i + 1;
          while (j < n && rest[j] !== quote) j++;
          j++;
          const val = rest.slice(i, j);
          res += '<span class="string">' + esc(val) + '</span>';
          i = j;
          continue;
        }

        if (/\s/.test(ch)) {
          let j = i + 1;
          while (j < n && /\s/.test(rest[j])) j++;
          res += esc(rest.slice(i, j));
          i = j;
          continue;
        }

        if (ch === '=') {
          res += '<span class="equals">' + esc(ch) + '</span>';
          i++;
          continue;
        }

        if (ch === '>') {
          res += '<span class="angle">' + esc(ch) + '</span>';
          i++;
          continue;
        }

        // nama atribut atau karakter lain
        let j = i + 1;
        while (j < n && !/[\s=>]/.test(rest[j])) j++;
        const name = rest.slice(i, j);
        res += '<span class="attribute">' + esc(name) + '</span>';
        i = j;
      }

      return res;
    }

    function processSegment(seg) {
      const doctypeRe = /<!DOCTYPE[^>]*>/gi;
      const entityRe  = /&[a-zA-Z0-9#]+;/g;
      let result = '';
      let idx = 0;
      let tm;

      // pertama pisahkan tag <...>
      const tagRe = /<\/?[a-zA-Z][^>]*>/g;

      while ((tm = tagRe.exec(seg)) !== null) {
        // sebelum tag: highlight doctype / entity secara ringan
        if (tm.index > idx) {
          let plain = seg.slice(idx, tm.index);

          // doctype
          plain = plain.replace(doctypeRe, d =>
            '<span class="html-doctype">' + esc(d) + '</span>'
          );

          // entity
          plain = plain.replace(entityRe, ent =>
            '<span class="entity">' + esc(ent) + '</span>'
          );

          result += plain ? plain.split('').map(ch => esc(ch)).join('') : '';
        }

        result += formatTag(tm[0]);
        idx = tagRe.lastIndex;
      }

      if (idx < seg.length) {
        let plain = seg.slice(idx);

        plain = plain.replace(doctypeRe, d =>
          '<span class="html-doctype">' + esc(d) + '</span>'
        );
        plain = plain.replace(entityRe, ent =>
          '<span class="entity">' + esc(ent) + '</span>'
        );

        result += plain ? plain.split('').map(ch => esc(ch)).join('') : '';
      }

      return result;
    }

    while ((m = commentRe.exec(code)) !== null) {
      if (m.index > last) {
        out += processSegment(code.slice(last, m.index));
      }
      out += '<span class="comment">' + esc(m[0]) + '</span>';
      last = commentRe.lastIndex;
    }

    if (last < code.length) {
      out += processSegment(code.slice(last));
    }
    return out;
  }

  // ====== LINKIFY PLAIN TEXT (URL & EMAIL) DI DALAM BUBBLE ======
  function linkifyText(text) {
    const pattern = /((?:https?:\/\/|www\.)\S+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/g;
    let result = '';
    let last = 0;
    let m;

    while ((m = pattern.exec(text)) !== null) {
      if (m.index > last) {
        result += esc(text.slice(last, m.index));
      }
      const token = m[0];

      if (token.includes('@') && !/^https?:\/\//i.test(token)) {
        result += '<a href="mailto:' + esc(token) + '" class="k2-link">' + esc(token) + '</a>';
      } else {
        const href = /^https?:\/\//i.test(token) ? token : 'https://' + token;
        result += '<a href="' + esc(href) + '" class="k2-link" target="_blank" rel="noopener noreferrer">' +
                  esc(token) + '</a>';
      }

      last = m.index + token.length;
    }

    if (last < text.length) {
      result += esc(text.slice(last));
    }

    return result;
  }

  function linkifyElement(rootEl) {
    if (!rootEl) return;

    const walker = document.createTreeWalker(
      rootEl,
      NodeFilter.SHOW_TEXT,
      null,
      false
    );

    const pattern = /((?:https?:\/\/|www\.)\S+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/;

    const nodes = [];
    let node;
    while ((node = walker.nextNode())) {
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
      while (span.firstChild) frag.appendChild(span.firstChild);
      if (n.parentNode) n.parentNode.replaceChild(frag, n);
    });
  }

  // ==== FUNGSI UTAMA: highlight kode + linkify text ====
  function applySyntaxHighlighting(root = document) {
    // 1) syntax highlight untuk setiap .k2-code-content
    const blocks = root.querySelectorAll('.k2-code-content');
    blocks.forEach(block => {
      if (block.dataset.k2Hl === '1') return;
      block.dataset.k2Hl = '1';
      const lang = (block.dataset.lang || '').toLowerCase();
      const raw  = block.textContent || '';
      const html = highlightCode(raw, lang);
      block.innerHTML = html;
    });

    // 2) linkify URL & email di semua bubble (di luar code block)
    const bubbles = root.querySelectorAll('.k2-bubble');
    bubbles.forEach(bubble => {
      if (bubble.dataset.k2Link === '1') return;
      linkifyElement(bubble);
      bubble.dataset.k2Link = '1';
    });
  }

  // Panggil awal + observe DOM yang berubah
  applySyntaxHighlighting(document);
  new MutationObserver(muts => {
    let need = false;
    muts.forEach(m => m.addedNodes && m.addedNodes.forEach(n => {
      if (n.nodeType === 1 && (
        n.matches?.('.k2-code-content, .k2-bubble') ||
        n.querySelector?.('.k2-code-content, .k2-bubble')
      )) need = true;
    }));
    if (need) applySyntaxHighlighting(document);
  }).observe(document.body, { childList: true, subtree: true });

  async function sendNow(opts = {}){
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

    stopRequested = false;

    // === TANDAKAN CHAT SUDAH DIMULAI →
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

    let reply = '';
    let finishReason = '';
    let noteText = '';

    try{
      const params = new URLSearchParams();
      params.set('q', q);
      if (isContinue) params.set('continue', '1');

      const res = await fetch(getActionUrl(), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: params.toString(),
        credentials: 'same-origin',
        signal: currentAbortController.signal
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
      try { await bubble.typingDone; }
      finally { toggleSendStopButtons(false); }

      // Kalau server bilang finish_reason = "length" → tampilkan "LANJUTKAN"
      if (finishReason === 'length'){
        attachContinueLink(bubble, noteText);
      }
    } else {
      toggleSendStopButtons(false);
    }

    // Kalau panggilan biasa, kosongkan textarea
    if (!isContinue && msg){
      msg.value = '';
      setTimeout(()=>msg.focus(),100);
    }
    setTimeout(scrollBottom,150);
  }

  btn?.addEventListener('click', ()=>{
    if (!isAIProcessing) sendNow();
  });

  msg?.addEventListener('keydown', e=>{
    if (e.key === 'Enter' && !e.shiftKey){
      e.preventDefault();
      if (!isAIProcessing) sendNow();
    }
  });

  // Resize & close tidak dipakai di fullscreen
  resizeBtn?.addEventListener('click', ev=>{
    ev.preventDefault();
  });

  document.addEventListener('keydown', e=>{
    if (e.key === 'Escape'){
      // tidak melakukan apa-apa di fullscreen
    }
  });

  window.addEventListener('resize', ()=>{ scrollBottom(); });

  setTimeout(()=>{ setupScrollDetection(); }, 1000);
})();
</script>