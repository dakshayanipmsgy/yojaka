# Yojaka

Yojaka is a multi-tenant government office work, file, and document system. This repository currently contains the initial PHP skeleton with a front controller and simple routing to prepare for future modules.

## Structure
- `public/` – Web root containing `index.php` front controller and rewrite rules.
- `app/` – Core application code (controllers, views, core classes, bootstrap).
- `config/` – Configuration files.
- `data/` – Filesystem storage placeholder (outside public web root), with `departments/` and `logs/` subdirectories.

## Getting Started
1. Point your web server document root to the `public/` directory.
2. Access the root URL (e.g., `/` or `/home/index`) to see the welcome page.
3. Unknown routes will return a simple 404 page.

No external dependencies or databases are required for this skeleton.
