<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get agent's assigned shipments
try {
    $query = "SELECT s.*, c.company_name FROM shipments s 
              JOIN clients c ON s.client_id = c.client_id 
              WHERE s.agent_id = :user_id ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading shipments: " . $e->getMessage();
    $shipments = [];
}

// Get recent documents for this agent's shipments
try {
    $docQuery = "SELECT d.*, s.tracking_number, c.company_name
                 FROM shipment_documents d
                 JOIN shipments s ON d.shipment_id = s.shipment_id
                 JOIN clients c ON s.client_id = c.client_id
                 WHERE s.agent_id = :user_id
                 ORDER BY d.uploaded_at DESC
                 LIMIT 10";
    $docStmt = $db->prepare($docQuery);
    $docStmt->bindParam(':user_id', $user_id);
    $docStmt->execute();
    $recent_documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_documents = [];
}

// Get agent's manifest and TPIN information
try {
    $manifest_query = "SELECT manifest_number, status FROM manifests WHERE agent_id = :user_id AND status = 'active' ORDER BY created_at DESC LIMIT 1";
    $manifest_stmt = $db->prepare($manifest_query);
    $manifest_stmt->bindParam(':user_id', $user_id);
    $manifest_stmt->execute();
    $manifest = $manifest_stmt->fetch(PDO::FETCH_ASSOC);

    $tpin_query = "SELECT tpin_number, status FROM tpin_assignments WHERE agent_id = :user_id AND status = 'active' ORDER BY created_at DESC LIMIT 1";
    $tpin_stmt = $db->prepare($tpin_query);
    $tpin_stmt->bindParam(':user_id', $user_id);
    $tpin_stmt->execute();
    $tpin = $tpin_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $manifest = null;
    $tpin = null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-ship me-2"></i>Prime Cargo Limited
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Main Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-user-tie me-2 text-primary"></i>
                            Agent Dashboard
                        </h2>
                        <p class="card-text">Manage your assigned shipments and clearance processes</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agent Identification Cards -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Manifest Number</h5>
                    </div>
                    <div class="card-body text-center">
                        <?php if ($manifest): ?>
                            <h3 class="text-primary mb-2"><?php echo htmlspecialchars($manifest['manifest_number']); ?></h3>
                            <span class="badge bg-success">Active</span>
                            <p class="text-muted mt-2">Your unique manifest identifier</p>
                        <?php else: ?>
                            <h3 class="text-muted mb-2">Not Assigned</h3>
                            <span class="badge bg-warning">Pending</span>
                            <p class="text-muted mt-2">Contact admin for assignment</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>TPIN Number</h5>
                    </div>
                    <div class="card-body text-center">
                        <?php if ($tpin): ?>
                            <h3 class="text-success mb-2"><?php echo htmlspecialchars($tpin['tpin_number']); ?></h3>
                            <span class="badge bg-success">Active</span>
                            <p class="text-muted mt-2">Your Tax Payer Identification Number</p>
                        <?php else: ?>
                            <h3 class="text-muted mb-2">Not Assigned</h3>
                            <span class="badge bg-warning">Pending</span>
                            <p class="text-muted mt-2">Contact admin for assignment</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>



        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="agent_review_tasks.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-tasks me-2"></i>Review Tasks
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="agent_documents.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-file-alt me-2"></i>Review Documents
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="agent_clearance.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-shield-check me-2"></i>Process Clearance
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="agent_tax_calculation.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-calculator me-2"></i>Tax Calculator
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="agent_messaging.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-comments me-2"></i>Client Messaging
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="agent_keeper_communication.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-user-shield me-2"></i>Keeper Communication
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Shipments and Documents -->
        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-ship me-2"></i>Recent Shipments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($shipments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-ship fa-3x text-muted mb-3"></i>
                                <h6>No Shipments Assigned</h6>
                                <p class="text-muted">You haven't been assigned any shipments yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tracking</th>
                                            <th>Client</th>
                                            <th>Goods</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($shipments, 0, 10) as $shipment): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($shipment['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($shipment['goods_description'], 0, 50)) . (strlen($shipment['goods_description']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $shipment['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="agent_declaration.php?shipment_id=<?php echo (int)$shipment['shipment_id']; ?>" class="btn btn-outline-success" title="Declaration">
                                                            <i class="fas fa-file-invoice-dollar"></i>
                                                        </a>
                                                        <a href="agent_tax_calculation.php?shipment_id=<?php echo (int)$shipment['shipment_id']; ?>" class="btn btn-outline-warning" title="Tax Calculator">
                                                            <i class="fas fa-calculator"></i>
                                                        </a>
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

            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Recent Documents</h5>
                        <a href="documents.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_documents)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-2x text-muted mb-2"></i>
                                <div class="text-muted">No documents uploaded yet for your shipments.</div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Shipment</th>
                                            <th>Client</th>
                                            <th>Document</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_documents as $doc): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary">#<?php echo htmlspecialchars($doc['tracking_number']); ?></span></td>
                                                <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars($doc['original_filename']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php $isVerified = (int)($doc['verified'] ?? 0) === 1; ?>
                                                    <span class="badge bg-<?php echo $isVerified ? 'success' : 'warning'; ?>">
                                                        <?php echo $isVerified ? 'Verified' : 'Pending'; ?>
                                                    </span>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>