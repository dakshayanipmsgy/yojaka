<?php
class DeptAdminController
{
    public function dashboard()
    {
        yojaka_require_dept_admin();

        $user = yojaka_current_user();
        $deptSlug = $user['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;
        $roles = $deptSlug ? yojaka_roles_load_for_department($deptSlug) : [];

        $message = $_SESSION['deptadmin_success'] ?? null;
        unset($_SESSION['deptadmin_success']);

        $data = [
            'title' => 'Department Admin Dashboard',
            'department' => $department,
            'roles' => $roles,
            'message' => $message,
        ];

        return yojaka_render_view('deptadmin/dashboard', $data, 'main');
    }

    public function roles_create()
    {
        yojaka_require_dept_admin();
        yojaka_require_permission('dept.roles.manage');

        $user = yojaka_current_user();
        $deptSlug = $user['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;
        $catalog = yojaka_permissions_catalog();
        $allPermissions = yojaka_permissions_all();

        $errors = [];
        $form = [
            'local_key' => '',
            'label' => '',
            'permissions' => [],
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form['local_key'] = isset($_POST['local_key']) ? trim($_POST['local_key']) : '';
            $form['label'] = isset($_POST['label']) ? trim($_POST['label']) : '';
            $form['permissions'] = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_values($_POST['permissions']) : [];

            if ($form['local_key'] === '' || !preg_match('/^[a-z0-9_]+$/', $form['local_key'])) {
                $errors[] = 'Local key is required and must be lowercase letters, numbers, or underscores.';
            }

            if ($form['label'] === '') {
                $errors[] = 'Label is required.';
            }

            // Validate permissions against global catalog.
            foreach ($form['permissions'] as $permission) {
                if (!in_array($permission, $allPermissions, true)) {
                    $errors[] = 'Invalid permission selected: ' . yojaka_escape($permission);
                    break;
                }
            }

            $existingRoles = yojaka_roles_load_for_department($deptSlug);
            foreach ($existingRoles as $role) {
                if (($role['local_key'] ?? '') === $form['local_key']) {
                    $errors[] = 'A role with that local key already exists in this department.';
                    break;
                }
            }

            if (empty($errors)) {
                $roleData = [
                    'local_key' => $form['local_key'],
                    'label' => $form['label'],
                    'permissions' => $form['permissions'],
                ];

                $result = yojaka_roles_add($deptSlug, $roleData);
                if ($result) {
                    yojaka_audit_log_action(
                        $deptSlug,
                        'roles',
                        $result['role_id'] ?? null,
                        'roles.create',
                        'Created department role',
                        [
                            'role_id' => $result['role_id'] ?? null,
                            'label' => $result['label'] ?? null,
                            'permissions' => $result['permissions'] ?? [],
                        ]
                    );

                    $_SESSION['deptadmin_success'] = 'Role created successfully';
                    header('Location: ' . yojaka_url('index.php?r=deptadmin/dashboard'));
                    exit;
                } else {
                    $errors[] = 'Unable to save role. Please try again.';
                }
            }
        }

        $data = [
            'title' => 'Create Role',
            'department' => $department,
            'catalog' => $catalog,
            'form' => $form,
            'errors' => $errors,
        ];

        return yojaka_render_view('deptadmin/roles_create', $data, 'main');
    }

    public function workflows()
    {
        yojaka_require_dept_admin();

        $user = yojaka_current_user();
        $deptSlug = $user['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;
        $workflows = $deptSlug ? yojaka_workflows_load_for_department($deptSlug) : [];

        $data = [
            'title' => 'Department Workflows',
            'department' => $department,
            'workflows' => $workflows,
        ];

        return yojaka_render_view('deptadmin/workflows', $data, 'main');
    }

    public function audit()
    {
        yojaka_require_dept_admin();

        $current = yojaka_current_user();
        $deptSlug = $current['department_slug'] ?? '';

        $entries = $deptSlug !== '' ? yojaka_audit_load_recent($deptSlug, 200) : [];

        $data = [
            'title' => 'Department Audit Log',
            'entries' => $entries,
        ];

        return yojaka_render_view('deptadmin/audit', $data, 'main');
    }

    public function change_password()
    {
        yojaka_require_dept_admin();

        $user = yojaka_current_user();
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!isset($user['password_hash']) || !password_verify($currentPassword, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }

            if (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters long.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirmation do not match.';
            }

            if (empty($errors)) {
                $users = yojaka_load_users();

                foreach ($users as &$storedUser) {
                    if (($storedUser['id'] ?? null) === ($user['id'] ?? null)) {
                        $storedUser['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                        $storedUser['must_change_password'] = false;
                        break;
                    }
                }
                unset($storedUser);

                yojaka_save_users($users);

                $updated = yojaka_users_find_by_username($user['username'] ?? '');
                if ($updated) {
                    yojaka_auth_login($updated);
                }

                $_SESSION['deptadmin_success'] = 'Password updated successfully.';
                header('Location: ' . yojaka_url('index.php?r=deptadmin/dashboard'));
                exit;
            }
        }

        $data = [
            'title' => 'Change Password',
            'errors' => $errors,
        ];

        return yojaka_render_view('deptadmin/change_password', $data, 'main');
    }

    public function branding_letterhead()
    {
        yojaka_require_dept_admin();
        yojaka_require_permission('dept.branding.manage');

        $user = yojaka_current_user();
        $deptSlug = $user['department_slug'] ?? '';

        $config = $deptSlug !== '' ? yojaka_branding_load_letterhead($deptSlug) : yojaka_branding_letterhead_defaults();
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $config['department_name'] = trim($_POST['department_name'] ?? '');
            $config['department_address'] = trim($_POST['department_address'] ?? '');
            $config['header_html'] = trim($_POST['header_html'] ?? '');
            $config['footer_html'] = trim($_POST['footer_html'] ?? '');

            if ($config['department_name'] === '') {
                $errors[] = 'Department name is required.';
            }

            if (strlen($config['department_name']) > 255) {
                $errors[] = 'Department name is too long.';
            }

            if (strlen($config['department_address']) > 1000) {
                $errors[] = 'Department address is too long.';
            }

            $existingLogo = $config['logo_file'] ?? null;
            $logoFile = $existingLogo;
            $logoUploaded = false;

            if (isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upload = $_FILES['logo'];
                if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($upload['tmp_name'] ?? '')) {
                    $originalName = $upload['name'] ?? '';
                    $tmpName = $upload['tmp_name'] ?? '';
                    $size = (int) ($upload['size'] ?? 0);
                    $sanitized = yojaka_attachment_sanitize_name($originalName);

                    $detectedMime = 'application/octet-stream';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfo) {
                            $mime = finfo_file($finfo, $tmpName);
                            if ($mime) {
                                $detectedMime = $mime;
                            }
                            finfo_close($finfo);
                        }
                    }

                    if ($sanitized === '') {
                        $errors[] = 'Invalid logo file name.';
                    } elseif (strpos($detectedMime, 'image/') !== 0) {
                        $errors[] = 'Logo must be an image file.';
                    } elseif ($size <= 0 || $size > (5 * 1024 * 1024)) {
                        $errors[] = 'Logo file is too large (max 5MB).';
                    } else {
                        $targetDir = yojaka_branding_assets_dir($deptSlug);
                        $uniqueName = date('Ymd_His') . '_' . $sanitized;
                        $targetPath = $targetDir . '/' . $uniqueName;

                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $logoFile = $uniqueName;
                            $logoUploaded = true;

                            if ($existingLogo && $existingLogo !== $logoFile) {
                                $oldPath = yojaka_branding_logo_path($deptSlug, $existingLogo);
                                if ($oldPath) {
                                    @unlink($oldPath);
                                }
                            }
                        } else {
                            $errors[] = 'Unable to save uploaded logo.';
                        }
                    }
                } else {
                    $errors[] = 'Error uploading logo file.';
                }
            }

            $removeLogo = isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1';
            if ($removeLogo && !$logoUploaded && $existingLogo) {
                $oldPath = yojaka_branding_logo_path($deptSlug, $existingLogo);
                if ($oldPath) {
                    @unlink($oldPath);
                }
                $logoFile = null;
            }

            if (empty($errors)) {
                $config['logo_file'] = $logoFile;
                yojaka_branding_save_letterhead($deptSlug, $config);

                $_SESSION['deptadmin_success'] = 'Letterhead saved successfully.';
                header('Location: ' . yojaka_url('index.php?r=deptadmin/branding/letterhead'));
                exit;
            }
        }

        $message = $_SESSION['deptadmin_success'] ?? null;
        unset($_SESSION['deptadmin_success']);

        $logoDataUri = $deptSlug !== '' ? yojaka_branding_logo_data_uri($deptSlug, $config['logo_file'] ?? null) : null;
        $hasPreviewContent = $logoDataUri
            || ($config['department_name'] ?? '') !== ''
            || ($config['department_address'] ?? '') !== ''
            || ($config['header_html'] ?? '') !== ''
            || ($config['footer_html'] ?? '') !== '';

        $previewHtml = '';
        if ($hasPreviewContent) {
            $previewHtml = '<div class="letterhead-preview-block">'
                . ($logoDataUri ? '<div><img src="' . yojaka_escape($logoDataUri) . '" alt="Preview logo" style="max-height: 80px; max-width: 160px;"></div>' : '')
                . '<div style="margin-top:8px;">'
                . ($config['department_name'] !== '' ? '<div style="font-weight:bold; font-size:18px;">' . yojaka_escape($config['department_name']) . '</div>' : '')
                . ($config['department_address'] !== '' ? '<div style="white-space:pre-line; color:#444;">' . nl2br(yojaka_escape($config['department_address'])) . '</div>' : '')
                . '</div>'
                . ($config['header_html'] !== '' ? '<div style="margin-top:10px; font-size:13px;">' . $config['header_html'] . '</div>' : '')
                . ($config['footer_html'] !== '' ? '<div style="margin-top:16px; padding-top:8px; border-top:1px solid #ddd; font-size:12px; color:#444;">' . $config['footer_html'] . '</div>' : '')
                . '</div>';
        }

        $data = [
            'title' => 'Branding / Letterhead',
            'config' => $config,
            'errors' => $errors,
            'message' => $message,
            'logoDataUri' => $logoDataUri,
            'previewHtml' => $previewHtml,
        ];

        return yojaka_render_view('deptadmin/branding_letterhead', $data, 'main');
    }
}
