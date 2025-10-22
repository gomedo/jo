<?php
require_once 'config.php';

// Get statistics
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'");
    $stats['active_jobs'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stats['job_seekers'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM applications");
    $stats['applications'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $stats = ['active_jobs' => 0, 'job_seekers' => 0, 'applications' => 0];
}

// Get categories with job counts
$categories = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(j.id) as job_count 
        FROM categories c 
        LEFT JOIN jobs j ON c.id = j.category_id AND j.status = 'active' 
        WHERE c.is_active = 1 
        GROUP BY c.id 
        ORDER BY c.display_order, c.name
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Get featured jobs
$featured_jobs = [];
try {
    $stmt = $pdo->prepare("
        SELECT j.*, c.name as category_name, c.color as category_color
        FROM jobs j 
        LEFT JOIN categories c ON j.category_id = c.id 
        WHERE j.status = 'active' AND j.is_featured = 1 
        ORDER BY j.created_at DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $featured_jobs = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching featured jobs: " . $e->getMessage());
    $featured_jobs = [];
}

// Get recent jobs
$recent_jobs = [];
try {
    $stmt = $pdo->prepare("
        SELECT j.*, c.name as category_name, c.color as category_color
        FROM jobs j 
        LEFT JOIN categories c ON j.category_id = c.id 
        WHERE j.status = 'active' 
        ORDER BY j.created_at DESC 
        LIMIT 8
    ");
    $stmt->execute();
    $recent_jobs = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching recent jobs: " . $e->getMessage());
    $recent_jobs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NZQRI Job Portal - Research & Innovation Careers</title>
    <meta name="description" content="Find your next research and innovation career with NZQRI. Explore opportunities in biomedical research, data science, environmental research, and more.">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 100px 0;
        }
        
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .hero-section p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        
        .search-box {
            background: white;
            border-radius: 50px;
            padding: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .search-box input {
            border: none;
            padding: 15px 25px;
        }
        
        .search-box input:focus {
            outline: none;
            box-shadow: none;
        }
        
        .search-btn {
            background: var(--accent-color);
            border: none;
            border-radius: 40px;
            padding: 15px 30px;
            color: white;
            font-weight: 600;
        }
        
        .search-btn:hover {
            background: #c0392b;
            color: white;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 80px 0;
        }
        
        .stat-card {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .category-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border-top: 4px solid;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .category-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .job-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .job-badge {
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 20px;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: #6c757d;
        }
        
        .btn-primary-custom {
            background: var(--secondary-color);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .navbar-nav .nav-link {
            font-weight: 500;
            margin: 0 10px;
            transition: color 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--secondary-color) !important;
        }
        
        footer {
            background: var(--primary-color);
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand text-primary" href="index.php">
                <i class="fas fa-flask me-2"></i>NZQRI Jobs
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.php">Jobs</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin-dashboard.php">Admin</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white px-3 ms-2" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1>Advance Your Research Career</h1>
                    <p class="lead">Join New Zealand's premier research and innovation community. Find opportunities that match your passion for discovery and scientific excellence.</p>
                    
                    <!-- Search Box -->
                    <div class="search-box mb-4">
                        <form action="jobs.php" method="GET" class="row g-0 align-items-center">
                            <div class="col">
                                <input type="text" name="search" class="form-control form-control-lg" 
                                       placeholder="Search jobs, keywords, or companies..." 
                                       value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn search-btn">
                                    <i class="fas fa-search me-2"></i>Search Jobs
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="d-flex gap-3">
                        <a href="jobs.php" class="btn btn-outline-light btn-lg">Browse All Jobs</a>
                        <a href="register.php" class="btn btn-light btn-lg">Join Now</a>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-microscope" style="font-size: 15rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['active_jobs']) ?></div>
                        <h5>Active Jobs</h5>
                        <p class="text-muted mb-0">Research opportunities available</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['job_seekers']) ?></div>
                        <h5>Job Seekers</h5>
                        <p class="text-muted mb-0">Researchers in our community</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['applications']) ?></div>
                        <h5>Applications</h5>
                        <p class="text-muted mb-0">Career connections made</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-5">
        <div class="container">
            <div class="section-title">
                <h2>Research Categories</h2>
                <p>Explore opportunities across diverse research and innovation fields</p>
            </div>
            
            <div class="row">
                <?php foreach ($categories as $category): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="jobs.php?category=<?= $category['id'] ?>" class="text-decoration-none">
                            <div class="category-card" style="border-top-color: <?= $category['color'] ?? '#007bff' ?>;">
                                <div class="category-icon" style="color: <?= $category['color'] ?? '#007bff' ?>;">
                                    <i class="<?= $category['icon'] ?? 'fas fa-flask' ?>"></i>
                                </div>
                                <h5><?= htmlspecialchars($category['name']) ?></h5>
                                <p class="text-muted mb-2"><?= $category['job_count'] ?> jobs available</p>
                                <small class="text-muted"><?= htmlspecialchars($category['description'] ?? '') ?></small>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Jobs Section -->
    <?php if (!empty($featured_jobs)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="section-title">
                <h2>Featured Opportunities</h2>
                <p>Handpicked research positions from leading institutions</p>
            </div>
            
            <div class="row">
                <?php foreach ($featured_jobs as $job): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="job-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="job-badge bg-warning text-dark">Featured</span>
                                    <?php if ($job['is_urgent']): ?>
                                        <span class="job-badge bg-danger text-white ms-1">Urgent</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= timeAgo($job['created_at']) ?></small>
                            </div>
                            
                            <h5 class="mb-2">
                                <a href="job-detail.php?id=<?= $job['id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($job['title']) ?>
                                </a>
                            </h5>
                            
                            <p class="text-muted mb-2">
                                <i class="fas fa-building me-1"></i><?= htmlspecialchars($job['company_name']) ?>
                                <i class="fas fa-map-marker-alt ms-3 me-1"></i><?= htmlspecialchars($job['location']) ?>
                            </p>
                            
                            <?php if ($job['category_name']): ?>
                                <span class="job-badge text-white mb-2" style="background-color: <?= $job['category_color'] ?? '#007bff' ?>;">
                                    <?= htmlspecialchars($job['category_name']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <p class="mb-3"><?= substr(strip_tags($job['description']), 0, 150) ?>...</p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="job-badge bg-primary text-white"><?= $job_types[$job['job_type']] ?? $job['job_type'] ?></span>
                                <a href="job-detail.php?id=<?= $job['id'] ?>" class="btn btn-primary-custom btn-sm">
                                    View Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center">
                <a href="jobs.php?featured=1" class="btn btn-primary-custom btn-lg">View All Featured Jobs</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Recent Jobs Section -->
    <section class="py-5">
        <div class="container">
            <div class="section-title">
                <h2>Latest Opportunities</h2>
                <p>Recently posted research and innovation positions</p>
            </div>
            
            <div class="row">
                <?php foreach (array_slice($recent_jobs, 0, 4) as $job): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="job-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <?php if ($job['is_urgent']): ?>
                                        <span class="job-badge bg-danger text-white">Urgent</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= timeAgo($job['created_at']) ?></small>
                            </div>
                            
                            <h5 class="mb-2">
                                <a href="job-detail.php?id=<?= $job['id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($job['title']) ?>
                                </a>
                            </h5>
                            
                            <p class="text-muted mb-2">
                                <i class="fas fa-building me-1"></i><?= htmlspecialchars($job['company_name']) ?>
                                <i class="fas fa-map-marker-alt ms-3 me-1"></i><?= htmlspecialchars($job['location']) ?>
                            </p>
                            
                            <?php if ($job['category_name']): ?>
                                <span class="job-badge text-white mb-2" style="background-color: <?= $job['category_color'] ?? '#007bff' ?>;">
                                    <?= htmlspecialchars($job['category_name']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <p class="mb-3"><?= substr(strip_tags($job['description']), 0, 120) ?>...</p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="job-badge bg-secondary text-white"><?= $job_types[$job['job_type']] ?? $job['job_type'] ?></span>
                                <a href="job-detail.php?id=<?= $job['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    Learn More <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center">
                <a href="jobs.php" class="btn btn-primary-custom btn-lg">Browse All Jobs</a>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="mb-3">Ready to Advance Your Research Career?</h2>
            <p class="lead mb-4">Join thousands of researchers finding their perfect opportunity with NZQRI</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="register.php" class="btn btn-light btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </a>
                <a href="jobs.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-search me-2"></i>Browse Jobs
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5><i class="fas fa-flask me-2"></i>NZQRI Jobs</h5>
                    <p class="mb-3">New Zealand's premier platform for research and innovation careers. Connecting talented researchers with groundbreaking opportunities.</p>
                    <div class="footer-links">
                        <a href="mailto:info@nzqri.co.nz" class="me-3"><i class="fas fa-envelope me-1"></i>info@nzqri.co.nz</a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-3 mb-4">
                    <h6>For Job Seekers</h6>
                    <div class="footer-links">
                        <a href="jobs.php" class="d-block mb-2">Browse Jobs</a>
                        <a href="register.php" class="d-block mb-2">Create Profile</a>
                        <a href="dashboard.php" class="d-block mb-2">My Applications</a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-3 mb-4">
                    <h6>For Employers</h6>
                    <div class="footer-links">
                        <a href="login.php" class="d-block mb-2">Post a Job</a>
                        <a href="login.php" class="d-block mb-2">Find Talent</a>
                        <a href="login.php" class="d-block mb-2">Employer Dashboard</a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-3 mb-4">
                    <h6>Company</h6>
                    <div class="footer-links">
                        <a href="#" class="d-block mb-2">About NZQRI</a>
                        <a href="#" class="d-block mb-2">Contact Us</a>
                        <a href="#" class="d-block mb-2">Privacy Policy</a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-3 mb-4">
                    <h6>Support</h6>
                    <div class="footer-links">
                        <a href="#" class="d-block mb-2">Help Center</a>
                        <a href="#" class="d-block mb-2">FAQ</a>
                        <a href="#" class="d-block mb-2">Contact Support</a>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small>&copy; 2024 NZQRI Job Portal. All rights reserved.</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>Advancing Research & Innovation Careers</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>