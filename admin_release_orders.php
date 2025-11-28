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

// Get shipments ready for release orders
try {
    $query = "SELECT s.*, c.company_name, c.contact_person, c.phone, c.email, u.full_name as agent_name
              FROM shipments s 
              JOIN clients c ON s.client_id = c.client_id 
              JOIN users u ON s.agent_id = u.user_id 
              WHERE s.status IN ('manifest_issued', 'under_clearance', 'clearance_approved') 
              AND s.manifest_number IS NOT NULL 
              AND s.manifest_number != ''
              AND s.tax_amount > 0
              ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ready_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading shipments: " . $e->getMessage();
    $ready_shipments = [];
}

// Get all issued release orders
try {
    $all_query = "SELECT s.*, c.company_name, c.contact_person, u.full_name as agent_name
                  FROM shipments s 
                  JOIN clients c ON s.client_id = c.client_id 
                  JOIN users u ON s.agent_id = u.user_id 
                  WHERE s.status = 'release_issued'
                  ORDER BY s.release_date DESC";
    $stmt = $db->prepare($all_query);
    $stmt->execute();
    $released_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $released_shipments = [];
}

// Handle release order issuance
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipment_id = (int)$_POST['shipment_id'];
    $release_number = trim($_POST['release_number']);
    $release_notes = trim($_POST['release_notes']);
    $payment_status = $_POST['payment_status'];

    if (empty($release_number)) {
        $release_error = "Release number is required";
    } else {
        try {
            // Check if release number already exists
            $check_query = "SELECT COUNT(*) as count FROM shipments WHERE release_number = :release_number AND shipment_id != :shipment_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':release_number', $release_number);
            $check_stmt->bindParam(':shipment_id', $shipment_id);
            $check_stmt->execute();

            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $release_error = "Release number already exists";
            } else {
                // Update shipment with release order
                $update_query = "UPDATE shipments 
                               SET release_number = :release_number,
                                   status = 'release_issued',
                                   release_date = NOW(),
                                   payment_status = :payment_status,
                                   updated_at = NOW()
                               WHERE shipment_id = :shipment_id";

                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':release_number', $release_number);
                $update_stmt->bindParam(':payment_status', $payment_status);
                $update_stmt->bindParam(':shipment_id', $shipment_id);

                if ($update_stmt->execute()) {
                    // Log the activity
                    $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                                  VALUES (:user_id, 'issue_release', 'shipments', :shipment_id, :details)";
                    $log_stmt = $db->prepare($log_query);
                    $log_details = "Release order issued: $release_number, Payment: $payment_status";
                    if ($release_notes) $log_details .= " - Notes: $release_notes";
                    $log_stmt->bindParam(':user_id', $user_id);
                    $log_stmt->bindParam(':shipment_id', $shipment_id);
                    $log_stmt->bindParam(':details', $log_details);
                    $log_stmt->execute();

                    $release_success = "Release order issued successfully!";
                    header("Location: admin_release_orders.php");
                    exit();
                } else {
                    $release_error = "Failed to issue release order";
                }
            }
        } catch (PDOException $e) {
            $release_error = "Database error: " . $e->getMessage();
        }
    }
}

// Generate next release number
function generateReleaseNumber()
{
    $year = date('Y');
    $month = date('m');
    return "RO-{$year}-{$month}-" . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Release Orders - Prime Cargo Limited</title>
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
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-check-circle me-2"></i>
                            Release Orders Management
                        </h2>
                        <p class="card-text">Issue final clearance and release orders for completed shipments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($release_success)): ?>
            <div class="alert alert-success"><?php echo $release_success; ?></div>
        <?php endif; ?>

        <?php if (isset($release_error)): ?>
            <div class="alert alert-danger"><?php echo $release_error; ?></div>
        <?php endif; ?>

        <!-- Shipments Ready for Release -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Shipments Ready for Release Orders</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ready_shipments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h6>No Shipments Ready for Release</h6>
                                <p class="text-muted">All shipments are either already released or not yet ready.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($ready_shipments as $shipment): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-ship me-2"></i>
                                            Shipment: <?php echo htmlspecialchars($shipment['tracking_number']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            Client: <?php echo htmlspecialchars($shipment['company_name']); ?> |
                                            Agent: <?php echo htmlspecialchars($shipment['agent_name']); ?> |
                                            Manifest: <?php echo htmlspecialchars($shipment['manifest_number']); ?>
                                        </small>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Shipment Details</h6>
                                                <p class="mb-1"><strong>Goods:</strong> <?php echo htmlspecialchars($shipment['goods_description']); ?></p>
                                                <p class="mb-1"><strong>Value:</strong> $<?php echo number_format($shipment['declared_value'], 2); ?></p>
                                                <p class="mb-1"><strong>Tax Amount:</strong> $<?php echo number_format($shipment['tax_amount'], 2); ?></p>
                                                <p class="mb-1"><strong>Origin:</strong> <?php echo htmlspecialchars($shipment['origin_country']); ?></p>
                                                <p class="mb-1"><strong>Destination:</strong> <?php echo htmlspecialchars($shipment['destination_port']); ?></p>
                                                <p class="mb-1"><strong>Current Status:</strong>
                                                    <span class="badge bg-<?php echo $shipment['status'] === 'clearance_approved' ? 'success' : 'info'; ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Issue Release Order</h6>
                                                <form method="POST">
                                                    <input type="hidden" name="shipment_id" value="<?php echo $shipment['shipment_id']; ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Release Number <span class="text-danger">*</span></label>
                                                        <div class="input-group">
                                                            <input type="text" name="release_number" class="form-control"
                                                                value="<?php echo generateReleaseNumber(); ?>" required>
                                                            <button type="button" class="btn btn-outline-secondary" onclick="generateNewRelease(this)">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        </div>
                                                        <div class="form-text">Format: RO-YYYY-MM-XXXX</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Payment Status <span class="text-danger">*</span></label>
                                                        <select name="payment_status" class="form-select" required>
                                                            <option value="pending">Pending Payment</option>
                                                            <option value="partial">Partial Payment</option>
                                                            <option value="completed">Payment Completed</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Release Notes (Optional)</label>
                                                        <textarea name="release_notes" class="form-control" rows="2"
                                                            placeholder="Add any notes about this release..."></textarea>
                                                    </div>

                                                    <button type="submit" class="btn btn-warning">
                                                        <i class="fas fa-check me-2"></i>Issue Release Order
                                                    </button>
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

        <!-- Issued Release Orders -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Issued Release Orders</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($released_shipments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                                <h6>No Release Orders Issued Yet</h6>
                                <p class="text-muted">Release orders will appear here once issued.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Release Number</th>
                                            <th>Tracking</th>
                                            <th>Client</th>
                                            <th>Agent</th>
                                            <th>Manifest</th>
                                            <th>Tax Amount</th>
                                            <th>Payment Status</th>
                                            <th>Release Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($released_shipments as $shipment): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-warning fs-6">
                                                        <?php echo htmlspecialchars($shipment['release_number']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($shipment['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($shipment['agent_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($shipment['manifest_number']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong>$<?php echo number_format($shipment['tax_amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $payment_badge = $shipment['payment_status'] === 'completed' ? 'success' : ($shipment['payment_status'] === 'partial' ? 'warning' : 'danger');
                                                    ?>
                                                    <span class="badge bg-<?php echo $payment_badge; ?>">
                                                        <?php echo ucfirst($shipment['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i', strtotime($shipment['release_date'])); ?>
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
    <script>
        function generateNewRelease(button) {
            const input = button.parentElement.querySelector('input');
            const year = new Date().getFullYear();
            const month = String(new Date().getMonth() + 1).padStart(2, '0');
            const random = String(Math.floor(Math.random() * 9000) + 1000);
            input.value = `RO-${year}-${month}-${random}`;
        }
    </script>
</body>

</html>