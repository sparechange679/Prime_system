<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$agent_filter = $_GET['agent'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "s.status = :status";
    $params[':status'] = $status_filter;
}

if ($agent_filter) {
    $where_conditions[] = "s.agent_id = :agent_id";
    $params[':agent_id'] = $agent_filter;
}

if ($date_from) {
    $where_conditions[] = "s.created_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $where_conditions[] = "s.created_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all shipments with filters
try {
    $shipments_query = "SELECT s.*, c.company_name, c.contact_person,
                               u.full_name as agent_name, u.email as agent_email,
                               k.full_name as keeper_name,
                               (SELECT COUNT(*) FROM shipment_documents WHERE shipment_id = s.shipment_id) as doc_count
                        FROM shipments s 
                        LEFT JOIN clients c ON s.client_id = c.client_id 
                        LEFT JOIN users u ON s.agent_id = u.user_id 
                        LEFT JOIN users k ON s.keeper_id = k.user_id
                        $where_clause
                        ORDER BY s.created_at DESC";

    $stmt = $db->prepare($shipments_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shipments = [];
}

// Get all agents for filter
try {
    $agents_query = "SELECT user_id, full_name FROM users WHERE role = 'agent' ORDER BY full_name";
    $stmt = $db->prepare($agents_query);
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $agents = [];
}

// Get shipment statistics
$total_shipments = count($shipments);
$status_counts = [];
$total_value = 0;
$total_tax = 0;

foreach ($shipments as $shipment) {
    $status_counts[$shipment['status']] = ($status_counts[$shipment['status']] ?? 0) + 1;
    $total_value += $shipment['declared_value'];
    $total_tax += $shipment['tax_amount'];
}

// Handle shipment status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $shipment_id = (int)$_POST['shipment_id'];
    $new_status = $_POST['status'];
    $admin_notes = trim($_POST['admin_notes']);

    try {
        $update_query = "UPDATE shipments 
                        SET status = :status,
                            admin_notes = :admin_notes,
                            updated_at = NOW()
                        WHERE shipment_id = :shipment_id";

        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':admin_notes', $admin_notes);
        $update_stmt->bindParam(':shipment_id', $shipment_id);

        if ($update_stmt->execute()) {
            // Log the activity
            $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                          VALUES (:user_id, 'update_shipment_status', 'shipments', :shipment_id, :details)";
            $log_stmt = $db->prepare($log_query);
            $log_details = "Shipment status updated to: $new_status";
            if ($admin_notes) $log_details .= " - Notes: $admin_notes";
            $log_stmt->bindParam(':user_id', $user_id);
            $log_stmt->bindParam(':shipment_id', $shipment_id);
            $log_stmt->bindParam(':details', $log_details);
            $log_stmt->execute();

            $status_success = "Shipment status updated successfully!";
            header("Location: admin_shipment_management.php" . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
            exit();
        } else {
            $status_error = "Failed to update shipment status";
        }
    } catch (PDOException $e) {
        $status_error = "Database error: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipment Management - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-shield-alt me-2"></i>Prime Cargo Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="admin_dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Admin Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-dark text-white">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-ship me-2"></i>
                            Shipment Management
                        </h2>
                        <p class="card-text">Monitor and manage all cargo shipments in the system</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($status_success)): ?>
            <div class="alert alert-success"><?php echo $status_success; ?></div>
        <?php endif; ?>

        <?php if (isset($status_error)): ?>
            <div class="alert alert-danger"><?php echo $status_error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'agent_assigned'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>Agent assigned successfully! Shipment status updated to 'Under Verification'.
            </div>
        <?php endif; ?>

        <!-- Shipment Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                        <h3 class="card-title"><?php echo $total_shipments; ?></h3>
                        <p class="card-text text-muted">Total Shipments</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-3x text-success mb-3"></i>
                        <h3 class="card-title">$<?php echo number_format($total_value, 2); ?></h3>
                        <p class="card-text text-muted">Total Declared Value</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-calculator fa-3x text-warning mb-3"></i>
                        <h3 class="card-title">$<?php echo number_format($total_tax, 2); ?></h3>
                        <p class="card-text text-muted">Total Tax Amount</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                        <h3 class="card-title"><?php echo count($status_counts); ?></h3>
                        <p class="card-text text-muted">Status Types</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="documents_submitted" <?php echo $status_filter === 'documents_submitted' ? 'selected' : ''; ?>>Documents Submitted</option>
                                    <option value="under_verification" <?php echo $status_filter === 'under_verification' ? 'selected' : ''; ?>>Under Verification</option>
                                    <option value="under_clearance" <?php echo $status_filter === 'under_clearance' ? 'selected' : ''; ?>>Under Clearance</option>
                                    <option value="manifest_issued" <?php echo $status_filter === 'manifest_issued' ? 'selected' : ''; ?>>Manifest Issued</option>
                                    <option value="clearance_approved" <?php echo $status_filter === 'clearance_approved' ? 'selected' : ''; ?>>Clearance Approved</option>
                                    <option value="release_issued" <?php echo $status_filter === 'release_issued' ? 'selected' : ''; ?>>Release Issued</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Agent</label>
                                <select name="agent" class="form-select">
                                    <option value="">All Agents</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['user_id']; ?>" <?php echo $agent_filter == $agent['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                        <?php if ($status_filter || $agent_filter || $date_from || $date_to): ?>
                            <div class="mt-3">
                                <a href="admin_shipment_management.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Distribution -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($status_counts as $status => $count): ?>
                                <div class="col-md-3 mb-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-<?php
                                                                echo $status === 'completed' ? 'success' : ($status === 'under_clearance' ? 'warning' : ($status === 'pending' ? 'secondary' : 'info'));
                                                                ?> fs-6 me-2">
                                            <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                        </span>
                                        <span class="fw-bold"><?php echo $count; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipments Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Shipments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($shipments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-ship fa-3x text-muted mb-3"></i>
                                <h6>No Shipments Found</h6>
                                <p class="text-muted">No shipments match the current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tracking</th>
                                            <th>Client</th>
                                            <th>Agent</th>
                                            <th>Goods</th>
                                            <th>Documents</th>
                                            <th>Value</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($shipments as $shipment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong>
                                                    <?php if ($shipment['manifest_number']): ?>
                                                        <br><small class="text-primary"><?php echo htmlspecialchars($shipment['manifest_number']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($shipment['company_name']): ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($shipment['company_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($shipment['contact_person']); ?></small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center">
                                                            <span class="badge bg-warning">Client ID: <?php echo $shipment['client_id']; ?></span>
                                                            <br><small class="text-muted">Client info missing</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($shipment['agent_name']): ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($shipment['agent_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($shipment['agent_email']); ?></small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center">
                                                            <span class="badge bg-danger">No Agent Assigned</span>
                                                            <br><small class="text-muted">Needs assignment</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($shipment['goods_description'], 0, 50)) . '...'; ?>
                                                    <?php if ($shipment['tariff_number']): ?>
                                                        <br><small class="text-info"><?php echo htmlspecialchars($shipment['tariff_number']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $shipment['doc_count'] > 0 ? 'success' : 'warning'; ?>">
                                                        <?php echo $shipment['doc_count']; ?> docs
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>$<?php echo number_format($shipment['declared_value'], 2); ?></strong>
                                                        <?php if ($shipment['tax_amount'] > 0): ?>
                                                            <br><small class="text-warning">Tax: $<?php echo number_format($shipment['tax_amount'], 2); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $shipment['status'] === 'completed' ? 'success' : ($shipment['status'] === 'under_clearance' ? 'warning' : ($shipment['status'] === 'pending' ? 'secondary' : 'info'));
                                                                            ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($shipment['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if (!$shipment['agent_name']): ?>
                                                            <a href="admin_assign_agent.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                                                class="btn btn-sm btn-success">
                                                                <i class="fas fa-user-plus me-1"></i>Assign Agent
                                                            </a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#statusModal<?php echo $shipment['shipment_id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
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
        </div>
    </div>

    <!-- Status Update Modals -->
    <?php foreach ($shipments as $shipment): ?>
        <div class="modal fade" id="statusModal<?php echo $shipment['shipment_id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Shipment Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <p>Update status for shipment <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong></p>
                            <input type="hidden" name="shipment_id" value="<?php echo $shipment['shipment_id']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Current Status</label>
                                <input type="text" class="form-control" value="<?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending">Pending</option>

                                    <option value="under_verification">Under Verification</option>
                                    <option value="under_clearance">Under Clearance</option>
                                    <option value="manifest_issued">Manifest Issued</option>
                                    <option value="clearance_approved">Clearance Approved</option>
                                    <option value="release_issued">Release Issued</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin Notes</label>
                                <textarea name="admin_notes" class="form-control" rows="3"
                                    placeholder="Add notes about this status change..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>