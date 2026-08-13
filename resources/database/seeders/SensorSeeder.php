<?php

namespace Database\Seeders;

use App\Models\SensorAktivitas;
use App\Models\SensorKadarOksigen;
use App\Models\SensorSuhu;
use Illuminate\Database\Seeder;

class SensorSeeder extends Seeder
{
    /**
     * Data contoh, setara dengan isi database.sql versi lama (7 ekor sapi).
     */
    public function run(): void
    {
        $sapi = [
            ['cow_id' => 'COW-001', 'nama' => 'Sapi 1', 'suhu' => 41.5, 'status_suhu' => 'danger', 'oksigen' => 84, 'status_oksigen' => 'danger', 'aktivitas' => 'tidak bergerak', 'intensitas' => 'rendah'],
            ['cow_id' => 'COW-002', 'nama' => 'Sapi 2', 'suhu' => 38.5, 'status_suhu' => 'normal', 'oksigen' => 97, 'status_oksigen' => 'normal', 'aktivitas' => 'makan', 'intensitas' => 'sedang'],
            ['cow_id' => 'COW-003', 'nama' => 'Sapi 3', 'suhu' => 38.5, 'status_suhu' => 'normal', 'oksigen' => 97, 'status_oksigen' => 'normal', 'aktivitas' => 'tidak bergerak', 'intensitas' => 'rendah'],
            ['cow_id' => 'COW-004', 'nama' => 'Sapi 4', 'suhu' => 38.5, 'status_suhu' => 'normal', 'oksigen' => 97, 'status_oksigen' => 'normal', 'aktivitas' => 'gelisah', 'intensitas' => 'tinggi'],
            ['cow_id' => 'COW-005', 'nama' => 'Sapi 5', 'suhu' => 38.5, 'status_suhu' => 'normal', 'oksigen' => 97, 'status_oksigen' => 'normal', 'aktivitas' => 'istirahat', 'intensitas' => 'rendah'],
            ['cow_id' => 'COW-006', 'nama' => 'Sapi 6', 'suhu' => 38.5, 'status_suhu' => 'normal', 'oksigen' => 97, 'status_oksigen' => 'normal', 'aktivitas' => 'makan', 'intensitas' => 'sedang'],
            ['cow_id' => 'COW-007', 'nama' => 'Sapi 7', 'suhu' => 38.5, 'status_suhu' => 'normal', 'oksigen' => 97, 'status_oksigen' => 'normal', 'aktivitas' => 'istirahat', 'intensitas' => 'rendah'],
        ];

        foreach ($sapi as $s) {
            SensorSuhu::updateOrCreate(
                ['cow_id' => $s['cow_id']],
                ['nama' => $s['nama'], 'suhu_celsius' => $s['suhu'], 'status' => $s['status_suhu'], 'timestamp' => now()]
            );

            SensorKadarOksigen::updateOrCreate(
                ['cow_id' => $s['cow_id']],
                ['nama' => $s['nama'], 'kadar_oksigen_persen' => $s['oksigen'], 'status' => $s['status_oksigen'], 'timestamp' => now()]
            );

            SensorAktivitas::updateOrCreate(
                ['cow_id' => $s['cow_id']],
                ['nama' => $s['nama'], 'aktivitas' => $s['aktivitas'], 'intensitas' => $s['intensitas'], 'timestamp' => now()]
            );
        }
    }
}