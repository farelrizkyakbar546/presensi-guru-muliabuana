<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tes QR Scanner</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background: #f5f5f5; color: #222; }
        .container { width: 100%; max-width: 520px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 14px; }
        h1 { margin-top: 0; font-size: 24px; }
        .reader { width: 100%; overflow: hidden; border-radius: 12px; background: #000; margin-top: 20px; }
        #reader { width: 100%; }
        .status { margin-top: 16px; padding: 12px; border-radius: 10px; background: #f1f1f1; line-height: 1.5; }
        .result { margin-top: 16px; padding: 12px; border-radius: 10px; background: #eef5ff; word-break: break-word; display: none; }
        .upload { margin-top: 24px; padding-top: 20px; border-top: 1px solid #ddd; }
        .upload label { display: block; margin-bottom: 10px; font-weight: 600; }
        input[type="file"] { width: 100%; }
        .note { margin-top: 10px; color: #666; font-size: 14px; line-height: 1.5; }
    </style>
</head>
<body>
<div class="container">
    <h1>Test QR Scanner</h1>
    <p>Arahkan kamera ke QR Code sekolah.</p>
    <div class="reader"><div id="reader"></div></div>
    <div id="status" class="status">Menyiapkan scanner...</div>
    <div id="result" class="result">
        <strong>QR terbaca:</strong>
        <div id="resultText"></div>
    </div>
    <div class="upload">
        <label for="qrImage">Atau pilih gambar QR dari galeri</label>
        <input type="file" id="qrImage" accept="image/png,image/jpeg,image/jpg">
        <div class="note">Kamu bisa memilih foto atau screenshot QR Code dari galeri HP.</div>
    </div>
</div>
<script src="../assets/js/html5-qrcode.min.js"></script>
<script>
const statusElement = document.getElementById('status');
const resultElement = document.getElementById('result');
const resultTextElement = document.getElementById('resultText');
let scanner = null;
let sudahTerbaca = false;

function tampilkanHasil(teks) {
    if (sudahTerbaca) return;
    sudahTerbaca = true;
    resultTextElement.textContent = teks;
    resultElement.style.display = 'block';
    statusElement.textContent = 'QR berhasil terbaca.';
    if (scanner) {
        scanner.clear().catch(function(error) {
            console.error('Gagal menghentikan scanner:', error);
        });
    }
}

function mulaiScanner() {
    scanner = new Html5Qrcode('reader');
    scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        function(decodedText) { tampilkanHasil(decodedText); },
        function(errorMessage) {}
    )
    .then(function() {
        statusElement.textContent = 'Kamera aktif. Arahkan kamera ke QR sekolah.';
    })
    .catch(function(error) {
        statusElement.textContent = 'Kamera tidak dapat dibuka: ' + error;
    });
}

const qrImage = document.getElementById('qrImage');
qrImage.addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (!file) return;
    sudahTerbaca = false;
    resultElement.style.display = 'none';
    statusElement.textContent = 'Membaca gambar QR...';
    const imageScanner = new Html5Qrcode('reader');
    imageScanner.scanFile(file, true)
    .then(function(decodedText) {
        tampilkanHasil(decodedText);
    })
    .catch(function(error) {
        statusElement.textContent = 'QR tidak ditemukan di gambar tersebut.';
    });
});

mulaiScanner();
</script>
</body>
</html>