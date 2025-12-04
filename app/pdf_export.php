<?php
require_once __DIR__ . '/../lib/pdf/tcpdf_min.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/logging.php';

function pdf_export_html(string $html, string $filename = 'document.pdf', string $module = '', string $entityId = ''): string
{
    $pdf = new TCPDF_MIN();
    $pdf->SetTitle($filename);
    $pdf->AddPage();
    $pdf->writeHTML($html);
    $data = $pdf->Output($filename, 'S');
    log_pdf_export($module, $entityId);
    return $data;
}

function pdf_export_template(string $templateName, array $data): string
{
    $html = render_template($templateName, $data);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $templateName) . '.pdf';
    return pdf_export_html($html, $filename, $data['module'] ?? '', $data['id'] ?? '');
}

function pdf_export_letterhead(string $html, string $officeId = ''): string
{
    $office = $officeId ? load_office_config_by_id($officeId) : ($GLOBALS['current_office_config'] ?? []);
    $header = $office['letterhead']['header_html'] ?? '';
    $footer = $office['letterhead']['footer_html'] ?? '';
    $wrapped = $header . $html . $footer;
    return pdf_export_html($wrapped, 'letter.pdf');
}

function render_template(string $templateName, array $data): string
{
    $templatePath = __DIR__ . '/print_views/' . $templateName . '.php';
    if (!file_exists($templatePath)) {
        return '<div>' . htmlspecialchars($templateName) . '</div>';
    }
    extract($data);
    ob_start();
    include $templatePath;
    return ob_get_clean();
}

function log_pdf_export(string $module, string $entityId): void
{
    $user = current_user();
    $actor = $user['username'] ?? 'public';
    $event = [
        'event' => 'pdf_export',
        'module' => $module,
        'entity_id' => $entityId,
        'user' => $actor,
        'timestamp' => gmdate('c'),
    ];
    audit_log_event('pdf_export', $event);
}
