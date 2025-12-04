<?php
require_login();
if (!user_has_permission('view_all_records') && !user_has_permission('manage_documents_repository')) {
    require_permission('view_all_records');
}

$attachments = load_attachments_meta();
$departments = load_departments();
$users = load_users();

$moduleFilter = $_GET['module'] ?? '';
$departmentFilter = $_GET['department'] ?? '';
$uploaderFilter = $_GET['uploader'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$query = trim($_GET['q'] ?? '');

$filtered = array_filter($attachments, function ($item) use ($moduleFilter, $departmentFilter, $uploaderFilter, $dateFrom, $dateTo, $query) {
    if ($moduleFilter !== '' && ($item['module'] ?? '') !== $moduleFilter) {
        return false;
    }
    if ($departmentFilter !== '' && ($item['department_id'] ?? '') !== $departmentFilter) {
        return false;
    }
    if ($uploaderFilter !== '' && ($item['uploaded_by'] ?? '') !== $uploaderFilter) {
        return false;
    }
    if ($dateFrom !== '') {
        if (empty($item['uploaded_at']) || substr($item['uploaded_at'], 0, 10) < $dateFrom) {
            return false;
        }
    }
    if ($dateTo !== '') {
        if (empty($item['uploaded_at']) || substr($item['uploaded_at'], 0, 10) > $dateTo) {
            return false;
        }
    }
    if ($query !== '') {
        $haystacks = [
            $item['original_name'] ?? '',
            $item['description'] ?? '',
            implode(' ', $item['tags'] ?? []),
            $item['module'] ?? '',
            $item['entity_id'] ?? '',
            $item['uploaded_by'] ?? '',
        ];
        $matched = false;
        foreach ($haystacks as $hay) {
            if (stripos((string) $hay, $query) !== false) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return false;
        }
    }
    return true;
});

usort($filtered, function ($a, $b) {
    return strcmp($b['uploaded_at'] ?? '', $a['uploaded_at'] ?? '');
});

$perPage = $config['pagination_per_page'] ?? 10;
$pageParam = 'p';
$pagination = paginate_array(array_values($filtered), get_page_param($pageParam), $perPage);
$items = $pagination['items'];

function repository_entity_link(array $attachment): ?string
{
    $entityId = $attachment['entity_id'] ?? '';
    if ($entityId === '') {
        return null;
    }
    $module = $attachment['module'] ?? '';
    switch ($module) {
        case 'rti':
            return YOJAKA_BASE_URL . '/app.php?page=rti&mode=view&id=' . urlencode($entityId);
        case 'dak':
            return YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($entityId);
        case 'inspection':
            return YOJAKA_BASE_URL . '/app.php?page=inspection&mode=view&id=' . urlencode($entityId);
        case 'documents':
            // Documents can belong to multiple categories; use meeting minutes as default view
            return YOJAKA_BASE_URL . '/app.php?page=meeting_minutes&mode=view&id=' . urlencode($entityId);
        case 'bills':
            return YOJAKA_BASE_URL . '/app.php?page=bills&mode=view&id=' . urlencode($entityId);
        default:
            return null;
    }
}
?>

<form method="get" action="<?= YOJAKA_BASE_URL; ?>/app.php" class="form-grid" style="margin-bottom: 1rem;">
    <input type="hidden" name="page" value="admin_repository">
    <div class="form-field">
        <label>Module</label>
        <select name="module">
            <option value="">All</option>
            <?php foreach (['rti' => 'RTI', 'dak' => 'Dak', 'inspection' => 'Inspection', 'documents' => 'Documents', 'bills' => 'Bills', 'misc' => 'Misc'] as $value => $label): ?>
                <option value="<?= htmlspecialchars($value); ?>" <?= $moduleFilter === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label>Department</label>
        <select name="department">
            <option value="">All</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= htmlspecialchars($dept['id'] ?? ''); ?>" <?= $departmentFilter === ($dept['id'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($dept['name'] ?? ''); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label>Uploader</label>
        <select name="uploader">
            <option value="">All</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= htmlspecialchars($u['username'] ?? ''); ?>" <?= $uploaderFilter === ($u['username'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($u['full_name'] ?? $u['username'] ?? ''); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label>Date From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom); ?>">
    </div>
    <div class="form-field">
        <label>Date To</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo); ?>">
    </div>
    <div class="form-field">
        <label>Search</label>
        <input type="text" name="q" placeholder="Filename, description, tags" value="<?= htmlspecialchars($query); ?>">
    </div>
    <div class="form-field" style="align-self: end;">
        <button type="submit" class="btn primary">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Module</th>
                <th>Entity</th>
                <th>Description</th>
                <th>Original Name</th>
                <th>Size</th>
                <th>Uploaded By</th>
                <th>Uploaded At</th>
                <th>Department</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="10">No attachments match your filters.</td></tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php $entityLink = repository_entity_link($item); ?>
                    <tr>
                        <td><?= htmlspecialchars($item['id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars(ucwords($item['module'] ?? '')); ?></td>
                        <td>
                            <?php if ($entityLink): ?>
                                <a href="<?= htmlspecialchars($entityLink); ?>"><?= htmlspecialchars($item['entity_id'] ?? ''); ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($item['entity_id'] ?? ''); ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['description'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($item['original_name'] ?? ''); ?></td>
                        <td><?= format_attachment_size((int) ($item['size_bytes'] ?? 0)); ?></td>
                        <td><?= htmlspecialchars($item['uploaded_by'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($item['uploaded_at'] ?? ''); ?></td>
                        <td><?= htmlspecialchars(find_department_by_id($departments, $item['department_id'] ?? '')['name'] ?? ''); ?></td>
                        <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/download_attachment.php?id=<?= urlencode($item['id'] ?? ''); ?>">Download</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination">
        <span>Page <?= (int) $pagination['page']; ?> of <?= (int) $pagination['total_pages']; ?></span>
        <div class="pager-links">
            <?php if ($pagination['page'] > 1): ?>
                <?php $prev = http_build_query(array_merge($_GET, [$pageParam => $pagination['page'] - 1])); ?>
                <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= htmlspecialchars($prev); ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                <?php $next = http_build_query(array_merge($_GET, [$pageParam => $pagination['page'] + 1])); ?>
                <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= htmlspecialchars($next); ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
