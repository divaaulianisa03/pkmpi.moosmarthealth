# MooSmartHealthGuard 🐄

Sistem monitoring kesehatan sapi berbasis IoT — memantau suhu tubuh, kadar oksigen, dan tingkat aktivitas/kestresan sapi secara real-time, lengkap dengan notifikasi otomatis ke Telegram saat kondisi sapi tidak normal.

Proyek ini merupakan bagian dari PKM-PI (Program Kreativitas Mahasiswa) yang bekerja sama dengan kelompok peternak sapi Gapoknak Wijaya Kusuma, Desa Danda Jaya, Kabupaten Barito Kuala, Kalimantan Selatan.

## Fitur

- **Dashboard real-time** — kondisi terkini seluruh sapi (suhu, kadar oksigen, tingkat aktivitas)
- **Riwayat data** — semua data sensor yang pernah masuk tersimpan lengkap (tidak ditimpa), bisa difilter per sapi dan diatur jumlah data yang ditampilkan
- **Grafik** — visualisasi tren data sensor
- **Notifikasi Telegram otomatis** — alert instan saat suhu, kadar oksigen, atau tingkat stres sapi masuk kategori waspada/bahaya
- **Simulator sensor** (Python) — untuk simulasi data sensor tanpa perangkat IoT fisik, cocok untuk development/testing

## Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 (PHP 8.3) |
| Frontend | React 18 + Vite |
| Database | MySQL |
| Notifikasi | Telegram Bot API |
| Simulator | Python |
| Hardware (target) | ESP32, MLX90614 (suhu), MAX30105 (kadar oksigen), MPU6050 (gerak/aktivitas) |

## Struktur Data Sensor

Setiap data sensor yang masuk dicatat di dua tempat:
1. **Tabel kondisi terkini** (`sensor_suhu`, `sensor_kadar_oksigen`, `sensor_aktivitas`) — satu baris per sapi, selalu berisi data terbaru (untuk Dashboard)
2. **Tabel riwayat** (`sensor_history`) — insert baru setiap kali data masuk, tidak pernah ditimpa (untuk halaman Riwayat & analisis tren)

## Instalasi

### Prasyarat
- PHP >= 8.3
- Composer
- Node.js & npm
- MySQL
- Python 3 (opsional, untuk simulator)

### Langkah-langkah

1. **Clone repository**
   ```bash
   git clone <url-repo-ini>
   cd moosmarthealthguard
   ```

2. **Install dependency PHP**
   ```bash
   composer install
   ```

3. **Install dependency JavaScript**
   ```bash
   npm install
   ```

4. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Konfigurasi database** di `.env`
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=monitoring_sapi
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   Buat database `monitoring_sapi` di MySQL terlebih dahulu, lalu jalankan migrasi:
   ```bash
   php artisan migrate
   ```

6. **Konfigurasi Telegram Bot** (opsional, untuk notifikasi alert)

   Buat bot baru lewat [@BotFather](https://t.me/BotFather) di Telegram untuk mendapatkan token, lalu ambil chat ID tujuan notifikasi melalui endpoint `getUpdates` dari Bot API. Isi di `.env`:
   ```
   TELEGRAM_BOT_TOKEN=your-bot-token
   TELEGRAM_CHAT_ID=your-chat-id
   ```
   Kalau tidak dikonfigurasi, aplikasi tetap berjalan normal — notifikasi Telegram hanya akan dilewati (dicatat sebagai warning di log).

7. **Build asset frontend**
   ```bash
   npm run dev
   ```

8. **Jalankan server**
   ```bash
   php artisan serve
   ```
   Aplikasi dapat diakses di `http://localhost:8000`

## Menjalankan Simulator Sensor

Untuk mengirim data sensor simulasi tanpa perangkat IoT fisik:

```bash
cd simulator
pip install requests
python simulator_sensor.py
```

Simulator akan mengirim data untuk 5 sapi setiap 10 detik ke endpoint `/api/sensor`, dengan kemungkinan acak menghasilkan kondisi tidak normal untuk menguji sistem alert.

## API Endpoints

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/sensor` | Kondisi terkini semua sapi |
| POST | `/api/sensor` | Kirim data sensor baru (dipakai simulator/perangkat IoT) |
| GET | `/api/sensor/history` | Riwayat data sensor. Query param opsional: `cow_id`, `limit` (maks 1000) |

Contoh kirim data sensor:
```bash
curl -X POST http://localhost:8000/api/sensor \
  -H "Content-Type: application/json" \
  -d '{"cow_id":"COW-001","nama":"Sapi 1","suhu_celsius":38.5,"kadar_oksigen_persen":96,"nilai_gerakan":2}'
```

## Ambang Batas Status

| Parameter | Normal | Waspada | Bahaya |
|---|---|---|---|
| Suhu tubuh | ≤ 39.5°C | 39.5–40.5°C | > 40.5°C |
| Kadar oksigen | ≥ 95% | 90–95% | < 90% |
| Tingkat gerakan (stres) | ≤ 2.5 | 2.5–5 | > 5 |

## Kontributor

Dikembangkan sebagai bagian dari program PKM-PI Belmawa Kemdikbud.