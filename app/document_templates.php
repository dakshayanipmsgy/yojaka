<?php
// Generic document template and document storage helpers (v0.9)

function document_templates_path(): string
{
    global $config;
    $dir = rtrim($config['templates_path'], DIRECTORY_SEPARATOR);
    $file = $config['document_templates_file'] ?? 'documents.json';
    return $dir . DIRECTORY_SEPARATOR . $file;
}

function ensure_document_templates_storage(): void
{
    global $config;
    $dir = rtrim($config['templates_path'], DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }
}

function load_document_templates(): array
{
    $path = document_templates_path();
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_document_templates(array $templates): bool
{
    $path = document_templates_path();
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }

    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return true;
}

function find_document_template_by_id(array $templates, string $id): ?array
{
    foreach ($templates as $template) {
        if (($template['id'] ?? '') === $id) {
            return $template;
        }
    }
    return null;
}

function get_templates_by_category(array $templates, string $category): array
{
    return array_values(array_filter($templates, function ($tpl) use ($category) {
        return ($tpl['category'] ?? '') === $category;
    }));
}

function ensure_default_document_templates(): void
{
    ensure_document_templates_storage();
    $path = document_templates_path();
    $templates = load_document_templates();
    if (file_exists($path) && !empty($templates)) {
        return;
    }

    $defaults = [
        [
            'id' => 'mm_general_meeting',
            'category' => 'meeting_minutes',
            'name' => 'General Meeting Minutes',
            'description' => 'Record of standard departmental meeting proceedings.',
            'active' => true,
            'fields' => [
                ['name' => 'meeting_title', 'label' => 'Meeting Title', 'type' => 'text', 'required' => true],
                ['name' => 'meeting_date', 'label' => 'Meeting Date', 'type' => 'date', 'required' => true],
                ['name' => 'meeting_time', 'label' => 'Meeting Time', 'type' => 'text', 'required' => false],
                ['name' => 'venue', 'label' => 'Venue', 'type' => 'text', 'required' => true],
                ['name' => 'participants', 'label' => 'Participants', 'type' => 'textarea', 'required' => true],
            ],
            'extra_sections' => [
                ['name' => 'agenda_items', 'label' => 'Agenda Items', 'type' => 'textarea', 'required' => false],
                ['name' => 'decisions', 'label' => 'Decisions', 'type' => 'textarea', 'required' => false],
                ['name' => 'action_points', 'label' => 'Action Points', 'type' => 'textarea', 'required' => false],
            ],
            'body' => "Minutes of the meeting titled {{meeting_title}} held on {{meeting_date}} at {{meeting_time}} at {{venue}}.\n\nParticipants:\n{{participants}}\n\nAgenda Items:\n{{agenda_items}}\n\nDecisions Taken:\n{{decisions}}\n\nAction Points:\n{{action_points}}",
            'footer_note' => 'These minutes are issued with the approval of the competent authority.',
        ],
        [
            'id' => 'wo_basic_order',
            'category' => 'work_order',
            'name' => 'Basic Work Order',
            'description' => 'Standard work order outlining scope, cost, and completion period.',
            'active' => true,
            'fields' => [
                ['name' => 'work_title', 'label' => 'Work Title', 'type' => 'text', 'required' => true],
                ['name' => 'work_order_no', 'label' => 'Work Order No.', 'type' => 'text', 'required' => true],
                ['name' => 'work_order_date', 'label' => 'Work Order Date', 'type' => 'date', 'required' => true],
                ['name' => 'contractor_name', 'label' => 'Contractor Name', 'type' => 'text', 'required' => true],
                ['name' => 'estimated_amount', 'label' => 'Estimated Amount', 'type' => 'number', 'required' => true],
                ['name' => 'completion_period', 'label' => 'Completion Period', 'type' => 'text', 'required' => true],
            ],
            'extra_sections' => [
                ['name' => 'work_description', 'label' => 'Work Description', 'type' => 'textarea', 'required' => true],
                ['name' => 'approval_reference', 'label' => 'Approval Reference', 'type' => 'text', 'required' => false],
                ['name' => 'payment_terms', 'label' => 'Payment Terms', 'type' => 'textarea', 'required' => false],
            ],
            'body' => "Work Order No: {{work_order_no}} dated {{work_order_date}} for {{work_title}} is hereby awarded to {{contractor_name}}.\n\nWork Description:\n{{work_description}}\n\nEstimated Amount: Rs. {{estimated_amount}}\nCompletion Period: {{completion_period}}\nApproval Reference: {{approval_reference}}\n\nPayment Terms:\n{{payment_terms}}",
            'footer_note' => 'Please acknowledge receipt and commence work as per terms.',
        ],
        [
            'id' => 'guc_basic_certificate',
            'category' => 'guc',
            'name' => 'Grant Utilization Certificate',
            'description' => 'Certificate of utilization for sanctioned grants.',
            'active' => true,
            'fields' => [
                ['name' => 'scheme_name', 'label' => 'Scheme Name', 'type' => 'text', 'required' => true],
                ['name' => 'sanction_order_no', 'label' => 'Sanction Order No.', 'type' => 'text', 'required' => true],
                ['name' => 'sanction_amount', 'label' => 'Sanctioned Amount', 'type' => 'number', 'required' => true],
                ['name' => 'amount_utilized', 'label' => 'Amount Utilized', 'type' => 'number', 'required' => true],
                ['name' => 'period_from', 'label' => 'Period From', 'type' => 'date', 'required' => true],
                ['name' => 'period_to', 'label' => 'Period To', 'type' => 'date', 'required' => true],
            ],
            'extra_sections' => [
                ['name' => 'utilization_cert_text', 'label' => 'Utilization Details', 'type' => 'textarea', 'required' => true],
                ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'required' => false],
            ],
            'body' => "This is to certify that a sum of Rs. {{sanction_amount}} was sanctioned under {{scheme_name}} vide order {{sanction_order_no}}. An amount of Rs. {{amount_utilized}} has been utilized during the period {{period_from}} to {{period_to}}.\n\nUtilization Details:\n{{utilization_cert_text}}\n\nRemarks:\n{{remarks}}",
            'footer_note' => 'Certified that the above information is true and correct to the best of our knowledge.',
        ],
    ];

    save_document_templates($defaults);
}

function documents_data_path(): string
{
    global $config;
    return rtrim($config['documents_data_path'], DIRECTORY_SEPARATOR);
}

function ensure_documents_storage(): void
{
    $dir = documents_data_path();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }

    $files = [
        document_records_filename('meeting_minutes'),
        document_records_filename('work_order'),
        document_records_filename('guc'),
    ];
    foreach ($files as $file) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
        if (!file_exists($fullPath)) {
            file_put_contents($fullPath, json_encode([]));
        }
    }
}

function document_records_filename(string $category): string
{
    global $config;
    switch ($category) {
        case 'meeting_minutes':
            return $config['documents_meeting_minutes_file'] ?? 'meeting_minutes.json';
        case 'work_order':
            return $config['documents_work_orders_file'] ?? 'work_orders.json';
        case 'guc':
            return $config['documents_guc_file'] ?? 'guc.json';
        default:
            return $category . '.json';
    }
}

function document_records_path(string $category): string
{
    return documents_data_path() . DIRECTORY_SEPARATOR . document_records_filename($category);
}

function load_document_records(string $category): array
{
    $path = document_records_path($category);
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_document_records(string $category, array $records): bool
{
    $path = document_records_path($category);
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return true;
}

function generate_document_id(string $category, array $existingRecords): string
{
    $prefix = '';
    switch ($category) {
        case 'meeting_minutes':
            $prefix = 'MM-';
            break;
        case 'work_order':
            $prefix = 'WO-';
            break;
        case 'guc':
            $prefix = 'GUC-';
            break;
        default:
            $prefix = strtoupper(substr($category, 0, 3)) . '-';
    }

    $maxNumber = 0;
    foreach ($existingRecords as $record) {
        $id = $record['id'] ?? '';
        if (strpos($id, $prefix) === 0) {
            $num = (int) substr($id, strlen($prefix));
            if ($num > $maxNumber) {
                $maxNumber = $num;
            }
        }
    }

    $next = $maxNumber + 1;
    return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function render_document_body(array $template, array $values): string
{
    $body = $template['body'] ?? '';
    $safeBody = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
    $replacements = [];
    foreach ($values as $key => $value) {
        $replacements['{{' . $key . '}}'] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
    $merged = strtr($safeBody, $replacements);
    if (!empty($template['footer_note'])) {
        $merged .= "\n\n" . htmlspecialchars($template['footer_note'], ENT_QUOTES, 'UTF-8');
    }
    return nl2br($merged);
}

function filter_document_records(array $records, string $searchTerm, array $fields = []): array
{
    $searchTerm = trim($searchTerm);
    if ($searchTerm === '') {
        return $records;
    }

    $needle = mb_strtolower($searchTerm);
    $filtered = [];
    foreach ($records as $record) {
        $haystack = [];
        foreach ($fields as $field) {
            $value = $record[$field] ?? '';
            if (is_scalar($value)) {
                $haystack[] = $value;
            }
        }
        foreach (($record['fields'] ?? []) as $value) {
            if (is_scalar($value)) {
                $haystack[] = $value;
            }
        }
        foreach (($record['extra_sections'] ?? []) as $value) {
            if (is_scalar($value)) {
                $haystack[] = $value;
            }
        }

        $matched = false;
        foreach ($haystack as $text) {
            if (mb_stripos((string) $text, $needle) !== false) {
                $matched = true;
                break;
            }
        }

        if ($matched) {
            $filtered[] = $record;
        }
    }

    return $filtered;
}

?>
