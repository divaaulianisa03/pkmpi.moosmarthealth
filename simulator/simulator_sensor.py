"""
Simulator Sensor Sapi - MooSmartHealthGuard (versi Laravel)
Script ini otomatis kirim data acak (suhu, kadar oksigen, gerakan) ke
API Laravel (routes/api.php -> SensorController@store) tiap beberapa detik,
biar dashboard keliatan "hidup" tanpa perlu hardware sensor asli.

Cara pakai:
    pip install requests
    python simulator_sensor.py

Tekan CTRL+C buat berhenti.
"""

import requests
import random
import time

# ==== KONFIGURASI ====
# Sesuaikan port dengan yang dipakai `php artisan serve` (default 8000)
API_URL = "http://localhost:8000/api/sensor"
INTERVAL_DETIK = 10  # jeda antar pengiriman data (detik)

SAPI_LIST = [
    {"cow_id": "COW-001", "nama": "Sapi 1"},
    {"cow_id": "COW-002", "nama": "Sapi 2"},
    {"cow_id": "COW-003", "nama": "Sapi 3"},
    {"cow_id": "COW-004", "nama": "Sapi 4"},
    {"cow_id": "COW-005", "nama": "Sapi 5"},
]

# Peluang kemunculan kondisi tidak normal (biar sesekali trigger alert Telegram)
PELUANG_TIDAK_NORMAL = 0.15  # 15% kemungkinan salah satu sapi kondisinya tidak normal ("danger")


def generate_data_normal():
    return {
        "suhu_celsius": round(random.uniform(37.5, 39.4), 1),
        "kadar_oksigen_persen": random.randint(95, 99),
        "nilai_gerakan": round(random.uniform(0.5, 2.4), 1),
    }


def generate_data_tidak_normal():
    return {
        "suhu_celsius": round(random.uniform(40.6, 42.0), 1),
        "kadar_oksigen_persen": random.randint(80, 89),
        "nilai_gerakan": round(random.uniform(5.1, 8.0), 1),
    }


def kirim_data(sapi):
    if random.random() < PELUANG_TIDAK_NORMAL:
        sensor = generate_data_tidak_normal()
        kondisi = "TIDAK NORMAL"
    else:
        sensor = generate_data_normal()
        kondisi = "normal"

    payload = {
        "cow_id": sapi["cow_id"],
        "nama": sapi["nama"],
        **sensor,
    }

    try:
        res = requests.post(API_URL, json=payload, timeout=5)
        print(f"[{kondisi}] {sapi['cow_id']} -> {payload} | Response: {res.status_code} {res.text}")
    except requests.exceptions.RequestException as e:
        print(f"Gagal kirim data untuk {sapi['cow_id']}: {e}")


def main():
    print("=== Simulator Sensor MooSmartHealthGuard (Laravel) ===")
    print(f"Target API : {API_URL}")
    print(f"Interval   : {INTERVAL_DETIK} detik")
    print("Tekan CTRL+C untuk berhenti.\n")

    try:
        while True:
            for sapi in SAPI_LIST:
                kirim_data(sapi)
            print("-" * 60)
            time.sleep(INTERVAL_DETIK)
    except KeyboardInterrupt:
        print("\nSimulator dihentikan.")


if __name__ == "__main__":
    main()