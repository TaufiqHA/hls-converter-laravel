# Settings Controller

## Fungsi-fungsi dan Response dari Settings Controller

### 1. **GET `/api/settings`** - `getSettings`
**Deskripsi:** Mendapatkan pengaturan pengguna
**Akses:** Private (butuh JWT token)
**Response (Success - 200):**
```json
{
  "success": true,
  "data": {
    "id": "settings-uuid",
    "userId": "user-uuid",
    "playerSettings": { /* objek player settings */ },
    "adsSettings": { /* objek ads settings */ },
    "defaultWatermark": { /* objek watermark settings */ },
    "defaultDownloadEnabled": true,
    "googleDriveSettings": { /* objek google drive settings */ },
    "subtitleSettings": { /* objek subtitle settings */ },
    "websiteSettings": { /* objek website settings */ },
    "ffmpegSettings": { /* objek ffmpeg settings */ },
    "s3Settings": { /* objek s3 settings */ },
    "redisSettings": { /* objek redis settings */ },
    "rateLimitSettings": { /* objek rate limit settings */ },
    "corsSettings": { /* objek cors settings */ },
    "analyticsSettings": { /* objek analytics settings */ },
    "securitySettings": { /* objek security settings */ },
    "emailSettings": { /* objek email settings */ },
    "createdAt": "timestamp",
    "updatedAt": "timestamp"
  }
}
```

### 2. **PUT `/api/settings/player`** - `updatePlayerSettings`
**Deskripsi:** Memperbarui pengaturan pemutar video
**Akses:** Private
**Request Body Fields:**
- `player` (string): jenis pemutar video ('videojs', 'dplayer')
- `autoplay` (boolean): otomatis putar
- `controls` (boolean): kontrol pemutar
- `theme` (string): 'light', 'dark', atau 'auto'
- `defaultQuality` (string): 'auto', '1080p', '720p', '480p', '360p'
- `seekInterval` (number): interval pencarian
- `skin` (string): skema tampilan
- `color1` (string): warna utama
- `color2` (string): warna sekunder
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Player settings updated successfully",
  "data": {
    "player": "videojs",
    "autoplay": false,
    "controls": true,
    "theme": "dark",
    "defaultQuality": "auto",
    "seekInterval": 10,
    "skin": "default",
    "color1": "#b7000e",
    "color2": "#b7045d"
  }
}
```
**Response (Error - 400):**
```json
{
  "success": false,
  "message": "Invalid player type. Must be one of: videojs, dplayer"
}
```

### 3. **PUT `/api/settings/ads`** - `updateAdsSettings`
**Deskripsi:** Memperbarui pengaturan iklan
**Akses:** Private
**Request Body Fields:**
- `enabled` (boolean): apakah iklan diaktifkan
- `preRollAd` (object): iklan sebelum video
- `midRollAd` (object): iklan tengah video
- `postRollAd` (object): iklan setelah video
- `overlayAd` (object): iklan overlay
- `popunderAd` (object): iklan popunder
- `nativeAd` (object): iklan native
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Ads settings updated successfully",
  "data": { /* objek ads settings yang diperbarui */ }
}
```

### 4. **PUT `/api/settings/watermark`** - `updateWatermarkSettings`
**Deskripsi:** Memperbarui pengaturan watermark
**Akses:** Private
**Request Body Fields:**
- `enabled` (boolean): apakah watermark diaktifkan
- `text` (string): teks watermark
- `position` (string): posisi watermark
- `opacity` (number): transparansi watermark
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Watermark settings updated successfully",
  "data": { /* objek watermark settings yang diperbarui */ }
}
```

### 5. **PUT `/api/settings/download`** - `updateDownloadSettings`
**Deskripsi:** Memperbarui pengaturan unduh bawaan
**Akses:** Private
**Request Body Fields:**
- `defaultDownloadEnabled` (boolean): apakah unduh diaktifkan secara default
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Download settings updated successfully",
  "data": { "defaultDownloadEnabled": true }
}
```

### 6. **PUT `/api/settings/googledrive`** - `updateGoogleDriveSettings`
**Deskripsi:** Memperbarui pengaturan Google Drive
**Akses:** Private
**Request Body Fields:**
- `enabled` (boolean): apakah Google Drive diaktifkan
- `mode` (string): 'api', 'oauth', 'rclone'
- `apiKey` (string): API key
- `clientId` (string): Client ID
- `clientSecret` (string): Client Secret
- `refreshToken` (string): Refresh Token
- `rcloneConfig` (string): Konfigurasi Rclone
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Google Drive settings updated successfully",
  "data": { /* objek google drive settings yang diperbarui */ }
}
```

### 7. **PUT `/api/settings/subtitles`** - `updateSubtitleSettings`
**Deskripsi:** Memperbarui pengaturan subtitle
**Akses:** Private
**Request Body Fields:**
- `defaultLanguage` (string): bahasa default
- `fontColor` (string): warna font
- `fontFamily` (string): jenis font
- `edgeStyle` (string): gaya tepi teks
- `backgroundOpacity` (number): transparansi latar belakang
- `backgroundColor` (string): warna latar belakang
- `windowOpacity` (number): transparansi jendela
- `windowColor` (string): warna jendela
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Subtitle settings updated successfully",
  "data": { /* objek subtitle settings yang diperbarui */ }
}
```

### 8. **GET `/api/settings/admin`** - `getAdminSettings`
**Deskripsi:** Mendapatkan semua pengaturan admin
**Akses:** Admin only
**Response (Success - 200):**
```json
{
  "success": true,
  "data": {
    "ffmpegSettings": { /* objek ffmpeg settings */ },
    "s3Settings": { /* objek s3 settings */ },
    "redisSettings": { /* objek redis settings */ },
    "websiteSettings": { /* objek website settings */ },
    "rateLimitSettings": { /* objek rate limit settings */ },
    "corsSettings": { /* objek cors settings */ },
    "analyticsSettings": { /* objek analytics settings */ },
    "securitySettings": { /* objek security settings */ },
    "emailSettings": { /* objek email settings */ },
    "playerSettings": { /* objek player settings */ },
    "adsSettings": { /* objek ads settings */ },
    "defaultWatermark": { /* objek watermark settings */ },
    "googleDriveSettings": { /* objek google drive settings */ },
    "subtitleSettings": { /* objek subtitle settings */ }
  }
}
```

### 9. **PUT `/api/settings/admin/ffmpeg`** - `updateFFmpegSettings`
**Deskripsi:** Memperbarui pengaturan FFmpeg
**Akses:** Admin only
**Request Body Fields:**
- `ffmpegPath` (string): path FFmpeg
- `ffprobePath` (string): path FFprobe
- `hlsSegmentDuration` (number): durasi segmen HLS
- `hlsPlaylistType` (string): tipe playlist HLS
- `enableAdaptiveStreaming` (boolean): aktifkan streaming adaptif
- `videoQualities` (array): kualitas video
- `videoCodec` (string): codec video
- `audioCodec` (string): codec audio
- `videoBitrate` (string): bitrate video
- `audioBitrate` (string): bitrate audio
- `preset` (string): preset encoding
- `crf` (number): CRF value
- `maxThreads` (number): jumlah thread maksimal
- `useHardwareAccel` (boolean): gunakan akselerasi perangkat keras
- `hwEncoder` (string): encoder perangkat keras
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "FFmpeg settings updated successfully",
  "data": { /* objek ffmpeg settings yang diperbarui */ }
}
```
**Response (Error - 400):**
```json
{
  "success": false,
  "message": "Invalid preset. Must be one of: ultrafast, superfast, veryfast, faster, fast, medium, slow, slower, veryslow"
}
```

### 10. **PUT `/api/settings/admin/s3`** - `updateS3Settings`
**Deskripsi:** Memperbarui pengaturan S3
**Akses:** Admin only
**Request Body Fields:**
- `enabled` (boolean): apakah S3 diaktifkan
- `storageType` (string): 'local', 's3', 'minio', 'garage', 'r2'
- `endpoint` (string): endpoint S3
- `accessKey` (string): access key
- `secretKey` (string): secret key
- `bucket` (string): bucket name
- `region` (string): region
- `forcePathStyle` (boolean): force path style
- `deleteLocalAfterUpload` (boolean): hapus lokal setelah upload
- `publicUrlBase` (string): URL basis publik
- `r2AccountId` (string): account ID R2
- `r2PublicDomain` (string): domain publik R2
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "S3 settings updated successfully",
  "data": { /* objek s3 settings yang diperbarui */ }
}
```
**Response (Error - 400):**
```json
{
  "success": false,
  "message": "Invalid storage type. Must be one of: local, s3, minio, garage, r2"
}
```

### 11. **PUT `/api/settings/admin/website`** - `updateWebsiteSettings`
**Deskripsi:** Memperbarui pengaturan website
**Akses:** Admin only
**Request Body Fields:**
- `siteName` (string): nama situs
- `siteDescription` (string): deskripsi situs
- `siteUrl` (string): URL situs
- `contactEmail` (string): email kontak
- `enableRegistration` (boolean): aktifkan pendaftaran
- `enableGuestUpload` (boolean): aktifkan upload tamu
- `maxUploadSizePerUser` (number): ukuran upload maksimum
- `allowedVideoFormats` (array): format video yang diizinkan
- `defaultUserRole` (string): role bawaan user
- `maintenanceMode` (boolean): mode perawatan
- `maintenanceMessage` (string): pesan perawatan
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Website settings updated successfully",
  "data": { /* objek website settings yang diperbarui */ }
}
```

### 12. **PUT `/api/settings/admin/security`** - `updateSecuritySettings`
**Deskripsi:** Memperbarui pengaturan keamanan
**Akses:** Admin only
**Request Body Fields:**
- `jwtExpiration` (string): durasi JWT
- `passwordMinLength` (number): panjang minimum password
- `requireEmailVerification` (boolean): butuh verifikasi email
- `maxLoginAttempts` (number): batas percobaan login
- `lockoutDuration` (number): durasi penguncian akun
- `enableTwoFactor` (boolean): aktifkan 2FA
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Security settings updated successfully",
  "data": { /* objek security settings yang diperbarui */ }
}
```

### 13. **POST `/api/settings/admin/favicon`** - `uploadFavicon`
**Deskripsi:** Upload favicon
**Akses:** Admin only
**Request Files:**
- `favicon`: file favicon
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Favicon uploaded successfully",
  "data": {
    "favicon": "/assets/filename.png",
    "fullUrl": "http://example.com/assets/filename.png"
  }
}
```
**Response (Error - 400):**
```json
{
  "success": false,
  "message": "No file uploaded"
}
```

### 14. **POST `/api/settings/admin/logo`** - `uploadLogo`
**Deskripsi:** Upload logo
**Akses:** Admin only
**Request Files:**
- `logo`: file logo
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Logo uploaded successfully",
  "data": {
    "logo": "/assets/filename.png",
    "fullUrl": "http://example.com/assets/filename.png"
  }
}
```

### 15. **DELETE `/api/settings/admin/favicon`** - `deleteFavicon`
**Deskripsi:** Hapus favicon
**Akses:** Admin only
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Favicon deleted successfully"
}
```

### 16. **DELETE `/api/settings/admin/logo`** - `deleteLogo`
**Deskripsi:** Hapus logo
**Akses:** Admin only
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Logo deleted successfully"
}
```

### 17. **PUT `/api/settings/admin/email`** - `updateEmailSettings`
**Deskripsi:** Memperbarui pengaturan email
**Akses:** Admin only
**Request Body Fields:**
- `enabled` (boolean): apakah email diaktifkan
- `provider` (string): 'smtp', 'sendgrid', 'mailgun', 'ses'
- `host` (string): host SMTP
- `port` (number): port SMTP
- `secure` (boolean): aman (SSL/TLS)
- `username` (string): username SMTP
- `password` (string): password SMTP
- `fromEmail` (string): email pengirim
- `fromName` (string): nama pengirim
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Email settings updated successfully",
  "data": { /* objek email settings yang diperbarui */ }
}
```

### 18. **POST `/api/settings/admin/email/test`** - `testEmailSettings`
**Deskripsi:** Uji konfigurasi email
**Akses:** Admin only
**Request Body Fields:**
- `testEmail` (string): alamat email untuk pengujian
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Test email sent successfully to test@example.com",
  "data": { "messageId": "message-id" }
}
```
**Response (Error - 400):**
```json
{
  "success": false,
  "message": "Email configuration error: error message"
}
```

### 19. **GET `/api/settings/googledrive/auth-url`** - `getGoogleDriveAuthUrl`
**Deskripsi:** Mendapatkan URL otorisasi Google Drive OAuth
**Akses:** Private
**Response (Success - 200):**
```json
{
  "success": true,
  "data": {
    "authUrl": "https://accounts.google.com/o/oauth2/auth/...",
    "redirectUri": "http://example.com/api/settings/googledrive/callback"
  }
}
```

### 20. **GET `/api/settings/googledrive/callback`** - `handleGoogleDriveCallback`
**Deskripsi:** Menangani callback OAuth Google Drive
**Akses:** Public (tapi dengan verifikasi state)
**Response (Success - 200):**
- Merender template `oauth-result` dengan parameter sukses

### 21. **DELETE `/api/settings/googledrive/revoke`** - `revokeGoogleDriveToken`
**Deskripsi:** Mencabut token otentikasi Google Drive
**Akses:** Private
**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Google Drive authorization revoked successfully"
}
```

## Error Handling
Semua fungsi yang tidak disebutkan di atas mengembalikan error ke `next(error)` untuk ditangani oleh middleware error global.