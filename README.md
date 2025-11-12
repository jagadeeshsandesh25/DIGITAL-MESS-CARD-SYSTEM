# DIGITAL MESS CARD SYSTEM

> **Short:** digital mess/card & ordering system for Restaurant . Lightweight PHP app — REST-style APIs + web UI for Admin / User / Waiter. Repo contains PHP controllers, API endpoints, views, assets and uploads.

---

## Overview

Digital Mess Card System is a small, easy-to-deploy system to manage mess cards, menus, orders, recharges and transactions. Designed for quick local deployment (XAMPP/LAMP) and modular enough to extend later with mobile apps or payment gateways.

This README gives setup steps, config notes, API overview, and common troubleshooting.

---

## Key Features

* User signup / login
* Admin dashboard: manage users, menus, tables, transactions
* User dashboard: view menu, recharge, order, view QR / card
* Waiter dashboard: receive and update orders
* REST-like API endpoints for all resources (users, menu, cards, feedback, transactions)
* Uploads for user profile pictures and QR codes
* Simple role-based access (admin / user / waiter)

---

## Tech Stack

* PHP (vanilla, file-based controllers)
* MySQL / MariaDB
* Apache (XAMPP / LAMP)
* HTML / CSS / JS (vanilla)
* Optional: Composer if repo uses external libs

---

## Repo Structure (important files)

* `api/` — API endpoints: `auth`, `cards`, `feedback`, `menu`, `recharge`, `tables`, `transactions`, `user_details`, `users`, `waiter`.
* `controllers/` — MVC-like controllers
* `models/` — DB models
* `views/` — frontend pages for auth / dashboard / menu / users
* `config/` — `config.php`, `database.php` (DB connection)
* `uploads/` — profile pics, qr_codes (make this writable)
* `assets/` — css, js, images
* `logs/` — `app.log`

---

## Quick Local Setup (XAMPP / LAMP)

> Assume your project folder: `/opt/lampp/htdocs/your-folder` or `~/Downloads/jai` — change paths as needed.

1. Put project folder inside your webroot (e.g. `/opt/lampp/htdocs/DIGITAL-MESS-CARD-SYSTEM`).
2. Start Apache & MySQL (XAMPP: `sudo /opt/lampp/lampp start`).
3. Create database:

   * Open phpMyAdmin or run:

     ```sql
     CREATE DATABASE digital_mess_card CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
4. Import SQL dump provided in the repo (look for `.sql` file in repo root or `/db` folder). In terminal:

   ```bash
   mysql -u root -p digital_mess_card < path/to/dump.sql
   ```

   Or use phpMyAdmin → Import.
5. Configure DB connection: open `config/database.php` (or `config/config.php`) and set DB host, name, user, password. Example keys you may find / set:

   ```php
   define('DB_HOST','localhost');
   define('DB_NAME','digital_mess_card');
   define('DB_USER','root');
   define('DB_PASS','');
   ```
6. Set file/folder permissions for uploads and logs:

   ```bash
   sudo chown -R www-data:www-data /opt/lampp/htdocs/DIGITAL-MESS-CARD-SYSTEM/uploads
   sudo chmod -R 775 /opt/lampp/htdocs/DIGITAL-MESS-CARD-SYSTEM/uploads
   sudo chmod -R 775 /opt/lampp/htdocs/DIGITAL-MESS-CARD-SYSTEM/logs
   ```
7. (Optional) Configure VirtualHost for clean URL:

   * Add site to `/etc/apache2/sites-available/your-site.conf` and enable `mod_rewrite`.
8. Open app in browser: `http://localhost/DIGITAL-MESS-CARD-SYSTEM` or your virtual host.

---

## Environment & Requirements

* PHP >= 7.4 (8.x recommended)
* MySQL / MariaDB
* PHP extensions: `mysqli`, `pdo`, `mbstring`, `json`, `gd` (if image processing), `fileinfo` (uploads)
* Apache with `mod_rewrite` (optional)

---

## API Endpoints (quick map)

> Base path: `/api/`

* `auth/` — `login.php`, `signup.php`, `logout.php`
* `cards/` — `create.php`, `index.php`, `update.php`, `delete.php`
* `feedback/` — `create.php`, `index.php`, `update.php`, `delete.php`
* `menu/` — `create.php`, `index.php`, `update.php`, `delete.php`
* `recharge/` — `create.php`, `index.php`, `update.php`, `delete.php`
* `tables/` — `create.php`, `index.php`, `update.php`, `delete.php`
* `transactions/` — `create.php`, `index.php`, `update.php`, `delete.php`
* `user_details/` — `create.php`, `index.php`, `update.php`, `delete.php`
* `users/` — `create.php`, `index.php`, `update.php`, `delete.php`
* `waiter/` — `create.php`, `index.php`, `update.php`, `delete.php`

Use these endpoints from frontend or mobile clients. They typically accept `POST`/`GET` form-data or JSON depending on controller.

---

## Common Tasks

### Create Admin user

* Either insert via SQL into `users` table or use `signup` API and update role to `admin` in DB.

### Recreate DB schema

* Re-import SQL dump or run schema script if provided.

### Enable uploads

* Make sure `uploads/` directory writable and `php.ini` `upload_max_filesize` / `post_max_size` are sufficient.

---

## Troubleshooting

* **403 / Permission denied on push** — unrelated to app; Git/GitHub issues.
* **Blank pages / PHP errors** — enable `display_errors` in `php.ini` or check `logs/app.log`.
* **DB connection errors** — check DB credentials in `config/database.php` and ensure DB server running.
* **File upload errors** — check folder perms and `fileinfo` extension.

---

## Deployment Tips

* Use secure DB user with a strong password on production.
* Set proper file permissions: `755` for files, `775` for upload dirs, and never run Apache as root.
* Use HTTPS and enable rate-limiting if exposing APIs publicly.
* For production, remove any example/demo files and set `display_errors = Off`.

---

## Roadmap / Next Features

* UPI / payment gateway integration for recharges
* Push notifications / WebSockets for real-time orders
* Mobile app (Flutter / React Native) using the existing API
* Multi-language support (Telugu / Hindi / English)

---

## Contribution

Feel free to open issues or PRs. Keep code style consistent and add migrations / SQL dumps when changing schema.

---

## Contact

Owner / Lead: **Jai (Jagadeesh Sandesh)**

* Email: `jagadeeshsandesh@gmail.com`

---

> _Ready to deploy. If you want, nenu convert chesi PDF ga or add sample `.env` file and a quick-install script kuda ivvagalanu. Tell me which one you wan
