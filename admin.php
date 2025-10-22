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

// Handle settings updates
if ($_POST) {
    try {
        if (isset($_POST['update_profile'])) {
            // Update admin profile
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'] ?? '',
                $_SESSION['user_id']
            ]);
            
            // Update session
            $_SESSION['first_name'] = $_POST['first_name'];
            $_SESSION['last_name'] = $_POST['last_name'];
            $_SESSION['email'] = $_POST['email'];
            
            $success = "Profile updated successfully!";
        }
        
        if (isset($_POST['change_password'])) {
            // Change password
            if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
                throw new Exception("All password fields are required.");
            }
            
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("New passwords do not match.");
            }
            
            if (strlen($_POST['new_password']) < 6) {
                throw new Exception("New password must be at least 6 characters long.");
            }
            
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($_POST['current_password'], $user['password'])) {
                throw new Exception("Current password is incorrect.");
            }
            
            // Update password
            $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
            
            $success = "Password changed successfully!";
        }
        
        if (isset($_POST['add_category'])) {
            // Add new category
            if (empty($_POST['category_name'])) {
                throw new Exception("Category name is required.");
            }
            
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, color) VALUES (?, ?, ?)");
            $stmt->execute([
                $_POST['category_name'],
                $_POST['category_description'] ?? '',
                $_POST['category_color'] ?? '#007bff'
            ]);
            
            $success = "Category added successfully!";
        }
        
        if (isset($_POST['delete_category'])) {
            // Delete category (if no jobs are using it)
            $categoryId = (int)$_POST['category_id'];
            
            // Check if category is in use
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM jobs WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            $jobCount = $stmt->fetch()['count'];
            
            if ($jobCount > 0) {
                throw new Exception("Cannot delete category - it is being used by $jobCount job(s).");
            }
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            
            $success = "Category deleted successfully!";
        }
        
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current admin info
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    // Get categories
    $stmt = $pdo->query("SELECT c.*, COUNT(j.id) as job_count 
                        FROM categories c 
                        LEFT JOIN jobs j ON c.id = j.category_id 
                        GROUP BY c.id 
                        ORDER BY c.name");
    $categories = $stmt->fetchAll();
    
    // Get system stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM jobs");
    $totalJobs = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM applications");
    $totalApplications = $stmt->fetch()['total'];
    
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
    <title>Admin Settings - NZQRI</title>
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
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
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
        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .category-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
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
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                        <a class="nav-link active" href="admin.php">
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
                        <h2><i class="fas fa-cog text-primary me-2"></i>Admin Settings</h2>
                        <span class="text-muted"><?php echo date('F j, Y'); ?></span>
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

                    <!-- System Overview -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat-item">
                                <h3><?php echo $totalJobs ?? 0; ?></h3>
                                <p class="mb-0">Total Jobs</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-item">
                                <h3><?php echo $totalUsers ?? 0; ?></h3>
                                <p class="mb-0">Total Users</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-item">
                                <h3><?php echo $totalApplications ?? 0; ?></h3>
                                <p class="mb-0">Total Applications</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Profile Settings -->
                        <div class="col-md-6">
                            <div class="settings-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-user text-primary me-2"></i>Profile Settings
                                </h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="col-md-6">
                            <div class="settings-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-lock text-warning me-2"></i>Change Password
                                </h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               minlength="6" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               minlength="6" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Category Management -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="settings-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-tags text-success me-2"></i>Add New Category
                                </h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="category_name" class="form-label">Category Name</label>
                                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="category_description" class="form-label">Description</label>
                                        <textarea class="form-control" id="category_description" name="category_description" rows="2"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="category_color" class="form-label">Color</label>
                                        <input type="color" class="form-control form-control-color" id="category_color" name="category_color" value="#007bff">
                                    </div>
                                    <button type="submit" name="add_category" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Add Category
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="settings-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-list text-info me-2"></i>Existing Categories
                                </h5>
                                <?php if (!empty($categories)): ?>
                                    <div style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach ($categories as $category): ?>
                                        <div class="category-item">
                                            <div class="d-flex align-items-center">
                                                <div class="category-color" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo $category['job_count']; ?> jobs</small>
                                                </div>
                                            </div>
                                            <?php if ($category['job_count'] == 0): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" name="delete_category" class="btn btn-outline-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">In use</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No categories found</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="settings-card">
                        <h5 class="mb-3">
                            <i class="fas fa-info-circle text-secondary me-2"></i>System Information
                        </h5>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>System Version:</strong><br>
                                <span class="text-muted">NZQRI Jobs v1.0</span>
                            </div>
                            <div class="col-md-3">
                                <strong>PHP Version:</strong><br>
                                <span class="text-muted"><?php echo PHP_VERSION; ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Server Time:</strong><br>
                                <span class="text-muted"><?php echo date('Y-m-d H:i:s'); ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Database:</strong><br>
                                <span class="text-muted">MySQL Connected</span>
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