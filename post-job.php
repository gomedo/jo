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

// Get categories for dropdown
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading categories: " . $e->getMessage();
}

// Handle form submission
if ($_POST) {
    try {
        // Validate required fields
        $required_fields = ['title', 'company_name', 'location', 'employment_type', 'salary_min', 'salary_max', 'category_id', 'description', 'requirements'];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Prepare job data
        $stmt = $pdo->prepare("INSERT INTO jobs (title, company_name, location, employment_type, salary_min, salary_max, 
                              category_id, description, requirements, benefits, company_description, contact_email, 
                              contact_phone, website, experience_level, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");

        $stmt->execute([
            $_POST['title'],
            $_POST['company_name'],
            $_POST['location'],
            $_POST['employment_type'],
            (int)$_POST['salary_min'],
            (int)$_POST['salary_max'],
            (int)$_POST['category_id'],
            $_POST['description'],
            $_POST['requirements'],
            $_POST['benefits'] ?? '',
            $_POST['company_description'] ?? '',
            $_POST['contact_email'] ?? '',
            $_POST['contact_phone'] ?? '',
            $_POST['website'] ?? '',
            $_POST['experience_level'] ?? 'Mid-Level'
        ]);

        $success = "Job posted successfully!";
        
        // Clear form data
        $_POST = array();
        
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Safe session variable access
$adminName = isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job - NZQRI Admin</title>
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
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
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
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .required {
            color: #dc3545;
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
                        <a class="nav-link active" href="post-job.php">
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
                        <h2><i class="fas fa-plus-circle text-primary me-2"></i>Post New Job</h2>
                        <a href="manage-jobs.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>View All Jobs
                        </a>
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

                    <div class="form-card">
                        <form method="POST" action="">
                            <div class="row">
                                <!-- Job Title -->
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">Job Title <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                </div>

                                <!-- Company Name -->
                                <div class="col-md-6 mb-3">
                                    <label for="company_name" class="form-label">Company Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" 
                                           value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required>
                                </div>

                                <!-- Location -->
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Location <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                           placeholder="e.g., Auckland, New Zealand" required>
                                </div>

                                <!-- Employment Type -->
                                <div class="col-md-6 mb-3">
                                    <label for="employment_type" class="form-label">Employment Type <span class="required">*</span></label>
                                    <select class="form-control" id="employment_type" name="employment_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Full-time" <?php echo (($_POST['employment_type'] ?? '') == 'Full-time') ? 'selected' : ''; ?>>Full-time</option>
                                        <option value="Part-time" <?php echo (($_POST['employment_type'] ?? '') == 'Part-time') ? 'selected' : ''; ?>>Part-time</option>
                                        <option value="Contract" <?php echo (($_POST['employment_type'] ?? '') == 'Contract') ? 'selected' : ''; ?>>Contract</option>
                                        <option value="Freelance" <?php echo (($_POST['employment_type'] ?? '') == 'Freelance') ? 'selected' : ''; ?>>Freelance</option>
                                        <option value="Internship" <?php echo (($_POST['employment_type'] ?? '') == 'Internship') ? 'selected' : ''; ?>>Internship</option>
                                    </select>
                                </div>

                                <!-- Salary Range -->
                                <div class="col-md-3 mb-3">
                                    <label for="salary_min" class="form-label">Min Salary (NZD) <span class="required">*</span></label>
                                    <input type="number" class="form-control" id="salary_min" name="salary_min" 
                                           value="<?php echo htmlspecialchars($_POST['salary_min'] ?? ''); ?>" 
                                           min="0" step="1000" required>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="salary_max" class="form-label">Max Salary (NZD) <span class="required">*</span></label>
                                    <input type="number" class="form-control" id="salary_max" name="salary_max" 
                                           value="<?php echo htmlspecialchars($_POST['salary_max'] ?? ''); ?>" 
                                           min="0" step="1000" required>
                                </div>

                                <!-- Category -->
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Category <span class="required">*</span></label>
                                    <select class="form-control" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo (($_POST['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Experience Level -->
                                <div class="col-md-6 mb-3">
                                    <label for="experience_level" class="form-label">Experience Level</label>
                                    <select class="form-control" id="experience_level" name="experience_level">
                                        <option value="Entry-Level" <?php echo (($_POST['experience_level'] ?? '') == 'Entry-Level') ? 'selected' : ''; ?>>Entry-Level</option>
                                        <option value="Mid-Level" <?php echo (($_POST['experience_level'] ?? 'Mid-Level') == 'Mid-Level') ? 'selected' : ''; ?>>Mid-Level</option>
                                        <option value="Senior-Level" <?php echo (($_POST['experience_level'] ?? '') == 'Senior-Level') ? 'selected' : ''; ?>>Senior-Level</option>
                                        <option value="Executive" <?php echo (($_POST['experience_level'] ?? '') == 'Executive') ? 'selected' : ''; ?>>Executive</option>
                                    </select>
                                </div>

                                <!-- Job Description -->
                                <div class="col-12 mb-3">
                                    <label for="description" class="form-label">Job Description <span class="required">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="6" 
                                              placeholder="Detailed job description..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <!-- Requirements -->
                                <div class="col-12 mb-3">
                                    <label for="requirements" class="form-label">Requirements <span class="required">*</span></label>
                                    <textarea class="form-control" id="requirements" name="requirements" rows="4" 
                                              placeholder="Job requirements and qualifications..." required><?php echo htmlspecialchars($_POST['requirements'] ?? ''); ?></textarea>
                                </div>

                                <!-- Benefits -->
                                <div class="col-12 mb-3">
                                    <label for="benefits" class="form-label">Benefits</label>
                                    <textarea class="form-control" id="benefits" name="benefits" rows="3" 
                                              placeholder="Employee benefits and perks..."><?php echo htmlspecialchars($_POST['benefits'] ?? ''); ?></textarea>
                                </div>

                                <!-- Company Description -->
                                <div class="col-12 mb-3">
                                    <label for="company_description" class="form-label">Company Description</label>
                                    <textarea class="form-control" id="company_description" name="company_description" rows="3" 
                                              placeholder="Brief company description..."><?php echo htmlspecialchars($_POST['company_description'] ?? ''); ?></textarea>
                                </div>

                                <!-- Contact Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="contact_email" class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                           value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="contact_phone" class="form-label">Contact Phone</label>
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone" 
                                           value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>">
                                </div>

                                <!-- Website -->
                                <div class="col-12 mb-4">
                                    <label for="website" class="form-label">Company Website</label>
                                    <input type="url" class="form-control" id="website" name="website" 
                                           value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>" 
                                           placeholder="https://company.com">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="admin-dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus-circle me-2"></i>Post Job
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>