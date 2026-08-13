<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensorAktivitas;
use App\Models\SensorHistory;
use App\Models\SensorKadarOksigen;
use App\Models\SensorSuhu;
use App\Services\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SensorController extends Controller
{
    public function index(): JsonResponse
    {
        $data = DB::table('sensor_suhu as s')
            ->leftJoin('sensor_kadar_oksigen as o', 's.cow_id', '=', 'o.cow_id')
            ->leftJoin('sensor_aktivitas as a', 's.cow_id', '=', 'a.cow_id')
            ->orderBy('s.cow_id')
            ->select([
                's.cow_id',
                's.nama',
                's.suhu_celsius',
                's.status as status_suhu',
                'o.kadar_oksigen_persen',
                'o.status as status_oksigen',
                'a.aktivitas as nilai_gerakan',
                'a.intensitas as level_stress',
                's.timestamp',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min($limit, 1000));

        $query = SensorHistory::query()->orderByDesc('recorded_at');

        if ($request->filled('cow_id')) {
            $query->where('cow_id', $request->query('cow_id'));
        }

        // Filter bulan, format: YYYY-MM (mis. 2026-07 untuk Juli 2026)
        if ($request->filled('bulan')) {
            $bulan = $request->query('bulan');
            if (preg_match('/^\d{4}-\d{2}$/', $bulan)) {
                $query->whereRaw("DATE_FORMAT(recorded_at, '%Y-%m') = ?", [$bulan]);
            }
        }

        $data = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request, TelegramNotifier $telegram): JsonResponse
    {
        $validated = $request->validate([
            'cow_id' => ['required', 'string', 'max:10'],
            'nama' => ['nullable', 'string', 'max:20'],
            'suhu_celsius' => ['nullable', 'numeric'],
            'kadar_oksigen_persen' => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai_gerakan' => ['nullable', 'numeric'],
        ]);

        $cowId = $validated['cow_id'];
        $nama = $validated['nama'] ?? $cowId;
        $suhu = (float) ($validated['suhu_celsius'] ?? 0);
        $oksigen = (int) ($validated['kadar_oksigen_persen'] ?? 0);
        $gerakan = (float) ($validated['nilai_gerakan'] ?? 0);

        $statusSuhu = $this->statusSuhu($suhu);
        $statusOksigen = $this->statusOksigen($oksigen);
        $levelStress = $this->levelStress($gerakan);

        $now = now();

        SensorSuhu::updateOrCreate(
            ['cow_id' => $cowId],
            ['nama' => $nama, 'suhu_celsius' => $suhu, 'status' => $statusSuhu, 'timestamp' => $now]
        );

        SensorKadarOksigen::updateOrCreate(
            ['cow_id' => $cowId],
            ['nama' => $nama, 'kadar_oksigen_persen' => $oksigen, 'status' => $statusOksigen, 'timestamp' => $now]
        );

        SensorAktivitas::updateOrCreate(
            ['cow_id' => $cowId],
            ['nama' => $nama, 'aktivitas' => 'gerakan', 'intensitas' => $levelStress, 'timestamp' => $now]
        );

        SensorHistory::create([
            'cow_id' => $cowId,
            'nama' => $nama,
            'suhu_celsius' => $suhu,
            'status_suhu' => $statusSuhu,
            'kadar_oksigen_persen' => $oksigen,
            'status_oksigen' => $statusOksigen,
            'nilai_gerakan' => $gerakan,
            'level_stress' => $levelStress,
            'recorded_at' => $now,
        ]);

        if ($statusSuhu !== 'normal' || $statusOksigen !== 'normal' || $levelStress !== 'rendah') {
            $labelSuhu = $statusSuhu === 'danger' ? 'BAHAYA' : ($statusSuhu === 'warning' ? 'WASPADA' : 'Normal');
            $labelOksigen = $statusOksigen === 'danger' ? 'BAHAYA' : ($statusOksigen === 'warning' ? 'WASPADA' : 'Normal');
            $labelStress = $levelStress === 'tinggi' ? 'TINGGI' : ($levelStress === 'sedang' ? 'SEDANG' : 'Rendah');

            $pesan = "ALERT MooSmartHealthGuard\n\n";
            $pesan .= "Sapi: {$nama} ({$cowId})\n";
            $pesan .= "Suhu: {$suhu}C - {$labelSuhu}\n";
            $pesan .= "Kadar Oksigen: {$oksigen}% - {$labelOksigen}\n";
            $pesan .= "Tingkat Kestressan: {$labelStress}\n\n";
            $pesan .= 'Waktu: ' . $now->format('d/m/Y H:i:s') . "\n";
            $pesan .= 'Segera periksa kondisi sapi.';

            $telegram->kirim($pesan);
        }

        return response()->json([
            'success' => true,
            'status_suhu' => $statusSuhu,
            'status_oksigen' => $statusOksigen,
            'level_stress' => $levelStress,
        ]);
    }

    private function statusSuhu(float $suhu): string
    {
        if ($suhu >= 40.4) {
            return 'danger';
        }
        if ($suhu >= 39.3) {
            return 'warning';
        }

        return 'normal';
    }

    private function statusOksigen(int $persen): string
    {
        if ($persen < 90) {
            return 'danger';
        }
        if ($persen < 95) {
            return 'warning';
        }

        return 'normal';
    }

    private function levelStress(float $gerakan): string
    {
        if ($gerakan > 5) {
            return 'tinggi';
        }
        if ($gerakan > 2.5) {
            return 'sedang';
        }

        return 'rendah';
    }
}