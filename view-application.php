<?php
require_once 'config.php';
require_admin();

$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';

if (!$application_id) {
    header('Location: admin.php');
    exit();
}

// Handle status update
if ($_POST && isset($_POST['update_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $new_status = sanitize_input($_POST['status']);
        $admin_notes = sanitize_input($_POST['admin_notes']);
        
        try {
            $stmt = $pdo->prepare("UPDATE applications SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $admin_notes, $application_id]);
            
            $success_message = 'Application status updated successfully!';
            
            // Optional: Send email notification to applicant
            // You can implement email notification here
            
        } catch (PDOException $e) {
            $error_message = 'Error updating application status. Please try again.';
        }
    }
}

// Get application details
$stmt = $pdo->prepare("
    SELECT a.*, j.title as job_title, j.company, j.location as job_location,
           u.name as applicant_name, u.email as applicant_email, u.phone as applicant_phone,
           u.location as applicant_location, u.bio, u.skills, u.experience, u.education,
           u.linkedin, u.website
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$application_id]);
$application = $stmt->fetch();

if (!$application) {
    header('Location: admin.php');
    exit();
}

// Get all applications for this job (for comparison)
$stmt = $pdo->prepare("
    SELECT a.*, u.name as applicant_name, u.email as applicant_email
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE a.job_id = ?
    ORDER BY a.applied_at DESC
");
$stmt->execute([$application['job_id']]);
$other_applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - <?php echo SITE_NAME; ?></title>
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

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            color: white;
            padding: 40px 0;
        }

        .application-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-title {
            color: var(--text-dark);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .info-value {
            color: #666;
            white-space: pre-wrap;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #ffeaa7; color: #d63031; }
        .status-reviewing { background: #74b9ff; color: white; }
        .status-shortlisted { background: #00b894; color: white; }
        .status-rejected { background: #d63031; color: white; }
        .status-hired { background: #00b894; color: white; }

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

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
        }

        .download-btn {
            background: var(--accent-color);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .download-btn:hover {
            background: #20a73b;
            color: white;
            transform: translateY(-2px);
        }

        .applicant-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 20px;
        }

        .other-applications {
            background: white;
            border-radius: 10px;
            padding: 20px;
        }

        .application-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .application-item:last-child {
            border-bottom: none;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
        }

        .alert {
            border-radius: 10px;
            border: none;
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
                        <a class="nav-link" href="admin.php">Admin Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-file-alt me-2"></i>Application Details</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="admin.php" class="text-light">Admin Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-applications.php" class="text-light">Applications</a></li>
                            <li class="breadcrumb-item active">View Application</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <!-- Application Content -->
    <div class="container py-5">
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

        <div class="row">
            <!-- Application Details -->
            <div class="col-lg-8">
                <!-- Job Information -->
                <div class="application-card">
                    <h3 class="section-title">
                        <i class="fas fa-briefcase me-2"></i>Job Information
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Job Title</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['job_title']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Company</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['company']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Location</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['job_location']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Applied Date</div>
                                <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($application['applied_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Applicant Information -->
                <div class="application-card">
                    <h3 class="section-title">
                        <i class="fas fa-user me-2"></i>Applicant Information
                    </h3>
                    
                    <div class="d-flex align-items-start mb-4">
                        <div class="applicant-photo">
                            <?php echo strtoupper(substr($application['applicant_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h4><?php echo htmlspecialchars($application['applicant_name']); ?></h4>
                            <p class="text-muted mb-1">
                                <i class="fas fa-envelope me-1"></i>
                                <a href="mailto:<?php echo htmlspecialchars($application['applicant_email']); ?>">
                                    <?php echo htmlspecialchars($application['applicant_email']); ?>
                                </a>
                            </p>
                            <?php if ($application['applicant_phone']): ?>
                                <p class="text-muted mb-1">
                                    <i class="fas fa-phone me-1"></i>
                                    <a href="tel:<?php echo htmlspecialchars($application['applicant_phone']); ?>">
                                        <?php echo htmlspecialchars($application['applicant_phone']); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            <?php if ($application['applicant_location']): ?>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($application['applicant_location']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <?php if ($application['bio']): ?>
                            <div class="col-12 mb-3">
                                <div class="info-item">
                                    <div class="info-label">Professional Bio</div>
                                    <div class="info-value"><?php echo htmlspecialchars($application['bio']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($application['skills']): ?>
                            <div class="col-12 mb-3">
                                <div class="info-item">
                                    <div class="info-label">Skills</div>
                                    <div class="info-value"><?php echo htmlspecialchars($application['skills']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($application['experience']): ?>
                            <div class="col-12 mb-3">
                                <div class="info-item">
                                    <div class="info-label">Work Experience</div>
                                    <div class="info-value"><?php echo htmlspecialchars($application['experience']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($application['education']): ?>
                            <div class="col-12 mb-3">
                                <div class="info-item">
                                    <div class="info-label">Education</div>
                                    <div class="info-value"><?php echo htmlspecialchars($application['education']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <?php if ($application['linkedin']): ?>
                                <div class="info-item">
                                    <div class="info-label">LinkedIn Profile</div>
                                    <div class="info-value">
                                        <a href="<?php echo htmlspecialchars($application['linkedin']); ?>" target="_blank" class="text-primary">
                                            <i class="fab fa-linkedin me-1"></i>View Profile
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if ($application['website']): ?>
                                <div class="info-item">
                                    <div class="info-label">Website/Portfolio</div>
                                    <div class="info-value">
                                        <a href="<?php echo htmlspecialchars($application['website']); ?>" target="_blank" class="text-primary">
                                            <i class="fas fa-globe me-1"></i>Visit Website
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Cover Letter -->
                <?php if ($application['cover_letter']): ?>
                    <div class="application-card">
                        <h3 class="section-title">
                            <i class="fas fa-file-text me-2"></i>Cover Letter
                        </h3>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Resume -->
                <?php if ($application['resume_path']): ?>
                    <div class="application-card">
                        <h3 class="section-title">
                            <i class="fas fa-file-pdf me-2"></i>Resume
                        </h3>
                        <a href="<?php echo htmlspecialchars($application['resume_path']); ?>" 
                           target="_blank" class="download-btn">
                            <i class="fas fa-download me-2"></i>Download Resume
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Status Update -->
                <div class="application-card">
                    <h3 class="section-title">
                        <i class="fas fa-tasks me-2"></i>Application Status
                    </h3>
                    
                    <div class="mb-3">
                        <div class="info-label">Current Status</div>
                        <span class="status-badge status-<?php echo $application['status']; ?>">
                            <?php echo ucfirst($application['status']); ?>
                        </span>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Update Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo $application['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="reviewing" <?php echo $application['status'] == 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                                <option value="shortlisted" <?php echo $application['status'] == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="rejected" <?php echo $application['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="hired" <?php echo $application['status'] == 'hired' ? 'selected' : ''; ?>>Hired</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Admin Notes</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4" 
                                      placeholder="Add notes about this application..."><?php echo htmlspecialchars($application['admin_notes']); ?></textarea>
                        </div>

                        <button type="submit" name="update_status" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </form>
                </div>

                <!-- Other Applications for this Job -->
                <div class="other-applications">
                    <h5><i class="fas fa-users me-2"></i>Other Applications (<?php echo count($other_applications); ?>)</h5>
                    <?php foreach ($other_applications as $other_app): ?>
                        <div class="application-item <?php echo $other_app['id'] == $application_id ? 'bg-light' : ''; ?>">
                            <div>
                                <strong><?php echo htmlspecialchars($other_app['applicant_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo time_ago($other_app['applied_at']); ?></small>
                            </div>
                            <div class="text-end">
                                <span class="status-badge status-<?php echo $other_app['status']; ?>" style="font-size: 0.7rem;">
                                    <?php echo ucfirst($other_app['status']); ?>
                                </span>
                                <?php if ($other_app['id'] != $application_id): ?>
                                    <br>
                                    <a href="view-application.php?id=<?php echo $other_app['id']; ?>" class="btn btn-sm btn-outline-primary mt-1">
                                        View
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="manage-applications.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Applications
                </a>
                <a href="job.php?id=<?php echo $application['job_id']; ?>" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-external-link-alt me-2"></i>View Job Posting
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>