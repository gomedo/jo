<?php
require_once __DIR__ . '/../../config.php';
requireRecruiter();
$page_title = 'Post New Job';

$error = '';
$success = '';

$categories = getCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $title = cleanInput($_POST['title'] ?? '');
        $company = cleanInput($_POST['company'] ?? 'NZQRI');
        $location = cleanInput($_POST['location'] ?? '');
        $type = cleanInput($_POST['type'] ?? 'full-time');
        $category = intval($_POST['category'] ?? 0);
        $salary_min = floatval($_POST['salary_min'] ?? 0);
        $salary_max = floatval($_POST['salary_max'] ?? 0);
        $description = cleanInput($_POST['description'] ?? '');
        $requirements = cleanInput($_POST['requirements'] ?? '');
        $benefits = cleanInput($_POST['benefits'] ?? '');
        $status = cleanInput($_POST['status'] ?? 'draft');
        
        if (empty($title) || empty($location) || empty($description)) {
            $error = 'Please fill in all required fields.';
        } else {
            $slug = createSlug($title);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetchColumn() > 0) {
                $slug .= '-' . time();
            }
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO jobs (title, slug, company, location, type, category, salary_min, salary_max, 
                                    description, requirements, benefits, status, posted_at, posted_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $title, $slug, $company, $location, $type, $category ?: null, 
                    $salary_min, $salary_max, $description, $requirements, $benefits, 
                    $status, $status === 'published' ? date('Y-m-d H:i:s') : null, $_SESSION['user_id']
                ]);
                
                $job_id = $pdo->lastInsertId();
                logActivity($_SESSION['user_id'], 'job_created', "Created job: $title (ID: $job_id)");
                
                $_SESSION['success_message'] = 'Job posted successfully!';
                redirect('/admin/jobs/list.php');
            } catch (PDOException $e) {
                error_log("Job create error: " . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}

$csrf_token = generateCSRFToken();
include __DIR__ . '/../../includes/admin-header.php';
?>

<h1 class="mb-4"><i class="fas fa-plus-circle text-primary"></i> Post New Job</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo sanitizeOutput($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Job Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required 
                               value="<?php echo isset($_POST['title']) ? sanitizeOutput($_POST['title']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" class="form-control" value="NZQRI">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" required placeholder="e.g., Auckland, New Zealand">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Job Type</label>
                        <select name="type" class="form-select">
                            <?php
                            global $job_types;
                            foreach ($job_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo sanitizeOutput($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Minimum Salary</label>
                        <input type="number" name="salary_min" class="form-control" placeholder="e.g., 60000">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Maximum Salary</label>
                        <input type="number" name="salary_max" class="form-control" placeholder="e.g., 80000">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Job Description <span class="text-danger">*</span></label>
                <textarea name="description" class="form-control" rows="6" required></textarea>
                <small class="text-muted">Describe the role, responsibilities, and what makes it exciting.</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Requirements</label>
                <textarea name="requirements" class="form-control" rows="5"></textarea>
                <small class="text-muted">Qualifications, skills, and experience needed.</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Benefits</label>
                <textarea name="benefits" class="form-control" rows="4"></textarea>
                <small class="text-muted">What we offer to employees.</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="draft">Draft (not visible to public)</option>
                    <option value="published">Published (visible to public)</option>
                </select>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Post Job
                </button>
                <a href="/admin/jobs/list.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
