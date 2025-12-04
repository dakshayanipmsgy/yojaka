<?php
// Simple i18n helper for Yojaka v1.6

if (!isset($GLOBALS['i18n_locale'])) {
    $GLOBALS['i18n_locale'] = null;
}
if (!isset($GLOBALS['i18n_dictionary'])) {
    $GLOBALS['i18n_dictionary'] = [];
}

function i18n_data_path(): string
{
    global $config;
    $path = $config['i18n_data_path'] ?? null;
    if (!$path) {
        return YOJAKA_DATA_PATH . '/i18n';
    }
    return $path;
}

function i18n_available_languages(): array
{
    global $config;
    $codes = $config['i18n_available_languages'] ?? ['en'];
    $dir = i18n_data_path();
    if (is_dir($dir)) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') as $file) {
            $code = basename($file, '.json');
            if (!in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
    }
    return array_values(array_unique($codes));
}

function i18n_load_language(string $langCode): array
{
    $dir = i18n_data_path();
    $path = $dir . DIRECTORY_SEPARATOR . $langCode . '.json';
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function i18n_set_current_language(string $langCode): void
{
    global $config;
    $available = i18n_available_languages();
    $default = $config['i18n_default_lang'] ?? 'en';
    $selected = in_array($langCode, $available, true) ? $langCode : $default;
    $_SESSION['preferred_language'] = $selected;
    $GLOBALS['i18n_locale'] = $selected;
    $GLOBALS['i18n_dictionary'] = i18n_load_language($selected);
    if ($selected !== $default) {
        $GLOBALS['i18n_dictionary_fallback'] = i18n_load_language($default);
    } else {
        $GLOBALS['i18n_dictionary_fallback'] = [];
    }
}

function i18n_current_language(): string
{
    global $config;
    if (!empty($GLOBALS['i18n_locale'])) {
        return $GLOBALS['i18n_locale'];
    }
    return $config['i18n_default_lang'] ?? 'en';
}

function i18n_get(string $key, array $placeholders = []): string
{
    $dict = $GLOBALS['i18n_dictionary'] ?? [];
    $fallbackDict = $GLOBALS['i18n_dictionary_fallback'] ?? [];
    $value = $dict[$key] ?? ($fallbackDict[$key] ?? $key);
    if (!empty($placeholders)) {
        foreach ($placeholders as $ph => $val) {
            $value = str_replace('{' . $ph . '}', (string) $val, $value);
        }
    }
    return $value;
}

function i18n_determine_language(): string
{
    global $config;
    $user = current_user();
    $office = get_current_office_config();
    $default = $config['i18n_default_lang'] ?? 'en';
    if (!empty($_SESSION['preferred_language'])) {
        return $_SESSION['preferred_language'];
    }
    if ($user && !empty($user['preferred_language'])) {
        return $user['preferred_language'];
    }
    if (!empty($office['default_language'])) {
        return $office['default_language'];
    }
    return $default;
}

function i18n_update_user_preference(string $langCode): void
{
    $user = current_user();
    if (!$user) {
        return;
    }
    $users = load_users();
    foreach ($users as &$u) {
        if (($u['id'] ?? null) === ($user['id'] ?? null)) {
            $u['preferred_language'] = $langCode;
            break;
        }
    }
    unset($u);
    save_users($users);
}
