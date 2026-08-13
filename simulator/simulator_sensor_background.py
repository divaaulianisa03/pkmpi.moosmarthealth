"""
Simulator Sensor Sapi - MooSmartHealthGuard (versi BACKGROUND / realtime terus-menerus)

Simulator ini dipakai SETELAH data historis (dari DummyHistorySeeder) selesai
di-seed, untuk melanjutkan data secara realtime tanpa batas waktu ("live"),
MENERUSKAN jadwal yang sama persis (bukan random) dengan DummyHistorySeeder.php.

Beda dengan simulator_sensor.py biasa:
- Semua output ditulis ke file log (simulator.log), BUKAN print ke console.
  Ini WAJIB kalau mau dijalankan lewat pythonw.exe (tanpa jendela console),
  karena pythonw tidak punya stdout - kalau tetap pakai print() dia akan crash.
- Ada auto-retry: kalau terjadi error tak terduga, simulator TIDAK berhenti,
  cukup dicatat di log lalu lanjut lagi setelah beberapa detik.
- Dirancang untuk dijalankan sebagai proses background yang "kekal" (jalan
  terus walau CMD/terminal yang memulainya sudah ditutup). Lihat README
  untuk cara menjalankannya, termasuk lewat jalankan_simulator.vbs.

PENTING - DURASI SAKIT DIACAK 3-5 HARI DI SEEDER (random_int), jadi TIDAK ADA
tanggal pasti yang tetap di sini secara default. Setelah menjalankan
`php artisan db:seed --class=DummyHistorySeeder`, seeder akan nge-print
tanggal PASTI yang kepilih untuk COW-006 (contoh: "waspada 2026-08-10 s/d
2026-08-13"). WAJIB salin tanggal itu ke JADWAL_PERIODE_2 di bawah SEBELUM
menjalankan simulator ini, kalau tidak datanya akan meleset/tidak nyambung
dengan histori.

Skenario (SAMA PERSIS dengan DummyHistorySeeder, JANGAN random lagi):
- Hanya SATU sapi yang ikut jadwal: COW-006.
- JADWAL_PERIODE_2 di bawah HARUS diisi manual, sama persis dengan tanggal
  yang di-print seeder saat dijalankan (lihat catatan di atas).
- Setelah tanggal terakhir di jadwal itu: sembuh SELAMANYA (fallback normal),
  single episode, TIDAK berulang lagi. Kalau tanggal hari ini sudah lewat
  tanggal terakhir di JADWAL_PERIODE_2, simulator otomatis cuma kirim normal
  terus untuk COW-006.
- Tiap hari statusnya STABIL (flat + noise kecil), transisi cuma halus di
  jam-jam awal (00:00-08:00) pas ganti hari ke kategori beda - SAMA seperti
  logika generateDataTerjadwal() di DummyHistorySeeder.php.
- 6 sapi lain (COW-001..005, COW-007) SELALU dikirim status normal. TIDAK ADA
  random sama sekali di sini, biar riwayatnya bersih (cuma 1 sapi sakit).

Catatan penting:
- Kalau nanti ganti sapi yang sakit atau jadwalnya, ubah SAPI_SAKIT dan
  JADWAL_PERIODE_2 di bawah, DAN samakan juga di DummyHistorySeeder.php
  supaya data historis & data live nyambung mulus.
- Simulator perlu API Laravel yang aktif (php artisan serve, atau nanti
  domain hosting) supaya data bisa terkirim.

Cara pakai manual (foreground, untuk tes):
    pip install requests
    python simulator_sensor_background.py

Untuk dijalankan di BACKGROUND (tidak mati saat terminal ditutup), pakai
jalankan_simulator.vbs (Windows) - lihat README.md.

Tekan CTRL+C buat berhenti (kalau dijalankan di foreground).
"""

import requests
import random
import math
import time
import logging
import os
from datetime import date, datetime

# ==== KONFIGURASI ====
API_URL = "http://localhost:8000/api/sensor"  # ganti ke domain hosting saat sudah online
INTERVAL_DETIK = 10  # jeda antar pengiriman data (detik)

SAPI_SAKIT = "COW-006"

# !!! WAJIB DIISI MANUAL !!! Salin dari output console seeder (durasinya
# diacak 3-5 hari tiap seeder dijalankan, jadi tanggal di bawah ini HARUS
# di-update tiap kali kamu re-seed). Tanggal di luar blok ini = 'normal'.
JADWAL_PERIODE_2 = [
    ("2026-08-10", "2026-08-14", "waspada"),
]

# Nilai target tiap kategori (SAMA dengan KATEGORI_TARGET di seeder PHP).
KATEGORI_TARGET = {
    "normal": {"suhu": 38.6, "oksigen": 97.0, "gerakan": 1.7},
    "waspada": {"suhu": 39.8, "oksigen": 92.0, "gerakan": 1.3},
    "bahaya": {"suhu": 40.9, "oksigen": 85.0, "gerakan": 0.45},
}

# Noise acak murni kecil (SAMA dengan NOISE di seeder PHP).
NOISE = {"suhu": 0.1, "oksigen": 0.4, "gerakan": 0.05}

# Amplitudo wobble (naik-turun halus) SAAT SEDANG SAKIT saja (SAMA dengan
# WOBBLE_AMPLITUDE di seeder PHP) - 'normal' tidak punya entri = tidak wobble.
WOBBLE_AMPLITUDE = {
    "waspada": {"suhu": 0.3, "oksigen": 1.5, "gerakan": 0.35},
    "bahaya": {"suhu": 0.35, "oksigen": 3.0, "gerakan": 0.15},
}
PERIODE_WOBBLE_JAM = 16  # panjang 1 gelombang wobble (jam), SAMA dengan seeder PHP

JAM_TRANSISI_SELESAI = 8

# Rentang untuk sapi yang selalu sehat (6 sapi lain, random kecil).
RANGE_NORMAL = {"suhu": (38.0, 39.2), "oksigen": (95, 99), "gerakan": (1.0, 2.4)}

LOG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "simulator.log")

SAPI_LIST = [
    {"cow_id": "COW-001", "nama": "Sapi 1"},
    {"cow_id": "COW-002", "nama": "Sapi 2"},
    {"cow_id": "COW-003", "nama": "Sapi 3"},
    {"cow_id": "COW-004", "nama": "Sapi 4"},
    {"cow_id": "COW-005", "nama": "Sapi 5"},
    {"cow_id": "COW-006", "nama": "Sapi 6"},
    {"cow_id": "COW-007", "nama": "Sapi 7"},
]

# ==== LOGGING (bukan print, supaya aman dijalankan via pythonw) ====
logging.basicConfig(
    filename=LOG_FILE,
    level=logging.INFO,
    format="%(asctime)s | %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)


def kategori_untuk_tanggal(tanggal: date) -> str:
    """Cari kategori untuk 1 tanggal berdasarkan JADWAL_PERIODE_2. Default 'normal'."""
    tgl_str = tanggal.isoformat()
    for mulai, selesai, kategori in JADWAL_PERIODE_2:
        if mulai <= tgl_str <= selesai:
            return kategori
    return "normal"


def noise(amplitude: float) -> float:
    return random.uniform(-amplitude, amplitude)


def generate_data_terjadwal(waktu: datetime) -> dict:
    """SAMA PERSIS logikanya dengan generateDataTerjadwal() di seeder PHP:
    berayun (wobble) di sekitar target kategori hari ini kalau lagi sakit
    (waspada/bahaya), flat kalau normal - kecuali di jam-jam awal hari saat
    kategori baru saja berubah dari kemarin, di situ nilainya (dan wobble-nya)
    di-blend/fade-in halus dari kondisi kemarin ke kondisi hari ini."""
    tanggal_ini = waktu.date()
    tanggal_kemarin = date.fromordinal(tanggal_ini.toordinal() - 1)

    kategori_ini = kategori_untuk_tanggal(tanggal_ini)
    kategori_kemarin = kategori_untuk_tanggal(tanggal_kemarin)

    target_ini = KATEGORI_TARGET[kategori_ini]

    if kategori_ini == kategori_kemarin:
        blend = 1.0
        target_kemarin = target_ini
    else:
        target_kemarin = KATEGORI_TARGET[kategori_kemarin]
        jam = waktu.hour
        if jam >= JAM_TRANSISI_SELESAI:
            blend = 1.0
        else:
            blend = (1 - math.cos(math.pi * jam / JAM_TRANSISI_SELESAI)) / 2

    suhu = target_kemarin["suhu"] + blend * (target_ini["suhu"] - target_kemarin["suhu"])
    oksigen = target_kemarin["oksigen"] + blend * (target_ini["oksigen"] - target_kemarin["oksigen"])
    gerakan = target_kemarin["gerakan"] + blend * (target_ini["gerakan"] - target_kemarin["gerakan"])

    amp = WOBBLE_AMPLITUDE.get(kategori_ini)
    if amp:
        fase = waktu.timestamp() / 3600  # jam absolut, kontinu
        wobble = math.sin(2 * math.pi * fase / PERIODE_WOBBLE_JAM)
        suhu += amp["suhu"] * wobble * blend
        oksigen += amp["oksigen"] * wobble * blend
        gerakan += amp["gerakan"] * wobble * blend

    suhu += noise(NOISE["suhu"])
    oksigen += noise(NOISE["oksigen"])
    gerakan += noise(NOISE["gerakan"])

    return {
        "suhu_celsius": round(suhu, 1),
        "kadar_oksigen_persen": int(round(max(0, min(100, oksigen)))),
        "nilai_gerakan": round(max(0, gerakan), 1),
    }


def generate_data_normal() -> dict:
    return {
        "suhu_celsius": round(random.uniform(*RANGE_NORMAL["suhu"]), 1),
        "kadar_oksigen_persen": random.randint(*RANGE_NORMAL["oksigen"]),
        "nilai_gerakan": round(random.uniform(*RANGE_NORMAL["gerakan"]), 1),
    }


def kirim_data(sapi: dict, data: dict):
    payload = {
        "cow_id": sapi["cow_id"],
        "nama": sapi["nama"],
        **data,
    }

    try:
        res = requests.post(API_URL, json=payload, timeout=5)
        logging.info(f"{sapi['cow_id']} -> {payload} | Response: {res.status_code} {res.text}")
    except requests.exceptions.RequestException as e:
        logging.warning(f"Gagal kirim data untuk {sapi['cow_id']}: {e}")


def main():
    logging.info("=== Simulator MooSmartHealthGuard (background) dimulai ===")
    logging.info(f"Target API   : {API_URL}")
    logging.info(f"Interval     : {INTERVAL_DETIK} detik")
    logging.info(f"Sapi sakit   : {SAPI_SAKIT} (jadwal blok, lihat JADWAL_PERIODE_2)")

    kategori_terakhir = None

    while True:
        try:
            waktu_sekarang = datetime.now()
            kategori_ini = kategori_untuk_tanggal(waktu_sekarang.date())
            if kategori_ini != kategori_terakhir:
                logging.info(f"Kategori {SAPI_SAKIT} hari ini -> {kategori_ini.upper()}")
                kategori_terakhir = kategori_ini

            for sapi in SAPI_LIST:
                data = generate_data_terjadwal(waktu_sekarang) if sapi["cow_id"] == SAPI_SAKIT else generate_data_normal()
                kirim_data(sapi, data)

            time.sleep(INTERVAL_DETIK)
        except KeyboardInterrupt:
            logging.info("Simulator dihentikan manual (CTRL+C).")
            break
        except Exception as e:
            # Jangan sampai simulator mati gara-gara error tak terduga.
            logging.error(f"Error tak terduga: {e}. Simulator tetap lanjut jalan.")
            time.sleep(5)


if __name__ == "__main__":
    main()