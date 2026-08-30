<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use App\Models\Transaction;
use App\Http\Controllers\RekapController;
use Illuminate\Console\Command;

class TelegramReport extends Command
{
    protected $signature = 'telegram:report {--kategori=kas : Kategori rekap (kas|komite)}';

    protected $description = 'Kirim laporan rekap kas + PDF ke Telegram';

    public function handle(TelegramService $telegram): int
    {
        if (!$telegram->enabled()) {
            $this->warn('Telegram belum dikonfigurasi (token/chat id kosong).');

            return self::FAILURE;
        }

        $income = (float) Transaction::where('type', 'income')->sum('amount');
        $expense = (float) Transaction::where('type', 'expense')->sum('amount');
        $balance = $income - $expense;

        $now = now();
        $monthIncome = (float) Transaction::where('type', 'income')
            ->whereBetween('transaction_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->sum('amount');
        $monthExpense = (float) Transaction::where('type', 'expense')
            ->whereBetween('transaction_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->sum('amount');
        $monthBalance = $monthIncome - $monthExpense;

        $text = "📊 <b>Laporan Keuangan Kas Korlas</b>\n"
            ."🗓 Periode: {$now->format('d-m-Y H:i')} WIB\n"
            ."──────────────\n"
            ."<b>Bulan {$now->translatedFormat('F Y')}</b>\n"
            ."🟢 Pemasukan: <b>Rp ".number_format($monthIncome, 0, ',', '.')."</b>\n"
            ."🔴 Pengeluaran: <b>Rp ".number_format($monthExpense, 0, ',', '.')."</b>\n"
            ."💵 Saldo Bulan Ini: <b>Rp ".number_format($monthBalance, 0, ',', '.')."</b>\n"
            ."──────────────\n"
            ."<b>Total Keseluruhan</b>\n"
            ."🟢 Total Pemasukan: <b>Rp ".number_format($income, 0, ',', '.')."</b>\n"
            ."🔴 Total Pengeluaran: <b>Rp ".number_format($expense, 0, ',', '.')."</b>\n"
            ."💰 <b>Sisa Saldo: Rp ".number_format($balance, 0, ',', '.')."</b>";

        $sentMsg = $telegram->sendMessage($text);

        $this->info('Message report: '.($sentMsg ? 'OK' : 'FAILED'));

        $kategori = $this->option('kategori');
        $kategoriLabel = $kategori === 'komite' ? 'Komite' : 'Kas Kelas';

        $rekap = app(RekapController::class);
        $students = $rekap->getStudents();
        $months = $rekap->academicMonths();
        $paid = $rekap->paidMatrix($kategori);

        $pdf = $rekap->buildRekapPdf($students, $months, $paid, $kategoriLabel);

        $tmp = tempnam(sys_get_temp_dir(), 'rekap');
        $pdfPath = $tmp.'.pdf';
        @unlink($pdfPath);
        $pdf->Output($pdfPath, 'F');

        $pdfName = 'Rekap '.$kategoriLabel.' '.$now->format('Y-m-d').'.pdf';
        $sentDoc = $telegram->sendDocument($pdfPath, $pdfName, "📄 Rekap Pembayaran — {$kategoriLabel}");

        $this->info('Document report: '.($sentDoc ? 'OK' : 'FAILED'));

        if (file_exists($pdfPath)) {
            @unlink($pdfPath);
        }

        return ($sentMsg && $sentDoc) ? self::SUCCESS : self::FAILURE;
    }
}
