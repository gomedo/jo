<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = sanitizeInput($_POST['role']); // 'user' or 'employer'
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (!in_array($role, ['user', 'employer'])) {
            $error = 'Please select a valid account type.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'This email is already registered. <a href="login.php">Login here</a>.';
            } else {
                // Create account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, phone, role, is_active, email_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW())");
                    
                    if ($stmt->execute([$email, $hashed_password, $first_name, $last_name, $phone, $role])) {
                        $user_id = $pdo->lastInsertId();
                        
                        // Log activity
                        logActivity($user_id, 'user_registered', "New $role account created");
                        
                        // Auto login
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['user_role'] = $role;
                        $_SESSION['first_name'] = $first_name;
                        $_SESSION['last_name'] = $last_name;
                        $_SESSION['email'] = $email;
                        
                        // Update last login
                        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user_id]);
                        
                        // Redirect based on role
                        if ($role === 'employer') {
                            redirect('admin-dashboard.php');
                        } else {
                            redirect('dashboard.php');
                        }
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                } catch (Exception $e) {
                    error_log("Registration error: " . $e->getMessage());
                    $error = 'An error occurred during registration. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - NZQRI Job Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .register-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 2.5rem;
        }
        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-section h1 {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .role-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .role-option {
            flex: 1;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .role-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .role-option input[type="radio"] {
            display: none;
        }
        .role-option input[type="radio"]:checked + label {
            color: #667eea;
        }
        .role-option.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-card">
                <div class="logo-section">
                    <a href="index.php" class="text-decoration-none">
                        <h1><i class="fas fa-flask"></i> NZQRI Jobs</h1>
                    </a>
                    <p class="text-muted">Create your account to get started</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <!-- Account Type Selection -->
                    <label class="form-label fw-bold">I want to:</label>
                    <div class="role-selector">
                        <div class="role-option" onclick="selectRole('user', this)">
                            <input type="radio" name="role" id="role_user" value="user" checked>
                            <label for="role_user">
                                <div class="role-icon"><i class="fas fa-user-graduate"></i></div>
                                <strong>Find a Job</strong>
                                <p class="small text-muted mb-0">Job Seeker / Researcher</p>
                            </label>
                        </div>
                        <div class="role-option" onclick="selectRole('employer', this)">
                            <input type="radio" name="role" id="role_employer" value="employer">
                            <label for="role_employer">
                                <div class="role-icon"><i class="fas fa-building"></i></div>
                                <strong>Post Jobs</strong>
                                <p class="small text-muted mb-0">Employer / Recruiter</p>
                            </label>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required 
                                   value="<?= $_POST['first_name'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required 
                                   value="<?= $_POST['last_name'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control" required 
                               value="<?= $_POST['email'] ?? '' ?>"
                               placeholder="you@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?= $_POST['phone'] ?? '' ?>"
                               placeholder="+64 123 456 789">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required 
                               minlength="<?= PASSWORD_MIN_LENGTH ?>"
                               placeholder="Minimum <?= PASSWORD_MIN_LENGTH ?> characters">
                        <small class="text-muted">Use a strong password with letters, numbers, and symbols</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required 
                               placeholder="Re-enter your password">
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-register btn-primary w-100 mb-3">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </form>

                <div class="text-center">
                    <p class="text-muted mb-0">Already have an account? <a href="login.php" class="fw-bold">Login here</a></p>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="index.php" class="text-white text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>Back to Homepage
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectRole(role, element) {
            // Remove selected class from all options
            document.querySelectorAll('.role-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Check the radio button
            document.getElementById('role_' + role).checked = true;
        }
        
        // Set initial selection
        document.addEventListener('DOMContentLoaded', function() {
            const checkedRole = document.querySelector('input[name="role"]:checked');
            if (checkedRole) {
                const roleValue = checkedRole.value;
                const roleOption = document.querySelector('.role-option input[value="' + roleValue + '"]').closest('.role-option');
                roleOption.classList.add('selected');
            }
        });

        // Password validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirm = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>