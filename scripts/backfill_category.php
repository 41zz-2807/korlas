<?php
/**
 * One-off backfill: tag imported income transactions (category & months were
 * stripped during backup import) so the Rekap report counts them.
 *
 * Heuristic:
 *   - income transaction with category NULL and amount 15000 -> Kas Kelas (Rp15.000/bulan)
 *   - months = [Y-m derived from transaction_date]
 *
 * Usage (inside container):
 *   php scripts/backfill_category.php <target.sqlite>
 */

$targetPath = $argv[1] ?? '/data/database.sqlite';

if (!file_exists($targetPath)) {
    fwrite(STDERR, "Target database not found: $targetPath\n");
    exit(1);
}

$db = new PDO('sqlite:' . $targetPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = $db->query(
    "SELECT id, name, transaction_date, amount FROM transactions WHERE type='income' AND category IS NULL"
)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No income transactions missing category. Nothing to do.\n";
    exit(0);
}

$stmt = $db->prepare("UPDATE transactions SET category = ?, months = ? WHERE id = ?");

$count = 0;
foreach ($rows as $r) {
    $month = substr($r['transaction_date'], 0, 7); // Y-m
    $months = json_encode([$month], JSON_UNESCAPED_UNICODE);
    $stmt->execute(['kas', $months, $r['id']]);
    $count++;
    echo "id={$r['id']} {$r['name']} -> kas/{$month}\n";
}

echo "Backfilled {$count} transaction(s) as Kas Kelas {$month}.\n";
