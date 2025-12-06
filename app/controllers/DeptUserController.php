<?php
class DeptUserController
{
    public function dashboard()
    {
        yojaka_require_dept_user();

        $user = yojaka_current_user();
        $deptSlug = $user['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;

        $data = [
            'title' => 'Department User Dashboard',
            'user' => $user,
            'department' => $department,
        ];

        return yojaka_render_view('deptuser/dashboard', $data, 'main');
    }
}
