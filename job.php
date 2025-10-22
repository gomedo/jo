<?php
require_once 'config.php';

$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';

if (!$job_id) {
    header('Location: index.php');
    exit();
}

// Get job details
$stmt = $pdo->prepare("
    SELECT j.*, c.name as category_name, c.color as category_color,
           COUNT(a.id) as application_count
    FROM jobs j
    LEFT JOIN categories c ON j.category_id = c.id
    LEFT JOIN applications a ON j.id = a.job_id
    WHERE j.id = ?
    GROUP BY j.id
");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: index.php');
    exit();
}

// Check if user already applied (if logged in)
$already_applied = false;
if (is_logged_in()) {
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $already_applied = $stmt->fetch() ? true : false;
}

// Track job view
if (is_logged_in()) {
    $stmt = $pdo->prepare("
        INSERT INTO job_views (job_id, user_id, ip_address, user_agent) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ");
    $stmt->execute([
        $job_id, 
        $_SESSION['user_id'], 
        $_SERVER['REMOTE_ADDR'], 
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
} else {
    $stmt = $pdo->prepare("
        INSERT INTO job_views (job_id, ip_address, user_agent) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $job_id, 
        $_SERVER['REMOTE_ADDR'], 
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// Update view count
$pdo->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?")->execute([$job_id]);

// Get similar jobs
$similar_jobs = $pdo->prepare("
    SELECT j.*, c.name as category_name, c.color as category_color
    FROM jobs j
    LEFT JOIN categories c ON j.category_id = c.id
    WHERE j.status = 'active' 
    AND j.id != ?
    AND (j.category_id = ? OR j.location LIKE ?)
    ORDER BY j.created_at DESC
    LIMIT 4
");
$similar_jobs->execute([$job_id, $job['category_id'], '%' . $job['location'] . '%']);
$related_jobs = $similar_jobs->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - <?php echo SITE_NAME; ?></title>
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

        .job-header {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            color: white;
            padding: 60px 0;
        }

        .job-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: -50px;
            position: relative;
            z-index: 2;
        }

        .job-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .job-title {
            color: var(--text-dark);
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .company-name {
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }

        .category-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            margin-bottom: 20px;
        }

        .job-type-badge {
            background: var(--accent-color);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .salary-range {
            background: #e8f4fd;
            color: var(--primary-color);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .apply-button {
            background: linear-gradient(135deg, var(--accent-color), #20a73b);
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .apply-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }

        .apply-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .section-title {
            color: var(--text-dark);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .job-description {
            line-height: 1.8;
            color: #555;
        }

        .job-description h1, .job-description h2, .job-description h3,
        .job-description h4, .job-description h5, .job-description h6 {
            color: var(--text-dark);
            margin-top: 25px;
            margin-bottom: 15px;
        }

        .job-description ul, .job-description ol {
            padding-left: 25px;
        }

        .job-description li {
            margin-bottom: 8px;
        }

        .sidebar-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 30px;
        }

        .contact-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .contact-item:last-child {
            margin-bottom: 0;
        }

        .contact-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
        }

        .similar-job {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .similar-job:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            color: inherit;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }

        .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: white;
        }

        .deadline-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }

        .stats-bar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .back-button {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }

        .back-button:hover {
            color: white;
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
                    <?php if (is_logged_in()): ?>
                        <?php if (is_admin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin.php">Admin Dashboard</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="dashboard.php">My Dashboard</a>
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
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Job Header -->
    <section class="job-header">
        <div class="container">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left me-2"></i>Back to Jobs
            </a>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Jobs</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($job['title']); ?></li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Job Details -->
    <div class="container pb-5">
        <div class="job-card p-4 mb-4">
            <div class="row">
                <div class="col-lg-8">
                    <?php if ($job['category_color']): ?>
                        <div class="category-badge" style="background-color: <?php echo $job['category_color']; ?>">
                            <?php echo htmlspecialchars($job['category_name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>
                    <div class="company-name">
                        <i class="fas fa-building me-2"></i>
                        <?php echo htmlspecialchars($job['company']); ?>
                    </div>
                    
                    <div class="job-meta">
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($job['location']); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <?php echo ucfirst($job['job_type']); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            Posted <?php echo time_ago($job['created_at']); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-eye"></i>
                            <?php echo number_format($job['views']); ?> views
                        </div>
                        <?php if ($job['remote_work']): ?>
                            <div class="meta-item">
                                <i class="fas fa-home"></i>
                                Remote Work Available
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($job['salary_min'] || $job['salary_max']): ?>
                        <div class="salary-range">
                            <i class="fas fa-dollar-sign me-2"></i>
                            <?php echo format_salary($job['salary_min'], $job['salary_max']); ?> NZD
                        </div>
                    <?php endif; ?>

                    <?php if ($job['urgent']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Urgent Hiring!</strong> This position needs to be filled quickly.
                        </div>
                    <?php endif; ?>

                    <?php if ($job['application_deadline'] && strtotime($job['application_deadline']) > time()): ?>
                        <div class="deadline-warning">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Application Deadline:</strong> <?php echo date('F j, Y', strtotime($job['application_deadline'])); ?>
                            (<?php echo ceil((strtotime($job['application_deadline']) - time()) / 86400); ?> days remaining)
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-lg-4 text-center">
                    <div class="stats-bar">
                        <div class="row">
                            <div class="col-6">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $job['application_count']; ?></div>
                                    <div class="stat-label">Applications</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo number_format($job['views']); ?></div>
                                    <div class="stat-label">Views</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (is_logged_in()): ?>
                        <?php if ($already_applied): ?>
                            <button class="apply-button" disabled>
                                <i class="fas fa-check me-2"></i>Already Applied
                            </button>
                            <p class="text-muted mt-2 small">You have already applied for this position</p>
                        <?php elseif ($job['status'] !== 'active'): ?>
                            <button class="apply-button" disabled>
                                <i class="fas fa-times me-2"></i>Applications Closed
                            </button>
                        <?php elseif ($job['application_deadline'] && strtotime($job['application_deadline']) < time()): ?>
                            <button class="apply-button" disabled>
                                <i class="fas fa-calendar-times me-2"></i>Deadline Passed
                            </button>
                        <?php else: ?>
                            <a href="apply.php?job_id=<?php echo $job['id']; ?>" class="apply-button">
                                <i class="fas fa-paper-plane me-2"></i>Apply Now
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php?redirect=<?php echo urlencode('job.php?id=' . $job['id']); ?>" class="apply-button">
                            <i class="fas fa-sign-in-alt me-2"></i>Login to Apply
                        </a>
                    <?php endif; ?>

                    <div class="mt-3">
                        <span class="job-type-badge"><?php echo ucfirst($job['job_type']); ?></span>
                        <?php if ($job['featured']): ?>
                            <span class="badge bg-warning text-dark ms-2">
                                <i class="fas fa-star me-1"></i>Featured
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Job Content -->
            <div class="col-lg-8">
                <!-- Job Description -->
                <div class="job-content p-4">
                    <h3 class="section-title">Job Description</h3>
                    <div class="job-description">
                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                    </div>
                </div>

                <!-- Requirements -->
                <?php if ($job['requirements']): ?>
                    <div class="job-content p-4">
                        <h3 class="section-title">Requirements & Qualifications</h3>
                        <div class="job-description">
                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Benefits -->
                <?php if ($job['benefits']): ?>
                    <div class="job-content p-4">
                        <h3 class="section-title">Benefits & Perks</h3>
                        <div class="job-description">
                            <?php echo nl2br(htmlspecialchars($job['benefits'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Contact Information -->
                <div class="sidebar-card">
                    <h5 class="section-title">Contact Information</h5>
                    <div class="contact-info">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <strong>Email</strong><br>
                                <a href="mailto:<?php echo htmlspecialchars($job['contact_email']); ?>">
                                    <?php echo htmlspecialchars($job['contact_email']); ?>
                                </a>
                            </div>
                        </div>
                        <?php if ($job['contact_phone']): ?>
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div>
                                    <strong>Phone</strong><br>
                                    <a href="tel:<?php echo htmlspecialchars($job['contact_phone']); ?>">
                                        <?php echo htmlspecialchars($job['contact_phone']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Similar Jobs -->
                <?php if (!empty($related_jobs)): ?>
                    <div class="sidebar-card">
                        <h5 class="section-title">Similar Jobs</h5>
                        <?php foreach ($related_jobs as $similar): ?>
                            <a href="job.php?id=<?php echo $similar['id']; ?>" class="similar-job">
                                <?php if ($similar['category_color']): ?>
                                    <div class="category-badge" style="background-color: <?php echo $similar['category_color']; ?>; font-size: 0.7rem;">
                                        <?php echo htmlspecialchars($similar['category_name']); ?>
                                    </div>
                                <?php endif; ?>
                                <h6 class="mb-1"><?php echo htmlspecialchars($similar['title']); ?></h6>
                                <p class="text-primary mb-1"><?php echo htmlspecialchars($similar['company']); ?></p>
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($similar['location']); ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Share Job -->
                <div class="sidebar-card">
                    <h5 class="section-title">Share This Job</h5>
                    <div class="d-flex gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/job.php?id=' . $job['id']); ?>" 
                           target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($job['title'] . ' at ' . $job['company']); ?>&url=<?php echo urlencode(SITE_URL . '/job.php?id=' . $job['id']); ?>" 
                           target="_blank" class="btn btn-outline-info btn-sm">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(SITE_URL . '/job.php?id=' . $job['id']); ?>" 
                           target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fab fa-linkedin"></i>
                        </a>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyJobLink()">
                            <i class="fas fa-link"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyJobLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(function() {
                alert('Job link copied to clipboard!');
            });
        }
    </script>
</body>
</html>