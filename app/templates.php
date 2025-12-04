<?php
// Helpers for managing letter templates and generation

function letters_templates_path(): string
{
    global $config;
    $dir = rtrim($config['templates_path'], DIRECTORY_SEPARATOR);
    $file = $config['letters_templates_file'] ?? 'letters.json';
    return $dir . DIRECTORY_SEPARATOR . $file;
}

function generated_letters_log_path(): string
{
    global $config;
    $file = $config['generated_letters_log'] ?? 'generated_letters.log';
    return rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
}

function ensure_templates_directory(): void
{
    global $config;
    $dir = rtrim($config['templates_path'], DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }
}

function load_letter_templates(): array
{
    $path = letters_templates_path();
    if (!file_exists($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_letter_templates(array $templates): bool
{
    $path = letters_templates_path();
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }

    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return true;
}

function find_letter_template_by_id(array $templates, string $id): ?array
{
    foreach ($templates as $template) {
        if (($template['id'] ?? '') === $id) {
            return $template;
        }
    }
    return null;
}

function ensure_default_letter_templates(): void
{
    ensure_templates_directory();
    $path = letters_templates_path();
    $templates = load_letter_templates();

    if (file_exists($path) && !empty($templates)) {
        return;
    }

    $defaults = [
        [
            'id' => 'notice_delay_payment',
            'name' => 'Notice for Delay in Payment',
            'description' => 'Standard notice for delayed payment cases.',
            'category' => 'Notice',
            'active' => true,
            'variables' => [
                ['name' => 'applicant_name', 'label' => 'Applicant Name', 'type' => 'text', 'required' => true],
                ['name' => 'reference_number', 'label' => 'Reference Number', 'type' => 'text', 'required' => true],
                ['name' => 'notice_date', 'label' => 'Notice Date', 'type' => 'date', 'required' => true],
            ],
            'body' => "To,\n{{applicant_name}}\nRef: {{reference_number}}\nDate: {{notice_date}}\n\nSubject: Notice regarding delayed payment.\n\n[Body content here]\n\nAuthorized Signatory",
        ],
        [
            'id' => 'appointment_letter_basic',
            'name' => 'Appointment Letter (Basic)',
            'description' => 'Basic appointment confirmation letter.',
            'category' => 'Letter',
            'active' => true,
            'variables' => [
                ['name' => 'recipient_name', 'label' => 'Recipient Name', 'type' => 'text', 'required' => true],
                ['name' => 'position', 'label' => 'Position/Role', 'type' => 'text', 'required' => true],
                ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'required' => true],
                ['name' => 'office_location', 'label' => 'Office Location', 'type' => 'text', 'required' => true],
            ],
            'body' => "Dear {{recipient_name}},\n\nWe are pleased to confirm your appointment as {{position}} effective {{start_date}} at {{office_location}}.\n\nPlease report to the undersigned on the mentioned date.\n\nRegards,\n\nAuthorized Signatory",
        ],
        [
            'id' => 'information_request',
            'name' => 'Request for Additional Information',
            'description' => 'Letter requesting additional documents or information.',
            'category' => 'Letter',
            'active' => true,
            'variables' => [
                ['name' => 'requestee_name', 'label' => 'Requestee Name', 'type' => 'text', 'required' => true],
                ['name' => 'case_number', 'label' => 'Case/File Number', 'type' => 'text', 'required' => true],
                ['name' => 'submission_deadline', 'label' => 'Submission Deadline', 'type' => 'date', 'required' => true],
                ['name' => 'required_documents', 'label' => 'Required Documents', 'type' => 'textarea', 'required' => true],
            ],
            'body' => "To,\n{{requestee_name}}\nSubject: Additional information required for case {{case_number}}.\n\nKindly submit the following documents by {{submission_deadline}}:\n\n{{required_documents}}\n\nThank you for your cooperation.\n\nAuthorized Signatory",
        ],
    ];

    save_letter_templates($defaults);
}

function render_template_body(string $templateBody, array $variablesInput): string
{
    $safeBody = htmlspecialchars($templateBody, ENT_QUOTES, 'UTF-8');
    $replacements = [];
    foreach ($variablesInput as $key => $value) {
        $replacements['{{' . $key . '}}'] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return strtr($safeBody, $replacements);
}

function append_generated_letter_record(string $username, string $templateId, string $templateName, string $mergedContent): void
{
    $path = generated_letters_log_path();
    $entry = [
        'timestamp' => gmdate('c'),
        'username' => $username,
        'template_id' => $templateId,
        'template_name' => $templateName,
        'preview_snippet' => mb_substr(strip_tags($mergedContent), 0, 200),
    ];

    $jsonLine = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
    $handle = @fopen($path, 'a');
    if (!$handle) {
        return;
    }

    if (flock($handle, LOCK_EX)) {
        fwrite($handle, $jsonLine);
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}
