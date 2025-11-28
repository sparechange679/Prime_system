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

// Get agent's shipments with keeper info
try {
    $query = "SELECT s.*, c.company_name, c.contact_person, k.full_name as keeper_name, k.user_id as keeper_id
              FROM shipments s 
              JOIN clients c ON s.client_id = c.client_id 
              LEFT JOIN users k ON s.keeper_id = k.user_id 
              WHERE s.agent_id = :user_id 
              ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading shipments: " . $e->getMessage();
    $shipments = [];
}

// Get available keepers
try {
    $keepers_query = "SELECT user_id, full_name FROM users WHERE role = 'keeper' ORDER BY full_name";
    $keepers_stmt = $db->prepare($keepers_query);
    $keepers_stmt->execute();
    $keepers = $keepers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $keepers = [];
}

// Handle keeper assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_keeper') {
        $shipment_id = (int)$_POST['shipment_id'];
        $keeper_id = (int)$_POST['keeper_id'];

        try {
            $update_query = "UPDATE shipments SET keeper_id = :keeper_id, updated_at = NOW() WHERE shipment_id = :shipment_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':keeper_id', $keeper_id);
            $update_stmt->bindParam(':shipment_id', $shipment_id);

            if ($update_stmt->execute()) {
                $keeper_success = "Keeper assigned successfully!";
                header("Location: agent_keeper_communication.php");
                exit();
            }
        } catch (PDOException $e) {
            $keeper_error = "Failed to assign keeper";
        }
    }
}

// Get verification reports from keepers
try {
    $verification_query = "SELECT v.*, s.tracking_number, s.goods_description, k.full_name as keeper_name
                          FROM verification v 
                          JOIN shipments s ON v.shipment_id = s.shipment_id 
                          JOIN users k ON v.keeper_id = k.user_id 
                          WHERE s.agent_id = :user_id 
                          ORDER BY v.verification_date DESC";
    $verification_stmt = $db->prepare($verification_query);
    $verification_stmt->bindParam(':user_id', $user_id);
    $verification_stmt->execute();
    $verifications = $verification_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $verifications = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keeper Communication - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="agent_dashboard.php">
                <i class="fas fa-ship me-2"></i>Prime Cargo Limited
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="agent_dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Agent Dashboard
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
                            <i class="fas fa-user-shield me-2 text-primary"></i>
                            Keeper Communication
                        </h2>
                        <p class="card-text">Assign keepers and receive verification reports</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($keeper_success)): ?>
            <div class="alert alert-success"><?php echo $keeper_success; ?></div>
        <?php endif; ?>

        <?php if (isset($keeper_error)): ?>
            <div class="alert alert-danger"><?php echo $keeper_error; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Keeper Assignment Section -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Assign Keeper to Shipment</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="assign_keeper">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Select Shipment <span class="text-danger">*</span></label>
                                    <select name="shipment_id" class="form-select" required>
                                        <option value="">Choose Shipment</option>
                                        <?php foreach ($shipments as $shipment): ?>
                                            <option value="<?php echo $shipment['shipment_id']; ?>">
                                                <?php echo $shipment['tracking_number']; ?> - <?php echo htmlspecialchars($shipment['company_name']); ?>
                                                <?php if ($shipment['keeper_id']): ?>
                                                    (Current: <?php echo htmlspecialchars($shipment['keeper_name']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Select Keeper <span class="text-danger">*</span></label>
                                    <select name="keeper_id" class="form-select" required>
                                        <option value="">Choose Keeper</option>
                                        <?php foreach ($keepers as $keeper): ?>
                                            <option value="<?php echo $keeper['user_id']; ?>">
                                                <?php echo htmlspecialchars($keeper['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Assign Keeper
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Shipment Status with Keepers -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Shipment Keeper Status</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($shipments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-ship fa-3x text-muted mb-3"></i>
                                <h6>No Shipments Found</h6>
                                <p class="text-muted">No shipments have been assigned to you yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($shipments as $shipment): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6 class="mb-1">
                                                <i class="fas fa-ship me-2"></i>
                                                <?php echo htmlspecialchars($shipment['tracking_number']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted">
                                                Client: <?php echo htmlspecialchars($shipment['company_name']); ?> |
                                                Goods: <?php echo htmlspecialchars(substr($shipment['goods_description'], 0, 50)) . '...'; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <?php if ($shipment['keeper_id']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-user-shield me-1"></i>
                                                    <?php echo htmlspecialchars($shipment['keeper_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    No Keeper Assigned
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Information Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Keeper Process</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Keeper Workflow:</h6>
                            <ol class="mb-0">
                                <li>Agent assigns keeper to shipment</li>
                                <li>Keeper receives document copies</li>
                                <li>Keeper verifies goods on arrival</li>
                                <li>Keeper submits verification report</li>
                                <li>Agent reviews verification status</li>
                            </ol>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>Keepers must be assigned before verification</li>
                                <li>Documents are automatically shared with keepers</li>
                                <li>Verification reports appear below</li>
                                <li>Status updates are automatic</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Reports from Keepers -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Verification Reports from Keepers</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($verifications)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                                <h6>No Verification Reports</h6>
                                <p class="text-muted">No verification reports have been submitted by keepers yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($verifications as $verification): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="mb-1">
                                                <i class="fas fa-clipboard-check me-2"></i>
                                                Verification Report - <?php echo htmlspecialchars($verification['tracking_number']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted">
                                                Keeper: <?php echo htmlspecialchars($verification['keeper_name']); ?> |
                                                Goods: <?php echo htmlspecialchars(substr($verification['goods_description'], 0, 50)) . '...'; ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Status:</strong>
                                                <span class="badge bg-<?php echo $verification['status'] === 'verified' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($verification['status']); ?>
                                                </span>
                                            </p>
                                            <?php if ($verification['verification_notes']): ?>
                                                <p class="mb-1"><strong>Notes:</strong> <?php echo htmlspecialchars($verification['verification_notes']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <small class="text-muted">
                                                <?php echo date('M d, Y H:i', strtotime($verification['verification_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>