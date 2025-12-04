<?php
// SLA helper functions for Yojaka v1.3

function compute_due_date(string $base_date, int $days): ?string
{
    if (empty($base_date)) {
        return null;
    }
    try {
        $date = new DateTime($base_date);
        $date->modify('+' . $days . ' days');
        return $date->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

function is_due_soon(?string $due_date, int $reminder_before_days): bool
{
    if (!$due_date) {
        return false;
    }
    $today = new DateTime('today');
    $due = DateTime::createFromFormat('Y-m-d', $due_date);
    if (!$due) {
        return false;
    }
    $diff = (int) $today->diff($due)->format('%r%a');
    return $diff >= 0 && $diff <= $reminder_before_days;
}

function is_overdue_generic(?string $due_date): bool
{
    if (!$due_date) {
        return false;
    }
    $today = new DateTime('today');
    $due = DateTime::createFromFormat('Y-m-d', $due_date);
    if (!$due) {
        return false;
    }
    return $today > $due;
}

function run_sla_checks(): void
{
    global $config;
    $currentUser = current_user();
    if (!$currentUser) {
        return;
    }

    $settings = $config['sla'] ?? [];
    $rtiReplyDays = (int) ($settings['rti_reply_days'] ?? ($config['rti_reply_days'] ?? 30));
    $rtiReminder = (int) ($settings['rti_reminder_before_days'] ?? 5);
    $dakProcessDays = (int) ($settings['dak_process_days'] ?? ($config['dak_overdue_days'] ?? 7));
    $dakReminder = (int) ($settings['dak_reminder_before_days'] ?? 2);
    $billApprovalDays = (int) ($settings['bill_approval_days'] ?? 10);
    $billReminder = (int) ($settings['bill_reminder_before_days'] ?? 3);

    // RTI reminders
    $rtiCases = load_rti_cases();
    $rtiChanged = false;
    foreach ($rtiCases as &$case) {
        $case = enrich_workflow_defaults('rti', $case);
        $dueDate = $case['reply_deadline'] ?? compute_due_date($case['date_of_receipt'] ?? '', $rtiReplyDays);
        if ($dueDate && empty($case['reply_deadline'])) {
            $case['reply_deadline'] = $dueDate;
            $rtiChanged = true;
        }
        $assignee = $case['assigned_to'] ?? null;
        if (!$assignee) {
            continue;
        }
        $lastReminder = $case['last_sla_reminder_at'] ?? null;
        $shouldRemind = is_due_soon($dueDate, $rtiReminder);
        $overdue = is_overdue_generic($dueDate);
        if (($shouldRemind || $overdue) && should_send_new_reminder($lastReminder)) {
            $title = $overdue ? 'RTI case overdue' : 'RTI case nearing deadline';
            $message = ($case['id'] ?? 'RTI case') . ' requires attention. Due date: ' . $dueDate;
            create_notification($assignee, 'rti', $case['id'] ?? '', 'sla_reminder', $title, $message);
            $case['last_sla_reminder_at'] = gmdate('c');
            $rtiChanged = true;
            log_event('sla_reminder_created', $currentUser['username'] ?? null, ['module' => 'rti', 'entity_id' => $case['id'] ?? '', 'due_date' => $dueDate]);
        }
    }
    unset($case);
    if ($rtiChanged) {
        save_rti_cases($rtiCases);
    }

    // Dak reminders
    $dakEntries = load_dak_entries();
    $dakChanged = false;
    foreach ($dakEntries as &$entry) {
        $entry = enrich_workflow_defaults('dak', $entry);
        $dueDate = compute_due_date($entry['date_received'] ?? '', $dakProcessDays);
        $assignee = $entry['assigned_to'] ?? null;
        if (!$assignee) {
            continue;
        }
        $lastReminder = $entry['last_sla_reminder_at'] ?? null;
        $shouldRemind = is_due_soon($dueDate, $dakReminder);
        $overdue = is_overdue_generic($dueDate);
        if (($shouldRemind || $overdue) && should_send_new_reminder($lastReminder)) {
            $title = $overdue ? 'Dak entry overdue' : 'Dak entry nearing SLA';
            $message = ($entry['id'] ?? 'Dak entry') . ' needs processing by ' . ($dueDate ?? 'N/A');
            create_notification($assignee, 'dak', $entry['id'] ?? '', 'sla_reminder', $title, $message);
            $entry['last_sla_reminder_at'] = gmdate('c');
            $dakChanged = true;
            log_event('sla_reminder_created', $currentUser['username'] ?? null, ['module' => 'dak', 'entity_id' => $entry['id'] ?? '', 'due_date' => $dueDate]);
        }
    }
    unset($entry);
    if ($dakChanged) {
        save_dak_entries($dakEntries);
    }

    // Bill reminders
    $bills = load_bills();
    $billChanged = false;
    foreach ($bills as &$bill) {
        $bill = enrich_workflow_defaults('bills', $bill);
        $base = $bill['submitted_at'] ?? $bill['created_at'] ?? null;
        $dueDate = $base ? compute_due_date(substr($base, 0, 10), $billApprovalDays) : null;
        $assignee = $bill['current_approver'] ?? $bill['assigned_to'] ?? null;
        if (!$assignee || !$dueDate) {
            continue;
        }
        $lastReminder = $bill['last_sla_reminder_at'] ?? null;
        $shouldRemind = is_due_soon($dueDate, $billReminder);
        $overdue = is_overdue_generic($dueDate);
        if (($shouldRemind || $overdue) && should_send_new_reminder($lastReminder)) {
            $title = $overdue ? 'Bill approval overdue' : 'Bill approval deadline approaching';
            $message = ($bill['bill_no'] ?? $bill['id'] ?? 'Bill') . ' needs approval by ' . $dueDate;
            create_notification($assignee, 'bills', $bill['id'] ?? '', 'sla_reminder', $title, $message);
            $bill['last_sla_reminder_at'] = gmdate('c');
            $billChanged = true;
            log_event('sla_reminder_created', $currentUser['username'] ?? null, ['module' => 'bills', 'entity_id' => $bill['id'] ?? '', 'due_date' => $dueDate]);
        }
    }
    unset($bill);
    if ($billChanged) {
        save_bills($bills);
    }
}

function should_send_new_reminder(?string $lastReminder): bool
{
    if (!$lastReminder) {
        return true;
    }
    $last = strtotime($lastReminder);
    return (time() - $last) > 86400; // once per day
}
