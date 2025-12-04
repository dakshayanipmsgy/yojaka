<?php
// Reply template helpers

function reply_templates_path(): string
{
    return YOJAKA_DATA_PATH . '/replies/templates.json';
}

function load_reply_templates(): array
{
    $path = reply_templates_path();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function find_reply_templates(string $module, string $category, string $language): array
{
    $templates = load_reply_templates();
    return array_values(array_filter($templates, function ($tpl) use ($module, $category, $language) {
        return (($tpl['module'] ?? '') === $module)
            && (($tpl['category'] ?? '') === $category)
            && (($tpl['language'] ?? '') === $language)
            && (!isset($tpl['active']) || $tpl['active']);
    }));
}

function render_reply_from_template(array $template, array $variables): string
{
    $body = $template['body_template'] ?? '';
    foreach ($variables as $key => $value) {
        $body = str_replace('{{' . $key . '}}', (string) $value, $body);
    }
    return $body;
}
