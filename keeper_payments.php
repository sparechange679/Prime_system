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

// Optional filters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$query = "SELECT p.*, s.tracking_number, c.company_name
          FROM payments p
          JOIN shipments s ON p.shipment_id = s.shipment_id
          JOIN clients c ON s.client_id = c.client_id";
$params = [];
if ($status !== '') {
    $query .= " WHERE p.status = :status";
    $params[':status'] = $status;
}
$query .= " ORDER BY COALESCE(p.payment_date, p.created_at) DESC";

try {
    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payments = [];
    $error = 'Error loading payments: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Prime Cargo Limited</title>
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
            <h3 class="mb-0"><i class="fas fa-credit-card me-2"></i>All Payments</h3>
            <div class="d-flex gap-2">
                <a href="keeper_payments.php" class="btn btn-outline-secondary btn-sm <?php echo $status === '' ? 'active' : ''; ?>">All</a>
                <a href="keeper_payments.php?status=completed" class="btn btn-outline-success btn-sm <?php echo $status === 'completed' ? 'active' : ''; ?>">Completed</a>
                <a href="keeper_payments.php?status=pending" class="btn btn-outline-warning btn-sm <?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="keeper_payments.php?status=failed" class="btn btn-outline-danger btn-sm <?php echo $status === 'failed' ? 'active' : ''; ?>">Failed</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
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
                                    <th>Tracking #</th>
                                    <th>Client</th>
                                    <th>Method</th>
                                    <th>Transaction</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($p['tracking_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($p['payment_method'] ?? '-'); ?></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($p['transaction_id'] ?? '-'); ?></small></td>
                                        <td>
                                            <?php
                                            $amt = (float)$p['amount'];
                                            $cur = $p['currency'] ?: 'USD';
                                            echo htmlspecialchars($cur) . ' ' . number_format($amt, 2);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $cls = match ($p['status']) {
                                                'completed' => 'success',
                                                'pending' => 'warning',
                                                'failed' => 'danger',
                                                'refunded' => 'secondary',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $cls; ?>"><?php echo ucfirst($p['status']); ?></span>
                                        </td>
                                        <td><small class="text-muted"><?php echo $p['payment_date'] ? date('M d, Y H:i', strtotime($p['payment_date'])) : date('M d, Y H:i', strtotime($p['created_at'])); ?></small></td>
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