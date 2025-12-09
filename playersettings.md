# playerSettings

**playerSettings** adalah objek JSONB dalam tabel `settings` yang menyimpan konfigurasi pemutar video. Struktur default-nya adalah:

```javascript
{
  player: 'hls.js',           // Jenis pemutar: hls.js, jwplayer, videojs, plyr
  autoplay: false,            // Apakah video otomatis diputar
  controls: true,             // Apakah kontrol pemutar ditampilkan
  theme: 'dark',             // Tema: 'light', 'dark', atau 'auto'
  defaultQuality: 'auto',     // Kualitas default: 'auto', '1080p', '720p', '480p', '360p'
  seekInterval: 10,          // Interval pencarian (dalam detik)
  skin: 'default',           // Skema tampilan
  color1: '#b7000e',         // Warna utama
  color2: '#b7045d'          // Warna sekunder
}
```

## Endpoint dan Fungsi Terkait

### 1. **PUT `/api/settings/player`** - Fungsi `updatePlayerSettings`
**Request Body Fields yang bisa diupdate:**
- `player` - jenis pemutar video
- `autoplay` - boolean (default: false)
- `controls` - boolean (default: true)
- `theme` - string (light/dark/auto, default: dark)
- `defaultQuality` - string (auto/1080p/720p/480p/360p, default: auto)
- `seekInterval` - number (default: 10)
- `skin` - string (default: 'default')
- `color1` - string (default: '#b7000e')
- `color2` - string (default: '#b7045d')

**Response (Success - 200):**
```json
{
  "success": true,
  "message": "Player settings updated successfully",
  "data": {
    "player": "videojs",
    "autoplay": true,
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

### 2. **GET `/api/settings`** - Fungsi `getSettings`
**Response (Success - 200):**
```json
{
  "success": true,
  "data": {
    "id": "settings-uuid",
    "userId": "user-uuid",
    "playerSettings": {
      "player": "hls.js",
      "autoplay": false,
      "controls": true,
      "theme": "dark",
      "defaultQuality": "auto",
      "seekInterval": 10,
      "skin": "default",
      "color1": "#b7000e",
      "color2": "#b7045d"
    },
    // ... other settings
    "createdAt": "timestamp",
    "updatedAt": "timestamp"
  }
}
```

## Validasi
Validasi untuk playerSettings (dalam `validator.js`):
- `theme` harus salah satu dari: 'light', 'dark', 'auto'
- `defaultQuality` harus salah satu dari: 'auto', '1080p', '720p', '480p', '360p'
- `autoplay` dan `controls` harus boolean
- `seekInterval` adalah angka

## Penggunaan dalam Template
Dalam template `embed.ejs`, playerSettings digunakan untuk:
- Menentukan jenis pemutar video yang digunakan
- Menentukan apakah video akan otomatis diputar
- Mengkonfigurasi tema, kontrol, kualitas default, dan warna

## Fungsi lain yang menggunakan playerSettings:
- **videoController.js** - Mengembalikan playerSettings dalam response `getVideo` dan `getVideos`
- **publicController.js** - Mengembalikan playerSettings dalam response publik
- **embedController.js** - Mengembalikan playerSettings ke template embed.ejs
- **embed.ejs** - Menggunakan playerSettings untuk mengkonfigurasi pemutar video

Jadi **playerSettings** berfungsi untuk menyimpan preferensi user terhadap tampilan dan perilaku pemutar video yang akan digunakan di halaman video dan halaman embed.