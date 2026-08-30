<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $token;

    protected string $chatId;

    public function __construct()
    {
        $this->token = (string) config('services.telegram.bot_token');
        $this->chatId = (string) config('services.telegram.chat_id');
    }

    public function enabled(): bool
    {
        return $this->token !== '' && $this->chatId !== '';
    }

    public function sendMessage(string $text, ?string $parseMode = 'HTML'): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        try {
            $response = Http::timeout(20)->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed: '.$e->getMessage());

            return false;
        }
    }

    public function sendDocument(string $filePath, string $filename, string $caption = ''): bool
    {
        if (!$this->enabled() || !file_exists($filePath)) {
            return false;
        }

        try {
            $response = Http::timeout(30)
                ->attach('document', fopen($filePath, 'r'), $filename)
                ->post("https://api.telegram.org/bot{$this->token}/sendDocument", [
                    'chat_id' => $this->chatId,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram sendDocument failed: '.$e->getMessage());

            return false;
        }
    }

    public function sendLoginAlert(string $username, string $ip, string $time): void
    {
        $this->sendMessage(
            "🔐 <b>Login Admin — Korlas</b>\n"
            ."👤 Username: <code>{$username}</code>\n"
            ."🌐 IP: <code>{$ip}</code>\n"
            ."🕐 Waktu: {$time} (WIB)"
        );
    }

    public function sendDailyReport(string $rekapText, string $pdfPath, string $pdfName): void
    {
        $this->sendMessage($rekapText);
        $this->sendDocument($pdfPath, $pdfName, "📄 Rekap Pembayaran Siswa — ".now()->format('d-m-Y'));
    }
}
