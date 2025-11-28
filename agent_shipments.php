<?php
require_once 'config.php';
require_once 'database.php';

// Agents only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Agent';

$labels_to_codes = [
    'pending' => 'pending',
    'under_verification' => 'under_verification',
    'under_clearance' => 'under_clearance',
    'approved' => 'clearance_approved',
    'manifest_issued' => 'manifest_issued',
    'release_issued' => 'release_issued',
    'completed' => 'completed',
    'cancelled' => 'cancelled'
];

$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
// Normalize human labels like "Approved" to status code
$normalized = str_replace(' ', '_', $status);
$status_code = $labels_to_codes[$normalized] ?? ($labels_to_codes[$status] ?? '');

$database = new Database();
$db = $database->getConnection();

$shipments = [];
$title = 'All Shipments';
if ($status_code) {
    $title = 'Shipments — ' . ucwords(str_replace('_', ' ', $normalized));
}

try {
    if ($status_code) {
        $sql = "SELECT s.*, c.company_name
                FROM shipments s
                JOIN clients c ON s.client_id = c.client_id
                WHERE s.agent_id = :aid AND s.status = :st
                ORDER BY s.updated_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':aid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':st', $status_code);
    } else {
        $sql = "SELECT s.*, c.company_name
                FROM shipments s
                JOIN clients c ON s.client_id = c.client_id
                WHERE s.agent_id = :aid
                ORDER BY s.updated_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':aid', $user_id, PDO::PARAM_INT);
    }
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
    <title><?php echo htmlspecialchars($title); ?> - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-ship me-2"></i>Prime Cargo Limited</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="agent_dashboard.php"><i class="fas fa-user-tie me-2"></i>Agent Dashboard</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0"><i class="fas fa-clipboard-list me-2"></i><?php echo htmlspecialchars($title); ?></h3>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
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
                                    <th>Goods</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shipments as $s): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['tracking_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($s['goods_description'], 0, 60)); ?><?php if (strlen($s['goods_description']) > 60) echo '...'; ?></td>
                                        <td>
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
                                            <span class="badge bg-<?php echo $cls; ?>"><?php echo ucwords(str_replace('_', ' ', $s['status'])); ?></span>
                                        </td>
                                        <td><small class="text-muted"><?php echo $s['updated_at'] ? date('M d, Y H:i', strtotime($s['updated_at'])) : '-'; ?></small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="track_shipment.php?shipment_id=<?php echo (int)$s['shipment_id']; ?>" class="btn btn-outline-primary" title="Track"><i class="fas fa-search"></i></a>
                                                <a href="upload_document.php?shipment_id=<?php echo (int)$s['shipment_id']; ?>" class="btn btn-outline-success" title="Upload Docs"><i class="fas fa-upload"></i></a>
                                                <a href="agent_clearance.php?shipment_id=<?php echo (int)$s['shipment_id']; ?>" class="btn btn-outline-warning" title="Process Clearance"><i class="fas fa-shield-check"></i></a>
                                                <a href="agent_declaration.php?shipment_id=<?php echo (int)$s['shipment_id']; ?>" class="btn btn-outline-info" title="Declaration"><i class="fas fa-file-invoice-dollar"></i></a>
                                            </div>
                                        </td>
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