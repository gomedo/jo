
<?php
require_once __DIR__ . '/../../config.php';
requireRecruiter();
$page_title = 'Manage Jobs';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = JOBS_PER_PAGE;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(title LIKE ? OR company LIKE ? OR location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs $where_sql");
    $count_stmt->execute($params);
    $total_jobs = $count_stmt->fetchColumn();
    $total_pages = ceil($total_jobs / $per_page);
    
    $stmt = $pdo->prepare("
        SELECT j.*, c.name as category_name,
        (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
        FROM jobs j
        LEFT JOIN categories c ON j.category = c.id
        $where_sql
        ORDER BY j.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Jobs list error: " . $e->getMessage());
    $jobs = [];
    $total_pages = 1;
}

include __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-briefcase text-primary"></i> Manage Jobs</h1>
    <a href="/admin/jobs/create.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Post New Job
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search jobs..." 
                       value="<?php echo sanitizeOutput($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!empty($jobs)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Applications</th>
                            <th>Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitizeOutput($job['title']); ?></strong><br>
                                <small class="text-muted"><?php echo sanitizeOutput($job['company']); ?></small>
                            </td>
                            <td><?php echo sanitizeOutput($job['location']); ?></td>
                            <td><span class="badge bg-info"><?php echo sanitizeOutput($job['type']); ?></span></td>
                            <td><span class="badge bg-<?php echo $job['status'] === 'published' ? 'success' : ($job['status'] === 'draft' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($job['status']); ?></span></td>
                            <td><?php echo $job['application_count']; ?></td>
                            <td><?php echo formatDate($job['created_at']); ?></td>
                            <td>
                                <a href="/admin/jobs/edit.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="/job/<?php echo $job['slug']; ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted text-center my-4">No jobs found. <a href="/admin/jobs/create.php">Post your first job</a>.</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
