<?php
require_once __DIR__ . '/../../config.php';
requireRecruiter();
$page_title = 'Manage Applications';

$job_filter = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';

$where = [];
$params = [];

if ($job_filter) {
    $where[] = "a.job_id = ?";
    $params[] = $job_filter;
}

if ($status_filter) {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $pdo->prepare("
        SELECT a.*, j.title as job_title, j.slug as job_slug
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        $where_sql
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    
    $jobs_stmt = $pdo->query("SELECT id, title FROM jobs ORDER BY title");
    $jobs = $jobs_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Applications list error: " . $e->getMessage());
    $applications = [];
    $jobs = [];
}

include __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-file-alt text-primary"></i> Manage Applications</h1>
    <a href="/admin/applications/export.php" class="btn btn-success">
        <i class="fas fa-download me-2"></i>Export CSV
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <select name="job_id" class="form-select">
                    <option value="">All Jobs</option>
                    <?php foreach ($jobs as $job): ?>
                        <option value="<?php echo $job['id']; ?>" <?php echo $job_filter === $job['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($job['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <?php
                    global $application_statuses;
                    foreach ($application_statuses as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!empty($applications)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Job</th>
                            <th>Status</th>
                            <th>Applied</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitizeOutput($app['first_name'] . ' ' . $app['last_name']); ?></strong><br>
                                <small class="text-muted"><?php echo sanitizeOutput($app['email']); ?></small>
                            </td>
                            <td><?php echo sanitizeOutput($app['job_title']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $app['status'] === 'new' ? 'primary' : 
                                        ($app['status'] === 'shortlisted' ? 'success' : 
                                        ($app['status'] === 'rejected' ? 'danger' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDateTime($app['created_at']); ?></td>
                            <td>
                                <a href="/admin/applications/view.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center my-4">No applications found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
