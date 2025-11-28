<?php
require_once 'config.php';
require_once 'database.php';

// Restrict to agents
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$database = new Database();
$db = $database->getConnection();

$payments = [];
$totals = [
    'count' => 0,
    'sum_usd' => 0.0,
    'sum_mwk' => 0.0
];

try {
    // Fetch recent payments for this agent's shipments
    $query = "SELECT p.payment_id, p.amount, p.currency, p.amount_mwk, p.payment_method, p.transaction_id, p.status,
                     COALESCE(p.payment_date, p.created_at) AS paid_at,
                     s.tracking_number, s.shipment_id,
                     c.company_name
              FROM payments p
              JOIN shipments s ON p.shipment_id = s.shipment_id
              JOIN clients c ON s.client_id = c.client_id
              WHERE s.agent_id = :agent_id
              ORDER BY COALESCE(p.payment_date, p.created_at) DESC
              LIMIT 100";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':agent_id', $user_id);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totals
    $totalQuery = "SELECT COUNT(*) AS cnt,
                          SUM(CASE WHEN currency = 'USD' THEN amount ELSE 0 END) AS usd_sum,
                          SUM(amount_mwk) AS mwk_sum
                   FROM payments p
                   JOIN shipments s ON p.shipment_id = s.shipment_id
                   WHERE s.agent_id = :agent_id AND p.status = 'completed'";
    $tstmt = $db->prepare($totalQuery);
    $tstmt->bindParam(':agent_id', $user_id);
    $tstmt->execute();
    $row = $tstmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $totals['count'] = (int)$row['cnt'];
        $totals['sum_usd'] = (float)$row['usd_sum'];
        $totals['sum_mwk'] = (float)$row['mwk_sum'];
    }
} catch (PDOException $e) {
    $payments = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Payments - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-ship me-2"></i>Prime Cargo Limited
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="agent_dashboard.php">
                    <i class="fas fa-user-tie me-2"></i>Agent Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="card-title mb-1">
                                <i class="fas fa-credit-card me-2 text-success"></i>Your Payments
                            </h2>
                            <p class="card-text text-muted mb-0">Payments related to your assigned shipments</p>
                        </div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-receipt fa-2x text-secondary mb-2"></i>
                        <h3 class="card-title mb-0"><?php echo (int)$totals['count']; ?></h3>
                        <small class="text-muted">Total Payments (completed)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                        <h4 class="card-title mb-0">USD <?php echo number_format($totals['sum_usd'], 2); ?></h4>
                        <small class="text-muted">Completed (USD)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                        <h4 class="card-title mb-0">MWK <?php echo number_format($totals['sum_mwk'], 2); ?></h4>
                        <small class="text-muted">Completed (MWK)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Payments</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                        <div>No payments found.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tracking</th>
                                    <th>Client</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($p['tracking_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['company_name']); ?></td>
                                        <td>
                                            <?php
                                            $amt = (float)$p['amount'];
                                            $cur = $p['currency'] ?: 'USD';
                                            echo htmlspecialchars($cur) . ' ' . number_format($amt, 2);
                                            echo ' | MWK ' . number_format((float)$p['amount_mwk'], 2);
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($p['payment_method'] ?: '—'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $p['status'] === 'completed' ? 'success' : ($p['status'] === 'pending' ? 'warning' : ($p['status'] === 'failed' ? 'danger' : 'secondary')); ?>">
                                                <?php echo ucfirst($p['status']); ?>
                                            </span>
                                        </td>
                                        <td><small class="text-muted"><?php echo $p['paid_at'] ? date('M d, Y H:i', strtotime($p['paid_at'])) : '—'; ?></small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="track_shipment.php?shipment_id=<?php echo (int)$p['shipment_id']; ?>" class="btn btn-outline-primary" title="View Shipment">
                                                    <i class="fas fa-search"></i>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>