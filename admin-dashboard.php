<?php
session_start();
require_once 'config.php';

// Check if user is admin
function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: login.php");
        exit();
    }
}

requireAdmin();

// Get dashboard statistics
try {
    // Total jobs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM jobs WHERE status = 'active'");
    $totalJobs = $stmt->fetch()['total'];

    // Total applications
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM applications");
    $totalApplications = $stmt->fetch()['total'];

    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
    $totalUsers = $stmt->fetch()['total'];

    // Total companies
    $stmt = $pdo->query("SELECT COUNT(DISTINCT company_name) as total FROM jobs");
    $totalCompanies = $stmt->fetch()['total'];

    // Recent applications
    $stmt = $pdo->prepare("SELECT a.*, j.title as job_title, u.first_name, u.last_name, u.email, j.company_name 
                          FROM applications a 
                          JOIN jobs j ON a.job_id = j.id 
                          JOIN users u ON a.user_id = u.id 
                          ORDER BY a.applied_at DESC LIMIT 5");
    $stmt->execute();
    $recentApplications = $stmt->fetchAll();

    // Recent jobs
    $stmt = $pdo->prepare("SELECT * FROM jobs ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recentJobs = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Safe session variable access
$adminName = isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'Admin';
$adminEmail = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NZQRI Jobs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
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
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 p-0">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">NZQRI Admin</h4>
                        <p class="text-white-50 mb-0"><?php echo $adminName; ?></p>
                        <small class="text-white-50"><?php echo $adminEmail; ?></small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="admin-dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="post-job.php">
                            <i class="fas fa-plus-circle me-2"></i> Post Job
                        </a>
                        <a class="nav-link" href="manage-jobs.php">
                            <i class="fas fa-briefcase me-2"></i> Manage Jobs
                        </a>
                        <a class="nav-link" href="manage-applications.php">
                            <i class="fas fa-file-alt me-2"></i> Applications
                        </a>
                        <a class="nav-link" href="manage-users.php">
                            <i class="fas fa-users me-2"></i> Manage Users
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                        <a class="nav-link" href="admin.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="index.php" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i> View Site
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-0">
                <div class="main-content p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Dashboard Overview</h2>
                        <span class="text-muted"><?php echo date('F j, Y'); ?></span>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon mx-auto mb-3" style="background: linear-gradient(45deg, #667eea, #764ba2);">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $totalJobs ?? 0; ?></h3>
                                <p class="text-muted mb-0">Active Jobs</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon mx-auto mb-3" style="background: linear-gradient(45deg, #f093fb, #f5576c);">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $totalApplications ?? 0; ?></h3>
                                <p class="text-muted mb-0">Applications</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon mx-auto mb-3" style="background: linear-gradient(45deg, #4facfe, #00f2fe);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $totalUsers ?? 0; ?></h3>
                                <p class="text-muted mb-0">Registered Users</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon mx-auto mb-3" style="background: linear-gradient(45deg, #43e97b, #38f9d7);">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $totalCompanies ?? 0; ?></h3>
                                <p class="text-muted mb-0">Companies</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="table-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-clock text-primary me-2"></i>Recent Applications
                                </h5>
                                <?php if (!empty($recentApplications)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Applicant</th>
                                                    <th>Job</th>
                                                    <th>Company</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentApplications as $app): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                                    <td>
                                                        <small><?php echo date('M j, Y', strtotime($app['applied_at'])); ?></small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No recent applications</p>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="manage-applications.php" class="btn btn-outline-primary">View All Applications</a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="table-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-briefcase text-success me-2"></i>Recent Jobs
                                </h5>
                                <?php if (!empty($recentJobs)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Job Title</th>
                                                    <th>Company</th>
                                                    <th>Location</th>
                                                    <th>Posted</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentJobs as $job): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($job['title']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($job['employment_type']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($job['location']); ?></td>
                                                    <td>
                                                        <small><?php echo date('M j, Y', strtotime($job['created_at'])); ?></small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No recent jobs</p>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="manage-jobs.php" class="btn btn-outline-success">Manage All Jobs</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="table-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-bolt text-warning me-2"></i>Quick Actions
                                </h5>
                                <div class="row">
                                    <div class="col-md-2 mb-3">
                                        <a href="post-job.php" class="btn btn-primary w-100">
                                            <i class="fas fa-plus-circle mb-2 d-block"></i>
                                            Post New Job
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="manage-applications.php" class="btn btn-info w-100">
                                            <i class="fas fa-file-alt mb-2 d-block"></i>
                                            View Applications
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="manage-users.php" class="btn btn-success w-100">
                                            <i class="fas fa-users mb-2 d-block"></i>
                                            Manage Users
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="reports.php" class="btn btn-warning w-100">
                                            <i class="fas fa-chart-bar mb-2 d-block"></i>
                                            View Reports
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="admin.php" class="btn btn-secondary w-100">
                                            <i class="fas fa-cog mb-2 d-block"></i>
                                            Settings
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="index.php" target="_blank" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-external-link-alt mb-2 d-block"></i>
                                            View Site
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>