<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user_id'];

// Get user profile
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        redirect('logout.php');
    }
} catch (Exception $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    redirect('logout.php');
}

// Get user statistics
try {
    $stats_sql = "SELECT 
        (SELECT COUNT(*) FROM applications WHERE user_id = ?) as total_applications,
        (SELECT COUNT(*) FROM applications WHERE user_id = ? AND status = 'pending') as pending_applications,
        (SELECT COUNT(*) FROM applications WHERE user_id = ? AND status = 'shortlisted') as shortlisted_applications,
        (SELECT COUNT(*) FROM applications WHERE user_id = ? AND status = 'interview') as interview_applications,
        (SELECT COUNT(*) FROM applications WHERE user_id = ? AND status = 'hired') as hired_applications,
        (SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?) as saved_jobs";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $stats = $stats_stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching user statistics: " . $e->getMessage());
    $stats = array_fill_keys(['total_applications', 'pending_applications', 'shortlisted_applications', 'interview_applications', 'hired_applications', 'saved_jobs'], 0);
}

// Get recent applications
try {
    $applications_sql = "SELECT a.*, j.title, j.company_name, j.location, j.job_type, j.category, j.slug
                        FROM applications a 
                        JOIN jobs j ON a.job_id = j.id 
                        WHERE a.user_id = ? 
                        ORDER BY a.applied_at DESC 
                        LIMIT 10";
    
    $applications_stmt = $pdo->prepare($applications_sql);
    $applications_stmt->execute([$user_id]);
    $recent_applications = $applications_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching recent applications: " . $e->getMessage());
    $recent_applications = [];
}

// Get saved jobs
try {
    $saved_jobs_sql = "SELECT sj.saved_at, j.* 
                      FROM saved_jobs sj 
                      JOIN jobs j ON sj.job_id = j.id 
                      WHERE sj.user_id = ? AND j.status = 'active'
                      ORDER BY sj.saved_at DESC 
                      LIMIT 5";
    
    $saved_jobs_stmt = $pdo->prepare($saved_jobs_sql);
    $saved_jobs_stmt->execute([$user_id]);
    $saved_jobs = $saved_jobs_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching saved jobs: " . $e->getMessage());
    $saved_jobs = [];
}

// Get recent notifications
try {
    $notifications_sql = "SELECT * FROM notifications 
                         WHERE user_id = ? 
                         ORDER BY created_at DESC 
                         LIMIT 5";
    
    $notifications_stmt = $pdo->prepare($notifications_sql);
    $notifications_stmt->execute([$user_id]);
    $notifications = $notifications_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    $notifications = [];
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'mark_notification_read' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'remove_saved_job' && isset($_GET['job_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ?");
            $stmt->execute([$_GET['job_id'], $user_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $site_name; ?></title>
    <meta name="description" content="Manage your job applications and profile on NZQRI Jobs">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        
        .navbar {
            background: var(--gradient-primary);
            box-shadow: 0 2px 20px rgba(102, 126, 234, 0.1);
        }
        
        .navbar-brand, .nav-link {
            color: white !important;
        }
        
        .dashboard-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem 0;
        }
        
        .welcome-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin: -1rem 0 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 2;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #64748b;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .application-item {
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .application-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .application-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .application-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.75rem;
        }
        
        .status-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-shortlisted { background: #dbeafe; color: #1e40af; }
        .status-interview { background: #e0e7ff; color: #5b21b6; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-hired { background: #d1fae5; color: #065f46; }
        
        .job-item {
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .job-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .job-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .job-title a {
            text-decoration: none;
            color: inherit;
        }
        
        .job-title a:hover {
            color: var(--primary-color);
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .notification-item {
            padding: 1rem;
            border-left: 4px solid #e2e8f0;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .notification-item.unread {
            background-color: rgba(102, 126, 234, 0.05);
            border-left-color: var(--primary-color);
        }
        
        .notification-item:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }
        
        .notification-message {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: #9ca3af;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 600;
        }
        
        .btn-outline-danger {
            border: 2px solid var(--danger-color);
            color: var(--danger-color);
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 0.8rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem 0;
            }
            
            .welcome-card, .section-card {
                padding: 1.5rem;
                margin-left: 0;
                margin-right: 0;
            }
            
            .application-meta, .job-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-atom me-2"></i>NZQRI Jobs
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.php">
                            <i class="fas fa-briefcase me-1"></i>Browse Jobs
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">
                                <i class="fas fa-cog me-1"></i>Admin
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i>Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <section class="dashboard-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-tachometer-alt me-3"></i>My Dashboard</h1>
                    <p class="lead mb-0">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3>Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening'); ?>, <?php echo htmlspecialchars($user['first_name']); ?>!</h3>
                    <p class="text-muted mb-0">
                        <?php if ($stats['total_applications'] > 0): ?>
                            You have <?php echo $stats['total_applications']; ?> application<?php echo $stats['total_applications'] != 1 ? 's' : ''; ?> in progress.
                        <?php else: ?>
                            Start your career journey by browsing our latest job opportunities.
                        <?php endif; ?>
                        <?php if ($user['last_login'] && $user['last_login'] !== date('Y-m-d H:i:s')): ?>
                            Last login: <?php echo formatDateTime($user['last_login']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="jobs.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-search me-2"></i>Find Jobs
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-number text-primary"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo $stats['shortlisted_applications']; ?></div>
                    <div class="stat-label">Shortlisted</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-number text-purple"><?php echo $stats['interview_applications']; ?></div>
                    <div class="stat-label">Interviews</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $stats['hired_applications']; ?></div>
                    <div class="stat-label">Hired</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-number text-secondary"><?php echo $stats['saved_jobs']; ?></div>
                    <div class="stat-label">Saved Jobs</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Applications -->
            <div class="col-lg-8 mb-4">
                <div class="section-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="section-title mb-0">Recent Applications</h4>
                        <a href="my-applications.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i>View All
                        </a>
                    </div>
                    
                    <?php if (empty($recent_applications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h5>No Applications Yet</h5>
                            <p>You haven't applied to any jobs yet. Start exploring opportunities!</p>
                            <a href="jobs.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Browse Jobs
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_applications as $application): ?>
                            <div class="application-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="application-title">
                                            <a href="job.php?id=<?php echo $application['job_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($application['title']); ?>
                                            </a>
                                        </div>
                                        <div class="application-meta">
                                            <span><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($application['company_name']); ?></span>
                                            <span><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($application['location'] ?: 'Remote'); ?></span>
                                            <span><i class="fas fa-clock me-1"></i><?php echo $job_types[$application['job_type']] ?? $application['job_type']; ?></span>
                                            <span><i class="fas fa-calendar-alt me-1"></i>Applied <?php echo timeAgo($application['applied_at']); ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="status-badge status-<?php echo $application['status']; ?>">
                                            <?php echo $application_status[$application['status']] ?? ucfirst($application['status']); ?>
                                        </span>
                                        <?php if ($application['interview_date']): ?>
                                            <div class="mt-1">
                                                <small class="text-info">
                                                    <i class="fas fa-calendar-check me-1"></i>
                                                    Interview: <?php echo formatDateTime($application['interview_date']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Notifications -->
                <div class="section-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="section-title mb-0">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </h5>
                        <?php if (count(array_filter($notifications, fn($n) => !$n['is_read'])) > 0): ?>
                            <span class="badge bg-danger">
                                <?php echo count(array_filter($notifications, fn($n) => !$n['is_read'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-bell-slash text-muted fa-2x mb-2"></i>
                            <p class="text-muted mb-0">No notifications yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                 onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                <div class="notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Saved Jobs -->
                <div class="section-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="section-title mb-0">
                            <i class="fas fa-bookmark me-2"></i>Saved Jobs
                        </h5>
                        <a href="saved-jobs.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i>View All
                        </a>
                    </div>
                    
                    <?php if (empty($saved_jobs)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-bookmark text-muted fa-2x mb-2"></i>
                            <p class="text-muted mb-0">No saved jobs yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($saved_jobs as $job): ?>
                            <div class="job-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="job-title">
                                            <a href="job.php?id=<?php echo $job['id']; ?>">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </div>
                                        <div class="job-meta">
                                            <span><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($job['company_name']); ?></span>
                                            <span><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location'] ?: 'Remote'); ?></span>
                                        </div>
                                    </div>
                                    <button class="btn btn-outline-danger btn-sm" 
                                            onclick="removeSavedJob(<?php echo $job['id']; ?>)"
                                            title="Remove from saved jobs">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-card">
            <h4 class="section-title">
                <i class="fas fa-bolt me-2"></i>Quick Actions
            </h4>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <a href="profile.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-user-edit me-2"></i>Update Profile
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="my-applications.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-file-alt me-2"></i>View Applications
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="saved-jobs.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-bookmark me-2"></i>Saved Jobs
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="jobs.php" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Find Jobs
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mark notification as read
        function markAsRead(notificationId) {
            fetch(`dashboard.php?action=mark_notification_read&id=${notificationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notificationElement = document.querySelector(`[onclick="markAsRead(${notificationId})"]`);
                        if (notificationElement) {
                            notificationElement.classList.remove('unread');
                            
                            // Update notification badge
                            const badge = document.querySelector('.badge.bg-danger');
                            if (badge) {
                                const currentCount = parseInt(badge.textContent);
                                if (currentCount > 1) {
                                    badge.textContent = currentCount - 1;
                                } else {
                                    badge.remove();
                                }
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
        }
        
        // Remove saved job
        function removeSavedJob(jobId) {
            if (confirm('Are you sure you want to remove this job from your saved list?')) {
                fetch(`dashboard.php?action=remove_saved_job&job_id=${jobId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error removing saved job. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error removing saved job:', error);
                        alert('Error removing saved job. Please try again.');
                    });
            }
        }
        
        // Auto-refresh dashboard every 5 minutes
        setInterval(() => {
            // Only refresh if the page is visible
            if (!document.hidden) {
                location.reload();
            }
        }, 5 * 60 * 1000); // 5 minutes
        
        // Show greeting animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            const welcomeCard = document.querySelector('.welcome-card');
            if (welcomeCard) {
                welcomeCard.style.transform = 'translateY(20px)';
                welcomeCard.style.opacity = '0';
                
                setTimeout(() => {
                    welcomeCard.style.transition = 'all 0.6s ease';
                    welcomeCard.style.transform = 'translateY(0)';
                    welcomeCard.style.opacity = '1';
                }, 100);
            }
        });
    </script>
</body>
</html>