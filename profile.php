<?php
require_once 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_POST) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $location = sanitize_input($_POST['location']);
        $bio = sanitize_input($_POST['bio']);
        $skills = sanitize_input($_POST['skills']);
        $experience = sanitize_input($_POST['experience']);
        $education = sanitize_input($_POST['education']);
        $linkedin = sanitize_input($_POST['linkedin']);
        $website = sanitize_input($_POST['website']);
        
        // Handle password update
        $update_password = false;
        $password_hash = '';
        
        if (!empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 6) {
                $error_message = 'Password must be at least 6 characters long.';
            } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
                $error_message = 'Passwords do not match.';
            } else {
                $update_password = true;
                $password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            }
        }
        
        if (!$error_message) {
            try {
                // Check if email is already taken by another user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error_message = 'This email is already registered to another account.';
                } else {
                    // Update user profile
                    $sql = "UPDATE users SET 
                            name = ?, email = ?, phone = ?, location = ?, 
                            bio = ?, skills = ?, experience = ?, education = ?, 
                            linkedin = ?, website = ?, updated_at = NOW()";
                    
                    $params = [$name, $email, $phone, $location, $bio, $skills, $experience, $education, $linkedin, $website];
                    
                    if ($update_password) {
                        $sql .= ", password = ?";
                        $params[] = $password_hash;
                    }
                    
                    $sql .= " WHERE id = ?";
                    $params[] = $user_id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    // Update session email if changed
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_name'] = $name;
                    
                    $success_message = 'Profile updated successfully!';
                }
            } catch (PDOException $e) {
                $error_message = 'Error updating profile. Please try again.';
            }
        }
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit();
}

// Get user's applications count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ?");
$stmt->execute([$user_id]);
$applications_count = $stmt->fetchColumn();

// Get user's recent applications
$stmt = $pdo->prepare("
    SELECT a.*, j.title as job_title, j.company 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE a.user_id = ? 
    ORDER BY a.applied_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
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

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            color: white;
            padding: 40px 0;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-top: -50px;
            position: relative;
            z-index: 2;
        }

        .profile-form {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }

        .form-section {
            margin-bottom: 40px;
        }

        .section-title {
            color: var(--text-dark);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .form-control, .form-select, .form-control:focus {
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
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1e3d6f, var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.4);
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .application-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending { background: #ffeaa7; color: #d63031; }
        .status-reviewing { background: #74b9ff; color: white; }
        .status-shortlisted { background: #00b894; color: white; }
        .status-rejected { background: #d63031; color: white; }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            margin: 0 auto 20px;
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Header -->
    <section class="profile-header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1><i class="fas fa-user-edit me-2"></i>My Profile</h1>
                    <p class="lead">Manage your personal information and preferences</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Profile Content -->
    <div class="container py-5">
        <div class="row">
            <!-- Profile Summary -->
            <div class="col-lg-4 mb-4">
                <div class="profile-card p-4">
                    <div class="text-center">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                        <?php if ($user['location']): ?>
                            <p class="text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($user['location']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-number"><?php echo $applications_count; ?></div>
                    <div class="text-muted">Total Applications</div>
                </div>

                <!-- Recent Applications -->
                <?php if (!empty($recent_applications)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Recent Applications</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($recent_applications as $app): ?>
                                <div class="application-item border-0 shadow-none mb-2">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($app['job_title']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['company']); ?></small>
                                    <div class="mt-2">
                                        <span class="status-badge status-<?php echo $app['status']; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Profile Form -->
            <div class="col-lg-8">
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

                <form method="POST" class="profile-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user me-2"></i>Personal Information
                        </h3>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="e.g. Auckland, New Zealand"
                                       value="<?php echo htmlspecialchars($user['location']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="bio" class="form-label">Professional Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4" 
                                      placeholder="Tell employers about yourself, your experience, and career goals..."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-briefcase me-2"></i>Professional Information
                        </h3>

                        <div class="mb-3">
                            <label for="skills" class="form-label">Skills</label>
                            <textarea class="form-control" id="skills" name="skills" rows="3" 
                                      placeholder="e.g. Python, Data Analysis, Project Management, Research..."><?php echo htmlspecialchars($user['skills']); ?></textarea>
                            <div class="form-text">Separate skills with commas</div>
                        </div>

                        <div class="mb-3">
                            <label for="experience" class="form-label">Work Experience</label>
                            <textarea class="form-control" id="experience" name="experience" rows="4" 
                                      placeholder="Describe your work experience, roles, and achievements..."><?php echo htmlspecialchars($user['experience']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="education" class="form-label">Education</label>
                            <textarea class="form-control" id="education" name="education" rows="3" 
                                      placeholder="Your educational background, degrees, certifications..."><?php echo htmlspecialchars($user['education']); ?></textarea>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-link me-2"></i>Professional Links
                        </h3>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="linkedin" class="form-label">LinkedIn Profile</label>
                                <input type="url" class="form-control" id="linkedin" name="linkedin" 
                                       placeholder="https://linkedin.com/in/your-profile"
                                       value="<?php echo htmlspecialchars($user['linkedin']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="website" class="form-label">Personal Website/Portfolio</label>
                                <input type="url" class="form-control" id="website" name="website" 
                                       placeholder="https://your-website.com"
                                       value="<?php echo htmlspecialchars($user['website']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Password Change -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-lock me-2"></i>Change Password
                        </h3>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       placeholder="Leave blank to keep current password">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm your new password">
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            
            if (password && confirm && password !== confirm) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>