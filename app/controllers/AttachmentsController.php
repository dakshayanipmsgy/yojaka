<?php
class AttachmentsController
{
    protected function requireLogin(): array
    {
        yojaka_require_login();
        $user = yojaka_current_user();

        if (!$user) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Login required'], 'main');
            exit;
        }

        return $user;
    }

    protected function loadRecord(string $deptSlug, string $module, string $id): ?array
    {
        if ($module === 'dak') {
            return yojaka_dak_load_record($deptSlug, $id);
        }

        if ($module === 'letters') {
            return yojaka_letters_load_record($deptSlug, $id);
        }

        if ($module === 'rti') {
            return yojaka_rti_load_record($deptSlug, $id);
        }

        return null;
    }

    protected function saveRecord(string $deptSlug, string $module, array $record): void
    {
        if ($module === 'dak') {
            yojaka_dak_save_record($deptSlug, $record);
        } elseif ($module === 'letters') {
            yojaka_letters_save_record($deptSlug, $record);
        } elseif ($module === 'rti') {
            yojaka_rti_save_record($deptSlug, $record);
        }
    }

    protected function redirectToRecord(string $module, string $id): void
    {
        $route = 'dak/view';
        if ($module === 'letters') {
            $route = 'letters/view';
        } elseif ($module === 'rti') {
            $route = 'rti/view';
        }
        header('Location: ' . yojaka_url('index.php?r=' . $route . '&id=' . urlencode($id)));
        exit;
    }

    public function upload()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $user = $this->requireLogin();
        $deptSlug = $user['department_slug'] ?? '';
        $module = $_POST['module'] ?? '';
        $recordId = $_POST['id'] ?? '';

        if (!in_array($module, ['dak', 'letters', 'rti'], true) || $recordId === '') {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Invalid attachment request.'], 'main');
            exit;
        }

        $record = $this->loadRecord($deptSlug, $module, $recordId);
        if (!$record) {
            http_response_code(404);
            echo yojaka_render_view('errors/404', ['route' => 'attachments/upload'], 'main');
            exit;
        }

        if (!yojaka_acl_can_edit_record($user, $record)) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Edit not allowed'], 'main');
            exit;
        }

        $file = $_FILES['attachment'] ?? null;
        $storedName = $file ? yojaka_attachment_save_uploaded($deptSlug, $module, $recordId, $file) : null;

        if ($storedName === null) {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Attachment upload failed.'], 'main');
            exit;
        }

        if (!isset($record['attachments']) || !is_array($record['attachments'])) {
            $record['attachments'] = [];
        }

        $record['attachments'][] = $storedName;
        $record['updated_at'] = date('c');

        $this->saveRecord($deptSlug, $module, $record);

        yojaka_audit_log_action(
            $deptSlug,
            $module,
            $recordId,
            $module . '.attachment_upload',
            'Uploaded attachment',
            ['file' => $storedName]
        );

        $this->redirectToRecord($module, $recordId);
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $user = $this->requireLogin();
        $deptSlug = $user['department_slug'] ?? '';
        $module = $_POST['module'] ?? '';
        $recordId = $_POST['id'] ?? '';
        $filename = $_POST['file'] ?? '';

        if (!in_array($module, ['dak', 'letters', 'rti'], true) || $recordId === '' || $filename === '') {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Invalid delete request.'], 'main');
            exit;
        }

        $record = $this->loadRecord($deptSlug, $module, $recordId);
        if (!$record) {
            http_response_code(404);
            echo yojaka_render_view('errors/404', ['route' => 'attachments/delete'], 'main');
            exit;
        }

        if (!yojaka_acl_can_edit_record($user, $record)) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Edit not allowed'], 'main');
            exit;
        }

        if (!isset($record['attachments']) || !is_array($record['attachments'])) {
            $record['attachments'] = [];
        }

        if (!in_array($filename, $record['attachments'], true)) {
            http_response_code(404);
            echo yojaka_render_view('errors/404', ['route' => 'attachments/delete'], 'main');
            exit;
        }

        $deleted = yojaka_attachment_delete($deptSlug, $module, $recordId, $filename);
        if ($deleted) {
            $record['attachments'] = array_values(array_filter(
                $record['attachments'],
                function ($item) use ($filename) {
                    return $item !== $filename;
                }
            ));
            $record['updated_at'] = date('c');
            $this->saveRecord($deptSlug, $module, $record);

            yojaka_audit_log_action(
                $deptSlug,
                $module,
                $recordId,
                $module . '.attachment_delete',
                'Deleted attachment',
                ['file' => $filename]
            );
        }

        $this->redirectToRecord($module, $recordId);
    }

    public function download()
    {
        $user = $this->requireLogin();
        $deptSlug = $user['department_slug'] ?? '';
        $module = $_GET['module'] ?? '';
        $recordId = $_GET['id'] ?? '';
        $filename = $_GET['file'] ?? '';

        if (!in_array($module, ['dak', 'letters', 'rti'], true) || $recordId === '' || $filename === '') {
            http_response_code(400);
            exit;
        }

        $record = $this->loadRecord($deptSlug, $module, $recordId);
        if (!$record || !yojaka_acl_can_view_record($user, $record)) {
            http_response_code(403);
            exit;
        }

        if (!isset($record['attachments']) || !is_array($record['attachments'])) {
            $record['attachments'] = [];
        }

        if (!in_array($filename, $record['attachments'], true)) {
            http_response_code(404);
            exit;
        }

        $path = yojaka_attachment_get_path($deptSlug, $module, $recordId, $filename);
        if ($path === null) {
            http_response_code(404);
            exit;
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
        if ($mime === false || $mime === '') {
            $mime = 'application/octet-stream';
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
