<?php
// pdf_viewer.php
// Viewer sederhana untuk file BAI, dipakai di iframe modal thesearching_bot

$rel = isset($_GET['f']) ? $_GET['f'] : '';
$rel = str_replace("\0", '', $rel);
$rel = trim($rel);

if ($rel === '') {
  http_response_code(400);
  echo 'File tidak valid.';
  exit;
}

// kalau user kirim absolut (https://efidi.montaklo.id/bot/file/xxx.pdf)
// ambil path-nya saja
$parsed = parse_url($rel);
if (!empty($parsed['scheme'])) {
  $path = $parsed['path'] ?? '';
} else {
  $path = $rel;
}

// amankan: hilangkan ../ dan leading slash
$path = ltrim($path, '/');
$path = preg_replace('~\.\.+~', '', $path); // buang .. kalau ada

// OPTIONAL: pastikan tetap di folder bot/file
if (strpos($path, 'bot/file/') !== 0) {
  http_response_code(400);
  echo 'Akses file tidak diizinkan.';
  exit;
}

$fileName = basename($path);

// buat URL absolut untuk file asli (dipakai render & download)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$downloadUrl = $scheme . '://' . $host . '/' . $path;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Viewer BAI</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <style>
    html,body{
      margin:0;
      padding:0;
      height:100%;
      background:#fefeffff;
      color: #060606ff;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
    }
    .wrap{
      height:100%;
      display:flex;
      flex-direction:column;
    }
    .viewer-header{
      padding:6px 10px;
      font-size:12px;
      background: #fefeffff;
      border-bottom:1px solid #1f2937;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
    }
    .vh-title{
      flex:1;
      min-width:0;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    .viewer-body{
      flex:1;
      overflow:auto;
      background: #fdfdffff;
      padding:8px 0;
      display:flex;
      justify-content:center;
    }
    #pdfContainer{
      width:100%;
      max-width:900px;
      box-sizing:border-box;
      padding:0 8px; /* biar ada jarak sedikit dari sisi modal */
    }
    .pdf-page{
      background:#ffffff;
      margin:0 auto 8px;
      box-shadow:0 8px 24px rgba(15, 23, 42, 0.24);
      display:block;

      /* PENTING: bikin lebar kertas PAS lebar container/modal */
      width:100%;
      height:auto;
      max-width:100%;
    }
    .loading{
      font-size:13px;
      padding:20px;
      text-align:center;
      color:#9ca3af;
    }
    .error{
      font-size:13px;
      padding:20px;
      text-align:center;
      color:#fecaca;
    }
  </style>

  <!-- Library pdf.js dari CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
  <script>
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
  </script>
</head>
<body>
<div class="wrap">
  <div class="viewer-header">
    <span class="vh-title">BAI: <?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?></span>
  </div>


  <div class="viewer-body">
    <div id="pdfContainer">
      <div id="pdfStatus" class="loading">Memuat dokumen…</div>
    </div>
  </div>
</div>

<script>
  (function () {
    // URL absolut ke file PDF asli
    const pdfUrl   = <?= json_encode($downloadUrl) ?>;
    const container = document.getElementById('pdfContainer');
    const statusEl  = document.getElementById('pdfStatus');

  

    // === 2) Render PDF pakai pdf.js, sumbernya tetap PDF ASLI ===
    pdfjsLib.getDocument(pdfUrl).promise.then(function (pdf) {
      statusEl.remove();

      const total = pdf.numPages;
      for (let pageNum = 1; pageNum <= total; pageNum++) {
        pdf.getPage(pageNum).then(function (page) {
          const viewport = page.getViewport({ scale: 1.2 });

          const canvas  = document.createElement('canvas');
          canvas.className = 'pdf-page';
          const context = canvas.getContext('2d');

          canvas.height = viewport.height;
          canvas.width  = viewport.width;
          container.appendChild(canvas);

          return page.render({
            canvasContext: context,
            viewport: viewport
          }).promise;
        });
      }
    }).catch(function (err) {
      statusEl.className = 'error';
      statusEl.textContent = 'Gagal memuat PDF: ' + err.message;
      console.error(err);
    });
  })();
</script>
</body>
</html>
