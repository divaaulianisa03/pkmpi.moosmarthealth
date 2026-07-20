<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    /**
     * Kirim pesan alert ke Telegram.
     * Token & chat ID diambil dari config/services.php -> .env
     * (tidak di-hardcode di kode seperti versi PHP lama).
     */
    public function kirim(string $pesan): bool
    {
        $token = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            Log::warning('Telegram belum dikonfigurasi (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID kosong di .env)');
            return false;
        }

        try {
            $response = Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $pesan,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi Telegram: ' . $e->getMessage());
            return false;
        }
    }
}
