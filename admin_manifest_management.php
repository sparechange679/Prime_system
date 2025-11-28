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

// Get shipments pending manifest numbers
try {
    $query = "SELECT s.*, c.company_name, c.contact_person, u.full_name as agent_name
              FROM shipments s 
              JOIN clients c ON s.client_id = c.client_id 
              JOIN users u ON s.agent_id = u.user_id 
              WHERE s.status IN ('under_clearance') 
              AND (s.manifest_number IS NULL OR s.manifest_number = '')
              ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading shipments: " . $e->getMessage();
    $pending_shipments = [];
}

// Get all shipments with manifest numbers
try {
    $all_query = "SELECT s.*, c.company_name, c.contact_person, u.full_name as agent_name
                  FROM shipments s 
                  JOIN clients c ON s.client_id = c.client_id 
                  JOIN users u ON s.agent_id = u.user_id 
                  WHERE s.manifest_number IS NOT NULL AND s.manifest_number != ''
                  ORDER BY s.manifest_number DESC";
    $stmt = $db->prepare($all_query);
    $stmt->execute();
    $manifested_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $manifested_shipments = [];
}

// Handle manifest number assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['assign_agent_manifest'])) {
        // Handle agent manifest assignment
        $agent_id = (int)$_POST['agent_id'];
        $manifest_number = trim($_POST['manifest_number']);
        $manifest_notes = trim($_POST['manifest_notes']);

        if (empty($manifest_number)) {
            $manifest_error = "Manifest number is required";
        } else {
            try {
                // Check if manifest number already exists
                $check_query = "SELECT COUNT(*) as count FROM manifests WHERE manifest_number = :manifest_number";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':manifest_number', $manifest_number);
                $check_stmt->execute();

                if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                    $manifest_error = "Manifest number already exists";
                } else {
                    // Insert new manifest for agent
                    $insert_query = "INSERT INTO manifests (agent_id, manifest_number, status, notes, created_at) 
                                   VALUES (:agent_id, :manifest_number, 'active', :notes, NOW())";

                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':agent_id', $agent_id);
                    $insert_stmt->bindParam(':manifest_number', $manifest_number);
                    $insert_stmt->bindParam(':notes', $manifest_notes);

                    if ($insert_stmt->execute()) {
                        // Log the activity
                        logActivity(
                            $user_id,
                            'assign_agent_manifest',
                            'manifests',
                            $db->lastInsertId(),
                            "Assigned manifest $manifest_number to agent ID: $agent_id"
                        );

                        $manifest_success = "Agent manifest assigned successfully!";
                        header("Location: admin_manifest_management.php");
                        exit();
                    } else {
                        $manifest_error = "Failed to assign agent manifest";
                    }
                }
            } catch (PDOException $e) {
                $manifest_error = "Database error: " . $e->getMessage();
            }
        }
    } else {
        // Handle shipment manifest assignment (existing code)
        $shipment_id = (int)$_POST['shipment_id'];
        $manifest_number = trim($_POST['manifest_number']);
        $manifest_notes = trim($_POST['manifest_notes']);

        if (empty($manifest_number)) {
            $manifest_error = "Manifest number is required";
        } else {
            try {
                // Check if manifest number already exists
                $check_query = "SELECT COUNT(*) as count FROM shipments WHERE manifest_number = :manifest_number AND shipment_id != :shipment_id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':manifest_number', $manifest_number);
                $check_stmt->bindParam(':shipment_id', $shipment_id);
                $check_stmt->execute();

                if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                    $manifest_error = "Manifest number already exists";
                } else {
                    // Update shipment with manifest number
                    $update_query = "UPDATE shipments 
                                   SET manifest_number = :manifest_number,
                                       status = 'manifest_issued',
                                       updated_at = NOW()
                                   WHERE shipment_id = :shipment_id";

                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':manifest_number', $manifest_number);
                    $update_stmt->bindParam(':shipment_id', $shipment_id);

                    if ($update_stmt->execute()) {
                        // Log the activity
                        $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                                      VALUES (:user_id, 'issue_manifest', 'shipments', :shipment_id, :details)";
                        $log_stmt = $db->prepare($log_query);
                        $log_details = "Manifest number issued: $manifest_number";
                        if ($manifest_notes) $log_details .= " - Notes: $manifest_notes";
                        $log_stmt->bindParam(':user_id', $user_id);
                        $log_stmt->bindParam(':shipment_id', $shipment_id);
                        $log_stmt->bindParam(':details', $log_details);
                        $log_stmt->execute();

                        $manifest_success = "Manifest number issued successfully!";
                        header("Location: admin_manifest_management.php");
                        exit();
                    } else {
                        $manifest_error = "Failed to issue manifest number";
                    }
                }
            } catch (PDOException $e) {
                $manifest_error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Generate next manifest number
function generateManifestNumber()
{
    $year = date('Y');
    $month = date('m');
    return "MRA-{$year}-{$month}-" . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifest Management - Prime Cargo Limited</title>
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
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-file-contract me-2"></i>
                            Manifest Management
                        </h2>
                        <p class="card-text">Issue and manage MRA manifest numbers for shipments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($manifest_success)): ?>
            <div class="alert alert-success"><?php echo $manifest_success; ?></div>
        <?php endif; ?>

        <?php if (isset($manifest_error)): ?>
            <div class="alert alert-danger"><?php echo $manifest_error; ?></div>
        <?php endif; ?>

        <!-- Agent Manifest Assignment -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Assign Manifest to Agent</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="agent_id" class="form-label">Select Agent <span class="text-danger">*</span></label>
                                <select name="agent_id" id="agent_id" class="form-select" required>
                                    <option value="">Choose an agent...</option>
                                    <?php
                                    // Get all active agents
                                    try {
                                        $agents_query = "SELECT user_id, full_name, email FROM users WHERE role = 'agent' AND status = 'active' ORDER BY full_name";
                                        $agents_stmt = $db->prepare($agents_query);
                                        $agents_stmt->execute();
                                        $agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($agents as $agent) {
                                            echo "<option value='{$agent['user_id']}'>{$agent['full_name']} ({$agent['email']})</option>";
                                        }
                                    } catch (PDOException $e) {
                                        echo "<option value=''>Error loading agents</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="manifest_number" class="form-label">Manifest Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="manifest_number" id="manifest_number" class="form-control"
                                        value="<?php echo generateManifestNumber(); ?>" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generateNewNumber(this)">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <div class="form-text">Format: MRA-YYYY-MM-XXXX</div>
                            </div>

                            <div class="col-12">
                                <label for="manifest_notes" class="form-label">Notes (Optional)</label>
                                <textarea name="manifest_notes" id="manifest_notes" class="form-control" rows="3"
                                    placeholder="Add any notes about this manifest assignment..."></textarea>
                            </div>

                            <div class="col-12">
                                <button type="submit" name="assign_agent_manifest" class="btn btn-primary">
                                    <i class="fas fa-check me-2"></i>Assign Manifest to Agent
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Manifest Assignments -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Manifest Assignments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_shipments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h6>All Shipments Have Manifest Numbers</h6>
                                <p class="text-muted">No shipments are waiting for manifest numbers.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_shipments as $shipment): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-ship me-2"></i>
                                            Shipment: <?php echo htmlspecialchars($shipment['tracking_number']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            Client: <?php echo htmlspecialchars($shipment['company_name']); ?> |
                                            Agent: <?php echo htmlspecialchars($shipment['agent_name']); ?>
                                        </small>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Shipment Details</h6>
                                                <p class="mb-1"><strong>Goods:</strong> <?php echo htmlspecialchars($shipment['goods_description']); ?></p>
                                                <p class="mb-1"><strong>Value:</strong> $<?php echo number_format($shipment['declared_value'], 2); ?></p>
                                                <p class="mb-1"><strong>Origin:</strong> <?php echo htmlspecialchars($shipment['origin_country']); ?></p>
                                                <p class="mb-1"><strong>Destination:</strong> <?php echo htmlspecialchars($shipment['destination_port']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Issue Manifest Number</h6>
                                                <form method="POST">
                                                    <input type="hidden" name="shipment_id" value="<?php echo $shipment['shipment_id']; ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Manifest Number <span class="text-danger">*</span></label>
                                                        <div class="input-group">
                                                            <input type="text" name="manifest_number" class="form-control"
                                                                value="<?php echo generateManifestNumber(); ?>" required>
                                                            <button type="button" class="btn btn-outline-secondary" onclick="generateNewNumber(this)">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        </div>
                                                        <div class="form-text">Format: MRA-YYYY-MM-XXXX</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Notes (Optional)</label>
                                                        <textarea name="manifest_notes" class="form-control" rows="2"
                                                            placeholder="Add any notes about this manifest..."></textarea>
                                                    </div>

                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-check me-2"></i>Issue Manifest
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

        <!-- Issued Manifests -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Issued Manifests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($manifested_shipments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                                <h6>No Manifests Issued Yet</h6>
                                <p class="text-muted">Manifest numbers will appear here once issued.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Manifest Number</th>
                                            <th>Tracking</th>
                                            <th>Client</th>
                                            <th>Agent</th>
                                            <th>Goods</th>
                                            <th>Status</th>
                                            <th>Date Issued</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($manifested_shipments as $shipment): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary fs-6">
                                                        <?php echo htmlspecialchars($shipment['manifest_number']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($shipment['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($shipment['agent_name']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($shipment['goods_description'], 0, 50)) . '...'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $shipment['status'] === 'manifest_issued' ? 'info' : 'success'; ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                                                    </span>
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

        <!-- Agent Manifests -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Agent Manifests</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get all agent manifests
                        try {
                            $agent_manifests_query = "SELECT m.*, u.full_name as agent_name, u.email as agent_email 
                                                     FROM manifests m 
                                                     JOIN users u ON m.agent_id = u.user_id 
                                                     ORDER BY m.created_at DESC";
                            $agent_manifests_stmt = $db->prepare($agent_manifests_query);
                            $agent_manifests_stmt->execute();
                            $agent_manifests = $agent_manifests_stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $agent_manifests = [];
                        }
                        ?>

                        <?php if (empty($agent_manifests)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                                <h6>No Agent Manifests Assigned</h6>
                                <p class="text-muted">Assign manifests to agents using the form above.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Manifest Number</th>
                                            <th>Agent</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                            <th>Date Assigned</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agent_manifests as $manifest): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-info fs-6">
                                                        <?php echo htmlspecialchars($manifest['manifest_number']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($manifest['agent_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($manifest['agent_email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $manifest['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($manifest['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($manifest['notes']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($manifest['notes']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i', strtotime($manifest['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary"
                                                        onclick="viewManifestDetails(<?php echo $manifest['manifest_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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
        function generateNewNumber(button) {
            const input = button.parentElement.querySelector('input');
            const year = new Date().getFullYear();
            const month = String(new Date().getMonth() + 1).padStart(2, '0');
            const random = String(Math.floor(Math.random() * 9000) + 1000);
            input.value = `MRA-${year}-${month}-${random}`;
        }
    </script>
</body>

</html>