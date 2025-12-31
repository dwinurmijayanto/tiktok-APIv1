# TikTok Downloader API - Vercel

API untuk mengunduh video TikTok tanpa watermark menggunakan tikwm.com service.

## 🚀 Deploy ke Vercel

### Cara Deploy

1. **Clone atau buat repository baru**
```bash
mkdir tiktok-api-vercel
cd tiktok-api-vercel
```

2. **Buat struktur folder**
```
tiktok-api-vercel/
├── api/
│   └── tiktok.php
├── vercel.json
└── README.md
```

3. **Deploy ke Vercel**

**Opsi 1: Via Vercel CLI**
```bash
npm i -g vercel
vercel
```

**Opsi 2: Via GitHub**
- Push repository ke GitHub
- Import project di [vercel.com](https://vercel.com)
- Vercel akan otomatis detect dan deploy

## 📝 API Usage

### Base URL
```
https://your-project.vercel.app
```

### Endpoint

**GET** `/api/tiktok`

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| url | string | Yes | TikTok video URL atau Video ID |

### Request Examples

**Full URL:**
```
GET /api/tiktok?url=https://www.tiktok.com/@username/video/7347080009141587201
```

**Video ID Only:**
```
GET /api/tiktok?url=7347080009141587201
```

**Short URL:**
```
GET /api/tiktok?url=https://vm.tiktok.com/ZSFsqLc9X/
```

### Response Example

**Success Response:**
```json
{
  "success": true,
  "data": {
    "video_id": "7347080009141587201",
    "region": "ID",
    "title": "Video description here",
    "duration": 30,
    "duration_formatted": "0:30",
    "author": {
      "id": "123456789",
      "unique_id": "username",
      "nickname": "Display Name",
      "avatar": "https://..."
    },
    "statistics": {
      "play_count": 1500000,
      "play_count_formatted": "1.5M",
      "like_count": 45000,
      "like_count_formatted": "45K",
      "comment_count": 1200,
      "comment_count_formatted": "1.2K",
      "share_count": 800,
      "share_count_formatted": "800"
    },
    "media": {
      "cover": "https://...",
      "video_no_watermark": "https://...",
      "video_watermark": "https://...",
      "music": "https://...",
      "size": 5242880,
      "size_mb": 5.0
    },
    "music_info": {
      "id": "7123456789",
      "title": "Song Title",
      "author": "Artist Name",
      "duration": 30,
      "play_url": "https://..."
    },
    "create_time": 1708934400,
    "create_time_formatted": "2024-02-26 12:00:00"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Error message here"
}
```

## ✨ Features

- ✅ Download video tanpa watermark
- ✅ Download video dengan watermark
- ✅ Extract audio/musik
- ✅ Statistik video (views, likes, comments, shares)
- ✅ Informasi author
- ✅ Format angka otomatis (1.7K, 2.5M)
- ✅ Thumbnail video
- ✅ Informasi musik
- ✅ CORS enabled
- ✅ Support multiple URL formats

## 🔧 Testing

### cURL
```bash
curl "https://your-project.vercel.app/api/tiktok?url=7347080009141587201"
```

### JavaScript (Fetch)
```javascript
fetch('https://your-project.vercel.app/api/tiktok?url=7347080009141587201')
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      console.log('Video URL:', data.data.media.video_no_watermark);
    }
  });
```

### PHP
```php
$url = 'https://your-project.vercel.app/api/tiktok?url=7347080009141587201';
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    echo $data['data']['media']['video_no_watermark'];
}
```

## ⚙️ Environment

Vercel secara otomatis menyediakan PHP runtime. Tidak perlu konfigurasi tambahan.

## 📄 License

MIT License

## ⚠️ Disclaimer

API ini menggunakan layanan pihak ketiga (tikwm.com). Pastikan untuk mematuhi Terms of Service TikTok dan tikwm.com saat menggunakan API ini.

## 🤝 Contributing

Pull requests are welcome!

## 📧 Support

Jika ada masalah, silakan buat issue di repository ini.
