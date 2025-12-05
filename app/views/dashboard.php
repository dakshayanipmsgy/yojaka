<?php
require_login();
$modules = [
    ['title' => 'RTI Cases', 'description' => 'Register and track RTI cases.', 'page' => 'rti'],
    ['title' => 'Dak & File Movement', 'description' => 'Manage dak receipt and file movement.', 'page' => 'dak'],
    ['title' => 'Inspection Reports', 'description' => 'Record inspections and track follow-up.', 'page' => 'inspection'],
    ['title' => 'Meeting Minutes', 'description' => 'Prepare and share meeting minutes.', 'page' => 'meeting_minutes'],
    ['title' => 'Work Orders', 'description' => 'Issue and monitor work orders.', 'page' => 'work_orders'],
    ['title' => 'Grant Utilization Certificates', 'description' => 'Generate and manage GUC submissions.', 'page' => 'guc'],
    ['title' => 'Contractor Bills', 'description' => 'Track contractor bills and payments.', 'page' => 'bills'],
];
?>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}
.dashboard-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    background: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.1s ease, box-shadow 0.1s ease;
}
.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.08);
}
.dashboard-card h3 {
    margin-top: 0;
    margin-bottom: 0.5rem;
}
.dashboard-card p {
    color: #555;
    flex: 1;
}
.dashboard-card .btn {
    align-self: flex-start;
}
</style>

<div class="dashboard-grid">
    <?php foreach ($modules as $module): ?>
        <a class="dashboard-card" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=<?= htmlspecialchars($module['page']); ?>">
            <div>
                <h3><?= htmlspecialchars($module['title']); ?></h3>
                <p><?= htmlspecialchars($module['description']); ?></p>
            </div>
            <span class="btn primary">Open</span>
        </a>
    <?php endforeach; ?>
</div>
