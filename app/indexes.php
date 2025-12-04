<?php
// Lightweight index helpers

function index_path_for_module(string $module): string
{
    global $config;
    $dir = rtrim($config['index_data_path'], DIRECTORY_SEPARATOR);
    return $dir . DIRECTORY_SEPARATOR . $module . '_index.json';
}

function ensure_index_directory(): void
{
    global $config;
    $dir = rtrim($config['index_data_path'], DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

function build_full_index_for_module(string $module): array
{
    ensure_index_directory();
    $data = [];
    switch ($module) {
        case 'rti':
            $cases = load_rti_cases();
            foreach ($cases as $case) {
                $data[] = [
                    'id' => $case['id'] ?? '',
                    'office_id' => $case['office_id'] ?? '',
                    'created_at' => $case['created_at'] ?? '',
                    'status' => $case['status'] ?? '',
                    'workflow_state' => $case['workflow_state'] ?? '',
                    'archived' => $case['archived'] ?? false,
                    'assigned_to' => $case['assigned_to'] ?? '',
                    'applicant_name' => $case['applicant_name'] ?? '',
                    'subject' => $case['subject'] ?? '',
                ];
            }
            break;
        case 'dak':
            $entries = load_dak_entries();
            foreach ($entries as $entry) {
                $data[] = [
                    'id' => $entry['id'] ?? '',
                    'office_id' => $entry['office_id'] ?? '',
                    'created_at' => $entry['created_at'] ?? ($entry['date_received'] ?? ''),
                    'status' => $entry['status'] ?? '',
                    'workflow_state' => $entry['workflow_state'] ?? '',
                    'archived' => $entry['archived'] ?? false,
                    'assigned_to' => $entry['assigned_to'] ?? '',
                    'subject' => $entry['subject'] ?? ($entry['summary'] ?? ''),
                ];
            }
            break;
        case 'inspection':
            $reports = load_inspection_reports();
            foreach ($reports as $report) {
                $data[] = [
                    'id' => $report['id'] ?? '',
                    'office_id' => $report['office_id'] ?? '',
                    'created_at' => $report['created_at'] ?? ($report['date_of_inspection'] ?? ''),
                    'status' => $report['status'] ?? '',
                    'workflow_state' => $report['workflow_state'] ?? '',
                    'archived' => $report['archived'] ?? false,
                    'subject' => $report['title'] ?? '',
                ];
            }
            break;
        case 'documents':
            $docs = array_merge(load_meeting_minutes(), load_work_orders(), load_guc_documents());
            foreach ($docs as $doc) {
                $data[] = [
                    'id' => $doc['id'] ?? '',
                    'office_id' => $doc['office_id'] ?? '',
                    'created_at' => $doc['created_at'] ?? '',
                    'status' => $doc['status'] ?? '',
                    'workflow_state' => $doc['workflow_state'] ?? '',
                    'archived' => $doc['archived'] ?? false,
                    'subject' => $doc['title'] ?? ($doc['subject'] ?? ''),
                ];
            }
            break;
        case 'bills':
            $bills = load_bills();
            foreach ($bills as $bill) {
                $data[] = [
                    'id' => $bill['id'] ?? '',
                    'office_id' => $bill['office_id'] ?? '',
                    'created_at' => $bill['created_at'] ?? '',
                    'status' => $bill['status'] ?? '',
                    'workflow_state' => $bill['workflow_state'] ?? '',
                    'archived' => $bill['archived'] ?? false,
                    'subject' => $bill['work_name'] ?? '',
                ];
            }
            break;
        default:
            $data = [];
    }
    $path = index_path_for_module($module);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    return $data;
}

function get_index_for_module(string $module): array
{
    $path = index_path_for_module($module);
    if (!file_exists($path)) {
        return build_full_index_for_module($module);
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return build_full_index_for_module($module);
    }
    return $data;
}
