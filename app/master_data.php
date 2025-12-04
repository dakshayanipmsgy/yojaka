<?php
function master_data_dir(): string
{
    return YOJAKA_DATA_PATH . '/master';
}

function master_contractors_path(): string
{
    return master_data_dir() . '/contractors.json';
}

function load_json_list(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_json_list(string $path, array $list): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }
    $result = false;
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        $result = fwrite($handle, json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return $result;
}

function load_contractors(): array
{
    return load_json_list(master_contractors_path());
}

function save_contractors(array $list): bool
{
    return save_json_list(master_contractors_path(), $list);
}

function next_master_id(array $items, string $prefix): string
{
    $max = 0;
    foreach ($items as $item) {
        if (!empty($item['id']) && preg_match('/' . preg_quote($prefix, '/') . '-(\d{4})/', $item['id'], $m)) {
            $num = (int) $m[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }
    $next = $max + 1;
    return sprintf('%s-%04d', $prefix, $next);
}

function import_contractors_from_csv(string $fileTmpPath): int
{
    $imported = 0;
    if (!is_readable($fileTmpPath)) {
        return 0;
    }
    $contractors = load_contractors();
    if (($handle = fopen($fileTmpPath, 'r')) !== false) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, 'strlen')) === 0) {
                continue;
            }
            [$name, $category, $address, $gstin, $pan, $phone, $email] = array_pad($row, 7, '');
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $contractors[] = [
                'id' => next_master_id($contractors, 'C'),
                'name' => $name,
                'category' => trim($category),
                'address' => trim($address),
                'gstin' => trim($gstin),
                'pan' => trim($pan),
                'phone' => trim($phone),
                'email' => trim($email),
                'active' => true,
            ];
            $imported++;
        }
        fclose($handle);
    }
    save_contractors($contractors);
    return $imported;
}

?>
