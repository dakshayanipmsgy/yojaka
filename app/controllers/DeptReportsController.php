<?php
class DeptReportsController
{
    public function reports()
    {
        yojaka_require_login();

        $user = yojaka_current_user();
        if (!$user || ($user['status'] ?? '') !== 'active') {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Active department account required'], 'main');
            exit;
        }

        if (($user['user_type'] ?? '') !== 'dept_admin' && !yojaka_has_permission($user, 'dept.reports.view')) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Reports access denied'], 'main');
            exit;
        }

        $deptSlug = $user['department_slug'] ?? '';
        if ($deptSlug === '') {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Department not found for this account.'], 'main');
            exit;
        }

        yojaka_dak_ensure_storage($deptSlug);
        yojaka_letters_ensure_storage($deptSlug);

        $dakRecords = $this->loadDakRecords($deptSlug, $user);
        $letterRecords = $this->loadLetterRecords($deptSlug, $user);

        $data = [
            'title' => 'Department Reports Dashboard',
            'dakStatusCounts' => $this->countDakStatuses($dakRecords),
            'letterStatusCounts' => $this->countLetterStatuses($letterRecords),
            'workload' => $this->buildWorkload($deptSlug, $user, $dakRecords, $letterRecords),
            'workflowSteps' => $this->dakStepBreakdown($deptSlug, $dakRecords),
            'timeMetrics' => [
                'dak' => $this->timeMetrics($dakRecords),
                'letters' => $this->timeMetrics($letterRecords),
            ],
        ];

        return yojaka_render_view('deptadmin/reports', $data, 'main');
    }

    protected function loadDakRecords(string $deptSlug, array $user): array
    {
        $index = yojaka_dak_load_index($deptSlug);
        $records = [];
        $isAdmin = ($user['user_type'] ?? '') === 'dept_admin';

        foreach ($index as $entry) {
            $id = $entry['id'] ?? null;
            if (!$id) {
                continue;
            }

            $record = yojaka_dak_load_record($deptSlug, $id);
            if (!$record) {
                continue;
            }

            if ($isAdmin || yojaka_acl_can_view_record($user, $record)) {
                $records[] = $record;
            }
        }

        return $records;
    }

    protected function loadLetterRecords(string $deptSlug, array $user): array
    {
        $index = yojaka_letters_load_index($deptSlug);
        $records = [];
        $isAdmin = ($user['user_type'] ?? '') === 'dept_admin';

        foreach ($index as $entry) {
            $id = $entry['id'] ?? null;
            if (!$id) {
                continue;
            }

            $record = yojaka_letters_load_record($deptSlug, $id);
            if (!$record) {
                continue;
            }

            if ($isAdmin || yojaka_acl_can_view_record($user, $record)) {
                $records[] = $record;
            }
        }

        return $records;
    }

    protected function countDakStatuses(array $records): array
    {
        $counts = [
            'total' => count($records),
            'open' => 0,
            'closed' => 0,
        ];

        foreach ($records as $record) {
            if (($record['status'] ?? '') === 'closed') {
                $counts['closed']++;
            } else {
                $counts['open']++;
            }
        }

        return $counts;
    }

    protected function countLetterStatuses(array $records): array
    {
        $counts = [
            'total' => count($records),
            'draft' => 0,
            'finalized' => 0,
        ];

        foreach ($records as $record) {
            $status = $record['status'] ?? '';
            if ($status === 'finalized') {
                $counts['finalized']++;
            } else {
                $counts['draft']++;
            }
        }

        return $counts;
    }

    protected function buildWorkload(string $deptSlug, array $user, array $dakRecords, array $letterRecords): array
    {
        $deptUsers = yojaka_dept_users_load($deptSlug);
        $workload = [];

        foreach ($deptUsers as $deptUser) {
            $identities = $deptUser['login_identities'] ?? [];
            $workload[] = [
                'label' => $deptUser['display_name'] ?? ($deptUser['username_base'] ?? ''),
                'identities' => $identities,
                'dak' => 0,
                'letters' => 0,
                'total' => 0,
            ];
        }

        $adminIdentity = $user['login_identity'] ?? ($user['username'] ?? '');
        if ($adminIdentity !== '') {
            $workload[] = [
                'label' => $user['display_name'] ?? 'Department Admin',
                'identities' => [$adminIdentity],
                'dak' => 0,
                'letters' => 0,
                'total' => 0,
            ];
        }

        foreach ($workload as &$entry) {
            foreach ($dakRecords as $record) {
                if (in_array($record['assignee_username'] ?? '', $entry['identities'], true)) {
                    $entry['dak']++;
                }
            }

            foreach ($letterRecords as $record) {
                if (in_array($record['assignee_username'] ?? '', $entry['identities'], true)) {
                    $entry['letters']++;
                }
            }

            $entry['total'] = $entry['dak'] + $entry['letters'];
        }
        unset($entry);

        return $workload;
    }

    protected function dakStepBreakdown(string $deptSlug, array $records): array
    {
        $workflows = yojaka_workflows_list_for_module($deptSlug, 'dak');
        $labels = [];
        foreach ($workflows as $workflow) {
            foreach ($workflow['steps'] ?? [] as $step) {
                $id = $step['id'] ?? '';
                if ($id === '') {
                    continue;
                }
                $labels[$id] = $step['label'] ?? $id;
            }
        }

        $counts = [];
        foreach ($records as $record) {
            $stepId = $record['workflow']['current_step'] ?? 'unknown';
            if (!isset($counts[$stepId])) {
                $counts[$stepId] = 0;
            }
            $counts[$stepId]++;
        }

        $result = [];
        foreach ($counts as $id => $count) {
            $result[] = [
                'id' => $id,
                'label' => $labels[$id] ?? $id,
                'count' => $count,
            ];
        }

        return $result;
    }

    protected function timeMetrics(array $records): array
    {
        $now = new DateTimeImmutable('now');
        $today = $now->format('Y-m-d');
        $weekStart = $now->modify('monday this week')->format('Y-m-d');
        $weekEnd = $now->modify('sunday this week')->format('Y-m-d');
        $month = $now->format('Y-m');

        $metrics = [
            'today' => 0,
            'week' => 0,
            'month' => 0,
        ];

        foreach ($records as $record) {
            $created = $record['created_at'] ?? '';
            $createdDate = $created ? date_create_immutable($created) : null;
            $createdDay = $createdDate ? $createdDate->format('Y-m-d') : null;

            if (!$createdDay) {
                continue;
            }

            if ($createdDay === $today) {
                $metrics['today']++;
            }

            if ($createdDay >= $weekStart && $createdDay <= $weekEnd) {
                $metrics['week']++;
            }

            if (strpos($createdDay, $month) === 0) {
                $metrics['month']++;
            }
        }

        return $metrics;
    }
}
