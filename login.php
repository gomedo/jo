<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin.php' : 'dashboard.php');
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Get user from database
                $stmt = $pdo->prepare("SELECT id, email, password, first_name, last_name, role, is_active, email_verified FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if (!$user['is_active']) {
                        $error = 'Your account has been deactivated. Please contact support.';
                    } else {
                        // Update last login
                        $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $update_stmt->execute([$user['id']]);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['login_time'] = time();
                        
                        // Set remember me cookie if requested
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expires = time() + (30 * 24 * 60 * 60); // 30 days
                            setcookie('remember_token', $token, $expires, '/', '', true, true);
                            
                            // Store token in database (you might want to create a remember_tokens table)
                            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                            $stmt->execute([$token, $user['id']]);
                        }
                        
                        // Log activity
                        logActivity($user['id'], 'login', 'User logged in');
                        
                        // Redirect based on role
                        $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : (isAdmin() ? 'admin.php' : 'dashboard.php');
                        redirect($redirect_url);
                    }
                } else {
                    $error = 'Invalid email or password.';
                    
                    // Log failed login attempt
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute(['failed_login', "Failed login attempt for email: $email", $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'Login failed. Please try again.';
            }
        }
    }
}

// Check for reset password success
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = 'Password reset successful. You can now login with your new password.';
}

// Check for registration success
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Registration successful! You can now login to your account.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $site_name; ?></title>
    <meta name="description" content="Login to your NZQRI Jobs account to manage applications and find career opportunities">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="25" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="25" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            max-width: 450px;
            width: 100%;
            margin: 2rem;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: none;
        }
        
        .login-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
        }
        
        .login-header .logo {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .login-body {
            padding: 2.5rem 2rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-floating .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 0.75rem;
            height: auto;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-floating .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-floating label {
            color: #64748b;
            font-weight: 500;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            z-index: 5;
        }
        
        .btn-login {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .forgot-password:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
            color: #64748b;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
        }
        
        .register-link {
            text-align: center;
            padding: 1.5rem 0 0;
            border-top: 1px solid #e2e8f0;
        }
        
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .register-link a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert-success {
            background-color: #f0fdf4;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }
        
        .back-home {
            position: absolute;
            top: 2rem;
            left: 2rem;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-home:hover {
            color: white;
            transform: translateX(-5px);
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
            }
            
            .login-header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .back-home {
                top: 1rem;
                left: 1rem;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-home">
        <i class="fas fa-arrow-left me-2"></i>Back to Home
    </a>
    
    <div class="login-container">
        <div class="card login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-atom"></i>
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to your NZQRI Jobs account</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-floating">
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               placeholder="Enter your email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               required 
                               autocomplete="email">
                        <label for="email">
                            <i class="fas fa-envelope me-2"></i>Email Address
                        </label>
                    </div>
                    
                    <div class="form-floating position-relative">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required 
                               autocomplete="current-password">
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="far fa-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                    
                    <div class="remember-forgot">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="remember" 
                                   name="remember"
                                   value="1">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        <a href="forgot-password.php" class="forgot-password">
                            Forgot Password?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>
                
                <div class="divider">
                    <span>New to NZQRI?</span>
                </div>
                
                <div class="register-link">
                    <p class="mb-0">
                        Don't have an account? 
                        <a href="register.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
                            Create Account
                        </a>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Demo Accounts Info -->
        <div class="mt-4 text-center">
            <div class="card" style="background: rgba(255,255,255,0.95); border-radius: 12px;">
                <div class="card-body py-3">
                    <h6 class="text-dark mb-2">Demo Accounts</h6>
                    <small class="text-muted d-block">
                        <strong>Admin:</strong> admin@nzqri.co.nz / admin123
                    </small>
                    <small class="text-muted d-block">
                        <strong>User:</strong> test@example.com / test123
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<div class="loading me-2"></div>Signing In...';
        });
        
        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
        });
        
        // Demo account quick login
        function quickLogin(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            document.getElementById('loginForm').submit();
        }
        
        // Add click handlers to demo account info
        document.addEventListener('DOMContentLoaded', function() {
            const demoCard = document.querySelector('.card-body');
            if (demoCard) {
                const adminInfo = demoCard.children[1];
                const userInfo = demoCard.children[2];
                
                adminInfo.style.cursor = 'pointer';
                userInfo.style.cursor = 'pointer';
                
                adminInfo.addEventListener('click', function() {
                    quickLogin('admin@nzqri.co.nz', 'admin123');
                });
                
                userInfo.addEventListener('click', function() {
                    quickLogin('test@example.com', 'test123');
                });
            }
        });
    </script>
</body>
</html>