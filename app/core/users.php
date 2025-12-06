<?php
// User repository helpers for Yojaka.

function yojaka_users_file_path(): string
{
    $systemDir = yojaka_config('paths.data_path') . '/system';
    if (!is_dir($systemDir)) {
        mkdir($systemDir, 0777, true);
    }

    return $systemDir . '/users.json';
}

function yojaka_load_users(): array
{
    $filePath = yojaka_users_file_path();

    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || $content === '') {
        return [];
    }

    $data = json_decode($content, true);

    if (!is_array($data)) {
        // If decoding fails, return an empty array to avoid breaking the app.
        return [];
    }

    return $data;
}

function yojaka_save_users(array $users): bool
{
    $filePath = yojaka_users_file_path();
    $json = json_encode($users, JSON_PRETTY_PRINT);

    return file_put_contents($filePath, $json, LOCK_EX) !== false;
}

function yojaka_find_user_by_username(string $username): ?array
{
    $users = yojaka_load_users();

    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }

    return null;
}

function yojaka_find_user_by_id(string $id): ?array
{
    $users = yojaka_load_users();

    foreach ($users as $user) {
        if (isset($user['id']) && $user['id'] === $id) {
            return $user;
        }
    }

    return null;
}

function yojaka_generate_user_id(array $users): string
{
    $maxNumber = 0;

    foreach ($users as $user) {
        if (isset($user['id']) && preg_match('/u_(\d+)/', $user['id'], $matches)) {
            $num = (int)$matches[1];
            if ($num > $maxNumber) {
                $maxNumber = $num;
            }
        }
    }

    $nextNumber = $maxNumber + 1;
    return 'u_' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

function yojaka_add_user(array $userData): ?array
{
    $users = yojaka_load_users();
    $userData['id'] = $userData['id'] ?? yojaka_generate_user_id($users);
    $users[] = $userData;

    if (yojaka_save_users($users)) {
        return $userData;
    }

    return null;
}

function yojaka_seed_superadmin(): void
{
    $users = yojaka_load_users();
    $hasSuperadmin = false;

    foreach ($users as $user) {
        if (isset($user['role']) && $user['role'] === 'superadmin') {
            $hasSuperadmin = true;
            break;
        }
    }

    if ($hasSuperadmin) {
        return;
    }

    $superadmin = [
        'id' => 'u_0001',
        'username' => 'superadmin',
        'display_name' => 'Super Administrator',
        'password_hash' => password_hash('ChangeMe@123', PASSWORD_DEFAULT),
        'role' => 'superadmin',
        'created_at' => date('c'),
        'status' => 'active',
    ];

    $users[] = $superadmin;
    yojaka_save_users($users);
}
