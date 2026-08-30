<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        BankAccount::insert([
            [
                'bank_name' => 'Bank Central Asia (BCA)',
                'account_number' => '1234567890',
                'account_holder' => 'Yayasan Korlas',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'bank_name' => 'Bank Rakyat Indonesia (BRI)',
                'account_number' => '0987654321',
                'account_holder' => 'Yayasan Korlas',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Announcement::insert([
            ['content' => 'Pembayaran iuran bulanan dapat dilakukan melalui transfer ke rekening yang tersedia.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['content' => 'Rapat tahunan akan dilaksanakan pada akhir bulan ini.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['content' => 'Saldo kas diperbarui setiap hari kerja.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $methods = ['Transfer Bank', 'Tunai', 'QRIS', 'E-Wallet'];
        $names = ['Bapak Anton', 'Ibu Siti', 'Bapak Budi', 'Ibu Dewi', 'Bapak Eko', 'Ibu Fitri', 'Bapak Galih', 'Ibu Hani'];
        $descriptions = [
            'Iuran bulanan anggota',
            'Donasi kegiatan sosial',
            'Pembelian alat tulis kantor',
            'Biaya listrik kantor',
            'Konsumsi rapat',
            'Perbaikan fasilitas',
            'Uang kas mingguan',
            'Sumbangan acara hari besar',
        ];

        $now = now();
        for ($m = 0; $m < 4; $m++) {
            $month = $now->copy()->subMonths($m);
            $daysInMonth = $month->daysInMonth;
            for ($d = 1; $d <= min($daysInMonth, 28); $d += 2) {
                $isIncome = rand(0, 1) === 1;
                Transaction::create([
                    'transaction_date' => $month->copy()->day($d),
                    'name' => $names[array_rand($names)],
                    'payment_method' => $methods[array_rand($methods)],
                    'description' => $descriptions[array_rand($descriptions)],
                    'type' => $isIncome ? 'income' : 'expense',
                    'amount' => $isIncome ? rand(100000, 2000000) : rand(50000, 1500000),
                ]);
            }
        }
    }
}
