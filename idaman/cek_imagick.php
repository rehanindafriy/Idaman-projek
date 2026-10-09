<?php
echo "<h3>Cek Ekstensi Server untuk Solusi 2 (PDF to Image)</h3>";

if (extension_loaded('imagick')) {
    echo "<p style='color:green; font-weight:bold;'>✅ Ekstensi Imagick AKTIF! Solusi 2 (Export PDF Google Sheets lalu convert ke Image secara lokal) BISA digunakan.</p>";
    
    // Cek format pendukung
    $imagick = new Imagick();
    $formats = $imagick->queryFormats();
    if (in_array('PDF', $formats) && in_array('PNG', $formats)) {
         echo "<p style='color:green;'>✅ Imagick mendukung konversi PDF ke PNG.</p>";
    } else {
         echo "<p style='color:orange;'>⚠️ Imagick aktif, tapi dukungan PDF/PNG mungkin perlu dikonfigurasi server.</p>";
    }
} else {
    echo "<p style='color:red; font-weight:bold;'>❌ Ekstensi Imagick TIDAK AKTIF di PHP server ini.</p>";
    echo "<p>Anda bisa mengaktifkannya di Hostinger Panel (hPanel) -> Advanced -> PHP Configuration -> centang 'imagick'.</p>";
}

echo "<hr>";
echo "<p>Silakan upload file ini dan buka URL-nya di browser. Beritahu saya hasilnya!</p>";
