<?php
require_once 'config.php';
requireAdmin();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    switch ($action) {
        case 'activate':
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success_message = "User activated successfully.";
            } else {
                $error_message = "Failed to activate user.";
            }
            break;
            
        case 'deactivate':
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success_message = "User deactivated successfully.";
            } else {
                $error_message = "Failed to deactivate user.";
            }
            break;
            
        case 'change_role':
            $new_role = $_POST['new_role'] ?? '';
            if (in_array($new_role, ['user', 'employer', 'admin'])) {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                if ($stmt->execute([$new_role, $user_id])) {
                    $success_message = "User role updated successfully.";
                } else {
                    $error_message = "Failed to update user role.";
                }
            }
            break;
            
        case 'reset_password':
            $new_password = bin2hex(random_bytes(4)); // Generate 8-character password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user_id])) {
                $success_message = "Password reset successfully. New password: <strong>$new_password</strong>";
            } else {
                $error_message = "Failed to reset password.";
            }
            break;
            
        case 'delete':
            try {
                $pdo->beginTransaction();
                
                // Delete user applications first
                $stmt = $pdo->prepare("DELETE FROM applications WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete user jobs if employer
                $stmt = $pdo->prepare("DELETE FROM jobs WHERE posted_by = ?");
                $stmt->execute([$user_id]);
                
                // Delete saved jobs
                $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete notifications
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $pdo->commit();
                $success_message = "User deleted successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Failed to delete user: " . $e->getMessage();
            }
            break;
    }
}

// Get filter parameters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if ($role_filter) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    if ($status_filter === 'active') {
        $conditions[] = "is_active = 1";
    } else {
        $conditions[] = "is_active = 0";
    }
}

if ($search) {
    $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) FROM users $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Get users
$query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as application_count,
                 (SELECT COUNT(*) FROM jobs WHERE posted_by = u.id) as job_count
          FROM users u 
          $where_clause 
          ORDER BY u.created_at DESC 
          LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as job_seekers,
    SUM(CASE WHEN role = 'employer' THEN 1 ELSE 0 END) as employers,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
FROM users";
$stats = $pdo->query($stats_query)->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - NZQRI Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .user-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }
        
        .user-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .role-user { background-color: #007bff; }
        .role-employer { background-color: #28a745; }
        .role-admin { background-color: #dc3545; }
        
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        
        .filters-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="admin-dashboard.php">
                <i class="fas fa-briefcase me-2"></i>NZQRI Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin-dashboard.php">Dashboard</a>
                <a class="nav-link" href="manage-jobs.php">Jobs</a>
                <a class="nav-link" href="manage-applications.php">Applications</a>
                <a class="nav-link active" href="manage-users.php">Users</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users me-2"></i>Manage Users</h2>
            <div class="d-flex gap-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-1"></i>Add User
                </button>
                <button class="btn btn-outline-primary" onclick="exportUsers()">
                    <i class="fas fa-download me-1"></i>Export
                </button>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><?= $stats['total_users'] ?></h5>
                        <p class="card-text small">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info"><?= $stats['job_seekers'] ?></h5>
                        <p class="card-text small">Job Seekers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?= $stats['employers'] ?></h5>
                        <p class="card-text small">Employers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><?= $stats['admins'] ?></h5>
                        <p class="card-text small">Admins</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?= $stats['active_users'] ?></h5>
                        <p class="card-text small">Active</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted"><?= $stats['inactive_users'] ?></h5>
                        <p class="card-text small">Inactive</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>Job Seeker</option>
                        <option value="employer" <?= $role_filter === 'employer' ? 'selected' : '' ?>>Employer</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Users List -->
        <div class="row">
            <?php if (empty($users)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No users found</h5>
                        <p class="text-muted">Try adjusting your search criteria.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <div class="col-12">
                        <div class="user-card p-3">
                            <div class="row align-items-center">
                                <div class="col-md-1">
                                    <div class="user-avatar">
                                        <?php if ($user['profile_image']): ?>
                                            <img src="<?= PROFILE_IMAGE_DIR . $user['profile_image'] ?>" alt="Profile" class="user-avatar">
                                        <?php else: ?>
                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($user['email']) ?>
                                    </small><br>
                                    <?php if ($user['phone']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-phone me-1"></i><?= htmlspecialchars($user['phone']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2">
                                    <span class="badge role-badge role-<?= $user['role'] ?> text-white">
                                        <?= ucfirst($user['role'] === 'user' ? 'Job Seeker' : $user['role']) ?>
                                    </span>
                                </div>
                                <div class="col-md-2">
                                    <i class="fas fa-circle status-<?= $user['is_active'] ? 'active' : 'inactive' ?> me-1"></i>
                                    <span class="status-<?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                    <br><small class="text-muted">Since <?= formatDate($user['created_at']) ?></small>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">
                                        <?= $user['application_count'] ?> applications<br>
                                        <?= $user['job_count'] ?> jobs posted
                                    </small>
                                </div>
                                <div class="col-md-2">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i> Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><h6 class="dropdown-header">User Status</h6></li>
                                            <?php if ($user['is_active']): ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-warning" onclick="return confirm('Deactivate this user?')">
                                                            <i class="fas fa-pause me-1"></i>Deactivate
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-success">
                                                            <i class="fas fa-play me-1"></i>Activate
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            <li><h6 class="dropdown-header">Role Management</h6></li>
                                            <?php foreach (['user', 'employer', 'admin'] as $role): ?>
                                                <?php if ($role !== $user['role']): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="change_role">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <input type="hidden" name="new_role" value="<?= $role ?>">
                                                            <button type="submit" class="dropdown-item" onclick="return confirm('Change role to <?= ucfirst($role === 'user' ? 'Job Seeker' : $role) ?>?')">
                                                                <i class="fas fa-user-tag me-1"></i>Make <?= ucfirst($role === 'user' ? 'Job Seeker' : $role) ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            <li><h6 class="dropdown-header">Security</h6></li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-info" onclick="return confirm('Reset password for this user?')">
                                                        <i class="fas fa-key me-1"></i>Reset Password
                                                    </button>
                                                </form>
                                            </li>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                        <i class="fas fa-trash me-1"></i>Delete User
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportUsers() {
            window.location.href = 'export-users.php?' + new URLSearchParams(window.location.search);
        }
    </script>
</body>
</html>