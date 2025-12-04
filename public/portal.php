<?php
require_once __DIR__ . '/../app/bootstrap.php';

$officeId = isset($_GET['office']) ? trim($_GET['office']) : get_default_office_id();
$registryEntry = find_office_registry_entry($officeId);
if (!$registryEntry || empty($registryEntry['active'])) {
    http_response_code(404);
    echo '<h2>Office not found or inactive.</h2>';
    exit;
}

$GLOBALS['current_office_id_override'] = $officeId;
$officeConfig = load_office_config_by_id($officeId);
$GLOBALS['current_office_config'] = $officeConfig;

$lang = $_GET['lang'] ?? ($officeConfig['default_language'] ?? 'en');
i18n_set_current_language($lang);

$portalConfig = $officeConfig['portal'] ?? [];
$features = $portalConfig['features'] ?? ['rti_status' => true, 'dak_status' => true, 'request' => true];
$kioskMode = (!empty($_GET['kiosk']) && $_GET['kiosk'] === '1') || (!empty($portalConfig['kiosk']['enabled']));
$action = $_GET['action'] ?? '';
if ($kioskMode && $action === '' && !empty($portalConfig['kiosk']['default_action'])) {
    $action = $portalConfig['kiosk']['default_action'];
}

if (empty($portalConfig['enabled'])) {
    echo '<div style="max-width:640px;margin:40px auto;font-family:Arial,sans-serif;text-align:center;">';
    echo '<h2>' . htmlspecialchars($officeConfig['office_name'] ?? 'Office') . '</h2>';
    echo '<p>Public portal is disabled for this office.</p>';
    echo '</div>';
    exit;
}

$ip = get_client_ip();

function portal_layout_start(array $officeConfig, bool $kioskMode): void
{
    $primary = $officeConfig['theme']['primary_color'] ?? '#0f5aa5';
    $secondary = $officeConfig['theme']['secondary_color'] ?? '#f5f7fb';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<title>' . htmlspecialchars(i18n_get('portal.title')) . ' - ' . htmlspecialchars($officeConfig['office_short_name'] ?? 'Yojaka') . '</title>';
    echo '<style>';
    echo 'body{font-family:Arial,sans-serif;background:' . htmlspecialchars($secondary) . ';margin:0;padding:0;}';
    echo '.portal-container{max-width:' . ($kioskMode ? '100%' : '900px') . ';margin:0 auto;padding:20px;}';
    echo '.portal-card{background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.1);margin-bottom:20px;}';
    echo '.portal-actions{display:flex;flex-wrap:wrap;gap:15px;justify-content:center;}';
    echo '.portal-btn{display:inline-block;padding:' . ($kioskMode ? '18px 24px' : '12px 18px') . ';font-size:' . ($kioskMode ? '18px' : '15px') . 'px;border-radius:6px;background:' . htmlspecialchars($primary) . ';color:#fff;text-decoration:none;text-align:center;min-width:220px;}';
    echo '.portal-btn.secondary{background:#444;}';
    echo 'form .form-field{margin-bottom:12px;}';
    echo 'label{display:block;margin-bottom:6px;font-weight:bold;}';
    echo 'input[type=text], input[type=email], input[type=tel], textarea, select{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;font-size:' . ($kioskMode ? '18px' : '14px') . 'px;}';
    echo 'textarea{min-height:120px;}';
    echo '.header{display:flex;align-items:center;gap:10px;padding:10px 20px;background:' . htmlspecialchars($primary) . ';color:#fff;}';
    echo '.header img{max-height:' . ($kioskMode ? '80px' : '60px') . ';}';
    echo '.status-card{background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:12px;margin-top:10px;}';
    echo '.alert{padding:10px;border-radius:4px;margin-bottom:10px;}';
    echo '.alert.error{background:#fee2e2;color:#991b1b;}';
    echo '.alert.success{background:#e0f7e9;color:#1b5e20;}';
    echo '.kiosk-home{margin-top:10px;}';
    echo '</style>';
    echo '</head><body>';
    echo '<div class="header">';
    if (!empty($officeConfig['theme']['logo_path'])) {
        echo '<img src="' . htmlspecialchars($officeConfig['theme']['logo_path']) . '" alt="logo">';
    }
    echo '<div><div style="font-size:' . ($kioskMode ? '24px' : '20px') . ';font-weight:bold;">' . htmlspecialchars($officeConfig['office_name'] ?? 'Yojaka Office') . '</div>';
    echo '<div>' . htmlspecialchars(i18n_get('portal.title')) . '</div></div>';
    echo '</div><div class="portal-container">';
}

function portal_layout_end(bool $kioskMode, array $portalConfig, string $officeId): void
{
    if ($kioskMode) {
        $timeout = (int) ($portalConfig['kiosk']['idle_timeout_seconds'] ?? 300);
        if ($timeout < 30) {
            $timeout = 30;
        }
        echo '<script> (function(){ var timer; function reset(){ clearTimeout(timer); timer=setTimeout(function(){ window.location.href="portal.php?office=' . htmlspecialchars(urlencode($officeId)) . '"; }, ' . ($timeout * 1000) . ');} document.addEventListener("click", reset); document.addEventListener("keydown", reset); reset();})(); </script>';
    }
    echo '</div></body></html>';
}

function portal_nav_links(string $officeId, bool $kioskMode): void
{
    if ($kioskMode) {
        return;
    }
    echo '<div class="portal-actions portal-card">';
    echo '<a class="portal-btn" href="portal.php?office=' . urlencode($officeId) . '&action=rti_status">' . htmlspecialchars(i18n_get('portal.rti_status')) . '</a>';
    echo '<a class="portal-btn" href="portal.php?office=' . urlencode($officeId) . '&action=dak_status">' . htmlspecialchars(i18n_get('portal.dak_status')) . '</a>';
    echo '<a class="portal-btn" href="portal.php?office=' . urlencode($officeId) . '&action=request">' . htmlspecialchars(i18n_get('portal.submit_request')) . '</a>';
    echo '<a class="portal-btn secondary" href="portal.php?office=' . urlencode($officeId) . '&kiosk=1">' . htmlspecialchars(i18n_get('portal.kiosk_mode')) . '</a>';
    echo '</div>';
}

function limited_applicant_name(?string $name): string
{
    if (!$name) {
        return '';
    }
    $first = mb_substr($name, 0, 1, 'UTF-8');
    $stars = str_repeat('*', max(0, mb_strlen($name, 'UTF-8') - 1));
    return $first . $stars;
}

function portal_rti_form(string $officeId, bool $featureEnabled, bool $kioskMode, string $ip): void
{
    $message = '';
    $result = null;
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$featureEnabled) {
            $error = i18n_get('portal.feature_disabled');
        } elseif (!portal_rate_limit_check($ip, 'rti_status')) {
            $error = i18n_get('portal.rate_limit');
        } else {
            portal_rate_limit_record($ip, 'rti_status');
            $rtiId = trim($_POST['rti_id'] ?? '');
            $hint = trim($_POST['verification_hint'] ?? '');
            if ($rtiId === '') {
                $error = i18n_get('validation.required', ['field' => 'RTI ID']);
            } else {
                $cases = filter_records_by_office(load_rti_cases(), $officeId);
                foreach ($cases as $case) {
                    if (($case['id'] ?? '') !== $rtiId) {
                        continue;
                    }
                    if ($hint !== '') {
                        $applicant = $case['applicant_name'] ?? '';
                        $year = substr($case['date_of_receipt'] ?? '', 0, 4);
                        if (stripos($applicant, $hint) !== 0 && $year !== $hint) {
                            continue;
                        }
                    }
                    $result = [
                        'id' => $case['id'] ?? '',
                        'subject' => $case['subject'] ?? '',
                        'applicant' => limited_applicant_name($case['applicant_name'] ?? ''),
                        'date_of_receipt' => $case['date_of_receipt'] ?? '',
                        'reply_deadline' => $case['reply_deadline'] ?? '',
                        'status' => $case['status'] ?? '',
                        'reply_dispatched' => !empty($case['reply_sent_on']),
                        'reply_sent_on' => $case['reply_sent_on'] ?? '',
                    ];
                    log_event('portal_rti_status_view', 'public', ['office_id' => $officeId, 'rti_id' => $rtiId]);
                    write_audit_log('rti', $rtiId, 'public_status_view', ['office_id' => $officeId]);
                    break;
                }
                if (!$result) {
                    $error = i18n_get('portal.no_record');
                }
            }
        }
    }

    echo '<div class="portal-card">';
    echo '<h3>' . htmlspecialchars(i18n_get('portal.rti_status')) . '</h3>';
    if ($error !== '') {
        echo '<div class="alert error">' . htmlspecialchars($error) . '</div>';
    }
    echo '<form method="post">';
    echo '<div class="form-field"><label>RTI ID</label><input type="text" name="rti_id" value="' . htmlspecialchars($_POST['rti_id'] ?? '') . '" required></div>';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.verification_hint')) . '</label><input type="text" name="verification_hint" value="' . htmlspecialchars($_POST['verification_hint'] ?? '') . '"></div>';
    echo '<button class="portal-btn" type="submit">' . htmlspecialchars(i18n_get('btn.search')) . '</button>';
    echo '</form>';
    if ($result) {
        echo '<div class="status-card">';
        echo '<strong>' . htmlspecialchars($result['id']) . '</strong><br>';
        echo htmlspecialchars($result['subject']) . '<br>';
        echo i18n_get('portal.applicant') . ': ' . htmlspecialchars($result['applicant']) . '<br>';
        echo i18n_get('portal.received_on') . ': ' . htmlspecialchars($result['date_of_receipt']) . '<br>';
        echo i18n_get('portal.reply_deadline') . ': ' . htmlspecialchars($result['reply_deadline']) . '<br>';
        echo i18n_get('portal.current_status') . ': ' . htmlspecialchars($result['status']) . '<br>';
        echo i18n_get('portal.reply_sent') . ': ' . (!empty($result['reply_dispatched']) ? i18n_get('portal.yes') : i18n_get('portal.no'));
        if (!empty($result['reply_sent_on'])) {
            echo ' (' . htmlspecialchars($result['reply_sent_on']) . ')';
        }
        echo '</div>';
    }
    echo '</div>';
}

function portal_dak_form(string $officeId, bool $featureEnabled, bool $kioskMode, string $ip): void
{
    $result = null;
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$featureEnabled) {
            $error = i18n_get('portal.feature_disabled');
        } elseif (!portal_rate_limit_check($ip, 'dak_status')) {
            $error = i18n_get('portal.rate_limit');
        } else {
            portal_rate_limit_record($ip, 'dak_status');
            $dakId = trim($_POST['dak_id'] ?? '');
            $hint = trim($_POST['verification_hint'] ?? '');
            if ($dakId === '') {
                $error = i18n_get('validation.required', ['field' => 'Dak/File ID']);
            } else {
                $entries = filter_records_by_office(load_dak_entries(), $officeId);
                foreach ($entries as $entry) {
                    if (($entry['id'] ?? '') !== $dakId) {
                        continue;
                    }
                    if ($hint !== '') {
                        $from = $entry['received_from'] ?? '';
                        if (stripos($from, $hint) !== 0) {
                            continue;
                        }
                    }
                    $movement = $entry['movements'] ?? [];
                    $lastMove = end($movement);
                    $responsible = $entry['assigned_department'] ?? ($entry['assigned_role'] ?? ($entry['section'] ?? ''));
                    if (!$responsible && !empty($entry['assigned_to'])) {
                        $responsible = i18n_get('portal.assigned_officer');
                    }
                    $result = [
                        'id' => $entry['id'] ?? '',
                        'subject' => $entry['subject'] ?? '',
                        'date_received' => $entry['date_received'] ?? ($entry['created_at'] ?? ''),
                        'status' => $entry['status'] ?? '',
                        'responsible' => $responsible,
                        'last_movement' => $lastMove['timestamp'] ?? '',
                    ];
                    log_event('portal_dak_status_view', 'public', ['office_id' => $officeId, 'dak_id' => $dakId]);
                    write_audit_log('dak', $dakId, 'public_status_view', ['office_id' => $officeId]);
                    break;
                }
                if (!$result) {
                    $error = i18n_get('portal.no_record');
                }
            }
        }
    }

    echo '<div class="portal-card">';
    echo '<h3>' . htmlspecialchars(i18n_get('portal.dak_status')) . '</h3>';
    if ($error !== '') {
        echo '<div class="alert error">' . htmlspecialchars($error) . '</div>';
    }
    echo '<form method="post">';
    echo '<div class="form-field"><label>Dak / File ID</label><input type="text" name="dak_id" value="' . htmlspecialchars($_POST['dak_id'] ?? '') . '" required></div>';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.verification_hint')) . '</label><input type="text" name="verification_hint" value="' . htmlspecialchars($_POST['verification_hint'] ?? '') . '"></div>';
    echo '<button class="portal-btn" type="submit">' . htmlspecialchars(i18n_get('btn.search')) . '</button>';
    echo '</form>';
    if ($result) {
        echo '<div class="status-card">';
        echo '<strong>' . htmlspecialchars($result['id']) . '</strong><br>';
        echo htmlspecialchars($result['subject']) . '<br>';
        echo i18n_get('portal.received_on') . ': ' . htmlspecialchars($result['date_received']) . '<br>';
        echo i18n_get('portal.current_status') . ': ' . htmlspecialchars($result['status']) . '<br>';
        if (!empty($result['responsible'])) {
            echo i18n_get('portal.responsible_section') . ': ' . htmlspecialchars($result['responsible']) . '<br>';
        }
        if (!empty($result['last_movement'])) {
            echo i18n_get('portal.last_moved_on') . ': ' . htmlspecialchars($result['last_movement']) . '<br>';
        }
        echo '</div>';
    }
    echo '</div>';
}

function portal_request_form(string $officeId, bool $featureEnabled, bool $kioskMode, string $ip): void
{
    $error = '';
    $successId = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$featureEnabled) {
            $error = i18n_get('portal.feature_disabled');
        } elseif (!portal_rate_limit_check($ip, 'request')) {
            $error = i18n_get('portal.rate_limit');
        } else {
            portal_rate_limit_record($ip, 'request');
            $name = trim($_POST['name'] ?? '');
            $contactEmail = trim($_POST['contact_email'] ?? '');
            $contactPhone = trim($_POST['contact_phone'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $antispam = trim($_POST['antispam'] ?? '');
            $honeypot = trim($_POST['company'] ?? '');

            if ($honeypot !== '') {
                $error = i18n_get('portal.no_record');
            } elseif ($antispam !== '7') {
                $error = i18n_get('portal.spam_failed');
            } elseif ($name === '' || $subject === '' || $description === '' || ($contactEmail === '' && $contactPhone === '')) {
                $error = i18n_get('portal.required_fields');
            } else {
                ensure_dak_storage();
                $entries = load_dak_entries();
                $newId = generate_next_dak_id($entries);
                $now = gmdate('c');
                $newEntry = [
                    'id' => $newId,
                    'office_id' => $officeId,
                    'received_from' => $name,
                    'contact' => trim($contactEmail . ' ' . $contactPhone),
                    'subject' => $subject,
                    'details' => $description,
                    'category' => $category,
                    'origin' => 'public_portal',
                    'status' => 'Received',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'date_received' => date('Y-m-d'),
                    'assigned_to' => null,
                    'movements' => [],
                ];
                append_dak_movement($newEntry, 'created', 'public_portal', null, 'Submitted via public portal');
                log_dak_movement($newId, 'created', 'public_portal', null, 'Submitted via public portal');
                $entries[] = $newEntry;
                save_dak_entries($entries);

                $notifyUser = null;
                foreach (load_users() as $u) {
                    if (($u['office_id'] ?? '') === $officeId && in_array($u['role'] ?? '', ['admin', 'clerk'], true)) {
                        $notifyUser = $u['username'] ?? null;
                        break;
                    }
                }
                if ($notifyUser) {
                    create_notification($notifyUser, 'dak', $newId, 'public_request_submitted', 'New public request', 'A new request was submitted via the public portal.');
                }
                log_event('public_request_created', 'system', ['office_id' => $officeId, 'dak_id' => $newId]);
                write_audit_log('dak', $newId, 'public_request_created', ['office_id' => $officeId]);
                $successId = $newId;
            }
        }
    }

    echo '<div class="portal-card">';
    echo '<h3>' . htmlspecialchars(i18n_get('portal.submit_request')) . '</h3>';
    if ($error !== '') {
        echo '<div class="alert error">' . htmlspecialchars($error) . '</div>';
    } elseif ($successId !== '') {
        echo '<div class="alert success">' . htmlspecialchars(i18n_get('portal.request_submitted')) . ' ' . htmlspecialchars($successId) . '</div>';
        echo '<p>' . htmlspecialchars(i18n_get('portal.check_status_hint')) . '</p>';
    }
    echo '<form method="post">';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.name')) . '</label><input type="text" name="name" value="' . htmlspecialchars($_POST['name'] ?? '') . '" required></div>';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.contact_email')) . '</label><input type="email" name="contact_email" value="' . htmlspecialchars($_POST['contact_email'] ?? '') . '"></div>';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.contact_phone')) . '</label><input type="tel" name="contact_phone" value="' . htmlspecialchars($_POST['contact_phone'] ?? '') . '"></div>';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.subject')) . '</label><input type="text" name="subject" value="' . htmlspecialchars($_POST['subject'] ?? '') . '" required></div>';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.description')) . '</label><textarea name="description" required>' . htmlspecialchars($_POST['description'] ?? '') . '</textarea></div>';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.category')) . '</label><select name="category">';
    $categories = ['' => i18n_get('portal.choose_category'), 'complaint' => i18n_get('portal.category_complaint'), 'suggestion' => i18n_get('portal.category_suggestion'), 'query' => i18n_get('portal.category_query')];
    foreach ($categories as $key => $label) {
        $selected = ($key === ($_POST['category'] ?? '')) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($key) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-field" style="display:none;"><label>Company</label><input type="text" name="company" value=""></div>';
    echo '<div class="form-field"><label>' . htmlspecialchars(i18n_get('portal.antispam_question')) . '</label><input type="text" name="antispam" value=""></div>';
    echo '<button class="portal-btn" type="submit">' . htmlspecialchars(i18n_get('portal.submit_request')) . '</button>';
    echo '</form>';
    echo '</div>';
}

portal_layout_start($officeConfig, $kioskMode);

if ($action === '') {
    echo '<div class="portal-card">';
    echo '<h2>' . htmlspecialchars(i18n_get('portal.title')) . '</h2>';
    echo '<p>' . htmlspecialchars(i18n_get('portal.landing_intro')) . '</p>';
    echo '</div>';
    portal_nav_links($officeId, $kioskMode);
} elseif ($action === 'rti_status') {
    portal_rti_form($officeId, !empty($features['rti_status']), $kioskMode, $ip);
} elseif ($action === 'dak_status') {
    portal_dak_form($officeId, !empty($features['dak_status']), $kioskMode, $ip);
} elseif ($action === 'request') {
    portal_request_form($officeId, !empty($features['request']), $kioskMode, $ip);
}

if ($kioskMode) {
    echo '<div class="kiosk-home">';
    echo '<a class="portal-btn secondary" href="portal.php?office=' . urlencode($officeId) . '&action=' . urlencode($action ?: '') . '">' . htmlspecialchars(i18n_get('portal.reset')) . '</a> ';
    echo '<a class="portal-btn" href="portal.php?office=' . urlencode($officeId) . '&action=' . urlencode($portalConfig['kiosk']['default_action'] ?? '') . '">' . htmlspecialchars(i18n_get('portal.home')) . '</a>';
    echo '</div>';
}

portal_layout_end($kioskMode, $portalConfig, $officeId);
