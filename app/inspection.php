<?php
// Inspection module helper functions for Yojaka v0.6

function inspection_templates_path(): string
{
    global $config;
    $directory = rtrim($config['inspection_data_path'], DIRECTORY_SEPARATOR);
    $file = $config['inspection_templates_file'] ?? 'templates.json';
    return $directory . DIRECTORY_SEPARATOR . $file;
}

function inspection_reports_path(): string
{
    global $config;
    $directory = rtrim($config['inspection_data_path'], DIRECTORY_SEPARATOR);
    $file = $config['inspection_reports_file'] ?? 'reports.json';
    return $directory . DIRECTORY_SEPARATOR . $file;
}

function ensure_inspection_storage(): void
{
    global $config;
    $dir = rtrim($config['inspection_data_path'], DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $templatesPath = inspection_templates_path();
    $reportsPath = inspection_reports_path();

    if (!file_exists($templatesPath)) {
        file_put_contents($templatesPath, json_encode([]));
    }

    if (!file_exists($reportsPath)) {
        file_put_contents($reportsPath, json_encode([]));
    }
}

function load_inspection_templates(): array
{
    $path = inspection_templates_path();
    if (!file_exists($path)) {
        return [];
    }
    $contents = file_get_contents($path);
    $data = json_decode($contents, true);
    $data = is_array($data) ? $data : [];
    $currentOffice = get_current_office_id();
    return array_map(function ($report) use ($currentOffice) {
        $report = ensure_record_office($report, $currentOffice);
        return ensure_archival_defaults($report);
    }, $data);
}

function save_inspection_templates(array $templates): void
{
    $path = inspection_templates_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        throw new RuntimeException('Unable to open inspection templates file.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock inspection templates file.');
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($templates, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function load_inspection_reports(): array
{
    $path = inspection_reports_path();
    if (!file_exists($path)) {
        return [];
    }
    $contents = file_get_contents($path);
    $data = json_decode($contents, true);
    return is_array($data) ? $data : [];
}

function save_inspection_reports(array $reports): void
{
    $path = inspection_reports_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        throw new RuntimeException('Unable to open inspection reports file.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock inspection reports file.');
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($reports, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function ensure_default_inspection_templates(): void
{
    $templates = load_inspection_templates();
    if (!empty($templates)) {
        return;
    }

    $templates[] = [
        'id' => 'generic_inspection',
        'name' => 'Generic Inspection',
        'description' => 'General purpose inspection checklist.',
        'category' => 'General',
        'active' => true,
        'fields' => [
            ['name' => 'location', 'label' => 'Location', 'type' => 'text', 'required' => true],
            ['name' => 'date_of_inspection', 'label' => 'Date of Inspection', 'type' => 'date', 'required' => true],
            ['name' => 'inspecting_officer', 'label' => 'Inspecting Officer', 'type' => 'text', 'required' => true],
        ],
        'checklist' => [
            ['code' => 'safety', 'label' => 'Safety compliance', 'default_status' => 'NA'],
            ['code' => 'documentation', 'label' => 'Documentation available', 'default_status' => 'NA'],
        ],
        'footer_note' => 'Generated via Yojaka Inspection module.',
    ];

    $templates[] = [
        'id' => 'school_inspection',
        'name' => 'School Inspection',
        'description' => 'Checklist for routine school inspection.',
        'category' => 'Education',
        'active' => true,
        'fields' => [
            ['name' => 'school_name', 'label' => 'School Name', 'type' => 'text', 'required' => true],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'required' => true],
            ['name' => 'date_of_inspection', 'label' => 'Date of Inspection', 'type' => 'date', 'required' => true],
            ['name' => 'inspecting_officer', 'label' => 'Inspecting Officer Name', 'type' => 'text', 'required' => true],
        ],
        'checklist' => [
            ['code' => 'classrooms', 'label' => 'Condition of classrooms', 'default_status' => 'NA'],
            ['code' => 'sanitation', 'label' => 'Availability & cleanliness of toilets', 'default_status' => 'NA'],
            ['code' => 'safety_equipment', 'label' => 'Safety equipment available', 'default_status' => 'NA'],
        ],
        'footer_note' => 'This is a standard school inspection report format.',
    ];

    save_inspection_templates($templates);
}

function generate_next_inspection_id(array $reports): string
{
    $max = 0;
    foreach ($reports as $report) {
        if (!empty($report['id']) && preg_match('/INSP-(\d+)/', $report['id'], $matches)) {
            $num = (int) $matches[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }
    $next = $max + 1;
    return 'INSP-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function find_inspection_template_by_id(array $templates, string $id): ?array
{
    foreach ($templates as $template) {
        if (($template['id'] ?? '') === $id) {
            return $template;
        }
    }
    return null;
}

function find_inspection_report_by_id(array $reports, string $id): ?array
{
    foreach ($reports as $report) {
        if (($report['id'] ?? '') === $id) {
            return $report;
        }
    }
    return null;
}

function valid_inspection_statuses(): array
{
    return ['Compliant', 'Non-compliant', 'NA'];
}

function default_checklist_statuses(array $template): array
{
    $statuses = [];
    foreach ($template['checklist'] ?? [] as $item) {
        $statuses[] = [
            'code' => $item['code'] ?? '',
            'status' => $item['default_status'] ?? 'NA',
            'remarks' => '',
        ];
    }
    return $statuses;
}

function update_inspection_report(array &$reports, array $updated): void
{
    foreach ($reports as $index => $report) {
        if (($report['id'] ?? '') === ($updated['id'] ?? '')) {
            $reports[$index] = $updated;
            return;
        }
    }
}
