<?php
// Custom fields helper for Yojaka v1.6

function get_office_custom_fields(string $module): array
{
    $office = get_current_office_config();
    $customFields = $office['custom_fields'][$module] ?? [];
    return is_array($customFields) ? $customFields : [];
}

function render_custom_fields_form(string $module, array $entityCustom = []): void
{
    $fields = get_office_custom_fields($module);
    if (empty($fields)) {
        return;
    }
    echo '<div class="custom-fields">';
    echo '<h3>' . htmlspecialchars(i18n_get('label.custom_fields')) . '</h3>';
    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        if ($name === '') {
            continue;
        }
        $type = $field['type'] ?? 'text';
        $required = !empty($field['required']);
        $value = $entityCustom[$name] ?? '';
        $label = i18n_get($field['label_key'] ?? $name);
        echo '<div class="form-group">';
        echo '<label for="custom_' . htmlspecialchars($name) . '">' . htmlspecialchars($label);
        if ($required) {
            echo ' <span class="required">*</span>';
        }
        echo '</label>';
        $inputName = 'custom[' . htmlspecialchars($name) . ']';
        if ($type === 'textarea') {
            echo '<textarea name="' . $inputName . '" id="custom_' . htmlspecialchars($name) . '" rows="3" ' . ($required ? 'required' : '') . '>' . htmlspecialchars((string) $value) . '</textarea>';
        } else {
            $inputType = in_array($type, ['text', 'date', 'number'], true) ? $type : 'text';
            echo '<input type="' . $inputType . '" name="' . $inputName . '" id="custom_' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string) $value) . '" ' . ($required ? 'required' : '') . ' />';
        }
        echo '</div>';
    }
    echo '</div>';
}

function validate_custom_fields_input(string $module, array $input): array
{
    $fields = get_office_custom_fields($module);
    $errors = [];
    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        if ($name === '') {
            continue;
        }
        $required = !empty($field['required']);
        $value = trim((string) ($input[$name] ?? ''));
        if ($required && $value === '') {
            $errors[] = i18n_get('validation.required', ['field' => i18n_get($field['label_key'] ?? $name)]);
        }
    }
    return $errors;
}

function merge_custom_fields_into_entity(array &$entity, string $module, array $input): void
{
    $fields = get_office_custom_fields($module);
    if (empty($fields)) {
        return;
    }
    if (empty($entity['custom']) || !is_array($entity['custom'])) {
        $entity['custom'] = [];
    }
    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        if ($name === '') {
            continue;
        }
        $value = trim((string) ($input[$name] ?? ''));
        if ($value === '' && empty($field['required'])) {
            unset($entity['custom'][$name]);
            continue;
        }
        $entity['custom'][$name] = $value;
    }
}

function render_custom_fields_view(string $module, array $entityCustom = []): void
{
    $fields = get_office_custom_fields($module);
    if (empty($fields) || empty($entityCustom)) {
        return;
    }
    echo '<div class="panel">';
    echo '<h3>' . htmlspecialchars(i18n_get('label.custom_fields')) . '</h3>';
    echo '<ul class="data-list">';
    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        if ($name === '' || !isset($entityCustom[$name])) {
            continue;
        }
        $label = i18n_get($field['label_key'] ?? $name);
        echo '<li><strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars((string) $entityCustom[$name]) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}
