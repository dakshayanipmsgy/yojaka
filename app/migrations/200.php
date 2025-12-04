<?php
// Migration script placeholder for schema version 200 (v2.0)
function migration_200_run(): array
{
    $summary = [];
    $summary[] = 'Checked base directories';
    bootstrap_ensure_base_directories();
    $summary[] = 'Permissions file ensured';
    bootstrap_seed_permissions();
    return $summary;
}
