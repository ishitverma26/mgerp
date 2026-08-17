-- ============================================================
-- CEMENT PLANT MANAGEMENT SYSTEM - DATABASE SCHEMA
-- ============================================================
CREATE DATABASE IF NOT EXISTS cement_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cement_erp;

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Simple key/value store for app-wide settings (company name, logo, ...)
-- managed from the Admin Settings page.
CREATE TABLE app_settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact_no VARCHAR(20),
  address VARCHAR(255),
  gst_no VARCHAR(30),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE raw_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  unit VARCHAR(10) DEFAULT 'MT',
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- A "Product" is a full SKU - name + size together (e.g. "MG Cem 25kg") -
-- there is no separate Bag Size master, by design.
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  size_kg DECIMAL(6,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token_value VARCHAR(20) NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Which tokens are valid for which product (SKU) - many-to-many, a token
-- can be shared across several products. Filters the Token dropdown when
-- creating/targeting a batch for a given product.
CREATE TABLE product_tokens (
  product_id INT NOT NULL,
  token_id INT NOT NULL,
  PRIMARY KEY (product_id, token_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE raw_material_inward (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lot_no VARCHAR(20) UNIQUE NOT NULL,
  vendor_id INT NOT NULL,
  raw_material_id INT NOT NULL,
  vehicle_no VARCHAR(30),
  inward_date DATE NOT NULL,
  gross_weight DECIMAL(10,4) NOT NULL,
  jumbo_qty INT NOT NULL,
  per_jumbo_weight DECIMAL(10,6) NOT NULL,
  weighbridge_slip_no VARCHAR(50),
  slip_photo VARCHAR(255),
  remarks TEXT,
  freight_payment_status ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
  material_payment_status ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id),
  FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE raw_material_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inward_id INT UNIQUE NOT NULL,
  lot_no VARCHAR(20) NOT NULL,
  vendor_id INT NOT NULL,
  raw_material_id INT NOT NULL,
  total_jumbo INT NOT NULL,
  remaining_jumbo INT NOT NULL,
  per_jumbo_weight DECIMAL(10,6) NOT NULL,
  total_mt DECIMAL(10,4) NOT NULL,
  remaining_mt DECIMAL(10,4) NOT NULL,
  status ENUM('active','exhausted') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inward_id) REFERENCES raw_material_inward(id),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id),
  FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
) ENGINE=InnoDB;

CREATE TABLE processing_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  raw_material_id INT NOT NULL,
  requirement_jumbo INT NOT NULL,
  total_mt_consumed DECIMAL(10,6) NOT NULL,
  processing_date DATE NOT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE processing_lot_consumption (
  id INT AUTO_INCREMENT PRIMARY KEY,
  processing_id INT NOT NULL,
  stock_id INT NOT NULL,
  jumbo_consumed INT NOT NULL,
  mt_consumed DECIMAL(10,6) NOT NULL,
  FOREIGN KEY (processing_id) REFERENCES processing_requests(id),
  FOREIGN KEY (stock_id) REFERENCES raw_material_stock(id)
) ENGINE=InnoDB;

-- A Batch (batch_no, e.g. "B-001") is a container - it can hold several
-- SKUs at once (one plant batch commonly produces multiple products).
-- Each SKU-within-a-batch is its own row in production_batches, carrying
-- its own product/token/target/progress independently.
CREATE TABLE batch_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_no VARCHAR(20) UNIQUE NOT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE production_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_group_id INT NOT NULL,
  product_id INT NOT NULL,
  token_id INT NOT NULL,
  target_bags INT NULL,
  status ENUM('active','completed','reopened') DEFAULT 'active',
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  -- Set whenever Admin edits this SKU's token/target after creation - lets
  -- the batch list flag the line as "Revised" for Plant Head.
  updated_at TIMESTAMP NULL,
  -- Live Stock carry-forward: how many bags of this SKU's target were
  -- pre-filled from another (older, completed) batch's surplus instead of
  -- needing fresh packing, and which batch(es) that surplus came from
  -- (comma-joined batch_no if it took more than one source to fill it).
  -- Counts toward this batch's own progress/completion but is never
  -- written into packing_production_updates - that table stays a pure
  -- log of real nozzle readings.
  carried_forward_qty INT NOT NULL DEFAULT 0,
  carried_forward_from_batch_no VARCHAR(100) NULL,
  -- On a COMPLETED batch, how much of ITS OWN surplus (completed minus
  -- target) has already been claimed as carry-forward by later batches -
  -- so the same surplus bags can't be handed out twice.
  live_stock_claimed_qty INT NOT NULL DEFAULT 0,
  FOREIGN KEY (batch_group_id) REFERENCES batch_groups(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (token_id) REFERENCES tokens(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Vehicle dispatch for a fully-completed batch (every SKU in the group
-- done). Admin assigns a vehicle (a batch can need more than one, so this
-- is one row per vehicle, not unique per batch) - Plant Head then walks it
-- through assigned -> accepted -> loaded -> dispatched. Shown right on the
-- batch's own card (see includes/partials/batch-cards.php) once the whole
-- group is Completed.
CREATE TABLE batch_vehicle_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_group_id INT NOT NULL,
  vehicle_no VARCHAR(50) NOT NULL,
  arrival_time DATETIME NOT NULL,
  capacity_mt DECIMAL(10,2) NOT NULL,
  status ENUM('assigned','accepted','loaded','dispatched') NOT NULL DEFAULT 'assigned',
  accepted_at DATETIME NULL,
  loaded_at DATETIME NULL,
  dispatched_at DATETIME NULL,
  assigned_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_group_id) REFERENCES batch_groups(id),
  FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- What's being loaded onto one vehicle - one row per SKU (picked from the
-- batch's own SKU list), bags entered by Admin, MT computed from that
-- SKU's size_kg so it doesn't have to be typed in separately.
CREATE TABLE batch_vehicle_load_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_assignment_id INT NOT NULL,
  production_batch_id INT NOT NULL,
  bags INT NOT NULL,
  mt DECIMAL(10,3) NOT NULL,
  FOREIGN KEY (vehicle_assignment_id) REFERENCES batch_vehicle_assignments(id),
  FOREIGN KEY (production_batch_id) REFERENCES production_batches(id)
) ENGINE=InnoDB;

-- Exact per-source ledger of Live Stock carry-forward claims (see
-- claimLiveStock() in includes/functions.php). production_batches only
-- keeps an AGGREGATE claimed/carried-forward total per batch - this table
-- records precisely how much of THAT total came from which source
-- batch(es), so deleting a claiming batch later (see batch-list.php) can
-- give the exact claimed amount back to each source's live_stock_claimed_qty
-- instead of guessing when a claim was split across more than one source.
CREATE TABLE live_stock_claims (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source_batch_id INT NOT NULL,
  claiming_batch_id INT NOT NULL,
  qty INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (source_batch_id) REFERENCES production_batches(id),
  FOREIGN KEY (claiming_batch_id) REFERENCES production_batches(id)
) ENGINE=InnoDB;

CREATE TABLE packing_production_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  update_datetime DATETIME NOT NULL,
  left_nozzle_qty INT NOT NULL,
  right_nozzle_qty INT NOT NULL,
  total_good_qty INT NOT NULL,
  increase_since_last INT NOT NULL,
  damage_qty_cumulative INT DEFAULT 0,
  remaining_target INT NOT NULL,
  photo VARCHAR(255) NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id) REFERENCES production_batches(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Standalone timestamped log of physical nozzle-counter resets. Purely a
-- record of "the machine counter was zeroed at this time" - it does NOT
-- feed into packing_production_updates' cumulative totals, which continue
-- to track progress toward the batch target independently.
CREATE TABLE nozzle_reset_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  nozzle ENUM('left','right','both') NOT NULL,
  reset_datetime DATETIME NOT NULL,
  photo VARCHAR(255) NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id) REFERENCES production_batches(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Machine Down Time log (Utilities section). Plant Head opens an entry
-- with a start_time and reason - end_time can be filled in right away if
-- the downtime is already over, or left NULL ("ongoing") and completed
-- later once the machine is back up. Plant Head can create entries and
-- close out an open one's end_time, but cannot edit/delete a logged entry
-- otherwise - only Admin has full edit/delete rights here (see
-- modules/utilities/downtime.php).
CREATE TABLE machine_downtime_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  start_time DATETIME NOT NULL,
  end_time DATETIME NULL,
  reason VARCHAR(255) NOT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Daily electricity meter reading (Utilities section) - one row per
-- calendar date, morning/evening readings filled in independently as the
-- day goes (either can be entered first). Plant Head logs today's own
-- readings only; Admin has full edit/delete (see
-- modules/utilities/electricity.php).
CREATE TABLE electricity_readings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reading_date DATE NOT NULL UNIQUE,
  morning_reading DECIMAL(10,2) NULL,
  evening_reading DECIMAL(10,2) NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Monthly electricity bill payment (Utilities section) - Admin-only, both
-- to view and to log: how much was paid for a given month's bill. Not
-- shown to Plant Head at all, same as Vendor Payments.
CREATE TABLE electricity_bills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bill_month DATE NOT NULL UNIQUE COMMENT 'stored as the 1st of the billed month',
  amount_paid DECIMAL(12,2) NOT NULL,
  paid_date DATE NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Diesel delivery log (Utilities section) - Plant Head notes how much
-- diesel arrived, when, and what was paid for it (price varies per
-- delivery, entered per-entry rather than a fixed rate/liter); Admin has
-- full edit/delete (see modules/utilities/diesel.php).
CREATE TABLE diesel_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  received_datetime DATETIME NOT NULL,
  quantity_liters DECIMAL(10,2) NOT NULL,
  price_paid DECIMAL(10,2) NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Water tanker delivery log (Utilities section). Same per-delivery pricing
-- as diesel above - price_paid is entered per-entry rather than computed
-- from a fixed rate -
-- the two common sizes (7000L/14000L) just prefill a starting point (see
-- modules/utilities/water-tanker.php).
CREATE TABLE water_tanker_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  received_datetime DATETIME NOT NULL,
  quantity_liters DECIMAL(10,2) NOT NULL,
  price_paid DECIMAL(10,2) NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Empty jumbo bag stock isn't logged directly - it's credited automatically
-- from raw_material_inward.jumbo_qty (bags that arrive with raw material).
-- This table only tracks what moves OUT of / back INTO that stock: bags
-- handed to a vendor for reuse, bags a vendor later brings back, and bags
-- written off as damaged. vendor_id is required for 'assigned'/'returned'
-- (per-vendor outstanding = SUM(assigned) - SUM(returned)) and NULL for
-- 'damaged'.
CREATE TABLE empty_jumbo_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_type ENUM('assigned','returned','damaged') NOT NULL,
  vendor_id INT NULL,
  bags INT NOT NULL,
  transaction_date DATE NOT NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE damage_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  nozzle ENUM('left','right','both','na') DEFAULT 'na',
  damage_qty INT NOT NULL,
  damage_date DATE NOT NULL,
  damage_time TIME NOT NULL,
  reason VARCHAR(150),
  remarks TEXT,
  action_status ENUM('pending','reprocessing','garbage') DEFAULT 'pending',
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id) REFERENCES production_batches(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE reprocessing_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  damage_id INT NOT NULL,
  reprocessing_qty INT NOT NULL,
  linked_processing_id INT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (damage_id) REFERENCES damage_entries(id),
  FOREIGN KEY (linked_processing_id) REFERENCES processing_requests(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE garbage_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  damage_id INT NOT NULL,
  garbage_qty INT NOT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (damage_id) REFERENCES damage_entries(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE stock_ledger (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_type ENUM('inward','processing_out','packing_finish','damage','reprocessing','garbage') NOT NULL,
  reference_table VARCHAR(50) NOT NULL,
  reference_id INT NOT NULL,
  material_type ENUM('raw','finished') NOT NULL,
  quantity DECIMAL(10,6) NOT NULL,
  unit VARCHAR(10) NOT NULL,
  previous_stock DECIMAL(10,6) NOT NULL,
  new_stock DECIMAL(10,6) NOT NULL,
  transaction_date DATETIME NOT NULL,
  user_id INT NOT NULL,
  remarks TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  module VARCHAR(50) NOT NULL,
  record_id INT,
  old_value JSON NULL,
  new_value JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Recurring checklist items Admin sets for Plant Head (e.g. "Check compressor
-- oil" daily, "Clean silo filters" weekly). A task is never auto-removed -
-- it keeps recurring every period until Admin deletes it from Settings.
-- 'custom' frequency repeats every custom_interval custom_unit(s) (e.g.
-- "every 10 days"), counted from created_at as the anchor - see
-- taskPeriodKey() in includes/tasks.php. due_time is optional: if set, the
-- task only counts as "pending" (needing attention) once that time of day
-- has passed - before that it's simply not due yet, not overdue.
CREATE TABLE tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  frequency ENUM('daily','weekly','monthly','custom') NOT NULL DEFAULT 'daily',
  custom_interval INT NULL,
  custom_unit ENUM('days','weeks','months','years') NULL,
  due_time TIME NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- One row per task per period it was marked done - period_key is a plain
-- 'YYYY-MM-DD' for a daily task or an ISO 'YYYY-Www' week for a weekly one,
-- so "is this task done right now" is just a lookup for the current key.
CREATE TABLE task_completions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  period_key VARCHAR(10) NOT NULL,
  completed_by INT NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_task_period (task_id, period_key),
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (completed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Labour master (Settings -> Labour), managed by Admin.
CREATE TABLE labour (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  -- Admin-set full-day wage rate for this labourer, used to compute daily
  -- labour cost from their marked attendance status (Full Day = full rate,
  -- Half Day = half, Absent = 0) - see labourWageForStatus() in
  -- includes/labour.php.
  daily_wage DECIMAL(10,2) NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------- C&F Partner onboarding ----------
-- A "C&F" (Carrying & Forwarding agent) is a district-level distribution
-- partner - Admin sends a one-time invite link (invite_token, no login
-- needed to open it), the prospective partner fills in their own details
-- and picks the district(s) they'll cover, then Admin reviews and approves
-- it - approval is what actually creates the users row (role 'C&F') and
-- links it back here via user_id. Everything before approval lives only in
-- this table; there's no user account until Admin says yes. invited_by is
-- nullable because a row can also come from the login page's own "C&F
-- Registration" button (self-service, no Admin involved yet) - not every
-- row here was actually invited by an Admin first.
CREATE TABLE cf_partners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invite_token VARCHAR(64) UNIQUE NOT NULL,
  status ENUM('invited','submitted','approved','rejected') NOT NULL DEFAULT 'invited',
  firm_name VARCHAR(150) NULL,
  contact_person VARCHAR(100) NULL,
  contact_no VARCHAR(20) NULL,
  email VARCHAR(150) NULL,
  gst_no VARCHAR(30) NULL,
  gst_doc VARCHAR(255) NULL,
  aadhaar_no VARCHAR(20) NULL,
  aadhaar_doc VARCHAR(255) NULL,
  aadhaar_doc_back VARCHAR(255) NULL,
  pan_no VARCHAR(20) NULL,
  pan_doc VARCHAR(255) NULL,
  country VARCHAR(60) NULL DEFAULT 'India',
  state VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  live_lat DECIMAL(10,7) NULL,
  live_lng DECIMAL(10,7) NULL,
  -- Applicant sets their own password at signup now (not Admin generating
  -- one at approval time) - stored hashed here, only ever copied into a
  -- real users row once Admin actually approves. terms_accepted_at is when
  -- they ticked the T&C checkbox (text of which is Admin-editable, see
  -- app_settings key 'cf_terms_conditions').
  password_hash VARCHAR(255) NULL,
  terms_accepted_at TIMESTAMP NULL,
  user_id INT NULL,
  invited_by INT NULL,
  invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  submitted_at TIMESTAMP NULL,
  reviewed_at TIMESTAMP NULL,
  reviewed_by INT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (invited_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- The district(s) a partner covers - denormalised name strings (not FK'd to
-- a districts table) since the state/district reference list is a static
-- JSON file (assets/data/india-states-districts.json), not a DB table.
-- active_dealer_count is how many active dealers/retailers the applicant
-- says they already have in that specific district - captured per-district,
-- not just once for the whole application.
CREATE TABLE cf_partner_districts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cf_partner_id INT NOT NULL,
  state_name VARCHAR(100) NOT NULL,
  district_name VARCHAR(100) NOT NULL,
  active_dealer_count INT NOT NULL DEFAULT 0,
  FOREIGN KEY (cf_partner_id) REFERENCES cf_partners(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- One row per labourer per day - Plant Head marks Full Day / Absent daily
-- (no Half Day - not a scenario that comes up at this plant). Unique on
-- (labour_id, attendance_date) so re-marking the same day updates the
-- existing row instead of creating a duplicate.
CREATE TABLE labour_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  labour_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('full_day','absent') NOT NULL,
  marked_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uniq_labour_date (labour_id, attendance_date),
  FOREIGN KEY (labour_id) REFERENCES labour(id) ON DELETE CASCADE,
  FOREIGN KEY (marked_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Per-user read/dismissed state for a notification bell entry. The
-- notification itself is just an audit_logs row (shared, one source of
-- truth for "what happened") - this table only tracks each user's own
-- read/dismiss state against it, so dismissing a notification hides it
-- from that user's bell without touching the underlying audit record.
CREATE TABLE notification_states (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  audit_log_id INT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  is_dismissed TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_audit (user_id, audit_log_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (audit_log_id) REFERENCES audit_logs(id) ON DELETE CASCADE
) ENGINE=InnoDB;
