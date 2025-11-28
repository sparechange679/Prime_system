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

// Get shipments pending clearance approval
try {
    $query = "SELECT s.*, c.company_name, c.contact_person, u.full_name as agent_name, u.email as agent_email
              FROM shipments s 
              JOIN clients c ON s.client_id = c.client_id 
              JOIN users u ON s.agent_id = u.user_id 
              WHERE s.status = 'under_clearance' 
              AND s.tax_amount > 0
              AND s.tariff_number IS NOT NULL
              ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading shipments: " . $e->getMessage();
    $pending_approvals = [];
}

// Get all clearance decisions
try {
    $all_query = "SELECT s.*, c.company_name, c.contact_person, u.full_name as agent_name
                  FROM shipments s 
                  JOIN clients c ON s.client_id = c.client_id 
                  JOIN users u ON s.agent_id = u.user_id 
                  WHERE s.status IN ('clearance_approved', 'clearance_rejected')
                  ORDER BY s.updated_at DESC";
    $stmt = $db->prepare($all_query);
    $stmt->execute();
    $processed_clearances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $processed_clearances = [];
}

// Handle clearance approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipment_id = (int)$_POST['shipment_id'];
    $decision = $_POST['decision'];
    $admin_notes = trim($_POST['admin_notes']);
    $next_action = $_POST['next_action'];

    if (empty($decision)) {
        $approval_error = "Decision is required";
    } else {
        try {
            $new_status = ($decision === 'approve') ? 'clearance_approved' : 'clearance_rejected';

            // Update shipment status
            $update_query = "UPDATE shipments 
                           SET status = :status,
                               admin_notes = :admin_notes,
                               next_action = :next_action,
                               updated_at = NOW()
                           WHERE shipment_id = :shipment_id";

            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $new_status);
            $update_stmt->bindParam(':admin_notes', $admin_notes);
            $update_stmt->bindParam(':next_action', $next_action);
            $update_stmt->bindParam(':shipment_id', $shipment_id);

            if ($update_stmt->execute()) {
                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                              VALUES (:user_id, :action, 'shipments', :shipment_id, :details)";
                $log_stmt = $db->prepare($log_query);
                $action = ($decision === 'approve') ? 'approve_clearance' : 'reject_clearance';
                $log_details = "Clearance " . ($decision === 'approve' ? 'approved' : 'rejected') . " - Notes: $admin_notes";
                if ($next_action) $log_details .= " - Next Action: $next_action";

                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':action', $action);
                $log_stmt->bindParam(':shipment_id', $shipment_id);
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();

                $approval_success = "Clearance " . ($decision === 'approve' ? 'approved' : 'rejected') . " successfully!";
                header("Location: admin_clearance_approval.php");
                exit();
            } else {
                $approval_error = "Failed to process clearance decision";
            }
        } catch (PDOException $e) {
            $approval_error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Approval - Prime Cargo Limited</title>
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
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-thumbs-up me-2"></i>
                            Clearance Approval Management
                        </h2>
                        <p class="card-text">Review and approve/reject agent clearance submissions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($approval_success)): ?>
            <div class="alert alert-success"><?php echo $approval_success; ?></div>
        <?php endif; ?>

        <?php if (isset($approval_error)): ?>
            <div class="alert alert-danger"><?php echo $approval_error; ?></div>
        <?php endif; ?>

        <!-- Pending Clearance Approvals -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Clearance Approvals</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_approvals)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h6>No Pending Clearance Approvals</h6>
                                <p class="text-muted">All clearance submissions have been processed.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_approvals as $shipment): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-ship me-2"></i>
                                            Shipment: <?php echo htmlspecialchars($shipment['tracking_number']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            Client: <?php echo htmlspecialchars($shipment['company_name']); ?> |
                                            Agent: <?php echo htmlspecialchars($shipment['agent_name']); ?> |
                                            Tax Amount: $<?php echo number_format($shipment['tax_amount'], 2); ?>
                                        </small>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Shipment Details</h6>
                                                <p class="mb-1"><strong>Goods:</strong> <?php echo htmlspecialchars($shipment['goods_description']); ?></p>
                                                <p class="mb-1"><strong>Value:</strong> $<?php echo number_format($shipment['declared_value'], 2); ?></p>
                                                <p class="mb-1"><strong>Tariff Code:</strong> <?php echo htmlspecialchars($shipment['tariff_number']); ?></p>
                                                <p class="mb-1"><strong>Origin:</strong> <?php echo htmlspecialchars($shipment['origin_country']); ?></p>
                                                <p class="mb-1"><strong>Destination:</strong> <?php echo htmlspecialchars($shipment['destination_port']); ?></p>
                                                <p class="mb-1"><strong>Agent Contact:</strong> <?php echo htmlspecialchars($shipment['agent_email']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Clearance Decision</h6>
                                                <form method="POST">
                                                    <input type="hidden" name="shipment_id" value="<?php echo $shipment['shipment_id']; ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Decision <span class="text-danger">*</span></label>
                                                        <div class="d-flex gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="decision" value="approve" id="approve_<?php echo $shipment['shipment_id']; ?>" required>
                                                                <label class="form-check-label text-success" for="approve_<?php echo $shipment['shipment_id']; ?>">
                                                                    <i class="fas fa-check me-1"></i>Approve
                                                                </label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="decision" value="reject" id="reject_<?php echo $shipment['shipment_id']; ?>" required>
                                                                <label class="form-check-label text-danger" for="reject_<?php echo $shipment['shipment_id']; ?>">
                                                                    <i class="fas fa-times me-1"></i>Reject
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Next Action</label>
                                                        <select name="next_action" class="form-select">
                                                            <option value="">Select Next Action</option>
                                                            <option value="issue_manifest">Issue Manifest Number</option>
                                                            <option value="request_additional_docs">Request Additional Documents</option>
                                                            <option value="schedule_inspection">Schedule Physical Inspection</option>
                                                            <option value="proceed_to_release">Proceed to Release Order</option>
                                                            <option value="other">Other</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Admin Notes <span class="text-danger">*</span></label>
                                                        <textarea name="admin_notes" class="form-control" rows="3"
                                                            placeholder="Provide detailed notes about your decision..." required></textarea>
                                                    </div>

                                                    <div class="d-flex gap-2">
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-check me-2"></i>Process Decision
                                                        </button>
                                                        <a href="admin_agent_communication.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                                            class="btn btn-outline-primary">
                                                            <i class="fas fa-comments me-2"></i>Contact Agent
                                                        </a>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Processed Clearances -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Processed Clearance Decisions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($processed_clearances)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                                <h6>No Clearance Decisions Processed Yet</h6>
                                <p class="text-muted">Clearance decisions will appear here once processed.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tracking</th>
                                            <th>Client</th>
                                            <th>Agent</th>
                                            <th>Decision</th>
                                            <th>Tax Amount</th>
                                            <th>Next Action</th>
                                            <th>Admin Notes</th>
                                            <th>Date Processed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($processed_clearances as $shipment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($shipment['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($shipment['agent_name']); ?></td>
                                                <td>
                                                    <?php
                                                    $decision_badge = $shipment['status'] === 'clearance_approved' ? 'success' : 'danger';
                                                    $decision_text = $shipment['status'] === 'clearance_approved' ? 'Approved' : 'Rejected';
                                                    ?>
                                                    <span class="badge bg-<?php echo $decision_badge; ?>">
                                                        <?php echo $decision_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong>$<?php echo number_format($shipment['tax_amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($shipment['next_action']): ?>
                                                        <span class="badge bg-info">
                                                            <?php echo ucwords(str_replace('_', ' ', $shipment['next_action'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($shipment['admin_notes']): ?>
                                                        <small><?php echo htmlspecialchars(substr($shipment['admin_notes'], 0, 50)) . '...'; ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i', strtotime($shipment['updated_at'])); ?>
                                                    </small>
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