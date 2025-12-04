# Staff module updates overview

This document summarizes the latest staff-related changes applied to the project:

- **Unified staff helpers** now live in `app/staff.php`, providing office-aware load/save operations, ID generation, CSV import, and lookup helpers that reference user accounts.
- **Legacy staff handlers** were removed from `app/master_data.php` to avoid duplicate function declarations and fatal errors.
- **Admin master data view** (`app/views/admin_master_data.php`) now loads and imports staff using the unified staff module with the current office context.

Refer to the inline documentation within each file for implementation details.
