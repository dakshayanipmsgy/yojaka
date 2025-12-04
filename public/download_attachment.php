<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login();

$id = $_GET['id'] ?? '';
if ($id === '') {
    http_response_code(400);
    echo 'Missing attachment ID.';
    exit;
}

$attachment = get_attachment_by_id($id);
if (!$attachment) {
    http_response_code(404);
    echo 'Attachment not found.';
    exit;
}

$user = current_user();
$module = $attachment['module'] ?? '';
$canViewAll = user_has_permission('view_all_records');
$canManage = false;

switch ($module) {
    case 'rti':
        $canManage = user_has_permission('manage_rti');
        break;
    case 'dak':
        $canManage = user_has_permission('manage_dak');
        break;
    case 'inspection':
        $canManage = user_has_permission('manage_inspection');
        break;
    case 'documents':
        $canManage = user_has_permission('create_documents');
        break;
    case 'bills':
        $canManage = user_has_permission('manage_bills');
        break;
    default:
        $canManage = user_has_permission('manage_documents_repository');
        break;
}

if (!$canViewAll && !$canManage && (($attachment['uploaded_by'] ?? '') !== ($user['username'] ?? ''))) {
    http_response_code(403);
    echo 'You do not have permission to access this attachment.';
    exit;
}

$filePath = attachments_base_path() . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . ($attachment['stored_name'] ?? '');
if (!is_file($filePath)) {
    http_response_code(404);
    echo 'File missing on server.';
    exit;
}

$downloadName = sanitize_filename_for_display($attachment['original_name'] ?? 'file');
header('Content-Type: ' . ($attachment['mime_type'] ?? 'application/octet-stream'));
header('Content-Length: ' . (string) ($attachment['size_bytes'] ?? filesize($filePath)));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');

log_event('attachment_downloaded', $user['username'] ?? null, [
    'attachment_id' => $attachment['id'] ?? '',
    'module' => $module,
]);

readfile($filePath);
exit;
