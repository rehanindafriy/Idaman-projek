<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/config.php';

session_start();

// Auth Check via Database (tabel akses_login)
$u   = $_GET['u'] ?? '';
$cid = $_GET['cid'] ?? '';
$auth_script = '';

if ($u && $cid) {
    // Cek apakah chat_id terdaftar sebagai admin aktif di database
    $stmt = $pdo->prepare("SELECT id_akses, nama, username FROM akses_login WHERE chat_id = ? AND role = 'admin' AND status = 'active' LIMIT 1");
    $stmt->execute([$cid]);
    $admin = $stmt->fetch();

    if ($admin) {
        // Set session valid
        $_SESSION['admin_logged_in'] = true;
        
        // Bersihkan URL dari parameter get agar rapi
        $auth_script = "
            window.history.replaceState({}, document.title, window.location.pathname);
        ";
    } else {
        die("<h3 style='color:red; font-family:sans-serif; text-align:center; margin-top:50px;'>❌ Akses Ditolak: Anda bukan Admin terdaftar atau akun Anda tidak aktif.</h3>");
    }
} else {
    // Jika tidak ada parameter GET, pastikan session aktif (untuk kasus reload page)
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        die("<h3 style='color:red; font-family:sans-serif; text-align:center; margin-top:50px;'>❌ Akses Ditolak: Sesi tidak valid. Silakan buka web ini melalui tombol di Telegram.</h3>");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel Admin Bot</title>
    <script>
        <?php echo $auth_script; ?>
    </script>
    <!-- PWA Settings (Removed to fix iOS Webview Black Screen) -->
    <meta name="theme-color" content="#4f46e5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Cropper.js -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: rgba(79, 70, 229, 0.08);
            --accent: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f1f5f9;
            --surface: #ffffff;
            --text: #1e293b;
            --text-light: #64748b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --radius: 16px;
            --radius-sm: 10px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.08), 0 8px 10px -6px rgba(0,0,0,0.04);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background-color: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased; }
        ::-webkit-scrollbar { height: 6px; width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }

        .input-group { margin-bottom: 16px; text-align: left; }
        .input-group label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--text); }
        input, textarea { 
            width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); 
            font-size: 14px; transition: var(--transition); background: var(--surface); color: var(--text);
        }
        input:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        input::placeholder, textarea::placeholder { color: var(--text-muted); }
        button { 
            padding: 10px 20px; background: var(--primary); color: white; border: none; 
            border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; cursor: pointer; 
            transition: var(--transition); display: inline-flex; align-items: center; gap: 6px;
        }
        button:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        button:active { transform: translateY(0) scale(0.98); box-shadow: none; }

        #dashboard-screen { max-width: 1200px; margin: 0 auto; padding: 24px 20px; }
        header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 28px; padding: 18px 24px; 
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            border-radius: var(--radius); box-shadow: 0 4px 20px rgba(79, 70, 229, 0.25);
        }
        header h2 { font-size: 18px; color: white; font-weight: 700; }
        header h2 img { filter: brightness(0) invert(1); }

        .tabs { display: flex; overflow-x: auto; gap: 6px; margin-bottom: 24px; padding: 4px; background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow-sm); }
        .tab { 
            padding: 10px 18px; background: transparent; border: none; 
            border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; 
            color: var(--text-light); transition: var(--transition);
        }
        .tab:hover { background: var(--primary-light); color: var(--primary); }
        .tab.active { background: var(--primary); color: white; box-shadow: 0 2px 8px rgba(79, 70, 229, 0.35); }
        
        .content-card { 
            background: var(--surface); border-radius: var(--radius); padding: 24px; 
            box-shadow: var(--shadow-md); margin-bottom: 24px; border: 1px solid var(--border-light);
        }
        .content-card h3 { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 16px; }
        .url-editor { display: flex; gap: 8px; margin-bottom: 24px; align-items: stretch; }
        .url-editor input { flex: 1; font-size: 13px; padding: 10px 14px; }
        .btn-save { width: auto; padding: 10px 18px; font-size: 13px; }
        .btn-tool { 
            width: auto; background: var(--surface); color: var(--text); padding: 8px 14px; font-size: 13px; 
            border: 1.5px solid var(--border); font-weight: 500;
        }
        .btn-tool:hover { background: var(--bg); border-color: var(--text-muted); transform: translateY(-1px); }
        
        .img-container {
            width: 100%; height: 60vh;
            background: linear-gradient(145deg, #e2e8f0 0%, #cbd5e1 100%);
            border-radius: var(--radius-sm); border: 2px dashed var(--border);
            overflow: hidden; margin-bottom: 16px;
        }
        .img-container img { display: block; max-width: 100%; }
        
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .spinner {
            display: inline-block; width: 16px; height: 16px;
            border: 2.5px solid rgba(255,255,255,0.3); border-radius: 50%;
            border-top-color: #fff; animation: spin 0.8s linear infinite;
            margin-right: 6px; vertical-align: middle;
        }

        .crop-controls { margin-top: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .crop-info { 
            font-family: 'SF Mono', monospace; background: var(--bg); padding: 8px 14px; 
            border-radius: 8px; font-size: 12px; color: var(--text-muted); border: 1px solid var(--border);
        }
        .sub-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
        .sub-tab { 
            padding: 7px 14px; font-size: 12px; font-weight: 600; 
            background: var(--bg); border: 1.5px solid var(--border); 
            border-radius: 8px; cursor: pointer; transition: var(--transition); color: var(--text-light);
        }
        .sub-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .sub-tab.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3); }

        #toast { 
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(100px); 
            background: linear-gradient(135deg, #10b981, #059669); color: white; 
            padding: 12px 28px; border-radius: 50px; 
            font-weight: 600; font-size: 13px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); 
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1); z-index: 9999;
        }
        #toast.show { transform: translateX(-50%) translateY(0); }

        @media (max-width: 600px) {
            #dashboard-screen { padding: 16px 12px; }
            header { padding: 14px 16px; margin-bottom: 20px; }
            header h2 { font-size: 15px; }
            .url-editor { flex-direction: column; }
            .btn-save { width: 100%; justify-content: center; }
            .crop-controls { flex-direction: column; align-items: stretch; }
            .crop-info { text-align: center; }
            .tabs { gap: 4px; padding: 3px; }
            .tab { padding: 8px 14px; font-size: 12px; }
        }
        
        .modal-overlay { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); 
            z-index: 1000; justify-content: center; align-items: center; 
        }
        .modal { 
            background: var(--surface); padding: 28px; border-radius: var(--radius); 
            width: 100%; max-width: 480px; box-shadow: var(--shadow-lg);
            animation: modalIn 0.25s ease-out;
        }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal h3 { margin-bottom: 20px; font-size: 17px; font-weight: 700; }
        .modal .input-group { margin-bottom: 14px; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; }
        .btn-cancel { background: var(--bg); color: var(--text-light); border: 1.5px solid var(--border); }
        .btn-cancel:hover { background: var(--border); color: var(--text); transform: translateY(-1px); }

        .metadata-panel { 
            display: flex; flex-direction: column; gap: 14px; margin-top: 20px; 
            border-top: 1.5px solid var(--border-light); padding-top: 20px; 
        }
        .metadata-panel label { font-size: 11px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em; }
        .metadata-panel input, .metadata-panel textarea { padding: 10px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; }
        .metadata-panel textarea { resize: vertical; min-height: 64px; }
    </style>
</head>
<body>

    <!-- DASHBOARD SCREEN -->
    <div id="dashboard-screen">
        <header>
            <h2 style="display: flex; align-items: center; gap: 10px;">
                <img src="assets/logobotidaman.png" alt="Logo" style="height: 32px; width: auto;">
                Bot Idaman Configurator
            </h2>
        </header>

        <div class="tabs" id="main-tabs">
            <!-- Tabs generated dynamically -->
        </div>

        <div class="content-card">
            <div class="report-actions" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1.5px solid var(--border-light); align-items: center;">
                <span style="font-size: 14px; font-weight: 600; color: var(--text); margin-right: auto;">Aksi Laporan Aktif:</span>
                <button class="btn-save" style="background: #10b981;" onclick="showAddModal()">➕ Tambah Laporan Baru</button>
                <button class="btn-save" style="background: var(--warning);" onclick="editReportName()">✏️ Edit Nama Laporan</button>
                <button class="btn-save" style="background: var(--danger);" onclick="deleteReport()">🗑️ Hapus Laporan</button>
            </div>

            <h3 style="margin-bottom: 12px;">Pengaturan Link (URL)</h3>
            <div class="url-editor">
                <input type="text" id="url-input" placeholder="https://lookerstudio.google.com/...">
                <button class="btn-save" onclick="saveUrl()">💾 Simpan URL</button>
            </div>
            
            <div class="input-group" style="margin-bottom: 24px;">
                <label>Jadwal Khusus Laporan Ini (WIB) - Opsional, biarkan kosong jika ikut jadwal utama</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="report-schedules" placeholder="Contoh: 09:00, 15:30">
                    <button class="btn-save" style="background: #8b5cf6;" onclick="saveSchedules()">⏰ Simpan Jadwal</button>
                </div>
                <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">*Pisahkan dengan koma jika lebih dari satu jadwal (Misal: 08:00, 12:00). Format harus HH:MM</small>
            </div>
            
            <h3 style="margin-bottom: 12px;">Pengaturan Visual Cropper</h3>
            <div class="sub-tabs" id="crop-tabs">
                <!-- Sub tabs generated dynamically -->
            </div>
            
            <div style="display: flex; justify-content: flex-start; align-items: center; margin-bottom: 10px;">
                <p style="font-size: 12px; color: var(--text-muted); margin: 0;">💡 Klik <b style='color: var(--text-light);'>Simpan URL</b> untuk mengambil gambar terbaru secara Real-Time</p>
            </div>
            <div class="img-container">
                <img id="bg-image" src="" alt="Latar Belakang">
            </div>

            <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div class="crop-info" id="crop-info">X: 0.00 | Y: 0.00 | W: 0.00 | H: 0.00</div>
                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <button class="btn-tool" onclick="window.cropperInstance.zoom(0.1)" title="Zoom In">🔍+</button>
                    <button class="btn-tool" onclick="window.cropperInstance.zoom(-0.1)" title="Zoom Out">🔍−</button>
                    <button class="btn-save" style="background: var(--success); padding: 8px 18px; font-size: 13px;" onclick="saveCrop()">💾 Simpan Crop</button>
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-start; align-items: center; margin-top: 12px;">
                <p style="font-size: 12px; color: var(--text-muted); margin: 0; line-height: 1.5;">
                    📌 Geser kotak biru di atas menggunakan jari atau mouse untuk menentukan area yang akan dikirim ke Telegram.
                </p>
            </div>
            
            <div class="metadata-panel">
                <label>Nama Crop (Tab)</label>
                <input type="text" id="crop-name" placeholder="Misal: Tabel Detail Bawah">

                <label>Pesan Telegram (Caption)</label>
                <textarea id="crop-caption" placeholder="Ketik caption laporan di sini..."></textarea>
                
                <label>ID Grup Target (Pisahkan dengan koma jika lebih dari satu)</label>
                <input type="text" id="crop-targets" placeholder="-5430717889, -123456789">
                <small style="color: #ef4444; font-size: 11px; margin-bottom: 8px; display: block;">*Dapatkan ID Grup dari URL web Telegram. https://web.telegram.org/k/#-5430717889, pisahkan dengan koma (Contoh: -5430717889, -67890)</small>
                
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                    <button class="btn-save" style="background: var(--danger); width: max-content; margin: 0;" onclick="deleteCrop()">🗑️ Hapus Crop Ini</button>
                    <button class="btn-save" style="background: #3b82f6; width: max-content; margin: 0;" onclick="saveMetadata()">💾 Simpan Metadata</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-add">
        <div class="modal">
            <h3>Tambah Laporan Baru</h3>
            <div class="input-group">
                <label>Nama Laporan (Tab)</label>
                <input type="text" id="add-name" placeholder="Misal: Data Sales harian">
            </div>
            <div class="input-group">
                <label>Link Dashboard / Spreadsheet</label>
                <input type="text" id="add-url" placeholder="https://...">
            </div>
            <div class="input-group">
                <label>Caption Pesan Telegram</label>
                <input type="text" id="add-caption" placeholder="Laporan Harian Terbaru">
            </div>
            <div class="input-group">
                <label>ID Grup Target</label>
                <input type="text" id="add-targets" placeholder="-5430717889">
                <small style="color: #ef4444; font-size: 11px; margin-top: 4px; display: block;">*Dapatkan ID Grup dari URL web Telegram. Untuk multi-grup, pisahkan dengan koma (Contoh: -12345, -67890)</small>
            </div>
            <div class="modal-buttons">
                <button class="btn-cancel" style="width:auto; padding: 10px 20px;" onclick="document.getElementById('modal-add').style.display='none'">Batal</button>
                <button class="btn-save" style="width:auto; padding: 10px 20px;" onclick="createNewReport()">Simpan</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-add-crop">
        <div class="modal">
            <h3>Tambah Crop (Area Potongan Baru)</h3>
            <div class="input-group">
                <label>Nama Crop (Tab)</label>
                <input type="text" id="add-crop-name" placeholder="Misal: Tabel Detail Bawah">
            </div>
            <div class="input-group">
                <label>Caption Pesan Telegram</label>
                <input type="text" id="add-crop-caption" placeholder="Detail Laporan...">
            </div>
            <div class="input-group">
                <label>ID Grup Target</label>
                <input type="text" id="add-crop-targets" placeholder="-5430717889">
                <small style="color: #ef4444; font-size: 11px; margin-top: 4px; display: block;">*Dapatkan ID Grup dari URL web Telegram. Untuk multi-grup, pisahkan dengan koma (Contoh: -12345, -67890)</small>
            </div>
            <div class="modal-buttons">
                <button class="btn-cancel" style="width:auto; padding: 10px 20px;" onclick="document.getElementById('modal-add-crop').style.display='none'">Batal</button>
                <button class="btn-save" style="width:auto; padding: 10px 20px;" onclick="submitNewCrop()">Simpan</button>
            </div>
        </div>
    </div>

    <div id="toast">Berhasil disimpan!</div>

    <script>
        const API_URL = 'api_admin.php';
        let state = { pin: '', config: null, activeReport: null, activeCrop: null };
        let imgObj = null;

        // Auto-Login via PHP Session
        login();

        async function login() {
            try {
                const res = await fetch(API_URL);
                const data = await res.json();
                
                if (res.ok) {
                    state.config = data.data;
                    initDashboard();
                } else {
                    document.body.innerHTML = "<h3 style='color:red; font-family:sans-serif; text-align:center; margin-top:50px;'>❌ Sesi kedaluwarsa. Silakan ambil link baru dari Telegram.</h3>";
                }
            } catch (e) {
                alert('Gagal terhubung ke server');
            }
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        }

        function initDashboard() {
            const tabsContainer = document.getElementById('main-tabs');
            tabsContainer.innerHTML = '';
            
            let first = true;
            for (const [id, report] of Object.entries(state.config.reports)) {
                const btn = document.createElement('div');
                btn.className = `tab ${first ? 'active' : ''}`;
                btn.innerText = report.name;
                btn.dataset.id = id;
                btn.onclick = () => selectReport(id);
                tabsContainer.appendChild(btn);
                
                if (first) { selectReport(id); first = false; }
            }
        }

        function showAddModal() {
            document.getElementById('add-name').value = '';
            document.getElementById('add-url').value = '';
            document.getElementById('add-caption').value = '';
            document.getElementById('add-targets').value = '';
            document.getElementById('modal-add').style.display = 'flex';
        }

        function selectReport(reportId) {
            state.activeReport = reportId;
            document.querySelectorAll('.tab[data-id]').forEach(el => {
                el.classList.toggle('active', el.dataset.id === reportId);
            });
            
            const report = state.config.reports[reportId];
            document.getElementById('url-input').value = report.url;
            document.getElementById('report-schedules').value = report.schedules ? report.schedules.join(', ') : '';
            
            const subTabs = document.getElementById('crop-tabs');
            subTabs.innerHTML = '';
            
            let firstSub = true;
            for (const crop of report.crops) {
                const btn = document.createElement('div');
                btn.className = `sub-tab ${firstSub ? 'active' : ''}`;
                btn.innerText = crop.name;
                btn.dataset.id = crop.id;
                btn.onclick = () => selectCrop(crop.id);
                subTabs.appendChild(btn);
                
                if (firstSub) { selectCrop(crop.id); firstSub = false; }
            }
            
            // Add Crop Button
            const addCropBtn = document.createElement('div');
            addCropBtn.className = 'sub-tab';
            addCropBtn.style.background = '#10b981';
            addCropBtn.style.color = 'white';
            addCropBtn.innerHTML = '➕ Tambah';
            addCropBtn.onclick = showAddCropModal;
            subTabs.appendChild(addCropBtn);
        }

        function showAddCropModal() {
            document.getElementById('add-crop-name').value = '';
            document.getElementById('add-crop-caption').value = '';
            document.getElementById('add-crop-targets').value = '';
            document.getElementById('modal-add-crop').style.display = 'flex';
        }

        function selectCrop(cropId) {
            state.activeCrop = cropId;
            const report = state.config.reports[state.activeReport];
            const crop = report.crops.find(c => c.id === cropId);
            
            document.querySelectorAll('.sub-tab[data-id]').forEach(el => {
                el.classList.toggle('active', el.dataset.id === cropId);
            });
            
            // Populate metadata
            document.getElementById('crop-name').value = crop.name || '';
            document.getElementById('crop-caption').value = crop.caption || '';
            document.getElementById('crop-targets').value = Array.isArray(crop.targets) ? crop.targets.join(', ') : '';
            
            // Load Background
            const bgImg = document.getElementById('bg-image');
            let baseImg = crop.raw_image || (state.activeReport + '_raw.png');
            if (!baseImg.includes('/')) {
                baseImg = 'raw/' + baseImg;
            }
            const imgPath = baseImg + '?v=' + new Date().getTime();
            
            if (window.cropperInstance) {
                window.cropperInstance.destroy();
                window.cropperInstance = null;
            }
            
            bgImg.onload = () => {
                setupCropper(crop);
            };
            
            bgImg.onerror = () => {
                bgImg.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" fill="%23f1f5f9"><rect width="100%" height="100%"/><text x="50%" y="50%" fill="%2394a3b8" font-size="24" text-anchor="middle" font-family="sans-serif">Gambar Mentah Belum Tersedia. Klik Simpan URL.</text></svg>';
                setupCropper(crop);
            };
            
            bgImg.src = imgPath;
        }

        // Cropper Logic
        let cropData = { x: 0, y: 0, w: 1, h: 1 };
        window.cropperInstance = null;

        function updateCropInfo() {
            if (!window.cropperInstance) return;
            const data = window.cropperInstance.getData(true);
            const imgData = window.cropperInstance.getImageData();
            
            if (imgData.naturalWidth === 0 || imgData.naturalHeight === 0) return;
            
            cropData.x = data.x / imgData.naturalWidth;
            cropData.y = data.y / imgData.naturalHeight;
            cropData.w = data.width / imgData.naturalWidth;
            cropData.h = data.height / imgData.naturalHeight;
            
            // Clamping
            cropData.x = Math.max(0, Math.min(1, cropData.x));
            cropData.y = Math.max(0, Math.min(1, cropData.y));
            cropData.w = Math.max(0.01, Math.min(1 - cropData.x, cropData.w));
            cropData.h = Math.max(0.01, Math.min(1 - cropData.y, cropData.h));
            
            document.getElementById('crop-info').innerText = 
                `X: ${cropData.x.toFixed(2)} | Y: ${cropData.y.toFixed(2)} | W: ${cropData.w.toFixed(2)} | H: ${cropData.h.toFixed(2)}`;
        }
        
        function setupCropper(cropConfig) {
            // Simpan ke variabel lokal agar tidak tertimpa oleh event crop() saat inisialisasi
            const targetX = cropConfig.x !== undefined ? cropConfig.x : 0;
            const targetY = cropConfig.y !== undefined ? cropConfig.y : 0;
            const targetW = cropConfig.w !== undefined ? cropConfig.w : 1;
            const targetH = cropConfig.h !== undefined ? cropConfig.h : 1;
            
            const bgImg = document.getElementById('bg-image');
            
            if (window.cropperInstance) {
                window.cropperInstance.destroy();
            }
            
            window.cropperInstance = new Cropper(bgImg, {
                viewMode: 1,
                dragMode: 'move', // Default mode geser agar user friendly
                autoCrop: true,
                autoCropArea: 1,
                background: false,
                zoomable: true,
                toggleDragModeOnDblclick: true,
                wheelZoomRatio: 0.1, // Zoom lebih smooth
                zoom(event) {
                    // Hanya izinkan zoom scroll mouse JIKA menekan tombol Ctrl
                    if (event.detail.originalEvent && event.detail.originalEvent.type === 'wheel') {
                        if (!event.detail.originalEvent.ctrlKey) {
                            event.preventDefault(); // Cegah zoom, biarkan scroll halaman bekerja biasa
                        }
                    }
                },
                ready() {
                    const imgData = this.cropper.getImageData();
                    this.cropper.setData({
                        x: targetX * imgData.naturalWidth,
                        y: targetY * imgData.naturalHeight,
                        width: targetW * imgData.naturalWidth,
                        height: targetH * imgData.naturalHeight
                    });
                    updateCropInfo();
                },
                crop(event) {
                    updateCropInfo();
                }
            });
        }

        // Save actions
        async function saveUrl() {
            const newUrl = document.getElementById('url-input').value;
            const btn = document.querySelector('.url-editor .btn-save');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<span class="spinner"></span> Loading... Tunggu';
            btn.style.background = '#f59e0b';
            btn.disabled = true;
            
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ update_url: true, report_id: state.activeReport, url: newUrl })
            });
            
            btn.innerHTML = originalText;
            btn.style.background = 'var(--primary)';
            btn.disabled = false;
            
            if (res.ok) {
                const data = await res.json();
                if (data.status === 'success') {
                    state.config.reports[state.activeReport].url = newUrl;
                    showToast('URL Tersimpan & Live Preview Berhasil!');
                    
                    // Refresh background image & cropper
                    const crop = state.config.reports[state.activeReport].crops.find(c => c.id === state.activeCrop);
                    if (crop) {
                        setTimeout(() => selectCrop(state.activeCrop), 500); // Reload cropper secara penuh
                    }
                } else {
                    showToast(data.message || 'Gagal memproses Live Preview.', true);
                }
            } else {
                showToast('Terjadi kesalahan pada server (HTTP Error).', true);
            }
        }

        async function refreshLiveImage() {
            const currentUrl = state.config.reports[state.activeReport].url;
            if (!currentUrl) {
                showToast('URL belum diisi! Silakan simpan URL terlebih dahulu.', true);
                return;
            }
            
            const btn = document.querySelector('button[onclick="refreshLiveImage()"]');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<span class="spinner"></span> Mengambil Gambar...';
            btn.disabled = true;
            
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ update_url: true, report_id: state.activeReport, url: currentUrl })
            });
            
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (res.ok) {
                const data = await res.json();
                if (data.status === 'success') {
                    showToast('Gambar berhasil diperbarui (Real-Time)!');
                    const crop = state.config.reports[state.activeReport].crops.find(c => c.id === state.activeCrop);
                    if (crop) {
                        setTimeout(() => selectCrop(state.activeCrop), 500); // Reload cropper secara penuh
                    }
                } else {
                    showToast(data.message || 'Gagal memuat gambar terbaru.', true);
                }
            } else {
                showToast('Terjadi kesalahan pada server.', true);
            }
        }

        async function saveCrop() {
            updateCropInfo();
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    update_crop: true, 
                    report_id: state.activeReport, 
                    crop_id: state.activeCrop,
                    x: cropData.x, y: cropData.y, w: cropData.w, h: cropData.h
                })
            });
            if (res.ok) {
                const crop = state.config.reports[state.activeReport].crops.find(c => c.id === state.activeCrop);
                Object.assign(crop, cropData);
                showToast('Posisi Crop Tersimpan!');
            } else alert('Gagal menyimpan Crop');
        }

        async function saveMetadata() {
            const btn = document.querySelector('button[onclick="saveMetadata()"]');
            btn.innerHTML = '⏳';
            const name = document.getElementById('crop-name').value;
            const caption = document.getElementById('crop-caption').value;
            const targets = document.getElementById('crop-targets').value;
            
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    update_metadata: true, report_id: state.activeReport, crop_id: state.activeCrop,
                    name: name, caption: caption, targets: targets
                })
            });
            if (res.ok) {
                const crop = state.config.reports[state.activeReport].crops.find(c => c.id === state.activeCrop);
                if (name) {
                    crop.name = name;
                    const activeTab = document.querySelector(`.sub-tab[data-id="${state.activeCrop}"]`);
                    if (activeTab) activeTab.innerText = name;
                }
                crop.caption = caption;
                crop.targets = targets.split(',').map(s => s.trim());
                showToast('Metadata Tersimpan!');
            } else alert('Gagal menyimpan Metadata');
            btn.innerHTML = 'Simpan Metadata';
        }

        async function deleteCrop() {
            if (!confirm('Anda yakin ingin menghapus crop ini?')) return;
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ delete_crop: true, report_id: state.activeReport, crop_id: state.activeCrop })
            });
            if (res.ok) {
                showToast('Crop berhasil dihapus!');
                setTimeout(() => login(), 500); // Reload data
            } else alert('Gagal menghapus crop');
        }

        async function createNewReport() {
            const name = document.getElementById('add-name').value;
            const url = document.getElementById('add-url').value;
            const caption = document.getElementById('add-caption').value;
            const targets = document.getElementById('add-targets').value;
            
            if (!name || !url) return alert('Nama dan URL wajib diisi');
            
            const btn = document.querySelector('button[onclick="createNewReport()"]');
            btn.innerText = 'Menyimpan...';
            
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ create_report: true, name: name, url: url, caption: caption, targets: targets })
            });
            
            if (res.ok) {
                document.getElementById('modal-add').style.display = 'none';
                showToast('Laporan baru berhasil ditambahkan!');
                setTimeout(() => login(), 500); // Reload data via AJAX
            } else alert('Gagal menambah laporan');
            btn.innerText = 'Simpan';
        }

        async function deleteReport() {
            if (!confirm('Anda yakin ingin menghapus laporan ini secara permanen?')) return;
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ delete_report: true, report_id: state.activeReport })
            });
            
            if (res.ok) {
                showToast('Laporan dihapus!');
                setTimeout(() => login(), 500); // Reload data via AJAX
            } else alert('Gagal menghapus laporan');
        }
        async function editReportName() {
            const currentName = state.config.reports[state.activeReport].name;
            const newName = prompt("Masukkan nama laporan baru:", currentName);
            if (!newName || newName === currentName) return;
            
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ edit_report_name: true, report_id: state.activeReport, name: newName })
            });
            if (res.ok) {
                showToast('Nama laporan diubah!');
                setTimeout(() => login(), 500);
            } else alert('Gagal mengubah nama');
        }

        async function saveSchedules() {
            const btn = document.querySelector('button[onclick="saveSchedules()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳';
            btn.disabled = true;

            const schedules = document.getElementById('report-schedules').value;
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ update_schedules: true, report_id: state.activeReport, schedules: schedules })
            });

            if (res.ok) {
                const data = await res.json();
                state.config.reports[state.activeReport].schedules = schedules.split(',').map(s => s.trim()).filter(s => s);
                showToast(data.message || 'Jadwal Tersimpan!');
            } else {
                alert('Gagal menyimpan jadwal');
            }

            btn.innerHTML = originalText;
            btn.disabled = false;
        }

        async function submitNewCrop() {
            const name = document.getElementById('add-crop-name').value;
            const caption = document.getElementById('add-crop-caption').value;
            const targets = document.getElementById('add-crop-targets').value;
            
            if (!name) return alert('Nama crop wajib diisi');
            
            const btn = document.querySelector('button[onclick="submitNewCrop()"]');
            btn.innerText = 'Menyimpan...';
            
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    create_crop: true, 
                    report_id: state.activeReport, 
                    name: name,
                    caption: caption,
                    targets: targets
                })
            });
            if (res.ok) {
                document.getElementById('modal-add-crop').style.display = 'none';
                showToast('Crop baru ditambahkan!');
                setTimeout(() => login(), 500);
            } else alert('Gagal menambah crop');
            btn.innerText = 'Simpan';
        }

        async function deleteCrop() {
            if (state.config.reports[state.activeReport].crops.length <= 1) {
                alert('Tidak bisa menghapus crop ini karena minimal harus ada 1 crop di setiap laporan.');
                return;
            }
            if (!confirm('Yakin ingin menghapus crop ini?')) return;
            
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ delete_crop: true, report_id: state.activeReport, crop_id: state.activeCrop })
            });
            if (res.ok) {
                showToast('Crop dihapus!');
                setTimeout(() => login(), 500);
            } else {
                const data = await res.json();
                alert(data.message || 'Gagal menghapus crop');
            }
        }
    </script>
</body>
</html>
