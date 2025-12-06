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

function yojaka_letters_render_full_html(array $departmentBranding, ?array $letterTemplate, array $letterRecord): string
{
    $deptSlug = $letterRecord['department_slug'] ?? '';
    $bodyHtml = $letterTemplate ? yojaka_templates_render_html($letterTemplate, $letterRecord['fields'] ?? []) : '';

    $logoUri = $deptSlug !== '' ? yojaka_branding_logo_data_uri($deptSlug, $departmentBranding['logo_file'] ?? null) : null;
    $departmentName = trim($departmentBranding['department_name'] ?? '');
    $departmentAddress = trim($departmentBranding['department_address'] ?? '');
    $headerHtml = $departmentBranding['header_html'] ?? '';
    $footerHtml = $departmentBranding['footer_html'] ?? '';

    $logoBlock = $logoUri ? '<img src="' . yojaka_escape($logoUri) . '" alt="Department Logo" class="letterhead-logo" />' : '';
    $nameBlock = $departmentName !== '' ? '<div class="letterhead-name">' . yojaka_escape($departmentName) . '</div>' : '';
    $addressBlock = $departmentAddress !== ''
        ? '<div class="letterhead-address">' . nl2br(yojaka_escape($departmentAddress)) . '</div>'
        : '';

    $html = '<!DOCTYPE html>'
        . '<html lang="en">'
        . '<head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Letter</title>'
        . '<style>'
        . 'body { font-family: Arial, sans-serif; color: #111; margin: 30px; }'
        . '.letter-wrapper { max-width: 900px; margin: 0 auto; }'
        . '.letterhead-header { border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 16px; }'
        . '.letterhead-brand { flex: 1; }'
        . '.letterhead-logo { max-height: 80px; max-width: 160px; object-fit: contain; }'
        . '.letterhead-name { font-size: 20px; font-weight: bold; }'
        . '.letterhead-address { white-space: pre-line; color: #444; margin-top: 4px; }'
        . '.letterhead-extra { margin-top: 8px; font-size: 13px; color: #333; }'
        . '.letter-body { font-size: 14px; line-height: 1.6; }'
        . '.letter-body p { margin-bottom: 12px; }'
        . '.letterhead-footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #ccc; font-size: 12px; color: #444; }'
        . '@media print { .print-note { display: none; } body { margin: 20mm; } }'
        . '</style>'
        . '</head>'
        . '<body>'
        . '<div class="letter-wrapper">'
        . '<div class="print-note"><p><strong>Print Preview:</strong> Use your browser print dialog to print or save as PDF.</p><hr></div>'
        . '<div class="letterhead-header">'
        . ($logoBlock !== '' ? '<div class="letterhead-logo-wrap">' . $logoBlock . '</div>' : '')
        . '<div class="letterhead-brand">' . $nameBlock . $addressBlock . '</div>'
        . '</div>'
        . ($headerHtml !== '' ? '<div class="letterhead-extra">' . $headerHtml . '</div>' : '')
        . '<div class="letter-body">' . $bodyHtml . '</div>'
        . ($footerHtml !== '' ? '<div class="letterhead-footer">' . $footerHtml . '</div>' : '')
        . '</div>'
        . '</body>'
        . '</html>';

    return $html;
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
