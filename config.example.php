<?php
/**
 * NZQRI Job Portal - Configuration File EXAMPLE
 * Copy this file to config.php and update with your actual values
 */

// ============================================
// DATABASE CONFIGURATION
// ⚠️ UPDATE THESE WITH YOUR ACTUAL VALUES
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'gotoa957_gotoausjobs');
define('DB_USER', 'gotoa957_goalsadi');
define('DB_PASS', 'medo123My@'); // ⚠️ CHANGE THIS!

// ============================================
// SITE CONFIGURATION
// ⚠️ UPDATE WITH YOUR ACTUAL DOMAIN
// ============================================
define('SITE_URL', 'https://jobs.gotoaus.com');
define('SITE_NAME', 'NZQRI Job Portal');
define('ADMIN_EMAIL', 'admin@nzqri.co.nz');

// ============================================
// SMTP/EMAIL CONFIGURATION (HostPapa)
// ⚠️ GET THESE FROM HOSTPAPA CPANEL
// ============================================
define('SMTP_ENABLED', false); // Set to true when configured
define('SMTP_HOST', 'mail.gotoaus.com'); // Your HostPapa mail server
define('SMTP_PORT', 587); // or 465 for SSL
define('SMTP_USERNAME', 'noreply@gotoaus.com'); // Your email address
define('SMTP_PASSWORD', 'YOUR_EMAIL_PASSWORD_HERE'); // ⚠️ SET THIS
define('SMTP_ENCRYPTION', 'tls'); // tls or ssl
define('SMTP_FROM_EMAIL', 'noreply@gotoaus.com');
define('SMTP_FROM_NAME', 'NZQRI Job Portal');

// ============================================
// ERROR REPORTING
// Set display_errors to 0 in production!
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 = OFF for production, 1 = ON for development
ini_set('log_errors', 1);

?>
