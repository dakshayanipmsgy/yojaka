<?php
// Template engine helpers for Yojaka.
// Provides loading, merging, and rendering of HTML templates stored as JSON.

function yojaka_templates_global_letters_path(): string
{
    return yojaka_config('paths.data_path') . '/system/templates/letters.json';
}

function yojaka_templates_department_letters_path(string $deptSlug): string
{
    return yojaka_config('paths.data_path') . '/departments/' . $deptSlug . '/templates/letters.json';
}

function yojaka_templates_load_letters_global(): array
{
    $path = yojaka_templates_global_letters_path();
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function yojaka_templates_load_letters_department(string $deptSlug): array
{
    $path = yojaka_templates_department_letters_path($deptSlug);
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function yojaka_templates_list_letters_for_department(string $deptSlug): array
{
    $global = yojaka_templates_load_letters_global();
    $department = yojaka_templates_load_letters_department($deptSlug);

    $merged = [];

    foreach ($global as $tpl) {
        if (!is_array($tpl) || empty($tpl['id'])) {
            continue;
        }
        $merged[$tpl['id']] = $tpl;
    }

    foreach ($department as $tpl) {
        if (!is_array($tpl) || empty($tpl['id'])) {
            continue;
        }
        $merged[$tpl['id']] = $tpl;
    }

    return array_values($merged);
}

function yojaka_templates_find_letter_for_department(string $deptSlug, string $templateId): ?array
{
    $templates = yojaka_templates_list_letters_for_department($deptSlug);
    foreach ($templates as $tpl) {
        if (($tpl['id'] ?? '') === $templateId) {
            return $tpl;
        }
    }

    return null;
}

function yojaka_templates_render_html(array $template, array $data): string
{
    $html = $template['html'] ?? '';
    if ($html === '') {
        return '';
    }

    // Replace {{placeholder}} with escaped values provided in $data.
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($data) {
        $key = $matches[1] ?? '';
        $value = $data[$key] ?? '';
        return yojaka_escape((string) $value);
    }, $html);
}

function yojaka_templates_ensure_seeded(): void
{
    $globalDir = yojaka_config('paths.data_path') . '/system/templates';
    if (!is_dir($globalDir)) {
        mkdir($globalDir, 0777, true);
    }

    $lettersPath = yojaka_templates_global_letters_path();
    if (!file_exists($lettersPath)) {
        $defaultTemplates = [
            [
                'id' => 'letter_generic_en',
                'module' => 'letters',
                'scope' => 'global',
                'name' => 'Generic Official Letter (English)',
                'description' => 'Standard outward letter format with heading, subject, body and signature.',
                'placeholders' => [
                    ['key' => 'letter_date', 'label' => 'Letter Date'],
                    ['key' => 'to_name', 'label' => 'Recipient Name'],
                    ['key' => 'to_address', 'label' => 'Recipient Address'],
                    ['key' => 'subject', 'label' => 'Subject'],
                    ['key' => 'body', 'label' => 'Body Text'],
                    ['key' => 'signatory_name', 'label' => 'Signatory Name'],
                    ['key' => 'signatory_designation', 'label' => 'Signatory Designation'],
                ],
                'html' => "<div class=\"yojaka-letter\">\n  <p>Date: {{letter_date}}</p>\n  <p>To,<br>{{to_name}}<br>{{to_address}}</p>\n  <p><strong>Subject:</strong> {{subject}}</p>\n  <p>{{body}}</p>\n  <p>Yours faithfully,<br>{{signatory_name}}<br>{{signatory_designation}}</p>\n</div>",
            ],
        ];

        file_put_contents($lettersPath, json_encode($defaultTemplates, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
