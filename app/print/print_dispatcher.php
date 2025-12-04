<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

function yojaka_print_page_size(): string
{
    $office = load_office_config();
    $size = strtoupper(trim($office['print_page_size'] ?? ''));

    return $size === 'LETTER' ? 'Letter' : 'A4';
}

switch ($type) {
    case 'bill':
        include __DIR__ . '/print_bill.php';
        break;

    case 'guc':
        include __DIR__ . '/print_guc.php';
        break;

    case 'work_order':
        include __DIR__ . '/print_work_order.php';
        break;

    case 'meeting_minutes':
        include __DIR__ . '/print_meeting_minutes.php';
        break;

    case 'inspection':
        include __DIR__ . '/print_inspection.php';
        break;

    case 'rti':
        include __DIR__ . '/print_rti.php';
        break;

    case 'dak':
        include __DIR__ . '/print_dak.php';
        break;

    case 'letter':
        include __DIR__ . '/print_letter.php';
        break;

    default:
        http_response_code(400);
        echo 'Unknown print document type.';
        break;
}
exit;
