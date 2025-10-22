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

$success = '';
$error = '';

// Handle application actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $applicationId = (int)$_GET['id'];
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE applications SET status = 'reviewed' WHERE id = ?");
                $stmt->execute([$applicationId]);
                $success = "Application marked as reviewed!";
                break;
                
            case 'reject':
                $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$applicationId]);
                $success = "Application rejected!";
                break;
                
            case 'pending':
                $stmt = $pdo->prepare("UPDATE applications SET status = 'pending' WHERE id = ?");
                $stmt->execute([$applicationId]);
                $success = "Application status reset to pending!";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
                $stmt->execute([$applicationId]);
                $success = "Application deleted successfully!";
                break;
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$job_filter = $_GET['job'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR j.title LIKE ? OR j.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($job_filter) {
    $where_conditions[] = "a.job_id = ?";
    $params[] = $job_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM applications a 
                   JOIN users u ON a.user_id = u.id 
                   JOIN jobs j ON a.job_id = j.id 
                   $where_clause";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_applications = $stmt->fetch()['total'];
    $total_pages = ceil($total_applications / $per_page);

    // Get applications with user and job details
    $query = "SELECT a.*, u.first_name, u.last_name, u.email, u.phone, 
              j.title as job_title, j.company_name, j.location
              FROM applications a 
              JOIN users u ON a.user_id = u.id 
              JOIN jobs j ON a.job_id = j.id 
              $where_clause 
              ORDER BY a.applied_at DESC 
              LIMIT $per_page OFFSET $offset";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();

    // Get jobs for filter dropdown
    $stmt = $pdo->query("SELECT id, title, company_name FROM jobs ORDER BY title");
    $jobs = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $applications = [];
    $jobs = [];
}

// Safe session variable access
$adminName = isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications - NZQRI Admin</title>
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
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        .application-row {
            transition: all 0.3s ease;
        }
        .application-row:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .action-btn {
            padding: 0.25rem 0.5rem;
            margin: 0 0.125rem;
            font-size: 0.75rem;
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
                        <a class="nav-link active" href="manage-applications.php">
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
                        <h2><i class="fas fa-file-alt text-primary me-2"></i>Manage Applications</h2>
                        <div>
                            <a href="reports.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-chart-bar me-2"></i>View Reports
                            </a>
                            <a href="manage-jobs.php" class="btn btn-primary">
                                <i class="fas fa-briefcase me-2"></i>Manage Jobs
                            </a>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="content-card">
                        <!-- Search and Filters -->
                        <form method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search applicants, jobs, companies...">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="reviewed" <?php echo ($status_filter == 'reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="job" class="form-label">Job</label>
                                    <select class="form-control" id="job" name="job">
                                        <option value="">All Jobs</option>
                                        <?php foreach ($jobs as $job): ?>
                                            <option value="<?php echo $job['id']; ?>" 
                                                    <?php echo ($job_filter == $job['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($job['title'] . ' - ' . $job['company_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Results Summary -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">
                                Showing <?php echo count($applications); ?> of <?php echo $total_applications; ?> applications
                            </span>
                            <?php if ($search || $status_filter || $job_filter): ?>
                                <a href="manage-applications.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Applications Table -->
                        <?php if (!empty($applications)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Job Details</th>
                                            <th>Resume</th>
                                            <th>Status</th>
                                            <th>Applied Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $app): ?>
                                        <tr class="application-row">
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($app['email']); ?>
                                                    </small>
                                                    <?php if ($app['phone']): ?>
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($app['phone']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($app['company_name']); ?></small><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($app['location']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($app['resume_path']): ?>
                                                    <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" 
                                                       class="btn btn-outline-primary btn-sm" target="_blank">
                                                        <i class="fas fa-file-pdf me-1"></i>View Resume
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">No resume</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_classes = [
                                                    'pending' => 'bg-warning',
                                                    'reviewed' => 'bg-success',
                                                    'rejected' => 'bg-danger'
                                                ];
                                                $status_class = $status_classes[$app['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?> status-badge">
                                                    <?php echo ucfirst($app['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y g:i A', strtotime($app['applied_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($app['status'] == 'pending'): ?>
                                                        <a href="?action=approve&id=<?php echo $app['id']; ?>" 
                                                           class="btn btn-outline-success action-btn" title="Mark as Reviewed">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                        <a href="?action=reject&id=<?php echo $app['id']; ?>" 
                                                           class="btn btn-outline-danger action-btn" title="Reject"
                                                           onclick="return confirm('Reject this application?')">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php elseif ($app['status'] == 'reviewed'): ?>
                                                        <a href="?action=pending&id=<?php echo $app['id']; ?>" 
                                                           class="btn btn-outline-warning action-btn" title="Reset to Pending">
                                                            <i class="fas fa-undo"></i>
                                                        </a>
                                                        <a href="?action=reject&id=<?php echo $app['id']; ?>" 
                                                           class="btn btn-outline-danger action-btn" title="Reject"
                                                           onclick="return confirm('Reject this application?')">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php elseif ($app['status'] == 'rejected'): ?>
                                                        <a href="?action=pending&id=<?php echo $app['id']; ?>" 
                                                           class="btn btn-outline-warning action-btn" title="Reset to Pending">
                                                            <i class="fas fa-undo"></i>
                                                        </a>
                                                        <a href="?action=approve&id=<?php echo $app['id']; ?>" 
                                                           class="btn btn-outline-success action-btn" title="Mark as Reviewed">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="?action=delete&id=<?php echo $app['id']; ?>" 
                                                       class="btn btn-outline-danger action-btn" title="Delete"
                                                       onclick="return confirm('Delete this application permanently?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h5>No applications found</h5>
                                <p class="text-muted">Try adjusting your search criteria or check back later for new applications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>