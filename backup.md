# Dokumentasi Backup System

## Fungsi-fungsi di `backupController.js`

### 1. `createBackup`
- **Deskripsi**: Membuat cadangan (backup) database
- **Rute**: POST `/api/admin/backup`
- **Akses**: Hanya Admin
- **Detail**: Fungsi ini membuat backup database dalam format SQL atau JSON berdasarkan parameter yang dikirimkan. Jika format tidak ditentukan, defaultnya adalah SQL.

### 2. `restoreBackup`
- **Deskripsi**: Mengembalikan (restore) database dari file cadangan
- **Rute**: POST `/api/admin/backup/restore`
- **Akses**: Hanya Admin
- **Detail**: Fungsi ini mengembalikan data database dari file backup yang ditentukan. Mendukung format .dump, .sql, dan .json.

### 3. `listBackups`
- **Deskripsi**: Menampilkan daftar semua file cadangan yang tersedia
- **Rute**: GET `/api/admin/backup`
- **Akses**: Hanya Admin
- **Detail**: Fungsi ini mengembalikan daftar semua file backup yang tersimpan di direktori backup.

### 4. `downloadBackup`
- **Deskripsi**: Mengunduh file cadangan
- **Rute**: GET `/api/admin/backup/download/:filename`
- **Akses**: Hanya Admin
- **Detail**: Fungsi ini memungkinkan pengguna untuk mengunduh file backup tertentu dengan menyediakan nama filenya.

### 5. `deleteBackup`
- **Deskripsi**: Menghapus file cadangan
- **Rute**: DELETE `/api/admin/backup/:filename`
- **Akses**: Hanya Admin
- **Detail**: Fungsi ini menghapus file backup yang ditentukan dari sistem.

### 6. `uploadAndRestore`
- **Deskripsi**: Mengunggah file cadangan dan sekaligus mengembalikannya
- **Rute**: POST `/api/admin/backup/upload`
- **Akses**: Hanya Admin
- **Detail**: Fungsi ini memungkinkan pengguna untuk mengunggah file backup langsung dan segera mengembalikannya ke database.

## Fungsi-fungsi di `backupService.js`

### 1. `createBackup()`
- Membuat backup database PostgreSQL dalam format custom (.dump) menggunakan perintah `pg_dump`

### 2. `restoreBackup(filename)`
- Mengembalikan database dari file backup (format .dump atau .sql) menggunakan `pg_restore` atau `psql`

### 3. `createJsonBackup()`
- Membuat backup database dalam format JSON, berguna ketika `pg_dump` tidak tersedia

### 4. `restoreJsonBackup(filename)`
- Mengembalikan database dari file backup JSON dengan mengeksekusi perintah insert untuk setiap tabel

### 5. `listBackups()`
- Mengembalikan daftar semua file backup yang tersedia di direktori backup

### 6. `getBackupFile(filename)`
- Mengembalikan informasi dan stream file backup untuk didownload

### 7. `deleteBackup(filename)`
- Menghapus file backup tertentu dari sistem file

### 8. `getDbConfig()`
- Mengambil konfigurasi koneksi database dari environment variables

### 9. `ensureBackupDir()`
- Memastikan direktori backup sudah ada atau membuatnya jika belum ada

## Routing (Rute-rute API)

### Rute-rute Utama Backup:
- **GET `/api/admin/backup`**: Menampilkan daftar backup (listBackups)
- **POST `/api/admin/backup`**: Membuat backup baru (createBackup)
- **GET `/api/admin/backup/download/:filename`**: Mengunduh file backup (downloadBackup)
- **POST `/api/admin/backup/restore`**: Mengembalikan database dari backup (restoreBackup)
- **POST `/api/admin/backup/upload`**: Mengunggah dan mengembalikan dari backup (uploadAndRestore)
- **DELETE `/api/admin/backup/:filename`**: Menghapus file backup (deleteBackup)

### Rute-rute Monitoring Tambahan:
- **GET `/api/admin/backup/status`**: Status backup (getBackupStatus)
- **GET `/api/admin/backup/stats`**: Statistik backup (getBackupStats)
- **POST `/api/admin/backup/verify/:filename`**: Verifikasi file backup (verifyBackup)
- **POST `/api/admin/backup/verify-all`**: Verifikasi semua file backup (verifyAllBackups)
- **GET `/api/admin/backup/file-stats/:filename`**: Statistik file backup (getBackupFileStats)
- **POST `/api/admin/backup/cleanup`**: Pembersihan backup (cleanupBackups)
- **GET `/api/admin/backup/management-status`**: Status manajemen backup (getManagementStatus)
- **POST `/api/admin/backup/trigger`**: Memicu pembuatan backup (triggerBackup)

## Fitur Tambahan

### Upload Middleware:
- Digunakan untuk mengunggah file backup dengan batas ukuran maksimal 500MB
- Hanya mendukung ekstensi .dump, .sql, dan .json
- File disimpan di direktori yang ditentukan oleh variabel lingkungan `BACKUP_PATH`

### Keamanan:
- Semua rute hanya dapat diakses oleh admin yang terotentikasi
- Ada validasi ekstra untuk mencegah path traversal attacks
- Cek validitas file sebelum operasi dilakukan

Semua fungsi ini dirancang untuk memberikan fungsionalitas manajemen backup dan restore database yang komprehensif dan aman bagi administrator sistem.