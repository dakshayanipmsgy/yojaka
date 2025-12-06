<?php
// Global permission catalog for Yojaka.

$YOJAKA_PERMISSION_CATALOG = [
    'rti' => [
        'rti.create',
        'rti.edit',
        'rti.forward',
        'rti.close',
    ],
    'dak' => [
        'dak.create',
        'dak.forward',
        'dak.close',
    ],
    'inspection' => [
        'inspection.create',
        'inspection.edit',
        'inspection.forward',
        'inspection.close',
    ],
    'meeting' => [
        'meeting.create',
        'meeting.edit',
        'meeting.forward',
        'meeting.close',
    ],
    'work_orders' => [
        'work_orders.create',
        'work_orders.edit',
        'work_orders.approve',
        'work_orders.close',
    ],
    'guc' => [
        'guc.create',
        'guc.edit',
        'guc.approve',
    ],
    'bills' => [
        'bills.create',
        'bills.edit',
        'bills.verify',
    ],
    'letters' => [
        'letters.create',
        'letters.edit',
        'letters.view',
        'letters.print',
    ],
    'admin' => [
        'dept.roles.manage',
        'dept.users.manage',
        'dept.workflows.manage',
    ],
];

function yojaka_permissions_catalog(): array
{
    global $YOJAKA_PERMISSION_CATALOG;
    return $YOJAKA_PERMISSION_CATALOG;
}

function yojaka_permissions_all(): array
{
    $catalog = yojaka_permissions_catalog();
    $all = [];

    foreach ($catalog as $group) {
        foreach ($group as $permission) {
            $all[] = $permission;
        }
    }

    return $all;
}
