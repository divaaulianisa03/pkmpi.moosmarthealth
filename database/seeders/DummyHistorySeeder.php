<?php

namespace Database\Seeders;

use App\Models\SensorAktivitas;
use App\Models\SensorHistory;
use App\Models\SensorKadarOksigen;
use App\Models\SensorSuhu;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeder dummy data MooSmartHealthGuard, terdiri dari 2 periode "rekaman":
 *
 * Model data: JADWAL BLOK PER HARI (bukan kurva yang jalan terus sepanjang
 * episode). Tiap hari punya SATU kategori tetap (normal/waspada/bahaya),
 * nilainya STABIL sepanjang hari itu (cuma noise kecil, tidak pernah
 * menyentuh batas kategori sebelah). Transisi HALUS hanya terjadi di
 * beberapa jam pertama saat ganti hari ke kategori berbeda (blend dari
 * target hari kemarin ke target hari ini) - supaya tidak ada lompatan
 * kasar tengah malam, tapi juga tidak "bercabut-cabut" berubah-ubah dalam
 * satu hari yang sama.
 *
 * Durasi episode sakit = ACAK 3-5 HARI tiap kali seeder dijalankan (bukan
 * dipatok manual), sesuai riset penyakit demam sapi paling umum di
 * lapangan (Bovine Ephemeral Fever / "Three Day Sickness": onset
 * mendadak, sembuh total dalam 2-5 hari, rata-rata 3 hari) - jadi rentang
 * 3-5 hari ini realistis, bukan asal comot.
 *
 * PERIODE 1 : 13 Juli 2026 - 30 Juli 2026 (COW-001)
 *   Mulai sakit 25 Juli, durasi acak 3-5 hari (lihat log/console saat
 *   seeder dijalankan untuk tanggal pastinya), puncak WASPADA saja
 *   (TIDAK PERNAH sampai bahaya). Sebelum & sesudah episode: normal.
 *   Sembuh total & selamanya - single episode, tidak berulang lagi.
 *
 * (GAP)     : 31 Juli - 7 Agustus 2026 -> SENGAJA TIDAK ADA DATA
 *             (mensimulasikan alat belum aktif / belum terpasang lagi)
 *
 * PERIODE 2 : 8 Agustus 2026 - waktu seeder dijalankan (COW-006)
 *   Mulai sakit 10 Agustus, durasi acak 3-5 hari (lihat log/console),
 *   puncak WASPADA saja (TIDAK PERNAH sampai bahaya). Sembuh TOTAL &
 *   SELAMANYA setelahnya - single episode, tidak berulang, termasuk saat
 *   dilanjutkan simulator_sensor_background.py.
 *   PENTING: karena durasinya ACAK, setelah menjalankan seeder WAJIB
 *   salin tanggal pasti yang di-print ke console ke JADWAL_PERIODE_2 di
 *   simulator_sensor_background.py, supaya data live nyambung persis.
 *
 * Klasifikasi status (mengikuti threshold di SensorController) MUNCUL
 * SECARA ALAMI dari nilai (bukan dipatok manual):
 * - Suhu   : normal < 39.3 | waspada 39.3 - 40.3 | bahaya >= 40.4
 * - Oksigen: normal >= 95  | waspada 90 - 94      | bahaya < 90
 * - Gerakan (dipetakan ke level_stress): rendah <=2.5 | sedang 2.5-5 | tinggi >5
 *   (saat sapi sakit, gerakan dibuat makin RENDAH -> makin lesu)
 *
 * PENTING: hanya SATU sapi yang ikut jadwal sakit per periode. 6 sapi
 * lainnya SELALU normal (random kecil di sekitar baseline sehat).
 *
 * Cara pakai:
 * 1. Taruh file ini di: database/seeders/DummyHistorySeeder.php
 * 2. Kosongkan dulu data lama (jalankan baris ini SATU-SATU di
 *    `php artisan tinker`, bukan lewat --execute biar tidak kepotong):
 *    DB::table('sensor_history')->truncate();
 *    DB::table('sensor_suhu')->truncate();
 *    DB::table('sensor_kadar_oksigen')->truncate();
 *    DB::table('sensor_aktivitas')->truncate();
 * 3. Jalankan: php artisan db:seed --class=DummyHistorySeeder
 */
class DummyHistorySeeder extends Seeder
{
    // Interval antar data (menit) untuk data historis. 240 = tiap 4 jam (6x/hari/sapi).
    private const INTERVAL_MENIT = 240;

    private const SAPI_SAKIT_PERIODE_1 = 'COW-001';
    private const SAPI_SAKIT_PERIODE_2 = 'COW-006';

    // Nilai target tiap kategori (dipakai untuk sapi yang sakit). Sengaja
    // ditaruh di TENGAH rentang kategorinya (jauh dari batas), supaya noise
    // kecil tidak pernah bikin statusnya bocor ke kategori sebelah.
    private const KATEGORI_TARGET = [
        'normal' => ['suhu' => 38.6, 'oksigen' => 97.0, 'gerakan' => 1.7],
        'waspada' => ['suhu' => 39.8, 'oksigen' => 92.0, 'gerakan' => 1.3],
        'bahaya' => ['suhu' => 40.9, 'oksigen' => 85.0, 'gerakan' => 0.45],
    ];

    // Rentang noise acak murni (kecil, tekstur random tiap pembacaan) +/-
    // di atas nilai target/wobble. Dikecilkan supaya wobble+noise gabungan
    // tetap aman dari batas kategori (lihat WOBBLE_AMPLITUDE di bawah).
    private const NOISE = ['suhu' => 0.1, 'oksigen' => 0.4, 'gerakan' => 0.05];

    // Amplitudo gelombang naik-turun (wobble) SAAT SEDANG SAKIT (waspada/
    // bahaya) - bikin nilainya gak flat rata, tapi berayun halus di dalam
    // rentang kategorinya (turun-turun-sedikit naik-turun lagi), TIDAK
    // dipakai saat 'normal' (sapi sehat memang stabil, gak perlu berayun).
    // Amplitudo dihitung supaya wobble + noise masih AMAN dari batas
    // kategori sebelah.
    private const WOBBLE_AMPLITUDE = [
        'waspada' => ['suhu' => 0.3, 'oksigen' => 1.5, 'gerakan' => 0.35],
        'bahaya' => ['suhu' => 0.35, 'oksigen' => 3.0, 'gerakan' => 0.15],
    ];

    // Panjang 1 gelombang wobble (jam). ~16 jam supaya dalam episode sakit
    // 3-5 hari kelihatan beberapa kali naik-turun, bukan cuma 1 kurva polos.
    private const PERIODE_WOBBLE_JAM = 16;

    // Jam ke berapa dalam sehari (0,4,8,...,20) transisi ke kategori hari ini
    // dianggap SELESAI (full). Sebelum itu, nilainya di-blend dari target
    // hari kemarin.
    private const JAM_TRANSISI_SELESAI = 8;

    // Tanggal mulai sakit tiap periode (durasi episode DIACAK 3-5 hari saat
    // run(), lihat buatJadwalEpisode()). Tanggal di luar blok yang terbentuk
    // otomatis dianggap 'normal'.
    private const MULAI_SAKIT_PERIODE_1 = '2026-07-25';
    private const MULAI_SAKIT_PERIODE_2 = '2026-08-10';
    private const DURASI_SAKIT_MIN_HARI = 3;
    private const DURASI_SAKIT_MAX_HARI = 5;

    // Rentang untuk sapi yang SELALU sehat (6 sapi lain, random kecil).
    private const RANGE_NORMAL = ['suhu' => [38.0, 39.2], 'oksigen' => [95, 99], 'gerakan' => [1.0, 2.4]];

    private array $sapiList = [
        ['cow_id' => 'COW-001', 'nama' => 'Sapi 1'],
        ['cow_id' => 'COW-002', 'nama' => 'Sapi 2'],
        ['cow_id' => 'COW-003', 'nama' => 'Sapi 3'],
        ['cow_id' => 'COW-004', 'nama' => 'Sapi 4'],
        ['cow_id' => 'COW-005', 'nama' => 'Sapi 5'],
        ['cow_id' => 'COW-006', 'nama' => 'Sapi 6'],
        ['cow_id' => 'COW-007', 'nama' => 'Sapi 7'],
    ];

    public function run(): void
    {
        $historyRows = [];
        $terakhirPerSapi = [];
        $totalDibuat = 0;

        // Durasi episode sakit diacak 3-5 hari (sesuai riset "Three Day
        // Sickness" pada sapi: sembuh total dalam 2-5 hari). Dibuat sekali
        // di awal supaya konsisten dipakai untuk semua baris data & untuk
        // ditampilkan di ringkasan console di akhir.
        $durasiSakit1 = random_int(self::DURASI_SAKIT_MIN_HARI, self::DURASI_SAKIT_MAX_HARI);
        $durasiSakit2 = random_int(self::DURASI_SAKIT_MIN_HARI, self::DURASI_SAKIT_MAX_HARI);
        $jadwal1 = $this->buatJadwalEpisode(self::MULAI_SAKIT_PERIODE_1, $durasiSakit1, 'waspada');
        $jadwal2 = $this->buatJadwalEpisode(self::MULAI_SAKIT_PERIODE_2, $durasiSakit2, 'waspada');

        // ================= PERIODE 1: 13 - 30 Juli 2026 =================
        $mulai1 = Carbon::create(2026, 7, 13, 0, 0, 0);
        $selesai1 = Carbon::create(2026, 7, 30, 23, 59, 59);

        $waktu = $mulai1->copy();
        while ($waktu->lte($selesai1)) {
            foreach ($this->sapiList as $sapi) {
                $data = $sapi['cow_id'] === self::SAPI_SAKIT_PERIODE_1
                    ? $this->generateDataTerjadwal($waktu, $jadwal1)
                    : $this->generateDataNormal();

                $row = $this->buatRow($sapi, $data, $waktu);
                $historyRows[] = $row;
                $terakhirPerSapi[$sapi['cow_id']] = $row;
                $totalDibuat++;

                if (count($historyRows) >= 500) {
                    SensorHistory::insert($historyRows);
                    $historyRows = [];
                }
            }

            $waktu->addMinutes(self::INTERVAL_MENIT);
        }

        // ================= GAP: 31 Juli - 7 Agustus 2026 (kosong) =================
        // Sengaja tidak ada data di sini.

        // ================= PERIODE 2: 8 Agustus 2026 - sekarang =================
        $mulai2 = Carbon::create(2026, 8, 8, 0, 0, 0);
        $selesai2 = Carbon::now();

        if ($mulai2->lte($selesai2)) {
            $waktu = $mulai2->copy();
            while ($waktu->lte($selesai2)) {
                foreach ($this->sapiList as $sapi) {
                    $data = $sapi['cow_id'] === self::SAPI_SAKIT_PERIODE_2
                        ? $this->generateDataTerjadwal($waktu, $jadwal2)
                        : $this->generateDataNormal();

                    $row = $this->buatRow($sapi, $data, $waktu);
                    $historyRows[] = $row;
                    $terakhirPerSapi[$sapi['cow_id']] = $row;
                    $totalDibuat++;

                    if (count($historyRows) >= 500) {
                        SensorHistory::insert($historyRows);
                        $historyRows = [];
                    }
                }

                $waktu->addMinutes(self::INTERVAL_MENIT);
            }
        }

        if (!empty($historyRows)) {
            SensorHistory::insert($historyRows);
        }

        // Update tabel status "terkini" (sensor_suhu, sensor_kadar_oksigen, sensor_aktivitas)
        // berdasarkan data paling akhir tiap sapi.
        foreach ($terakhirPerSapi as $cowId => $row) {
            SensorSuhu::updateOrCreate(
                ['cow_id' => $cowId],
                ['nama' => $row['nama'], 'suhu_celsius' => $row['suhu_celsius'], 'status' => $row['status_suhu'], 'timestamp' => $row['recorded_at']]
            );

            SensorKadarOksigen::updateOrCreate(
                ['cow_id' => $cowId],
                ['nama' => $row['nama'], 'kadar_oksigen_persen' => $row['kadar_oksigen_persen'], 'status' => $row['status_oksigen'], 'timestamp' => $row['recorded_at']]
            );

            SensorAktivitas::updateOrCreate(
                ['cow_id' => $cowId],
                ['nama' => $row['nama'], 'aktivitas' => 'gerakan', 'intensitas' => $row['level_stress'], 'timestamp' => $row['recorded_at']]
            );
        }

        $tglMulai1 = $jadwal1[0][0];
        $tglAkhir1 = $jadwal1[0][1];
        $tglMulai2 = $jadwal2[0][0];
        $tglAkhir2 = $jadwal2[0][1];

        $this->command->info("Selesai! {$totalDibuat} baris dummy data berhasil dibuat.");
        $this->command->info("Periode 1: 13-30 Juli 2026 ({$this->sapiList[0]['cow_id']} = " . self::SAPI_SAKIT_PERIODE_1 . " waspada {$tglMulai1} s/d {$tglAkhir1} [{$durasiSakit1} hari], sembuh selamanya)");
        $this->command->info('Gap kosong: 31 Juli - 7 Agustus 2026');
        $this->command->info('Periode 2: 8 Agustus 2026 - sekarang (' . self::SAPI_SAKIT_PERIODE_2 . " waspada {$tglMulai2} s/d {$tglAkhir2} [{$durasiSakit2} hari], sembuh selamanya)");
        $this->command->warn("PENTING: salin tanggal Periode 2 di atas ({$tglMulai2} s/d {$tglAkhir2}) ke JADWAL_PERIODE_2 di simulator_sensor_background.py supaya data live nyambung persis!");
    }

    /**
     * Bikin jadwal 1 blok episode sakit: [tanggal_mulai_sakit,
     * tanggal_mulai_sakit + $durasiHari - 1, $kategori]. Di luar rentang
     * ini otomatis 'normal' (lihat kategoriUntukTanggal - fallback normal).
     */
    private function buatJadwalEpisode(string $tanggalMulaiSakit, int $durasiHari, string $kategori): array
    {
        $mulai = Carbon::parse($tanggalMulaiSakit);
        $akhir = $mulai->copy()->addDays($durasiHari - 1);

        return [
            [$mulai->format('Y-m-d'), $akhir->format('Y-m-d'), $kategori],
        ];
    }

    /** Cari kategori untuk 1 tanggal tertentu berdasarkan daftar blok jadwal. Default 'normal'. */
    private function kategoriUntukTanggal(Carbon $tanggal, array $jadwal): string
    {
        $tglStr = $tanggal->format('Y-m-d');
        foreach ($jadwal as [$mulai, $selesai, $kategori]) {
            if ($tglStr >= $mulai && $tglStr <= $selesai) {
                return $kategori;
            }
        }

        return 'normal';
    }

    /**
     * Nilai sensor untuk sapi yang ikut jadwal, di waktu $waktu. Kalau
     * kategori hari ini sama dengan kemarin: nilai berayun (wobble) di
     * sekitar target kategori itu sepanjang hari (turun-turun-sedikit
     * naik-turun lagi, TIDAK pernah keluar rentang kategorinya). Kalau beda
     * (baru ganti hari ke kategori lain): di jam-jam awal hari (00:00 s.d.
     * JAM_TRANSISI_SELESAI) nilainya di-blend halus dari target kemarin ke
     * target hari ini (wobble ikut fade-in bareng blend-nya), baru setelah
     * itu berayun penuh di kategori hari ini. Saat kategorinya 'normal'
     * tidak ada wobble (sapi sehat memang stabil).
     */
    private function generateDataTerjadwal(Carbon $waktu, array $jadwal): array
    {
        $tanggalIni = $waktu->copy()->startOfDay();
        $tanggalKemarin = $tanggalIni->copy()->subDay();

        $kategoriIni = $this->kategoriUntukTanggal($tanggalIni, $jadwal);
        $kategoriKemarin = $this->kategoriUntukTanggal($tanggalKemarin, $jadwal);

        $targetIni = self::KATEGORI_TARGET[$kategoriIni];

        if ($kategoriIni === $kategoriKemarin) {
            $blend = 1.0;
            $targetKemarin = $targetIni;
        } else {
            $targetKemarin = self::KATEGORI_TARGET[$kategoriKemarin];
            $jam = $waktu->hour;
            $blend = $jam >= self::JAM_TRANSISI_SELESAI
                ? 1.0
                : (1 - cos(M_PI * $jam / self::JAM_TRANSISI_SELESAI)) / 2;
        }

        $suhu = $targetKemarin['suhu'] + $blend * ($targetIni['suhu'] - $targetKemarin['suhu']);
        $oksigen = $targetKemarin['oksigen'] + $blend * ($targetIni['oksigen'] - $targetKemarin['oksigen']);
        $gerakan = $targetKemarin['gerakan'] + $blend * ($targetIni['gerakan'] - $targetKemarin['gerakan']);

        // Wobble: gelombang naik-turun halus, HANYA saat kategori hari ini
        // sedang sakit (waspada/bahaya), fade-in mengikuti $blend supaya
        // tetap mulus pas baru ganti hari.
        if (isset(self::WOBBLE_AMPLITUDE[$kategoriIni])) {
            $amp = self::WOBBLE_AMPLITUDE[$kategoriIni];
            $fase = $waktu->timestamp / 3600; // jam absolut, kontinu
            $wobble = sin(2 * M_PI * $fase / self::PERIODE_WOBBLE_JAM);

            $suhu += $amp['suhu'] * $wobble * $blend;
            $oksigen += $amp['oksigen'] * $wobble * $blend;
            $gerakan += $amp['gerakan'] * $wobble * $blend;
        }

        $suhu += $this->noise(self::NOISE['suhu']);
        $oksigen += $this->noise(self::NOISE['oksigen']);
        $gerakan += $this->noise(self::NOISE['gerakan']);

        return [
            'suhu_celsius' => round($suhu, 1),
            'kadar_oksigen_persen' => (int) round(max(0, min(100, $oksigen))),
            'nilai_gerakan' => round(max(0, $gerakan), 1),
        ];
    }

    /** Data untuk sapi yang selalu normal (random kecil di dalam rentang sehat). */
    private function generateDataNormal(): array
    {
        [$suhuMin, $suhuMax] = self::RANGE_NORMAL['suhu'];
        [$oksigenMin, $oksigenMax] = self::RANGE_NORMAL['oksigen'];
        [$gerakanMin, $gerakanMax] = self::RANGE_NORMAL['gerakan'];

        return [
            'suhu_celsius' => round(mt_rand((int) ($suhuMin * 100), (int) ($suhuMax * 100)) / 100, 1),
            'kadar_oksigen_persen' => mt_rand((int) $oksigenMin, (int) $oksigenMax),
            'nilai_gerakan' => round(mt_rand((int) ($gerakanMin * 100), (int) ($gerakanMax * 100)) / 100, 1),
        ];
    }

    /** Noise acak kecil +/- $amplitude. */
    private function noise(float $amplitude): float
    {
        return (mt_rand(-1000, 1000) / 1000) * $amplitude;
    }

    private function buatRow(array $sapi, array $data, Carbon $waktu): array
    {
        $statusSuhu = $this->statusSuhu($data['suhu_celsius']);
        $statusOksigen = $this->statusOksigen($data['kadar_oksigen_persen']);
        $levelStress = $this->levelStress($data['nilai_gerakan']);

        return [
            'cow_id' => $sapi['cow_id'],
            'nama' => $sapi['nama'],
            'suhu_celsius' => $data['suhu_celsius'],
            'status_suhu' => $statusSuhu,
            'kadar_oksigen_persen' => $data['kadar_oksigen_persen'],
            'status_oksigen' => $statusOksigen,
            'nilai_gerakan' => $data['nilai_gerakan'],
            'level_stress' => $levelStress,
            'recorded_at' => $waktu->copy(),
        ];
    }

    private function statusSuhu(float $suhu): string
    {
        if ($suhu >= 40.4) return 'danger';
        if ($suhu >= 39.3) return 'warning';
        return 'normal';
    }

    private function statusOksigen(int $persen): string
    {
        if ($persen < 90) return 'danger';
        if ($persen < 95) return 'warning';
        return 'normal';
    }

    private function levelStress(float $gerakan): string
    {
        if ($gerakan > 5) return 'tinggi';
        if ($gerakan > 2.5) return 'sedang';
        return 'rendah';
    }
}