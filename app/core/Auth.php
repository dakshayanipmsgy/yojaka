<?php
namespace App\Core;

class Auth
{
    protected static function usersFilePath(): string
    {
        $config = $GLOBALS['config'] ?? [];
        $dataRoot = $config['paths']['data'] ?? __DIR__ . '/../../data';
        return $dataRoot . '/system/users.json';
    }

    protected static function loadUsers(): array
    {
        $file = self::usersFilePath();

        if (!file_exists($file)) {
            return [];
        }

        $contents = file_get_contents($file);
        $data = json_decode($contents, true);

        return is_array($data) ? $data : [];
    }

    protected static function saveUsers(array $users): void
    {
        $file = self::usersFilePath();
        file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);
    }

    public static function findUserByUsername(string $username): ?array
    {
        $users = self::loadUsers();

        foreach ($users as $user) {
            if (isset($user['username']) && $user['username'] === $username) {
                return $user;
            }
        }

        return null;
    }

    public static function attemptLogin(string $username, string $password): bool
    {
        $user = self::findUserByUsername($username);

        if ($user && isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'] ?? null;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? null;
            $_SESSION['logged_in_at'] = time();

            return true;
        }

        return false;
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
    }

    public static function currentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        $users = self::loadUsers();
        foreach ($users as $user) {
            if (isset($user['id']) && $user['id'] == $_SESSION['user_id']) {
                return $user;
            }
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'] ?? null,
        ];
    }

    public static function userCount(): int
    {
        return count(self::loadUsers());
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        session_start();
    }
}
