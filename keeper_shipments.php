<?php
require_once 'config.php';
require_once 'database.php';

// Keepers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'keeper') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Keeper';

$database = new Database();
$db = $database->getConnection();

// List all shipments (system-wide) so keeper can see new submissions
try {
    $sql = "SELECT s.shipment_id, s.tracking_number, s.status, s.created_at, s.updated_at,
                   c.company_name, cu.full_name AS client_name
            FROM shipments s
            JOIN clients c ON s.client_id = c.client_id
            LEFT JOIN users cu ON c.user_id = cu.user_id
            ORDER BY s.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shipments = [];
    $error = 'Error loading shipments: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipments - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-ship me-2"></i>Prime Cargo Limited</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="dashboard.php"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0"><i class="fas fa-ship me-2"></i>All Shipments</h3>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <?php if (empty($shipments)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-ship fa-2x mb-2"></i>
                        <div>No shipments found.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tracking #</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shipments as $s): ?>
                                    <?php
                                    $cls = match ($s['status']) {
                                        'pending' => 'warning',
                                        'under_verification' => 'primary',
                                        'under_clearance' => 'info',
                                        'clearance_approved' => 'success',
                                        'manifest_issued' => 'info',
                                        'release_issued' => 'success',
                                        'completed' => 'secondary',
                                        'cancelled' => 'dark',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['tracking_number']); ?></strong></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($s['company_name']); ?></div>
                                            <?php if (!empty($s['client_name'])): ?>
                                                <small class="text-muted">Submitted by: <?php echo htmlspecialchars($s['client_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?php echo $cls; ?>"><?php echo ucwords(str_replace('_', ' ', $s['status'])); ?></span></td>
                                        <td><small class="text-muted"><?php echo $s['created_at'] ? date('M d, Y H:i', strtotime($s['created_at'])) : '-'; ?></small></td>
                                        <td><small class="text-muted"><?php echo $s['updated_at'] ? date('M d, Y H:i', strtotime($s['updated_at'])) : '-'; ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>