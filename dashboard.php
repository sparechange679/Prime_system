<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'database.php';
$database = new Database();
$db = $database->getConnection();

// Get user info
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Get statistics based on role
try {
    if ($role == 'admin') {
        // Admin sees all statistics
        $shipment_query = "SELECT COUNT(*) as total FROM shipments";
        $client_query = "SELECT COUNT(*) as total FROM clients";
        $payment_query = "SELECT COUNT(*) as total FROM payments";
    } elseif ($role == 'agent') {
        // Agent sees their own shipments
        $shipment_query = "SELECT COUNT(*) as total FROM shipments WHERE agent_id = :user_id";
        $client_query = "SELECT COUNT(DISTINCT client_id) as total FROM shipments WHERE agent_id = :user_id";
        $payment_query = "SELECT COUNT(*) as total FROM payments p JOIN shipments s ON p.shipment_id = s.shipment_id WHERE s.agent_id = :user_id";
    } elseif ($role == 'client') {
        // Client sees their own data
        $shipment_query = "SELECT COUNT(*) as total FROM shipments s JOIN clients c ON s.client_id = c.client_id WHERE c.user_id = :user_id";
        $client_query = "SELECT 1 as total"; // Client only sees themselves
        $payment_query = "SELECT COUNT(*) as total FROM payments p JOIN shipments s ON p.shipment_id = s.shipment_id JOIN clients c ON s.client_id = c.client_id WHERE c.user_id = :user_id";
    } else {
        // Keeper: show system-wide totals so new client submissions are visible immediately
        $shipment_query = "SELECT COUNT(*) as total FROM shipments";
        $client_query = "SELECT COUNT(DISTINCT client_id) as total FROM shipments";
        // Payments count system-wide for visibility
        $payment_query = "SELECT COUNT(*) as total FROM payments";
    }

    // Execute queries
    $stmt = $db->prepare($shipment_query);
    if ($role == 'agent' || $role == 'client') $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shipment_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $db->prepare($client_query);
    if ($role == 'agent' || $role == 'client') $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $client_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $db->prepare($payment_query);
    if ($role == 'agent' || $role == 'client') $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $payment_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Extra agent-specific metrics and lists
    if ($role === 'agent') {
        try {
            // Shipment status breakdown for this agent
            $status_counts_query = "SELECT status, COUNT(*) as total FROM shipments WHERE agent_id = :user_id GROUP BY status";
            $sc_stmt = $db->prepare($status_counts_query);
            $sc_stmt->bindParam(':user_id', $user_id);
            $sc_stmt->execute();
            $agent_status_counts = [
                'pending' => 0,
                'under_verification' => 0,
                'under_clearance' => 0,
                'clearance_approved' => 0,
                'manifest_issued' => 0,
                'release_issued' => 0,
                'completed' => 0,
                'cancelled' => 0
            ];
            foreach ($sc_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $agent_status_counts[$row['status']] = (int)$row['total'];
            }

            // Shipments requiring action (limited list)
            $attention_query = "SELECT s.shipment_id, s.tracking_number, s.goods_description, s.status, c.company_name,
                                       (SELECT COUNT(*) FROM shipment_documents d WHERE d.shipment_id = s.shipment_id) AS doc_count,
                                       s.updated_at
                                FROM shipments s
                                JOIN clients c ON s.client_id = c.client_id
                                WHERE s.agent_id = :user_id AND s.status IN ('pending','under_verification','under_clearance')
                                ORDER BY s.updated_at DESC
                                LIMIT 5";
            $att_stmt = $db->prepare($attention_query);
            $att_stmt->bindParam(':user_id', $user_id);
            $att_stmt->execute();
            $shipments_attention = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent completed payments for this agent's shipments
            $recent_payments_query = "SELECT p.payment_id, p.amount, p.currency, p.amount_mwk, p.payment_method, p.transaction_id, p.payment_date, p.status,
                                             s.tracking_number, c.company_name
                                      FROM payments p
                                      JOIN shipments s ON p.shipment_id = s.shipment_id
                                      JOIN clients c ON s.client_id = c.client_id
                                      WHERE s.agent_id = :user_id AND p.status = 'completed'
                                      ORDER BY COALESCE(p.payment_date, p.created_at) DESC
                                      LIMIT 5";
            $rp_stmt = $db->prepare($recent_payments_query);
            $rp_stmt->bindParam(':user_id', $user_id);
            $rp_stmt->execute();
            $recent_payments = $rp_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            $agent_status_counts = [
                'pending' => 0,
                'under_verification' => 0,
                'under_clearance' => 0,
                'clearance_approved' => 0,
                'manifest_issued' => 0,
                'release_issued' => 0,
                'completed' => 0,
                'cancelled' => 0
            ];
            $shipments_attention = [];
            $recent_payments = [];
        }
    }
} catch (PDOException $e) {
    $shipment_count = 0;
    $client_count = 0;
    $payment_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shipping-fast me-2"></i>
                Prime Cargo Limited
            </a>

            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="documents.php">
                    <i class="fas fa-file-alt me-2"></i>Documents
                </a>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($full_name); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-2"></i>Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                            Welcome, <?php echo htmlspecialchars($full_name); ?>!
                        </h2>
                        <p class="card-text">You are logged in as: <span class="badge bg-primary"><?php echo ucfirst($role); ?></span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <?php if ($role === 'agent'): ?>
                    <a href="agent_dashboard.php" class="text-decoration-none">
                        <div class="card text-center border-0 shadow-sm clickable-card">
                            <div class="card-body">
                                <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                                <h3 class="card-title"><?php echo $shipment_count; ?></h3>
                                <p class="card-text text-muted">Total Shipments</p>
                                <small class="text-primary"><i class="fas fa-mouse-pointer me-1"></i>Click to view your assigned shipments</small>
                            </div>
                        </div>
                    </a>
                <?php else: ?>
                    <?php if ($role === 'admin'): ?>
                        <a href="admin_shipment_management.php" class="text-decoration-none">
                            <div class="card text-center border-0 shadow-sm clickable-card">
                                <div class="card-body">
                                    <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                                    <h3 class="card-title"><?php echo $shipment_count; ?></h3>
                                    <p class="card-text text-muted">Total Shipments</p>
                                    <small class="text-primary"><i class="fas fa-mouse-pointer me-1"></i>Click to view shipments</small>
                                </div>
                            </div>
                        </a>
                    <?php elseif ($role === 'keeper'): ?>
                        <a href="keeper_shipments.php" class="text-decoration-none">
                            <div class="card text-center border-0 shadow-sm clickable-card">
                                <div class="card-body">
                                    <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                                    <h3 class="card-title"><?php echo $shipment_count; ?></h3>
                                    <p class="card-text text-muted">Total Shipments</p>
                                    <small class="text-primary"><i class="fas fa-mouse-pointer me-1"></i>Click to view shipments</small>
                                </div>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="card text-center border-0 shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                                <h3 class="card-title"><?php echo $shipment_count; ?></h3>
                                <p class="card-text text-muted">Total Shipments</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="col-md-4 mb-3">
                <?php if ($role === 'agent'): ?>
                    <a href="agent_clients.php" class="text-decoration-none">
                        <div class="card text-center border-0 shadow-sm clickable-card">
                            <div class="card-body">
                                <i class="fas fa-users fa-3x text-info mb-3"></i>
                                <h3 class="card-title"><?php echo $client_count; ?></h3>
                                <p class="card-text text-muted">Total Clients</p>
                                <small class="text-info"><i class="fas fa-mouse-pointer me-1"></i>Click to view your clients</small>
                            </div>
                        </div>
                    </a>
                <?php else: ?>
                    <?php if ($role === 'admin'): ?>
                        <a href="admin_user_management.php" class="text-decoration-none">
                            <div class="card text-center border-0 shadow-sm clickable-card">
                                <div class="card-body">
                                    <i class="fas fa-users fa-3x text-info mb-3"></i>
                                    <h3 class="card-title"><?php echo $client_count; ?></h3>
                                    <p class="card-text text-muted">Total Clients</p>
                                    <small class="text-info"><i class="fas fa-mouse-pointer me-1"></i>Click to view clients</small>
                                </div>
                            </div>
                        </a>
                    <?php elseif ($role === 'keeper'): ?>
                        <a href="keeper_shipments.php" class="text-decoration-none">
                            <div class="card text-center border-0 shadow-sm clickable-card">
                                <div class="card-body">
                                    <i class="fas fa-users fa-3x text-info mb-3"></i>
                                    <h3 class="card-title"><?php echo $client_count; ?></h3>
                                    <p class="card-text text-muted">Total Clients</p>
                                    <small class="text-info"><i class="fas fa-mouse-pointer me-1"></i>Click to view related shipments</small>
                                </div>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="card text-center border-0 shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-users fa-3x text-info mb-3"></i>
                                <h3 class="card-title"><?php echo $client_count; ?></h3>
                                <p class="card-text text-muted">Total Clients</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="col-md-4 mb-3">
                <?php if ($role === 'agent'): ?>
                    <a href="agent_payments.php" class="text-decoration-none">
                        <div class="card text-center border-0 shadow-sm clickable-card">
                            <div class="card-body">
                                <i class="fas fa-credit-card fa-3x text-success mb-3"></i>
                                <h3 class="card-title"><?php echo $payment_count; ?></h3>
                                <p class="card-text text-muted">Total Payments</p>
                                <small class="text-success"><i class="fas fa-mouse-pointer me-1"></i>Click to view your payments</small>
                            </div>
                        </div>
                    </a>
                <?php elseif ($role === 'admin'): ?>
                    <a href="admin_payments.php" class="text-decoration-none">
                        <div class="card text-center border-0 shadow-sm clickable-card">
                            <div class="card-body">
                                <i class="fas fa-credit-card fa-3x text-success mb-3"></i>
                                <h3 class="card-title"><?php echo $payment_count; ?></h3>
                                <p class="card-text text-muted">Total Payments</p>
                                <small class="text-success"><i class="fas fa-mouse-pointer me-1"></i>Click to view payments</small>
                            </div>
                        </div>
                    </a>
                <?php elseif ($role === 'keeper'): ?>
                    <a href="keeper_payments.php" class="text-decoration-none">
                        <div class="card text-center border-0 shadow-sm clickable-card">
                            <div class="card-body">
                                <i class="fas fa-credit-card fa-3x text-success mb-3"></i>
                                <h3 class="card-title"><?php echo $payment_count; ?></h3>
                                <p class="card-text text-muted">Total Payments</p>
                                <small class="text-success"><i class="fas fa-mouse-pointer me-1"></i>Click to view payments</small>
                            </div>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-credit-card fa-3x text-success mb-3"></i>
                            <h3 class="card-title"><?php echo $payment_count; ?></h3>
                            <p class="card-text text-muted">Total Payments</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($role == 'admin'): ?>
                                <div class="col-md-3 mb-3">
                                    <a href="admin_shipment_management.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-ship me-2"></i>Manage Shipments
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($role == 'agent'): ?>
                                <div class="col-md-3 mb-3">
                                    <a href="agent_dashboard.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-user-tie me-2"></i>Agent Dashboard
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($role == 'client'): ?>
                                <div class="col-md-3 mb-3">
                                    <a href="new_shipment.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-plus me-2"></i>New Shipment
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="track_shipment.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-search me-2"></i>Track Shipments
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="client_payments.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-wallet me-2"></i>My Payments
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($role == 'keeper'): ?>
                                <div class="col-md-3 mb-3">
                                    <a href="verify_document.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-check-circle me-2"></i>Verify Documents
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-3 mb-3">
                                <a href="documents.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-file-alt me-2"></i>View Documents
                                </a>
                            </div>

                            <div class="col-md-3 mb-3">
                                <a href="document_guide.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-headset me-2"></i>Get Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($role == 'agent'): ?>
            <!-- Agent Status Breakdown -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Your Shipment Status Overview</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Safe defaults if not set
                            $agent_status_counts = $agent_status_counts ?? [
                                'pending' => 0,
                                'under_verification' => 0,
                                'under_clearance' => 0,
                                'clearance_approved' => 0,
                                'manifest_issued' => 0,
                                'release_issued' => 0,
                                'completed' => 0,
                                'cancelled' => 0
                            ];
                            ?>
                            <div class="row text-center">
                                <div class="col-6 col-md-3 mb-3">
                                    <a class="text-decoration-none" href="agent_shipments.php?status=pending">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <span class="badge bg-warning mb-2">Pending</span>
                                                <h4 class="mb-0"><?php echo (int)$agent_status_counts['pending']; ?></h4>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <a class="text-decoration-none" href="agent_shipments.php?status=under_verification">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <span class="badge bg-primary mb-2">Under Verification</span>
                                                <h4 class="mb-0"><?php echo (int)$agent_status_counts['under_verification']; ?></h4>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <a class="text-decoration-none" href="agent_shipments.php?status=under_clearance">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <span class="badge bg-info mb-2">Under Clearance</span>
                                                <h4 class="mb-0"><?php echo (int)$agent_status_counts['under_clearance']; ?></h4>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <a class="text-decoration-none" href="agent_shipments.php?status=approved">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <span class="badge bg-success mb-2">Approved</span>
                                                <h4 class="mb-0"><?php echo (int)$agent_status_counts['clearance_approved']; ?></h4>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <a class="text-decoration-none" href="agent_shipments.php?status=manifest_issued">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <span class="badge bg-info mb-2">Manifest Issued</span>
                                                <h4 class="mb-0"><?php echo (int)$agent_status_counts['manifest_issued']; ?></h4>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <a class="text-decoration-none" href="agent_shipments.php?status=release_issued">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <span class="badge bg-success mb-2">Release Issued</span>
                                                <h4 class="mb-0"><?php echo (int)$agent_status_counts['release_issued']; ?></h4>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <a class="text-decoration-none" href="agent_shipments.php?status=completed">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <span class="badge bg-secondary mb-2">Completed</span>
                                                <h4 class="mb-0"><?php echo (int)$agent_status_counts['completed']; ?></h4>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3 mb-3">
                                    <a class="text-decoration-none" href="agent_shipments.php?status=cancelled">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <span class="badge bg-dark mb-2">Cancelled</span>
                                                <h4 class="mb-0"><?php echo (int)$agent_status_counts['cancelled']; ?></h4>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agent Work Queue and Recent Payments -->
            <div class="row mb-4">
                <div class="col-lg-7 mb-4 mb-lg-0">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Shipments Requiring Action</h5>
                        </div>
                        <div class="card-body">
                            <?php $shipments_attention = $shipments_attention ?? []; ?>
                            <?php if (empty($shipments_attention)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <div>Nothing requires your attention right now.</div>
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
                                                <th>Docs</th>
                                                <th>Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($shipments_attention as $row): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($row['tracking_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($row['goods_description'], 0, 40)); ?><?php if (strlen($row['goods_description']) > 40) echo '...'; ?></td>
                                                    <td>
                                                        <?php
                                                        $status_class = match ($row['status']) {
                                                            'pending' => 'warning',
                                                            'under_verification' => 'primary',
                                                            'under_clearance' => 'info',
                                                            'clearance_approved' => 'success',
                                                            default => 'secondary'
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_class; ?>"><?php echo ucwords(str_replace('_', ' ', $row['status'])); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ((int)$row['doc_count'] >= 3) ? 'success' : 'warning'; ?>"><?php echo (int)$row['doc_count']; ?></span>
                                                    </td>
                                                    <td><small class="text-muted"><?php echo date('M d, Y H:i', strtotime($row['updated_at'])); ?></small></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="track_shipment.php?shipment_id=<?php echo (int)$row['shipment_id']; ?>" class="btn btn-outline-primary" title="Track"><i class="fas fa-search"></i></a>
                                                            <a href="upload_document.php?shipment_id=<?php echo (int)$row['shipment_id']; ?>" class="btn btn-outline-success" title="Upload Docs"><i class="fas fa-upload"></i></a>
                                                            <a href="agent_clearance.php?shipment_id=<?php echo (int)$row['shipment_id']; ?>" class="btn btn-outline-warning" title="Process Clearance"><i class="fas fa-shield-check"></i></a>
                                                            <a href="agent_declaration.php?shipment_id=<?php echo (int)$row['shipment_id']; ?>" class="btn btn-outline-info" title="Declaration"><i class="fas fa-file-invoice-dollar"></i></a>
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
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Recent Payments</h5>
                        </div>
                        <div class="card-body">
                            <?php $recent_payments = $recent_payments ?? []; ?>
                            <?php if (empty($recent_payments)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                                    <div>No recent payments found.</div>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tracking</th>
                                                <th>Client</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_payments as $p): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($p['tracking_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($p['company_name']); ?></td>
                                                    <td>
                                                        <?php
                                                        $amt = (float)$p['amount'];
                                                        $cur = $p['currency'] ?: 'USD';
                                                        echo htmlspecialchars($cur) . ' ' . number_format($amt, 2);
                                                        ?>
                                                    </td>
                                                    <td><small class="text-muted"><?php echo $p['payment_date'] ? date('M d, Y H:i', strtotime($p['payment_date'])) : '-'; ?></small></td>
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
        <?php endif; ?>

        <!-- Client Shipments Overview -->
        <?php if ($role == 'client'): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-ship me-2"></i>Recent Shipments</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get client's recent shipments
                            try {
                                $shipments_query = "SELECT s.*, 
                                                     u.full_name as agent_name,
                                                     k.full_name as keeper_name
                                              FROM shipments s 
                                              JOIN clients c ON s.client_id = c.client_id 
                                              LEFT JOIN users u ON s.agent_id = u.user_id 
                                              LEFT JOIN users k ON s.keeper_id = k.user_id 
                                              WHERE c.user_id = :user_id 
                                              ORDER BY s.created_at DESC 
                                              LIMIT 5";
                                $stmt = $db->prepare($shipments_query);
                                $stmt->bindParam(':user_id', $user_id);
                                $stmt->execute();
                                $recent_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                $recent_shipments = [];
                            }
                            ?>

                            <?php if (empty($recent_shipments)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-ship fa-3x text-muted mb-3"></i>
                                    <h6>No Shipments Yet</h6>
                                    <p class="text-muted">Create your first shipment to get started.</p>
                                    <a href="new_shipment.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create Shipment
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tracking #</th>
                                                <th>Goods Description</th>
                                                <th>Status</th>
                                                <th>Agent</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_shipments as $shipment): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars(substr($shipment['goods_description'], 0, 50)); ?>
                                                        <?php if (strlen($shipment['goods_description']) > 50): ?>...<?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = match ($shipment['status']) {
                                                            'pending' => 'warning',
                                                            'under_verification' => 'primary',
                                                            'verified' => 'success',
                                                            'under_clearance' => 'info',
                                                            'manifest_issued' => 'info',
                                                            'clearance_approved' => 'success',
                                                            'release_issued' => 'success',
                                                            'completed' => 'secondary',
                                                            default => 'secondary'
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($shipment['agent_name']): ?>
                                                            <?php echo htmlspecialchars($shipment['agent_name']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, Y', strtotime($shipment['created_at'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="track_shipment.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                                                class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-search me-1"></i>Track
                                                            </a>
                                                            <a href="upload_document.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                                                class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-upload me-1"></i>Documents
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="track_shipment.php" class="btn btn-outline-primary">
                                        <i class="fas fa-list me-2"></i>View All Shipments
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>

</html>