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

$error = '';

try {
    // Basic Statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM jobs WHERE status = 'active'");
    $totalActiveJobs = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM jobs WHERE status = 'inactive'");
    $totalInactiveJobs = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM applications");
    $totalApplications = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
    $totalUsers = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'job_seeker'");
    $totalJobSeekers = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'employer'");
    $totalEmployers = $stmt->fetch()['total'];

    // Top Companies by Job Count
    $stmt = $pdo->query("SELECT company_name, COUNT(*) as job_count 
                        FROM jobs 
                        WHERE status = 'active' 
                        GROUP BY company_name 
                        ORDER BY job_count DESC 
                        LIMIT 10");
    $topCompanies = $stmt->fetchAll();

    // Applications by Status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count 
                        FROM applications 
                        GROUP BY status 
                        ORDER BY count DESC");
    $applicationsByStatus = $stmt->fetchAll();

    // Jobs by Category
    $stmt = $pdo->query("SELECT c.name as category_name, COUNT(j.id) as job_count 
                        FROM categories c 
                        LEFT JOIN jobs j ON c.id = j.category_id AND j.status = 'active'
                        GROUP BY c.id, c.name 
                        ORDER BY job_count DESC");
    $jobsByCategory = $stmt->fetchAll();

    // Most Applied Jobs
    $stmt = $pdo->query("SELECT j.title, j.company_name, j.location, COUNT(a.id) as application_count 
                        FROM jobs j 
                        LEFT JOIN applications a ON j.id = a.job_id 
                        GROUP BY j.id 
                        ORDER BY application_count DESC 
                        LIMIT 10");
    $mostAppliedJobs = $stmt->fetchAll();

    // Salary Statistics
    $stmt = $pdo->query("SELECT 
                            AVG(salary_min) as avg_min_salary,
                            AVG(salary_max) as avg_max_salary,
                            MIN(salary_min) as min_salary,
                            MAX(salary_max) as max_salary
                        FROM jobs 
                        WHERE status = 'active' AND salary_min > 0 AND salary_max > 0");
    $salaryStats = $stmt->fetch();

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Safe session variable access
$adminName = isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - NZQRI Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
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
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin: 0 auto 15px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
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
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="admin-dashboard.php">
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
                        <a class="nav-link active" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                        <a class="nav-link" href="admin.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                        <hr class="text-white-50">
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
                        <h2><i class="fas fa-chart-bar text-primary me-2"></i>Reports & Analytics</h2>
                        <span class="text-muted"><?php echo date('F j, Y'); ?></span>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Key Statistics -->
                    <div class="row">
                        <div class="col-md-2">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #667eea, #764ba2);">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <h4 class="mb-1"><?php echo $totalActiveJobs ?? 0; ?></h4>
                                <p class="text-muted mb-0">Active Jobs</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #f093fb, #f5576c);">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h4 class="mb-1"><?php echo $totalApplications ?? 0; ?></h4>
                                <p class="text-muted mb-0">Applications</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #4facfe, #00f2fe);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4 class="mb-1"><?php echo $totalUsers ?? 0; ?></h4>
                                <p class="text-muted mb-0">Total Users</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #43e97b, #38f9d7);">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <h4 class="mb-1"><?php echo $totalJobSeekers ?? 0; ?></h4>
                                <p class="text-muted mb-0">Job Seekers</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #fa709a, #fee140);">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h4 class="mb-1"><?php echo $totalEmployers ?? 0; ?></h4>
                                <p class="text-muted mb-0">Employers</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #a8edea, #fed6e3);">
                                    <i class="fas fa-pause-circle"></i>
                                </div>
                                <h4 class="mb-1"><?php echo $totalInactiveJobs ?? 0; ?></h4>
                                <p class="text-muted mb-0">Inactive Jobs</p>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row">
                        <!-- Applications by Status -->
                        <div class="col-md-6">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-pie text-primary me-2"></i>Applications by Status
                                </h5>
                                <div class="chart-container">
                                    <canvas id="applicationsChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Jobs by Category -->
                        <div class="col-md-6">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-bar text-success me-2"></i>Jobs by Category
                                </h5>
                                <div class="chart-container">
                                    <canvas id="categoriesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tables Row -->
                    <div class="row">
                        <!-- Top Companies -->
                        <div class="col-md-6">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-building text-warning me-2"></i>Top Companies by Job Count
                                </h5>
                                <?php if (!empty($topCompanies)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Company Name</th>
                                                    <th>Active Jobs</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topCompanies as $company): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $company['job_count']; ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No company data available</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Most Applied Jobs -->
                        <div class="col-md-6">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-star text-info me-2"></i>Most Applied Jobs
                                </h5>
                                <?php if (!empty($mostAppliedJobs)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Job Title</th>
                                                    <th>Company</th>
                                                    <th>Applications</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mostAppliedJobs as $job): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($job['title']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($job['location']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                                    <td><span class="badge bg-success"><?php echo $job['application_count']; ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No application data available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Salary Statistics -->
                    <?php if ($salaryStats && $salaryStats['avg_min_salary']): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-dollar-sign text-success me-2"></i>Salary Statistics (NZD)
                                </h5>
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-primary">$<?php echo number_format($salaryStats['avg_min_salary']); ?></h4>
                                        <p class="text-muted mb-0">Average Min Salary</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-success">$<?php echo number_format($salaryStats['avg_max_salary']); ?></h4>
                                        <p class="text-muted mb-0">Average Max Salary</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-warning">$<?php echo number_format($salaryStats['min_salary']); ?></h4>
                                        <p class="text-muted mb-0">Lowest Salary</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-danger">$<?php echo number_format($salaryStats['max_salary']); ?></h4>
                                        <p class="text-muted mb-0">Highest Salary</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Applications by Status Chart
        <?php if (!empty($applicationsByStatus)): ?>
        const applicationsCtx = document.getElementById('applicationsChart').getContext('2d');
        new Chart(applicationsCtx, {
            type: 'pie',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return '"' . ucfirst($item['status']) . '"'; }, $applicationsByStatus)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($applicationsByStatus, 'count')); ?>],
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545', '#17a2b8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        // Jobs by Category Chart
        <?php if (!empty($jobsByCategory)): ?>
        const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
        new Chart(categoriesCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return '"' . addslashes($item['category_name']) . '"'; }, $jobsByCategory)); ?>],
                datasets: [{
                    label: 'Active Jobs',
                    data: [<?php echo implode(',', array_column($jobsByCategory, 'job_count')); ?>],
                    backgroundColor: 'rgba(102, 126, 234, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>