<?php
// ============================================================
// APP-WIDE CONSTANTS - edit these for your environment
// ============================================================

// All dates/times in the app (form defaults, timestamps, history) are IST.
date_default_timezone_set('Asia/Kolkata');

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'cement_erp');
define('DB_USER', 'root');
define('DB_PASS', '');

// If the project folder sits at htdocs/cement-erp, APP_URL stays as below.
// If you rename the folder, change this to match (no trailing slash).
define('APP_URL', '/cement-erp');

define('APP_NAME', 'Cement Plant ERP');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
