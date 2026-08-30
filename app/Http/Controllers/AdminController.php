<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function loginForm()
    {
        if (session()->has('admin_authenticated')) {
            return redirect('/');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $adminUsername = (string) config('app.admin_username');
        $adminPassword = (string) config('app.admin_password');

        if ($adminUsername === '' || $adminPassword === '') {
            if (app()->environment('production')) {
                abort(503, 'Kredensial admin belum dikonfigurasi.');
            }

            return back()->withErrors(['password' => 'Kredensial admin belum dikonfigurasi.']);
        }

        $valid = hash_equals($adminUsername, (string) $request->input('username'))
            && hash_equals($adminPassword, (string) $request->input('password'));

        if ($valid) {
            $request->session()->regenerate();
            session(['admin_authenticated' => true]);

            $this->notifyLogin($request, $request->username);

            return redirect('/');
        }

        return back()->withErrors(['password' => 'Username atau password admin salah.'])->withInput();
    }

    public function logout(Request $request)
    {
        session()->forget('admin_authenticated');

        return redirect('/');
    }

    public function create()
    {
        $students = $this->getStudents();
        $months = $this->academicMonths();
        $isAdmin = session()->has('admin_authenticated');

        return view('admin.transactions.create', compact('students', 'months', 'isAdmin'));
    }

    public function store(Request $request)
    {
        $type = $request->input('type');

        if ($type === 'expense') {
            $data = $request->validate([
                'transaction_date' => 'required|date',
                'payment_method' => 'required|in:transfer,cash',
                'recipient' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'description' => 'nullable|string',
                'proofs.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);

            $proof = $this->handleProofs($request->file('proofs', []));

            Transaction::create([
                'transaction_date' => $data['transaction_date'],
                'name' => $data['recipient'],
                'payment_method' => $data['payment_method'],
                'description' => $data['description'],
                'type' => 'expense',
                'amount' => $data['amount'],
                'recipient' => $data['recipient'],
                'proof_path' => $proof,
            ]);

            return redirect('/')->with('success', 'Transaksi pengeluaran berhasil disimpan.');
        }

        // Income
        $category = $request->input('category');
        $name = $this->resolveName($request);
        $paymentMethod = $request->input('payment_method');
        $description = $request->input('description');
        $date = $request->input('transaction_date');
        $proof = $this->handleProofs($request->file('proofs', []));

        if ($category === 'kas') {
            $request->validate(['months' => 'required|array|min:1']);
            $months = $request->input('months');
            $perMonth = 15000;

            foreach ($months as $m) {
                Transaction::create([
                    'transaction_date' => Carbon::parse($m.'-01')->startOfMonth(),
                    'name' => $name,
                    'payment_method' => $paymentMethod,
                    'description' => $description,
                    'type' => 'income',
                    'amount' => $perMonth,
                    'category' => 'kas',
                    'months' => [$m],
                    'proof_path' => $proof,
                ]);
            }

            return redirect('/')->with('success', count($months).' transaksi kas berhasil disimpan.');
        }

        if ($category === 'komite') {
            $request->validate(['amount' => 'required|numeric|min:0']);
            Transaction::create([
                'transaction_date' => $date,
                'name' => $name,
                'payment_method' => $paymentMethod,
                'description' => $description,
                'type' => 'income',
                'amount' => $request->input('amount'),
                'category' => 'komite',
                'months' => null,
                'proof_path' => $proof,
            ]);

            return redirect('/')->with('success', 'Transaksi komite berhasil disimpan.');
        }

        // lain-lain
        $request->validate(['amount' => 'required|numeric|min:0']);
        Transaction::create([
            'transaction_date' => $date,
            'name' => $name,
            'payment_method' => $paymentMethod,
            'description' => $description,
            'type' => 'income',
            'amount' => $request->input('amount'),
            'category' => 'lain_lain',
            'proof_path' => $proof,
        ]);

        return redirect('/')->with('success', 'Transaksi lain-lain berhasil disimpan.');
    }

    public function update(Request $request, Transaction $transaction)
    {
        $request->validate([
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'proofs.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $data = $request->only(['description', 'amount']);

        if ($request->hasFile('proofs')) {
            if ($transaction->proof_path) {
                Storage::disk('public')->delete($transaction->proof_path);
            }
            $data['proof_path'] = $this->handleProofs($request->file('proofs'));
        }

        $transaction->update($data);

        return redirect('/')->with('success', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->proof_path) {
            Storage::disk('public')->delete($transaction->proof_path);
        }
        $transaction->delete();

        return redirect('/')->with('success', 'Transaksi berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:transactions,id',
        ]);

        $transactions = Transaction::whereIn('id', $request->ids)->get();
        foreach ($transactions as $transaction) {
            if ($transaction->proof_path) {
                Storage::disk('public')->delete($transaction->proof_path);
            }
        }
        Transaction::whereIn('id', $request->ids)->delete();

        return redirect('/')->with('success', count($request->ids).' transaksi berhasil dihapus.');
    }

    private function resolveName(Request $request)
    {
        if ($request->input('name_source') === 'manual') {
            return $request->input('manual_name');
        }

        return $request->input('student_name');
    }

    private function notifyLogin(Request $request, string $username): void
    {
        try {
            app(TelegramService::class)->sendLoginAlert(
                $username,
                $request->ip() ?? 'unknown',
                now()->format('d-m-Y H:i:s')
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function backupIndex()
    {
        $dbPath = $this->databasePath();
        $uploadsPath = $this->uploadsPath();

        return view('admin.backup', [
            'dbExists' => file_exists($dbPath),
            'dbSize' => file_exists($dbPath) ? $this->humanBytes(filesize($dbPath)) : '0',
            'dbModified' => file_exists($dbPath) ? date('d-m-Y H:i:s', filemtime($dbPath)) : '-',
            'uploadsPath' => $uploadsPath,
            'uploadsExists' => is_dir($uploadsPath),
        ]);
    }

    public function backupDatabase()
    {
        $dbPath = $this->databasePath();
        if (! file_exists($dbPath)) {
            return back()->with('error', 'Database tidak ditemukan.');
        }

        return response()->download($dbPath, 'korlas_db_'.now()->format('Ymd_His').'.sqlite');
    }

    public function backupUploads()
    {
        $base = $this->uploadsPath();
        if (! is_dir($base)) {
            return back()->with('error', 'Folder bukti upload tidak ditemukan.');
        }

        $zip = new \ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'uploads');
        $zipPath = $tmp.'.zip';
        @unlink($zipPath);

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Gagal membuat arsip backup.');
        }

        $this->addDirToZip($zip, $base, '');
        $zip->close();

        return response()->download($zipPath, 'korlas_uploads_'.now()->format('Ymd_His').'.zip')
            ->deleteFileAfterSend(true);
    }

    public function restoreDatabase(Request $request)
    {
        $request->validate([
            'db_file' => 'required|file',
        ]);

        $uploaded = $request->file('db_file');

        if (strtolower($uploaded->getClientOriginalExtension()) !== 'sqlite') {
            return back()->with('error', 'File backup harus berakhiran .sqlite/.db');
        }

        $temp = $uploaded->getRealPath();
        $dbPath = $this->databasePath();

        $check = new \PDO('sqlite:'.$temp);
        $check->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $tables = $check->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($tables)) {
            return back()->with('error', 'File bukan database SQLite yang valid.');
        }

        $backup = $dbPath.'.pre_restore_'.now()->format('Ymd_His');
        if (file_exists($dbPath)) {
            copy($dbPath, $backup);
        }

        if (! copy($temp, $dbPath)) {
            return back()->with('error', 'Gagal menyalin file restore.');
        }
        @chmod($dbPath, 0664);

        return back()->with('success', 'Database berhasil direstorasi. Backup lama disimpan: '.basename($backup));
    }

    public function sendTelegramReport(Request $request)
    {
        $kategori = $request->input('kategori', 'kas') === 'komite' ? 'komite' : 'kas';

        $exit = Artisan::call('telegram:report', ['--kategori' => $kategori]);

        if ($exit === 0) {
            return back()->with('success', 'Laporan rekap '.($kategori === 'komite' ? 'Komite' : 'Kas Kelas').' berhasil dikirim ke Telegram.');
        }

        return back()->with('error', Artisan::output() ?: 'Gagal mengirim laporan ke Telegram.');
    }

    private function databasePath(): string
    {
        $path = config('database.connections.'.config('database.default').'.database');
        if (is_string($path) && str_starts_with($path, 'sqlite:')) {
            return substr($path, 7);
        }

        return $path;
    }

    private function uploadsPath(): string
    {
        return storage_path('app/public');
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $prefix): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $local = ltrim($prefix.'/'.substr($file->getPathname(), strlen($dir)), '/');
            $zip->addFile($file->getPathname(), $local);
        }
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }

    private function handleProofs(array $files): ?string
    {
        $files = array_filter($files);
        if (empty($files)) {
            return null;
        }

        if (count($files) === 1) {
            return reset($files)->store('proofs', 'public');
        }

        $images = [];
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $images[] = storage_path('app/public/'.$file->store('proofs', 'public'));
            }
        }

        if (empty($images)) {
            return reset($files)->store('proofs', 'public');
        }

        $pdfName = 'proofs/proof_'.time().'_'.uniqid().'.pdf';
        $this->buildPdf($images, storage_path('app/public/'.$pdfName));

        return $pdfName;
    }

    private function buildPdf(array $images, string $outputPath): void
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetAutoPageBreak(true);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        foreach ($images as $image) {
            $pdf->AddPage();
            $pdf->Image($image, 10, 10, 190);
        }

        $pdf->Output($outputPath, 'F');
    }

    private function getStudents(): array
    {
        $path = storage_path('app/siswa.txt');
        if (! file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return array_values(array_filter(array_map('trim', $lines)));
    }

    private function academicMonths(): array
    {
        $months = [];
        $startYear = now()->month >= 7 ? now()->year : now()->year - 1;
        $cursor = Carbon::create($startYear, 7, 1);

        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $months[$key] = $cursor->translatedFormat('M Y');
            $cursor->addMonth();
        }

        return $months;
    }
}
