<?php
/**
 * One-off importer: copies rows from an old "transaksi" table (backup .sqlite)
 * into the new Laravel "transactions" table.
 *
 * Usage (inside the container):
 *   php scripts/import_backup.php <backup.sqlite> <target.sqlite>
 *
 * Mapping:
 *   transaksi.tanggal   -> transactions.transaction_date
 *   transaksi.nama      -> transactions.name
 *   transaksi.tipe      -> transactions.type  (pemasukan=>income, pengeluaran=>expense)
 *   transaksi.jumlah    -> transactions.amount
 *   transaksi.deskripsi -> transactions.description
 *   transaksi.metode    -> transactions.payment_method
 *   bukti_file          -> dropped (only filenames, no real files in backup)
 */

$backupPath = $argv[1] ?? '/data/backup.sqlite';
$targetPath = $argv[2] ?? '/data/database.sqlite';

if (!file_exists($backupPath)) {
    fwrite(STDERR, "Backup file not found: $backupPath\n");
    exit(1);
}
if (!file_exists($targetPath)) {
    fwrite(STDERR, "Target database not found: $targetPath\n");
    exit(1);
}

$backup = new PDO("sqlite:$backupPath");
$backup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$target = new PDO("sqlite:$targetPath");
$target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Reading source rows...\n";
$rows = $backup->query("SELECT * FROM transaksi ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " rows in backup.\n";

echo "Clearing existing transactions table...\n";
$target->exec("DELETE FROM transactions");
$target->exec("DELETE FROM sqlite_sequence WHERE name='transactions'");

$insert = $target->prepare(
    "INSERT INTO transactions
        (transaction_date, name, type, amount, description, payment_method, proof_path, created_at, updated_at)
     VALUES
        (:date, :name, :type, :amount, :description, :method, NULL, :now, :now)"
);

$map = ['pemasukan' => 'income', 'pengeluaran' => 'expense'];
$now = date('Y-m-d H:i:s');
$count = 0;

foreach ($rows as $r) {
    $type = $map[strtolower(trim($r['tipe'] ?? ''))] ?? 'expense';
    $description = $r['deskripsi'] ?? null;
    if (empty($description) && !empty($r['kategori'])) {
        $description = $r['kategori'];
    }

    $insert->execute([
        ':date'        => $r['tanggal'],
        ':name'        => $r['nama'],
        ':type'        => $type,
        ':amount'      => $r['jumlah'],
        ':description' => $description,
        ':method'      => $r['metode'] ?? 'transfer',
        ':now'         => $now,
    ]);
    $count++;
}

echo "Imported $count transactions successfully.\n";
