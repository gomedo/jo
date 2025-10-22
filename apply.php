<?php
require_once 'config.php';
require_login();

$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$success_message = '';
$error_message = '';

if (!$job_id) {
    header('Location: index.php');
    exit();
}

// Get job details
$stmt = $pdo->prepare("
    SELECT j.*, c.name as category_name, c.color as category_color
    FROM jobs j
    LEFT JOIN categories c ON j.category_id = c.id
    WHERE j.id = ? AND j.status = 'active'
");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: index.php');
    exit();
}

// Check if application deadline has passed
if ($job['application_deadline'] && strtotime($job['application_deadline']) < time()) {
    $error_message = 'The application deadline for this job has passed.';
}

// Check if user already applied
$stmt = $pdo->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
$stmt->execute([$job_id, $_SESSION['user_id']]);
$existing_application = $stmt->fetch();

if ($existing_application) {
    $error_message = 'You have already applied for this position.';
}

// Handle form submission
if ($_POST && !$error_message) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $cover_letter = sanitize_input($_POST['cover_letter']);
        $resume_uploaded = false;
        $resume_path = '';
        
        // Handle file upload
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['resume'];
            $file_size = $file['size'];
            $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file
            if ($file_size > MAX_FILE_SIZE) {
                $error_message = 'File size exceeds the maximum limit of ' . (MAX_FILE_SIZE / 1048576) . 'MB.';
            } elseif (!in_array($file_type, ALLOWED_EXTENSIONS)) {
                $error_message = 'Invalid file type. Allowed types: ' . implode(', ', ALLOWED_EXTENSIONS);
            } else {
                // Create upload directory if it doesn't exist
                if (!file_exists(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }
                
                // Generate unique filename
                $filename = 'resume_' . $_SESSION['user_id'] . '_' . $job_id . '_' . time() . '.' . $file_type;
                $upload_path = UPLOAD_DIR . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $resume_uploaded = true;
                    $resume_path = $upload_path;
                } else {
                    $error_message = 'Failed to upload resume. Please try again.';
                }
            }
        }
        
        if (!$error_message) {
            if (empty($cover_letter) && !$resume_uploaded) {
                $error_message = 'Please provide either a cover letter or upload your resume.';
            } else {
                try {
                    // Insert application
                    $stmt = $pdo->prepare("
                        INSERT INTO applications (job_id, user_id, cover_letter, resume_path, applied_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$job_id, $_SESSION['user_id'], $cover_letter, $resume_path]);
                    
                    // Send notification email to employer
                    $user_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $user_stmt->execute([$_SESSION['user_id']]);
                    $user = $user_stmt->fetch();
                    
                    $email_body = "New application received for: {$job['title']}\n\n";
                    $email_body .= "Applicant: {$user['name']} ({$user['email']})\n";
                    $email_body .= "Company: {$job['company']}\n";
                    $email_body .= "Applied: " . date('Y-m-d H:i:s') . "\n\n";
                    $email_body .= "View application: " . SITE_URL . "/admin.php\n";
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO email_queue (to_email, subject, body, template) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $job['contact_email'],
                        'New Application - ' . $job['title'],
                        $email_body,
                        'new_application'
                    ]);
                    
                    $success_message = 'Your application has been submitted successfully!';
                    
                    // Redirect after 3 seconds
                    echo "<script>setTimeout(function(){ window.location.href = 'dashboard.php'; }, 3000);</script>";
                    
                } catch (PDOException $e) {
                    $error_message = 'Failed to submit application. Please try again.';
                }
            }
        }
    }
}

// Get user profile data for pre-filling
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?php echo htmlspecialchars($job['title']); ?> - <?php echo SITE_NAME; ?></title>
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

        .apply-header {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            color: white;
            padding: 40px 0;
        }

        .job-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-top: -30px;
            position: relative;
            z-index: 2;
            padding: 25px;
            margin-bottom: 30px;
        }

        .application-form {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 40px;
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

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #1e3d6f);
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1e3d6f, var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .category-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            margin-bottom: 10px;
        }

        .job-title {
            color: var(--text-dark);
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-name {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            color: #666;
            font-size: 0.9rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(44, 90, 160, 0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background-color: rgba(44, 90, 160, 0.1);
        }

        .upload-icon {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }

        .file-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            display: none;
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

        .tips-card {
            background: #e8f4fd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid var(--primary-color);
        }

        .tip-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .tip-item:last-child {
            margin-bottom: 0;
        }

        .tip-icon {
            color: var(--primary-color);
            margin-right: 10px;
            margin-top: 2px;
        }

        .character-count {
            text-align: right;
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .progress {
            height: 6px;
            border-radius: 3px;
            margin-bottom: 20px;
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
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Apply Header -->
    <section class="apply-header">
        <div class="container">
            <a href="job.php?id=<?php echo $job['id']; ?>" class="back-button">
                <i class="fas fa-arrow-left me-2"></i>Back to Job Details
            </a>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="job.php?id=<?php echo $job['id']; ?>">Job Details</a></li>
                    <li class="breadcrumb-item active">Apply</li>
                </ol>
            </nav>

            <h1><i class="fas fa-paper-plane me-2"></i>Apply for Position</h1>
            <p class="lead">Submit your application and take the next step in your career</p>
        </div>
    </section>

    <!-- Job Summary -->
    <div class="container">
        <div class="job-summary">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <?php if ($job['category_color']): ?>
                        <div class="category-badge" style="background-color: <?php echo $job['category_color']; ?>">
                            <?php echo htmlspecialchars($job['category_name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h2>
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
                        <?php if ($job['salary_min'] || $job['salary_max']): ?>
                            <div class="meta-item">
                                <i class="fas fa-dollar-sign"></i>
                                <?php echo format_salary($job['salary_min'], $job['salary_max']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: 75%"></div>
                    </div>
                    <p class="text-muted mb-0">Step 3 of 4: Submit Application</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Form -->
    <div class="container py-5">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <br><small>Redirecting to your dashboard...</small>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!$error_message && !$success_message): ?>
            <!-- Application Tips -->
            <div class="tips-card">
                <h5><i class="fas fa-lightbulb me-2"></i>Application Tips</h5>
                <div class="tip-item">
                    <i class="fas fa-check tip-icon"></i>
                    <div>Customize your cover letter to highlight relevant experience for this specific role</div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-check tip-icon"></i>
                    <div>Upload your most current resume in PDF format for best compatibility</div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-check tip-icon"></i>
                    <div>Mention specific skills and achievements that match the job requirements</div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-check tip-icon"></i>
                    <div>Keep your application professional and error-free</div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="application-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <!-- Personal Information (Pre-filled) -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user me-2"></i>Your Information
                    </h3>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['location'] ?: 'Not provided'); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        To update your personal information, please visit your <a href="profile.php">profile page</a>.
                    </div>
                </div>

                <!-- Cover Letter -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-file-text me-2"></i>Cover Letter
                    </h3>
                    <div class="mb-3">
                        <label for="cover_letter" class="form-label">Tell us why you're perfect for this role</label>
                        <textarea class="form-control" id="cover_letter" name="cover_letter" rows="8" 
                                  placeholder="Write a compelling cover letter that highlights your relevant experience, skills, and enthusiasm for this position..."
                                  maxlength="2000" onkeyup="updateCharacterCount(this, 'cover_letter_count')"><?php echo isset($_POST['cover_letter']) ? htmlspecialchars($_POST['cover_letter']) : ''; ?></textarea>
                        <div class="character-count">
                            <span id="cover_letter_count">0</span> / 2000 characters
                        </div>
                    </div>
                </div>

                <!-- Resume Upload -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-file-upload me-2"></i>Resume Upload
                    </h3>
                    <div class="mb-3">
                        <label class="form-label">Upload your resume (PDF, DOC, DOCX - Max 5MB)</label>
                        <div class="file-upload-area" onclick="document.getElementById('resume').click()" 
                             ondragover="handleDragOver(event)" ondrop="handleDrop(event)">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <h5>Click to upload or drag and drop</h5>
                            <p class="text-muted mb-0">Supported formats: PDF, DOC, DOCX (Max 5MB)</p>
                        </div>
                        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" style="display: none;" onchange="displayFileInfo(this)">
                        <div class="file-info" id="file-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file-pdf text-danger me-2"></i>
                                    <span id="file-name"></span>
                                    <small class="text-muted">(<span id="file-size"></span>)</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <?php if ($user['skills'] || $user['experience']): ?>
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle me-2"></i>Your Profile Summary
                        </h3>
                        <?php if ($user['skills']): ?>
                            <div class="mb-3">
                                <label class="form-label">Skills</label>
                                <div class="form-control" style="background-color: #f8f9fa;">
                                    <?php echo nl2br(htmlspecialchars($user['skills'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($user['experience']): ?>
                            <div class="mb-3">
                                <label class="form-label">Experience</label>
                                <div class="form-control" style="background-color: #f8f9fa; height: auto; min-height: 100px;">
                                    <?php echo nl2br(htmlspecialchars($user['experience'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This information is from your profile and will be included with your application.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Submit Application -->
                <div class="text-center">
                    <button type="submit" class="btn btn-primary me-3">
                        <i class="fas fa-paper-plane me-2"></i>Submit Application
                    </button>
                    <a href="job.php?id=<?php echo $job['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCharacterCount(textarea, counterId) {
            const count = textarea.value.length;
            document.getElementById(counterId).textContent = count;
            
            if (count > textarea.maxLength * 0.9) {
                document.getElementById(counterId).style.color = '#dc3545';
            } else {
                document.getElementById(counterId).style.color = '#666';
            }
        }

        function displayFileInfo(input) {
            const file = input.files[0];
            if (file) {
                document.getElementById('file-name').textContent = file.name;
                document.getElementById('file-size').textContent = formatFileSize(file.size);
                document.getElementById('file-info').style.display = 'block';
                
                // Update icon based on file type
                const icon = document.querySelector('#file-info i');
                const extension = file.name.split('.').pop().toLowerCase();
                icon.className = 'fas fa-file-' + (extension === 'pdf' ? 'pdf text-danger' : 'word text-primary') + ' me-2';
            }
        }

        function removeFile() {
            document.getElementById('resume').value = '';
            document.getElementById('file-info').style.display = 'none';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function handleDragOver(event) {
            event.preventDefault();
            event.currentTarget.classList.add('dragover');
        }

        function handleDrop(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('dragover');
            
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('resume').files = files;
                displayFileInfo(document.getElementById('resume'));
            }
        }

        // Initialize character count
        document.addEventListener('DOMContentLoaded', function() {
            const coverLetter = document.getElementById('cover_letter');
            if (coverLetter) {
                updateCharacterCount(coverLetter, 'cover_letter_count');
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const coverLetter = document.getElementById('cover_letter').value.trim();
            const resume = document.getElementById('resume').files.length > 0;
            
            if (!coverLetter && !resume) {
                e.preventDefault();
                alert('Please provide either a cover letter or upload your resume.');
                return false;
            }
            
            if (resume) {
                const file = document.getElementById('resume').files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('File size exceeds 5MB limit. Please choose a smaller file.');
                    return false;
                }
            }
        });
    </script>
</body>
</html>