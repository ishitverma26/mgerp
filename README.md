# Cement Plant Management System (Core PHP)

Digitises raw material inward, lot-wise FIFO stock, processing, packing production
(cumulative, nozzle-wise), damage/reprocessing/garbage, stock ledger, audit log,
reports and dashboards - built to the module plan discussed earlier.

## 1. Requirements
- PHP 8.0+ with the `pdo_mysql` extension
- MySQL 5.7+ / MariaDB
- Apache with `mod_rewrite`/`.htaccess` support (XAMPP, Laragon, WAMP all work)

## 2. Setup
1. Copy this whole `cement-erp` folder into your server's web root
   (e.g. `C:\xampp\htdocs\cement-erp`).
2. Start Apache + MySQL (e.g. from the XAMPP Control Panel).
3. Open `http://localhost/phpmyadmin`, create nothing manually - instead open the
   **SQL** tab and run, in order:
   - `database/schema.sql` (creates the `cement_erp` database and all tables)
   - `database/seed.sql` (roles, sample products/tokens/raw materials)
4. Edit `config/constants.php` if your DB user/password or folder name differ
   from the defaults (`root` / no password / `/cement-erp`).
5. Visit `http://localhost/cement-erp/database/create-admin.php` **once** to
   create your first Admin login. Then delete that file (it has no login check
   on purpose, since there's no admin yet to log in with).
6. Log in at `http://localhost/cement-erp/modules/auth/login.php`.

## 3. What's implemented (Modules 0-12 from the plan)
- Auth + role-based access (Admin / Plant Head)
- Master data: Vendors, Raw Materials, Products (full SKU - name + size together), Tokens, Users
- Raw Material Inward with automatic per-Jumbo weight calculation and Lot creation
- Lot-wise Raw Material Stock
- FIFO Processing engine (`classes/FifoProcessor.php`) - preview then confirm
- Production Batches (auto batch numbers, target, auto-complete, reopen)
- Packing Production Updates - cumulative, Left/Right nozzle, full history kept
- Damage entry -> Reprocessing / Garbage routing
- Stock Ledger (every stock movement, one shared helper)
- Audit Log (every create/update/status-change, one shared helper)
- 6 reports + Admin dashboard (detailed) + Plant Head dashboard (simple)

## 4. Folder structure
See `cement-plant-erp-module-plan.md` (the earlier planning document) for the
full module-by-module breakdown - the code here follows it exactly.

## 5. Security notes already in place
- Passwords hashed with `password_hash()` / verified with `password_verify()`
- All queries use PDO prepared statements
- `config/`, `includes/`, `classes/`, `database/` are blocked from direct
  browser access via `.htaccess`
- Uploaded slip files can't be executed as PHP even if renamed
- Delete `database/create-admin.php` after first use

## 6. Known simplifications / good next steps
- No formal permissions table yet - access is role-based (Admin / Plant Head)
  only, matching the original requirement. A granular per-module permission
  table can be added later without changing the rest of the schema.
- Reports are on-screen only; Excel/PDF export can be added with
  PhpSpreadsheet / TCPDF when needed.
- Reprocessing currently records the quantity and status; linking a
  reprocessing entry to the *new* processing run that actually recovers the
  material is a manual step (create a Processing entry as usual once the
  material is physically reprocessed).
