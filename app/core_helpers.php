<?php

/**
 * Parse username into [baseUser, baseRoleId, deptSlug].
 * Examples:
 *  - "rkumar.ee.departmentabc" => ["rkumar", "ee", "departmentabc"]
 *  - "admin.departmentabc"     => ["admin", null, "departmentabc"]
 *  - "superadmin"              => ["superadmin", null, null]
 */
function parse_username_parts(?string $username): array {
    if ($username === null) {
        return [null, null, null];
    }
    $parts = explode('.', $username);
    if (count($parts) >= 3) {
        $deptSlug   = array_pop($parts);
        $baseRoleId = array_pop($parts);
        $baseUser   = implode('.', $parts);
        return [$baseUser, $baseRoleId, $deptSlug];
    }
    if (count($parts) === 2) {
        $deptSlug = array_pop($parts);
        $baseUser = implode('.', $parts);
        return [$baseUser, null, $deptSlug];
    }
    return [$username, null, null];
}

/**
 * Safely get ISO8601 timestamp (actual time).
 */
function now_iso8601(): string {
    return date('c');
}
