<?php
require_login();

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../acl.php';

$q = trim($_GET['q'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$selectedModules = $_GET['modules'] ?? ['rti', 'dak', 'inspection', 'documents', 'bills', 'attachments'];
if (!is_array($selectedModules)) {
    $selectedModules = [$selectedModules];
}

function gs_text_match(string $haystack, string $needle): bool
{
    return stripos($haystack, $needle) !== false;
}

function gs_date_within(?string $dateValue, string $from, string $to): bool
{
    if ($from === '' && $to === '') {
        return true;
    }
    if (!$dateValue) {
        return false;
    }
    $date = substr($dateValue, 0, 10);
    if ($from !== '' && $date < $from) {
        return false;
    }
    if ($to !== '' && $date > $to) {
        return false;
    }
    return true;
}

$user = yojaka_current_user();
$currentUser = $user;
$canViewAll = user_has_permission('view_all_records');
$results = [];

if ($q !== '') {
    if (in_array('rti', $selectedModules, true)) {
        $items = [];
        foreach (load_rti_cases() as $case) {
            if (!$canViewAll && !user_has_permission('manage_rti') && ($case['created_by'] ?? '') !== ($user['username'] ?? '')) {
                continue;
            }
            if (!gs_date_within($case['created_at'] ?? $case['date_of_receipt'] ?? '', $dateFrom, $dateTo)) {
                continue;
            }
            $hay = implode(' ', [
                $case['id'] ?? '',
                $case['reference_number'] ?? '',
                $case['applicant_name'] ?? '',
                $case['subject'] ?? '',
                $case['details'] ?? '',
            ]);
            if (gs_text_match($hay, $q)) {
                $items[] = $case;
            }
        }
        $results['rti'] = array_slice($items, 0, 20);
    }

    if (in_array('dak', $selectedModules, true)) {
        $items = [];
        foreach (load_dak_entries() as $entry) {
            if (!$canViewAll && !user_has_permission('manage_dak')) {
                $allowed = ($entry['created_by'] ?? '') === ($user['username'] ?? '') || ($entry['assigned_to'] ?? '') === ($user['username'] ?? '');
                if (!$allowed) {
                    continue;
                }
            }
            if (!gs_date_within($entry['created_at'] ?? $entry['date_received'] ?? '', $dateFrom, $dateTo)) {
                continue;
            }
            $hay = implode(' ', [
                $entry['id'] ?? '',
                $entry['reference_no'] ?? '',
                $entry['received_from'] ?? '',
                $entry['subject'] ?? '',
                $entry['details'] ?? '',
            ]);
            if (gs_text_match($hay, $q)) {
                $items[] = $entry;
            }
        }
        $results['dak'] = array_slice($items, 0, 20);
    }

    if (in_array('inspection', $selectedModules, true)) {
        $items = [];
        foreach (load_inspection_reports() as $rep) {
            $rep = acl_normalize($rep);
            if (!acl_can_view($currentUser, $rep)) {
                continue;
            }
            if (!gs_date_within($rep['created_at'] ?? '', $dateFrom, $dateTo)) {
                continue;
            }
            $hay = implode(' ', [
                $rep['id'] ?? '',
                $rep['title'] ?? '',
                $rep['template_name'] ?? '',
                $rep['location'] ?? '',
                $rep['inspecting_officer'] ?? '',
                $rep['summary'] ?? '',
            ]);
            if (gs_text_match($hay, $q)) {
                $items[] = $rep;
            }
        }
        $results['inspection'] = array_slice($items, 0, 20);
    }

    if (in_array('documents', $selectedModules, true)) {
        $categories = ['meeting_minutes', 'work_orders', 'guc'];
        $items = [];
        foreach ($categories as $cat) {
            foreach (load_document_records($cat) as $doc) {
                if (!$canViewAll && ($doc['created_by'] ?? '') !== ($user['username'] ?? '')) {
                    continue;
                }
                if (!gs_date_within($doc['created_at'] ?? '', $dateFrom, $dateTo)) {
                    continue;
                }
                $hay = implode(' ', [
                    $doc['id'] ?? '',
                    $doc['template_name'] ?? '',
                    $doc['category'] ?? '',
                    implode(' ', $doc['fields'] ?? []),
                ]);
                if (gs_text_match($hay, $q)) {
                    $doc['category'] = $cat;
                    $items[] = $doc;
                }
            }
        }
        $results['documents'] = array_slice($items, 0, 20);
    }

    if (in_array('bills', $selectedModules, true)) {
        $items = [];
        foreach (load_bills() as $bill) {
            if (!$canViewAll && !user_has_permission('manage_bills') && ($bill['created_by'] ?? '') !== ($user['username'] ?? '')) {
                continue;
            }
            if (!gs_date_within($bill['created_at'] ?? '', $dateFrom, $dateTo)) {
                continue;
            }
            $hay = implode(' ', [
                $bill['id'] ?? '',
                $bill['bill_no'] ?? '',
                $bill['contractor_name'] ?? '',
                $bill['work_description'] ?? '',
            ]);
            if (gs_text_match($hay, $q)) {
                $items[] = $bill;
            }
        }
        $results['bills'] = array_slice($items, 0, 20);
    }

    if (in_array('attachments', $selectedModules, true)) {
        $items = [];
        foreach (load_attachments_meta() as $att) {
            $allowed = $canViewAll || user_has_permission('manage_documents_repository') || (($att['uploaded_by'] ?? '') === ($user['username'] ?? ''));
            if (!$allowed) {
                continue;
            }
            if (!gs_date_within($att['uploaded_at'] ?? '', $dateFrom, $dateTo)) {
                continue;
            }
            $hay = implode(' ', [
                $att['id'] ?? '',
                $att['original_name'] ?? '',
                $att['description'] ?? '',
                implode(' ', $att['tags'] ?? []),
                $att['module'] ?? '',
                $att['entity_id'] ?? '',
            ]);
            if (gs_text_match($hay, $q)) {
                $items[] = $att;
            }
        }
        $results['attachments'] = array_slice($items, 0, 20);
    }
}
?>

<div class="card" style="margin-bottom:1rem;">
    <form method="get" action="<?= YOJAKA_BASE_URL; ?>/app.php">
        <input type="hidden" name="page" value="global_search">
        <div class="form-grid">
            <div class="form-field">
                <label>Search text</label>
                <input type="text" name="q" placeholder="Search across modules" value="<?= htmlspecialchars($q); ?>">
            </div>
            <div class="form-field">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="form-field">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo); ?>">
            </div>
        </div>
        <div class="form-field" style="display:flex; gap:10px; flex-wrap:wrap;">
            <?php
            $moduleLabels = [
                'rti' => 'RTI',
                'dak' => 'Dak',
                'inspection' => 'Inspection',
                'documents' => 'Documents',
                'bills' => 'Bills',
                'attachments' => 'Attachments',
            ];
            foreach ($moduleLabels as $value => $label):
                $checked = in_array($value, $selectedModules, true) ? 'checked' : '';
            ?>
                <label style="display:inline-flex; align-items:center; gap:6px;">
                    <input type="checkbox" name="modules[]" value="<?= htmlspecialchars($value); ?>" <?= $checked; ?>> <?= htmlspecialchars($label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="actions">
            <button type="submit" class="btn primary">Search</button>
        </div>
    </form>
</div>

<?php if ($q === ''): ?>
    <p class="muted">Enter a search term to find records across modules and attachments.</p>
<?php else: ?>
    <?php if (empty($results)): ?>
        <p>No results found.</p>
    <?php else: ?>
        <?php if (!empty($results['rti'])): ?>
            <h3>RTI Cases</h3>
            <table class="table">
                <thead><tr><th>ID</th><th>Applicant</th><th>Subject</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($results['rti'] as $case): ?>
                        <tr>
                            <td><?= htmlspecialchars($case['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['applicant_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['subject'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['date_of_receipt'] ?? ''); ?></td>
                            <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti&mode=view&id=<?= urlencode($case['id'] ?? ''); ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($results['dak'])): ?>
            <h3>Dak Entries</h3>
            <table class="table">
                <thead><tr><th>ID</th><th>Reference</th><th>From</th><th>Subject</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($results['dak'] as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($entry['reference_no'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($entry['received_from'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($entry['subject'] ?? ''); ?></td>
                            <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak&mode=view&id=<?= urlencode($entry['id'] ?? ''); ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($results['inspection'])): ?>
            <h3>Inspection Reports</h3>
            <table class="table">
                <thead><tr><th>ID</th><th>Title</th><th>Location</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($results['inspection'] as $rep): ?>
                        <tr>
                            <td><?= htmlspecialchars($rep['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($rep['title'] ?? ($rep['template_name'] ?? '')); ?></td>
                            <td><?= htmlspecialchars($rep['location'] ?? ''); ?></td>
                            <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection&mode=view&id=<?= urlencode($rep['id'] ?? ''); ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($results['documents'])): ?>
            <h3>Documents</h3>
            <table class="table">
                <thead><tr><th>ID</th><th>Category</th><th>Template</th><th>Created</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($results['documents'] as $doc): ?>
                        <?php
                        $categoryPage = $doc['category'] ?? 'meeting_minutes';
                        $pageMap = [
                            'meeting_minutes' => 'meeting_minutes',
                            'work_orders' => 'work_orders',
                            'guc' => 'guc',
                        ];
                        $page = $pageMap[$categoryPage] ?? 'meeting_minutes';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $categoryPage)); ?></td>
                            <td><?= htmlspecialchars($doc['template_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($doc['created_at'] ?? ''); ?></td>
                            <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=<?= urlencode($page); ?>&mode=view&id=<?= urlencode($doc['id'] ?? ''); ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($results['bills'])): ?>
            <h3>Bills</h3>
            <table class="table">
                <thead><tr><th>ID</th><th>Bill No</th><th>Contractor</th><th>Work</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($results['bills'] as $bill): ?>
                        <tr>
                            <td><?= htmlspecialchars($bill['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($bill['bill_no'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($bill['contractor_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($bill['work_description'] ?? ''); ?></td>
                            <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills&mode=view&id=<?= urlencode($bill['id'] ?? ''); ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($results['attachments'])): ?>
            <h3>Attachments</h3>
            <table class="table">
                <thead><tr><th>ID</th><th>Module</th><th>Entity</th><th>Description</th><th>File</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($results['attachments'] as $att): ?>
                        <tr>
                            <td><?= htmlspecialchars($att['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($att['module'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($att['entity_id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($att['description'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($att['original_name'] ?? ''); ?></td>
                            <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/download_attachment.php?id=<?= urlencode($att['id'] ?? ''); ?>">Download</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
