<?php
require_once __DIR__ . '/../config.php';
requireRecruiter();
$page_title = 'Dashboard';

$stats = [];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM jobs GROUP BY status");
    foreach ($stmt->fetchAll() as $stat) {
        $stats['jobs_' . $stat['status']] = $stat['count'];
    }
    
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
    foreach ($stmt->fetchAll() as $stat) {
        $stats['apps_' . $stat['status']] = $stat['count'];
    }
    
    $stmt = $pdo->query("
        SELECT a.*, j.title as job_title 
        FROM applications a 
        INNER JOIN jobs j ON a.job_id = j.id 
        ORDER BY a.created_at DESC LIMIT 10
    ");
    $recent_applications = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM jobs");
    $stats['total_jobs'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM applications");
    $stats['total_applications'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

include __DIR__ . '/../includes/admin-header.php';
?>

<h1 class="mb-4"><i class="fas fa-tachometer-alt text-primary"></i> Dashboard</h1>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats['total_jobs'] ?? 0; ?></h3>
                <p class="mb-0">Total Jobs</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats['jobs_published'] ?? 0; ?></h3>
                <p class="mb-0">Published</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats['total_applications'] ?? 0; ?></h3>
                <p class="mb-0">Applications</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats['apps_new'] ?? 0; ?></h3>
                <p class="mb-0">New Applications</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Recent Applications</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($recent_applications)): ?>
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
                        <?php foreach ($recent_applications as $app): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitizeOutput($app['first_name'] . ' ' . $app['last_name']); ?></strong><br>
                                <small class="text-muted"><?php echo sanitizeOutput($app['email']); ?></small>
                            </td>
                            <td><?php echo sanitizeOutput($app['job_title']); ?></td>
                            <td><span class="badge bg-<?php echo $app['status'] === 'new' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($app['status']); ?></span></td>
                            <td><?php echo timeAgo($app['created_at']); ?></td>
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
            <p class="text-muted mb-0">No applications yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
