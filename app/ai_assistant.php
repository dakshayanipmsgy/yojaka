<?php
// Simple AI assistance helper layer

function ai_config_path(): string
{
    return YOJAKA_DATA_PATH . '/ai/config.json';
}

function ai_load_config(): array
{
    $path = ai_config_path();
    if (!file_exists($path)) {
        return [
            'enabled' => false,
            'provider' => 'stub',
            'endpoint_url' => '',
            'api_key' => '',
            'max_tokens' => 800,
            'temperature' => 0.3,
            'mask_personal_data' => true,
        ];
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        return [];
    }
    return $json;
}

function ai_is_enabled(): bool
{
    $config = ai_load_config();
    if (empty($config['enabled'])) {
        return false;
    }
    return in_array($config['provider'] ?? '', ['external_api', 'stub'], true);
}

function ai_mask_personal_data(string $text): string
{
    // Mask phone numbers, emails, and simple identifiers
    $text = preg_replace('/\b\d{10}\b/', '##########', $text);
    $text = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[redacted-email]', $text);
    $text = preg_replace('/\b[A-Z]{2,5}-\d{3,}\b/', '[redacted-id]', $text);
    $text = preg_replace('/\b([A-Z][a-z]+\s[A-Z][a-z]+)\b/u', '[name]', $text);
    return $text;
}

function ai_generate_text(string $taskType, string $prompt, array $context = []): ?string
{
    $config = ai_load_config();
    $provider = $config['provider'] ?? 'stub';
    $enabled = !empty($config['enabled']);

    if (!$enabled || $provider === 'stub') {
        return ai_generate_stub($taskType, $prompt, $context);
    }

    if ($provider === 'external_api') {
        $payload = [
            'task_type' => $taskType,
            'prompt' => $prompt,
            'context' => ($config['mask_personal_data'] ?? true) ? ai_mask_personal_data(json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : $context,
            'max_tokens' => $config['max_tokens'] ?? 800,
            'temperature' => $config['temperature'] ?? 0.3,
        ];
        $ch = curl_init($config['endpoint_url'] ?? '');
        if (!$ch) {
            log_event('ai_error', $_SESSION['username'] ?? null, ['error' => 'curl_init_failed']);
            return null;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter([
            'Content-Type: application/json',
            $config['api_key'] ? 'Authorization: Bearer ' . $config['api_key'] : null,
        ]));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        if ($response === false) {
            log_event('ai_error', $_SESSION['username'] ?? null, ['error' => curl_error($ch)]);
            curl_close($ch);
            return null;
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            log_event('ai_error', $_SESSION['username'] ?? null, ['error' => 'http_' . $code]);
            return null;
        }
        $json = json_decode($response, true);
        if (is_array($json) && isset($json['text'])) {
            return $json['text'];
        }
        return null;
    }

    return null;
}

function ai_generate_stub(string $taskType, string $prompt, array $context = []): string
{
    $module = $context['module'] ?? 'general';
    switch ($taskType) {
        case 'draft_document':
            return "Draft for {$module}: " . (!empty($context['subject']) ? $context['subject'] . ' - ' : '') . 'This is a placeholder draft generated locally. Please review and edit.';
        case 'summary_short':
            $body = $context['body_text'] ?? $prompt;
            return mb_substr(trim($body), 0, 200) . '...';
        case 'summary_long':
            $body = $context['body_text'] ?? $prompt;
            return mb_substr(trim($body), 0, 800) . '...';
        case 'suggest_route':
            return 'Based on past movements, forward to the most common next position.';
        case 'rti_reply':
            return 'Thank you for your application. Here is a standard reply based on provided details.';
        default:
            return 'Assistance placeholder: please tailor this text to your needs.';
    }
}
