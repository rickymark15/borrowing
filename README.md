# HulamBee (PHP + MySQL)

A community tool and equipment lending shelf web application.

## Quick Setup
1. Start Apache and MySQL in the XAMPP Control Panel.
2. Ensure this project folder is inside `htdocs/share` (or `htdocs/hulambee`).
3. Open http://localhost/share/ in your web browser. The database `hulambee` and all tables will automatically be created and seeded.
4. (Optional) Run `php install.php` from a terminal in this directory to verify database setup.

## Accounts
- **Admin**: `admin` or `admin@hulambee.local` / Password: `admin123` (Access coordinator dashboard at `/admin`)
- **Member**: `member` or `member@example.com` / Password: `Member123!` (or register a new member with Sign Up)

*Note: Passwords in MySQL are unhashed (plain-text in both `password` and `password_hash` columns) so you can directly view user passwords in phpMyAdmin or the MySQL command line.*

## Features & Fixes
- **Unhashed Database Passwords**: User passwords are saved in plain text directly into the MySQL `password` and `password_hash` columns for simple viewing in phpMyAdmin.
- **Auto Database Creation**: Automatically creates database `hulambee` on startup if it doesn't already exist.
- **Flexible Login**: Members and administrators can log in using either their username or email address.
- **Fully Responsive Design**: Optimized for mobile phones, tablets, and desktop displays with responsive navigation menus, card grids, stacked mobile forms, and scrollable tables.
- **Equipment & Request Management**: Borrow requests, admin approval/decline/return workflows, and image uploads.

## Helpful Scripts (Terminal)
- `php install.php`: Verify database status and table counts.
- `php diagnose.php`: View registered accounts and passwords.
- `php reset_admin.php`: Reset admin credentials to `admin@hulambee.local` / `admin123`.
