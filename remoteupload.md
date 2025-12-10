# Dokumentasi Fungsi Remote Upload

Dokumentasi ini menjelaskan semua fungsi dan proses terkait remote upload dalam sistem HLS Video Converter, termasuk upload dari URL remote dan Google Drive.

## Tabel Isi

1. [Fungsi Remote Upload di Video Controller](#fungsi-remote-upload-di-video-controller)
2. [Fungsi di Layanan Remote Uploader](#fungsi-di-layanan-remote-uploader)
3. [Fungsi di Video Queue](#fungsi-di-video-queue)
4. [Fungsi di Google Drive Service](#fungsi-di-google-drive-service)
5. [Endpoint API](#endpoint-api)
6. [Proses Keseluruhan](#proses-keseluruhan)

## Fungsi Remote Upload di Video Controller

### `remoteUpload` (videoController.js)

Fungsi utama untuk meng-handle upload video dari URL remote.

**Parameter Input:**
- `url`: URL dari file video yang akan di-upload
- `title`: Judul video
- `description`: Deskripsi video

**Fungsi Utama:**
1. Validasi URL menggunakan `remoteUploader.validateUrl()`
2. Deteksi nama file dari URL menggunakan `remoteUploader.getFilenameFromUrl()`
3. Buat record video di database dengan status `uploading`
4. Tambahkan tugas ke queue untuk proses download dan konversi
5. Kembalikan response bahwa proses telah dimulai

**Alur Proses:**
- Cek apakah URL adalah Google Drive URL
- Validasi URL dan Google Drive settings
- Auto-detect filename jika title tidak disediakan
- Buat record video di database
- Tambahkan ke queue untuk proses asynchronous

## Fungsi di Layanan Remote Uploader

### `getFilenameFromUrl` (remoteUploader.js)

Mendapatkan nama file dari URL atau header Content-Disposition.

**Parameter Input:**
- `url`: URL dari file yang akan di-download
- `googleDriveSettings`: Konfigurasi Google Drive (opsional)

**Fungsi Utama:**
- Cek apakah URL adalah Google Drive URL
- Jika Google Drive, panggil `getGoogleDriveFilename()`
- Lakukan request HEAD untuk mendapatkan Content-Disposition
- Ekstrak nama file dari URL path jika tidak ada Content-Disposition

### `getGoogleDriveFilename` (remoteUploader.js)

Mendapatkan nama file dari Google Drive API menggunakan settings Google Drive.

**Parameter Input:**
- `url`: Google Drive URL
- `googleDriveSettings`: Konfigurasi Google Drive

**Fungsi Utama:**
- Inisialisasi Google Drive service berdasarkan mode (API key atau OAuth)
- Mendapatkan metadata file dari Google Drive API
- Kembalikan nama file dari metadata

### `downloadFromUrl` (remoteUploader.js)

Mengunduh file dari URL remote ke server lokal.

**Parameter Input:**
- `url`: URL dari file yang akan di-download
- `userId`: ID pengguna yang meng-upload
- `onProgress`: Callback untuk melacak progress (opsional)

**Fungsi Utama:**
- Validasi protokol URL (harus HTTP/HTTPS)
- Buat direktori upload pengguna
- Lakukan request HEAD untuk mendapatkan info file
- Check limit ukuran file
- Download file dengan tracking progress
- Kembalikan informasi file yang di-download

### `downloadFromGoogleDrive` (remoteUploader.js)

Mengunduh file dari Google Drive dengan menggunakan API Google Drive.

**Parameter Input:**
- `url`: Google Drive URL
- `userId`: ID pengguna yang meng-upload
- `googleDriveSettings`: Konfigurasi Google Drive
- `onProgress`: Callback untuk melacak progress (opsional)

**Fungsi Utama:**
- Inisialisasi Google Drive service berdasarkan mode konfigurasi
- Dapatkan metadata file dari Google Drive
- Buat direktori upload pengguna
- Download file dengan progress tracking
- Kembalikan informasi file yang di-download

### `validateUrl` (remoteUploader.js)

Memvalidasi URL remote sebelum proses download.

**Parameter Input:**
- `url`: URL yang akan divalidasi
- `googleDriveSettings`: Konfigurasi Google Drive (opsional)

**Fungsi Utama:**
- Cek apakah URL adalah Google Drive URL
- Jika Google Drive, verifikasi setting dan aksesibilitas
- Untuk URL biasa, cek protokol dan content type
- Kembalikan status validasi dan jenis URL (Google Drive atau bukan)

## Fungsi di Video Queue

### `processRemoteUpload` (videoQueue.js)

Fungsi inti untuk memproses tugas remote upload dari queue.

**Parameter Input:**
- `videoId`: ID video yang akan diproses
- `url`: URL dari file yang akan di-download
- `userId`: ID pengguna yang meng-upload
- `googleDriveSettings`: Konfigurasi Google Drive (opsional)
- `isGoogleDrive`: Flag apakah ini Google Drive upload

**Fungsi Utama:**
- Update status video ke `downloading`
- Panggil fungsi download yang sesuai (Google Drive atau URL biasa)
- Update progress saat download
- Setelah download, perbarui database dan pindah ke fase `converting`
- Panggil `processVideoConversionWithPhase()` untuk konversi

### `processVideoConversionWithPhase` (videoQueue.js)

Fungsi untuk memproses konversi video dengan tracking fase.

**Parameter Input:**
- `videoId`: ID video yang akan dikonversi

**Fungsi Utama:**
- Update status ke `converting`
- Panggil HLS converter untuk konversi
- Tracking progress konversi
- Update database dengan hasil konversi
- Simpan informasi HLS ke database

## Fungsi di Google Drive Service

### `extractFileId` (googleDriveService.js)

Mengekstrak file ID dari berbagai format URL Google Drive.

**Parameter Input:**
- `url`: URL Google Drive

**Fungsi Utama:**
- Cocokkan URL dengan berbagai pola format Google Drive
- Kembalikan file ID jika ditemukan

### `getFileInfo` (googleDriveService.js)

Mendapatkan informasi lengkap dari file Google Drive.

**Parameter Input:**
- `url`: URL Google Drive

**Fungsi Utama:**
- Ekstrak file ID dari URL
- Dapatkan metadata file dari Google Drive API
- Kembalikan informasi file (ID, nama, MIME type, ukuran, URL download)

### `downloadFileWithProgress` (googleDriveService.js)

Mengunduh file dari Google Drive dengan tracking progress.

**Parameter Input:**
- `fileId`: ID file Google Drive
- `destinationPath`: Lokasi tujuan di server lokal
- `totalSize`: Ukuran total file (opsional)
- `onProgress`: Callback untuk melacak progress

**Fungsi Utama:**
- Buat request download otentikasi ke Google Drive
- Stream file dengan tracking progress
- Panggil callback progress saat download

### `isGoogleDriveUrl` (googleDriveService.js)

Memeriksa apakah URL adalah Google Drive URL.

**Parameter Input:**
- `url`: URL yang akan dicek

**Fungsi Utama:**
- Cek apakah URL mengandung 'drive.google.com' atau 'docs.google.com'
- Kembalikan boolean

## Endpoint API

### POST `/api/videos/remote-upload`

Endpoint utama untuk meng-upload video dari URL remote.

**Input:**
- Headers: Authorization token (jika tidak guest)
- Body: {url, title, description}

**Output:**
- Response sukses dengan informasi video dan status upload

### POST `/api/public/remote-upload`

Endpoint untuk guest upload dari URL remote (jika diaktifkan).

**Input:**
- Body: {url, title, description, password}

**Output:**
- Response sukses dengan informasi video

## Proses Keseluruhan

1. **Request Upload**: Pengguna mengirimkan URL ke endpoint API
2. **Validasi**: Sistem memvalidasi URL dan konfigurasi Google Drive
3. **Database Record**: Buat record video dengan status `uploading`
4. **Queue**: Tambahkan ke queue untuk proses asynchronous
5. **Download**: Download file dari URL remote atau Google Drive
6. **Konversi**: Konversi file ke format HLS
7. **Selesai**: Update database dan kirim notifikasi

Proses ini dirancang untuk menangani besar file dan URL yang tidak dapat diakses secara langsung, dengan fitur progress tracking dan error handling yang robust.