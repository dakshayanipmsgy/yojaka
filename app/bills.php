<?php
// Contractor bills helper functions

function bills_directory(): string
{
    global $config;
    return rtrim($config['bills_data_path'] ?? (YOJAKA_DATA_PATH . '/bills'), DIRECTORY_SEPARATOR);
}

function bills_file_path(): string
{
    global $config;
    $file = $config['bills_file'] ?? 'bills.json';
    return bills_directory() . DIRECTORY_SEPARATOR . $file;
}

function ensure_bills_storage(): void
{
    $dir = bills_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }
    $path = bills_file_path();
    if (!file_exists($path)) {
        save_bills([]);
    }
}

function load_bills(): array
{
    ensure_bills_storage();
    $path = bills_file_path();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_bills(array $bills): bool
{
    ensure_bills_storage();
    $path = bills_file_path();
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($bills, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return true;
}

function generate_next_bill_id(array $existing): string
{
    $prefix = get_id_prefix('bill', 'BILL');
    $numbers = array_map(function ($bill) use ($prefix) {
        $id = $bill['id'] ?? '';
        if (strpos($id, $prefix . '-') === 0) {
            return (int) substr($id, strlen($prefix) + 1);
        }
        return 0;
    }, $existing);
    $next = empty($numbers) ? 1 : (max($numbers) + 1);
    return sprintf('%s-%06d', $prefix, $next);
}

function calculate_bill_totals(array $items, array $deductions): array
{
    $subTotal = 0;
    foreach ($items as &$item) {
        $qty = (float) ($item['quantity'] ?? 0);
        $rate = (float) ($item['rate'] ?? 0);
        $item['amount'] = round($qty * $rate, 2);
        $subTotal += $item['amount'];
    }
    unset($item);

    $totalDeductions = 0;
    foreach ($deductions as &$deduction) {
        $deduction['amount'] = (float) ($deduction['amount'] ?? 0);
        $totalDeductions += $deduction['amount'];
    }
    unset($deduction);

    $net = $subTotal - $totalDeductions;

    return [
        'items' => $items,
        'deductions' => $deductions,
        'sub_total' => round($subTotal, 2),
        'total_deductions' => round($totalDeductions, 2),
        'net_payable' => round($net, 2),
    ];
}

// Placeholder for future AI integration for bill suggestions.
// function generate_bill_ai_suggestion(array $context): string {
//     return '';
// }

?>
