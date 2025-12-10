# Proses Konversi Video ke HLS Saat S3 Storage Diaktifkan

Ketika S3 storage diaktifkan dalam aplikasi HLS video converter ini, prosesnya berjalan sebagai berikut:

## 1. Upload Awal dan Antrian

-   Seorang pengguna mengupload file video melalui API (`POST /api/videos/upload`)
-   File video sementara disimpan secara lokal di sistem
-   Catatan dibuat di database dengan `status: "processing"`
-   ID video ditambahkan ke antrian pemrosesan (baik menggunakan Bull queue berbasis Redis atau antrian di memori)

## 2. Proses Konversi Video

-   Prosesor antrian mengambil tugas dan memanggil `hlsConverter.convertToHLS()`
-   Konverter membaca metadata video (durasi, resolusi) menggunakan FFprobe
-   Variasi kualitas ditentukan berdasarkan:
    -   Resolusi video sumber (tidak bisa di-upscale)
    -   Preset kualitas yang dikonfigurasi admin (1080p, 720p, 480p, 360p)
    -   Preferensi kualitas spesifik pengguna (jika ada)

## 3. Segmentasi HLS

-   Sistem menghasilkan beberapa stream kualitas (misalnya 1080p, 720p, 480p, 360p)
-   Setiap varian kualitas diproses menggunakan FFmpeg dengan:
    -   Codec video H.264 (`libx264` atau encoder perangkat keras seperti `nvenc`)
    -   Codec audio AAC
    -   Pengaturan bitrate adaptif per preset kualitas
    -   Parameter HLS spesifik (durasi segmen 10 detik secara default)
-   Proses ini membuat:
    -   File segmen (file `.ts`)
    -   File playlist (`.m3u8`) untuk setiap kualitas
    -   Playlist utama (`master.m3u8`) yang merujuk ke semua varian kualitas
    -   Gambar thumbnail (grid 2x2 dari screenshot)

## 4. Proses Upload S3

-   Setelah konversi HLS lokal berhasil, sistem memanggil `s3Storage.uploadHLSFiles()`
-   Seluruh direktori output HLS diupload ke S3 dengan struktur path:
    ```
    hls/{userId}/{videoId}/
    ├── master.m3u8
    ├── 1080p/
    │   ├── playlist.m3u8
    │   └── segment_001.ts, segment_002.ts, dst.
    ├── 720p/
    │   ├── playlist.m3u8
    │   └── segment_001.ts, segment_002.ts, dst.
    ├── 480p/
    │   ├── playlist.m3u8
    │   └── segment_001.ts, segment_002.ts, dst.
    └── thumbnail.jpg
    ```

## 5. Update Database

-   Catatan video diperbarui dengan:
    -   `storageType: "s3"` (daripada default "local")
    -   `s3Key`: Kunci objek S3 prefix (`hls/{userId}/{videoId}`)
    -   `s3Bucket`: Nama bucket
    -   `s3PublicUrl`: URL publik dasar pada waktu upload (untuk mencegah URL rusak ketika pengaturan berubah)
    -   URL playlist HLS dan path thumbnail

## 6. Pembersihan Lokal Opsional

-   Jika variabel lingkungan `DELETE_LOCAL_AFTER_S3` diatur ke `true`, sistem menghapus file HLS lokal setelah upload S3 berhasil
-   Ini menghemat ruang penyimpanan lokal sambil tetap menyediakan konten di S3

## 7. Penyampaian Konten

-   Ketika pengguna meminta untuk men-stream video, sistem dapat menyajikan konten dengan dua cara:
    1. **Akses S3 langsung** (jika akses publik tersedia): URL video langsung ke S3
    2. **Streaming melalui proxy** (untuk S3 pribadi): endpoint `/api/stream` menghasilkan URL bertanda dan menyediakan konten melalui proxy

## 8. Dukungan Layanan S3-Kompatibel

Sistem mendukung beberapa penyedia storage S3-kompatibel:

-   **AWS S3**
-   **Cloudflare R2** (dengan dukungan domain publik khusus)
-   **MinIO**
-   **Garage**

## 9. Penanganan Kesalahan

-   Jika upload S3 gagal, catatan video ditandai sebagai gagal dengan pesan error
-   Sistem mempertahankan file asli secara lokal sehingga pengguna bisa mencoba ulang konversi
-   Jika video berhasil diupload ke S3 dan file lokal dihapus, URL S3 masih bisa digunakan

Arsitektur ini memungkinkan hosting video yang skalabel di mana sistem tidak perlu menyimpan file video besar secara lokal, sambil tetap menyediakan kemampuan streaming bitrate adaptif yang disediakan HLS.
