<?php
class SuperadminController
{
    public function dashboard()
    {
        yojaka_require_superadmin();

        $message = null;
        $error = null;
        $adminNotice = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';

            if ($name === '') {
                $error = 'Department name is required';
            } else {
                if ($slug === '') {
                    $slug = $this->generateSlugFromName($name);
                }

                if (!$this->isSlugValid($slug)) {
                    $error = 'Slug can only contain lowercase letters, numbers, and dashes';
                } elseif (yojaka_find_department_by_slug($slug)) {
                    $error = 'A department with that slug already exists';
                } else {
                    $department = [
                        'name' => $name,
                        'slug' => $slug,
                        'status' => 'active',
                    ];

                    if (yojaka_add_department($department)) {
                        yojaka_departments_initialize_storage($slug);

                        $tempPassword = 'Admin@' . random_int(10000, 99999);
                        $createdAdmin = yojaka_users_create_department_admin($slug, $name, $tempPassword);

                        if ($createdAdmin) {
                            $adminNotice = 'Department admin username: ' . $createdAdmin['username'] . '. Temporary password: ' . $tempPassword;
                        } else {
                            $adminNotice = 'Department admin account already exists for this department.';
                        }

                        $message = 'Department created successfully';
                    } else {
                        $error = 'Unable to create department. Please try again.';
                    }
                }
            }
        }

        $departments = yojaka_load_departments();

        $data = [
            'title' => 'Superadmin Dashboard',
            'departments' => $departments,
            'message' => $message,
            'error' => $error,
            'adminNotice' => $adminNotice ?? null,
        ];

        return yojaka_render_view('superadmin/dashboard', $data, 'main');
    }

    protected function generateSlugFromName(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'dept';
    }

    protected function isSlugValid(string $slug): bool
    {
        return (bool)preg_match('/^[a-z0-9\-]+$/', $slug);
    }
}
