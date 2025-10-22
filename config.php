<?php
/**
 * NZQRI Job Portal - Configuration File
 * Database: gotoa957_gotoausjobs
 * PHP 8.x Compatible - HostPapa Shared Hosting
 */

// ============================================
// ERROR REPORTING (Production: set to 0)
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Never show errors to users in production
ini_set('log_errors', 1);
if (!is_dir(__DIR__ . '/logs')) { @mkdir(__DIR__ . '/logs', 0775, true); }
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// Create logs directory
if (!file_exists(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'gotoa957_job_portal_db');
define('DB_USER', 'gotoa957_goalsadi');
define('DB_PASS', 'password'); // 
define('DB_CHARSET', 'utf8mb4');

// ============================================
// SITE CONFIGURATION
// ============================================
define('SITE_URL', 'https://jobs.gotoaus.com');
define('SITE_NAME', 'NZQRI Job Portal');
define('ADMIN_EMAIL', 'admin@nzqri.co.nz');

// ============================================
// UPLOAD CONFIGURATION
// ============================================
define('UPLOAD_BASE_DIR', __DIR__ . '/uploads/');
define('RESUME_DIR', UPLOAD_BASE_DIR . 'resumes/');
define('PROFILE_IMAGE_DIR', UPLOAD_BASE_DIR . 'profile_images/');
define('DOCUMENTS_DIR', UPLOAD_BASE_DIR . 'documents/');

// Web-accessible paths (relative to document root)
define('UPLOAD_BASE_URL', '/uploads/');
define('RESUME_URL', UPLOAD_BASE_URL . 'resumes/');
define('PROFILE_IMAGE_URL', UPLOAD_BASE_URL . 'profile_images/');
define('DOCUMENTS_URL', UPLOAD_BASE_URL . 'documents/');

// Create upload directories if they don't exist
$upload_dirs = [UPLOAD_BASE_DIR, RESUME_DIR, PROFILE_IMAGE_DIR, DOCUMENTS_DIR];
foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ============================================
// SECURITY CONFIGURATION
// ============================================
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRE', 7200); // 2 hours

// File Upload Limits
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_RESUME_TYPES', ['pdf', 'doc', 'docx']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);

// ============================================
// APPLICATION SETTINGS
// ============================================
define('JOBS_PER_PAGE', 10);
define('TIMEZONE', 'Pacific/Auckland');

// Job types
$job_types = [
    'full-time' => 'Full Time',
    'part-time' => 'Part Time',
    'contract' => 'Contract',
    'casual' => 'Casual',
    'intern' => 'Internship'
];

// Application statuses
$application_statuses = [
    'new' => 'New',
    'reviewed' => 'Reviewed',
    'shortlisted' => 'Shortlisted',
    'rejected' => 'Rejected',
    'hired' => 'Hired'
];

// Job statuses
$job_statuses = [
    'draft' => 'Draft',
    'published' => 'Published',
    'closed' => 'Closed'
];

// User roles
$user_roles = [
    'admin' => 'Administrator',
    'recruiter' => 'Recruiter',
    'viewer' => 'Viewer'
];

// ============================================
// SMTP/EMAIL CONFIGURATION (HostPapa)
// ============================================
define('SMTP_ENABLED', false); // Set to true when configured
define('SMTP_HOST', 'mail.gotoaus.com'); // HostPapa SMTP server
define('SMTP_PORT', 587); // or 465 for SSL
define('SMTP_USERNAME', 'noreply@gotoaus.com'); // Your email
define('SMTP_PASSWORD', ''); // ⚠️ SET YOUR SMTP PASSWORD
define('SMTP_ENCRYPTION', 'tls'); // tls or ssl
define('SMTP_FROM_EMAIL', 'noreply@gotoaus.com');
define('SMTP_FROM_NAME', 'NZQRI Job Portal');

// ============================================
// DATABASE CONNECTION (PDO)
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ]
    );
} catch(PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact the administrator.");
}

// ============================================
// SESSION MANAGEMENT
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start([
        'cookie_lifetime' => SESSION_TIMEOUT,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
    
    // Session timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRE) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF Token
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if ((time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRE) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize Input (for output, not storage)
 */
function sanitizeOutput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Clean input for storage (basic trim)
 */
function cleanInput($input) {
    return trim($input);
}

/**
 * Validate Email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Create slug from string
 */
function createSlug($string) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    return $slug;
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if user is recruiter or admin
 */
function isRecruiter() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'recruiter']);
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /admin/login.php');
        exit();
    }
}

/**
 * Require user to be admin
 */
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header('Location: /admin/login.php');
        exit();
    }
}

/**
 * Require user to be recruiter or admin
 */
function requireRecruiter() {
    if (!isLoggedIn() || !isRecruiter()) {
        header('Location: /admin/login.php');
        exit();
    }
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

// ============================================
// FORMATTING FUNCTIONS
// ============================================

/**
 * Format date for display
 */
function formatDate($date) {
    if (!$date) return 'N/A';
    try {
        return date('F j, Y', strtotime($date));
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime) {
    if (!$datetime) return 'N/A';
    try {
        return date('F j, Y g:i A', strtotime($datetime));
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Time ago helper
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minute' . (floor($time/60) != 1 ? 's' : '') . ' ago';
    if ($time < 86400) return floor($time/3600) . ' hour' . (floor($time/3600) != 1 ? 's' : '') . ' ago';
    if ($time < 2592000) return floor($time/86400) . ' day' . (floor($time/86400) != 1 ? 's' : '') . ' ago';
    if ($time < 31104000) return floor($time/2592000) . ' month' . (floor($time/2592000) != 1 ? 's' : '') . ' ago';
    
    return floor($time/31104000) . ' year' . (floor($time/31104000) != 1 ? 's' : '') . ' ago';
}

/**
 * Format salary range
 */
function formatSalary($min, $max) {
    if (!$min && !$max) return 'Negotiable';
    if (!$max) return '$' . number_format($min) . '+';
    if (!$min) return 'Up to $' . number_format($max);
    return '$' . number_format($min) . ' - $' . number_format($max);
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . $suffix;
}

// ============================================
// FILE UPLOAD FUNCTIONS
// ============================================

/**
 * Validate and sanitize filename
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $filename = preg_replace('/_{2,}/', '_', $filename);
    return $filename;
}

/**
 * Get MIME type from file
 */
function getMimeType($filepath) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    return $mime;
}

/**
 * Upload file with validation
 */
function uploadFile($file, $type = 'document') {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid file upload.');
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        throw new RuntimeException($errors[$file['error']] ?? 'Unknown upload error');
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('File size exceeds limit (5MB).');
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Determine upload directory and allowed types
    switch ($type) {
        case 'resume':
            $upload_dir = RESUME_DIR;
            $allowed_types = ALLOWED_RESUME_TYPES;
            break;
        case 'profile_image':
            $upload_dir = PROFILE_IMAGE_DIR;
            $allowed_types = ALLOWED_IMAGE_TYPES;
            break;
        case 'document':
            $upload_dir = DOCUMENTS_DIR;
            $allowed_types = ALLOWED_DOC_TYPES;
            break;
        default:
            throw new RuntimeException('Invalid upload type.');
    }
    
    if (!in_array($ext, $allowed_types)) {
        throw new RuntimeException('Invalid file type. Allowed: ' . implode(', ', $allowed_types));
    }
    
    // Generate secure filename
    $filename = uniqid('file_', true) . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }
    
    // Verify MIME type
    $mime = getMimeType($filepath);
    $allowed_mimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];
    
    if (!in_array($mime, $allowed_mimes)) {
        unlink($filepath);
        throw new RuntimeException('Invalid file MIME type.');
    }
    
    return $filename;
}

// ============================================
// DATABASE HELPER FUNCTIONS
// ============================================

/**
 * Get categories from database
 */
function getCategories($active_only = true) {
    global $pdo;
    try {
        $sql = "SELECT * FROM categories";
        if ($active_only) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY display_order, name";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Send email notification
 */
function sendEmail($to, $subject, $body, $html = false) {
    if (!SMTP_ENABLED) {
        error_log("Email would be sent to: $to - Subject: $subject");
        return true; // Return true in dev mode
    }
    
    // Use PHPMailer here in production
    // For now, just log
    error_log("Email to: $to, Subject: $subject, Body: $body");
    return true;
}

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set(TIMEZONE);

?>

if (!defined('DB_COLLATION')) define('DB_COLLATION', "utf8mb4_unicode_ci");

$site_name = $site_name ?? 'NZQRI Job Portal';

// --------------------------------------------
// Input sanitization helper (fallback)
// --------------------------------------------
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value) {
        if (is_array($value)) {
            return array_map('sanitizeInput', $value);
        }
        $value = trim($value);
        // Remove invisible control chars
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        // Encode for HTML contexts by default
        return filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
}

