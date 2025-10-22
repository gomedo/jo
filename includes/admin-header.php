<?php
if (!isset($page_title)) $page_title = 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding: 20px;
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 12px 20px !important;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white !important;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="text-center mb-4">
            <h4 class="text-white">NZQRI Admin</h4>
            <p class="text-white-50 mb-0"><?php echo sanitizeOutput($_SESSION['first_name'] ?? 'Admin'); ?></p>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link" href="/admin/dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link" href="/admin/jobs/list.php">
                <i class="fas fa-briefcase me-2"></i> Manage Jobs
            </a>
            <a class="nav-link" href="/admin/jobs/create.php">
                <i class="fas fa-plus-circle me-2"></i> Post New Job
            </a>
            <a class="nav-link" href="/admin/applications/list.php">
                <i class="fas fa-file-alt me-2"></i> Applications
            </a>
            <?php if (isAdmin()): ?>
            <a class="nav-link" href="/admin/users/list.php">
                <i class="fas fa-users me-2"></i> Manage Users
            </a>
            <a class="nav-link" href="/admin/settings.php">
                <i class="fas fa-cog me-2"></i> Settings
            </a>
            <?php endif; ?>
            <hr class="text-white-50">
            <a class="nav-link" href="<?php echo SITE_URL; ?>" target="_blank">
                <i class="fas fa-external-link-alt me-2"></i> View Website
            </a>
            <a class="nav-link" href="/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>
    
    <div class="main-content">
