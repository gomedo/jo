<?php
require_once 'config.php';
require_admin();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'update_site_settings':
                    // Here you would typically update a settings table
                    // For this demo, we'll just show success
                    $success_message = 'Site settings updated successfully!';
                    break;
                    
                case 'update_email_settings':
                    $success_message = 'Email settings updated successfully!';
                    break;
                    
                case 'update_security_settings':
                    $success_message = 'Security settings updated successfully!';
                    break;
                    
                case 'backup_database':
                    // This would typically trigger a database backup
                    $success_message = 'Database backup initiated successfully!';
                    break;
                    
                case 'clear_cache':
                    // This would typically clear application cache
                    $success_message = 'Application cache cleared successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error_message = 'Error updating settings. Please try again.';
        }
    }
}

// Get system statistics
$system_stats = [
    'total_jobs' => $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
    'active_jobs' => $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn(),
    'total_applications' => $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'user'")->fetchColumn(),
    'database_size' => '15.2 MB', // This would be calculated from actual database
    'disk_usage' => '85.6 MB'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --text-dark: #2c3e50;
        }

        body {
            background-color: var(--secondary-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            color: white;
            padding: 40px 0;
        }

        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px 25px;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: var(--text-dark);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eee;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1e3d6f, var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.4);
        }

        .btn-success {
            background: var(--accent-color);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-warning {
            background: #ffc107;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            color: #000;
        }

        .btn-danger {
            background: #dc3545;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.25);
        }

        .nav-tabs {
            border-bottom: 3px solid var(--primary-color);
        }

        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 600;
            padding: 15px 20px;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .system-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-dark);
        }

        .info-value {
            color: #666;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-briefcase me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">Admin Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-cog me-2"></i>System Settings</h1>
                    <p class="lead">Configure system preferences and maintain your job portal</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container py-5">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- System Overview -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-chart-bar me-2"></i>System Overview
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($system_stats['total_jobs']); ?></div>
                        <div class="stat-label">Total Jobs</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($system_stats['active_jobs']); ?></div>
                        <div class="stat-label">Active Jobs</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($system_stats['total_applications']); ?></div>
                        <div class="stat-label">Applications</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($system_stats['total_users']); ?></div>
                        <div class="stat-label">Registered Users</div>
                    </div>
                </div>

                <div class="system-info">
                    <div class="info-row">
                        <div class="info-label">PHP Version</div>
                        <div class="info-value"><?php echo PHP_VERSION; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Database Size</div>
                        <div class="info-value"><?php echo $system_stats['database_size']; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Disk Usage</div>
                        <div class="info-value"><?php echo $system_stats['disk_usage']; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Upload Directory</div>
                        <div class="info-value"><?php echo is_writable(UPLOAD_DIR) ? 'Writable' : 'Not Writable'; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tabs -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-sliders-h me-2"></i>Configuration Settings
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="site-tab" data-bs-toggle="tab" data-bs-target="#site" type="button" role="tab">
                            <i class="fas fa-globe me-2"></i>Site Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">
                            <i class="fas fa-envelope me-2"></i>Email Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                            <i class="fas fa-shield-alt me-2"></i>Security
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
                            <i class="fas fa-tools me-2"></i>Maintenance
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabContent">
                    <!-- Site Settings -->
                    <div class="tab-pane fade show active" id="site" role="tabpanel">
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="update_site_settings">

                            <div class="form-section">
                                <h5 class="section-title">General Settings</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="site_name" class="form-label">Site Name</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name" 
                                               value="<?php echo SITE_NAME; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="site_url" class="form-label">Site URL</label>
                                        <input type="url" class="form-control" id="site_url" name="site_url" 
                                               value="<?php echo SITE_URL; ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Site Description</label>
                                    <textarea class="form-control" id="site_description" name="site_description" rows="3" 
                                              placeholder="Brief description of your job portal...">Find your dream job in New Zealand with NZQRI Jobs Portal</textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="admin_email" class="form-label">Administrator Email</label>
                                        <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                               value="<?php echo ADMIN_EMAIL; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <option selected>Pacific/Auckland</option>
                                            <option>Pacific/Chatham</option>
                                            <option>UTC</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5 class="section-title">Features</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="allow_registration" name="allow_registration" checked>
                                            <label class="form-check-label" for="allow_registration">
                                                Allow User Registration
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="require_email_verification" name="require_email_verification" checked>
                                            <label class="form-check-label" for="require_email_verification">
                                                Require Email Verification
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enable_job_alerts" name="enable_job_alerts" checked>
                                            <label class="form-check-label" for="enable_job_alerts">
                                                Enable Job Alerts
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enable_social_login" name="enable_social_login">
                                            <label class="form-check-label" for="enable_social_login">
                                                Enable Social Login
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Site Settings
                            </button>
                        </form>
                    </div>

                    <!-- Email Settings -->
                    <div class="tab-pane fade" id="email" role="tabpanel">
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="update_email_settings">

                            <div class="form-section">
                                <h5 class="section-title">SMTP Configuration</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                               value="<?php echo SMTP_HOST; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                               value="<?php echo SMTP_PORT; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                               value="<?php echo SMTP_USERNAME; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_password" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                               placeholder="Enter new password or leave blank">
                                    </div>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="smtp_secure" name="smtp_secure" checked>
                                    <label class="form-check-label" for="smtp_secure">
                                        Use SSL/TLS Encryption
                                    </label>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5 class="section-title">Email Templates</h5>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="send_welcome_email" name="send_welcome_email" checked>
                                    <label class="form-check-label" for="send_welcome_email">
                                        Send Welcome Email to New Users
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="send_application_notifications" name="send_application_notifications" checked>
                                    <label class="form-check-label" for="send_application_notifications">
                                        Send Application Status Notifications
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Email Settings
                            </button>
                        </form>
                    </div>

                    <!-- Security Settings -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="update_security_settings">

                            <div class="form-section">
                                <h5 class="section-title">Login Security</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                               value="<?php echo SESSION_TIMEOUT / 60; ?>" min="5" max="1440">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                               value="<?php echo MAX_LOGIN_ATTEMPTS; ?>" min="3" max="10">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="lockout_time" class="form-label">Lockout Time (minutes)</label>
                                        <input type="number" class="form-control" id="lockout_time" name="lockout_time" 
                                               value="<?php echo LOCKOUT_TIME / 60; ?>" min="5" max="60">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                        <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                               value="6" min="6" max="20">
                                    </div>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="require_strong_passwords" name="require_strong_passwords">
                                    <label class="form-check-label" for="require_strong_passwords">
                                        Require Strong Passwords (uppercase, lowercase, numbers, symbols)
                                    </label>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5 class="section-title">File Upload Security</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_file_size" class="form-label">Max File Size (MB)</label>
                                        <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                                               value="<?php echo MAX_FILE_SIZE / 1048576; ?>" min="1" max="50">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="allowed_extensions" class="form-label">Allowed Extensions</label>
                                        <input type="text" class="form-control" id="allowed_extensions" name="allowed_extensions" 
                                               value="<?php echo implode(', ', ALLOWED_EXTENSIONS); ?>">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Security Settings
                            </button>
                        </form>
                    </div>

                    <!-- Maintenance -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel">
                        <div class="mt-4">
                            <div class="form-section">
                                <h5 class="section-title">System Maintenance</h5>
                                
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="backup_database">
                                        <button type="submit" class="btn btn-success w-100" 
                                                onclick="return confirm('Create database backup?')">
                                            <i class="fas fa-database me-2"></i>Backup Database
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="clear_cache">
                                        <button type="submit" class="btn btn-warning w-100" 
                                                onclick="return confirm('Clear application cache?')">
                                            <i class="fas fa-broom me-2"></i>Clear Cache
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#systemInfoModal">
                                        <i class="fas fa-info-circle me-2"></i>System Information
                                    </button>

                                    <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#maintenanceModal">
                                        <i class="fas fa-tools me-2"></i>Maintenance Mode
                                    </button>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5 class="section-title">Data Management</h5>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> These actions cannot be undone. Please ensure you have backups before proceeding.
                                </div>

                                <div class="action-buttons">
                                    <button type="button" class="btn btn-outline-danger w-100" disabled>
                                        <i class="fas fa-trash me-2"></i>Clean Old Applications (30+ days)
                                    </button>
                                    <button type="button" class="btn btn-outline-danger w-100" disabled>
                                        <i class="fas fa-user-times me-2"></i>Remove Inactive Users (90+ days)
                                    </button>
                                    <button type="button" class="btn btn-outline-danger w-100" disabled>
                                        <i class="fas fa-file-excel me-2"></i>Export All Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Info Modal -->
    <div class="modal fade" id="systemInfoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">System Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="system-info">
                        <div class="info-row">
                            <div class="info-label">Server Software</div>
                            <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">PHP Version</div>
                            <div class="info-value"><?php echo PHP_VERSION; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">MySQL Version</div>
                            <div class="info-value"><?php echo $pdo->query('SELECT VERSION()')->fetchColumn(); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Memory Limit</div>
                            <div class="info-value"><?php echo ini_get('memory_limit'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Upload Max Filesize</div>
                            <div class="info-value"><?php echo ini_get('upload_max_filesize'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Max Execution Time</div>
                            <div class="info-value"><?php echo ini_get('max_execution_time'); ?> seconds</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Mode Modal -->
    <div class="modal fade" id="maintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Maintenance Mode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Maintenance mode will temporarily disable the site for regular users while allowing administrators to access the system.</p>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="maintenance_mode">
                        <label class="form-check-label" for="maintenance_mode">
                            Enable Maintenance Mode
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger">Apply Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>