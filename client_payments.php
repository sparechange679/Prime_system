<?php
require_once 'config.php';
require_once 'database.php';

// Clients only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'client') {
  header('Location: login.php');
  exit();
}

$user_id = (int)$_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Client';

$database = new Database();
$db = $database->getConnection();
if (!$db) {
  die('Database connection failed');
}

// Fetch client's pending and completed payments
$pending = [];
$completed = [];
try {
  $sqlBase = "SELECT p.payment_id, p.amount, p.currency, p.amount_mwk, p.payment_method, p.transaction_id,
                        p.status, p.created_at, p.payment_date,
                        s.shipment_id, s.tracking_number, s.goods_description,
                        c.company_name
                 FROM payments p
                 JOIN shipments s ON p.shipment_id = s.shipment_id
                 JOIN clients c ON s.client_id = c.client_id
                 WHERE c.user_id = :uid AND p.status = :st
                 ORDER BY COALESCE(p.payment_date, p.created_at) DESC";

  $stmt = $db->prepare($sqlBase);
  $st = 'pending';
  $stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
  $stmt->bindParam(':st', $st);
  $stmt->execute();
  $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt2 = $db->prepare($sqlBase);
  $st2 = 'completed';
  $stmt2->bindParam(':uid', $user_id, PDO::PARAM_INT);
  $stmt2->bindParam(':st', $st2);
  $stmt2->execute();
  $completed = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $pending = [];
  $completed = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Payments - Prime Cargo Limited</title>
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
        <a class="nav-link text-white" href="track_shipment.php">
          <i class="fas fa-arrow-left me-2"></i>Back
        </a>
      </div>
    </div>
  </nav>
  <div class="container mt-4">
    <div class="row mb-3">
      <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="mb-0"><i class="fas fa-wallet me-2"></i>My Payments</h2>
        <span class="text-muted">Welcome, <?php echo htmlspecialchars($full_name); ?></span>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6">
        <div class="card mb-4">
          <div class="card-header"><strong><i class="fas fa-clock me-2"></i>Pending Payments</strong></div>
          <div class="card-body">
            <?php if (empty($pending)): ?>
              <div class="text-center text-muted py-3">No pending payments</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Tracking</th>
                      <th>Amount</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pending as $p): ?>
                      <tr>
                        <td>
                          <div class="fw-bold"><?php echo htmlspecialchars($p['tracking_number']); ?></div>
                          <small class="text-muted"><?php echo htmlspecialchars(substr($p['goods_description'], 0, 40)); ?><?php if (strlen($p['goods_description']) > 40) echo '...'; ?></small>
                        </td>
                        <td>
                          <?php
                          $amt = (float)$p['amount'];
                          $cur = $p['currency'] ?: 'MWK';
                          echo htmlspecialchars($cur) . ' ' . number_format($amt, 2);
                          ?>
                        </td>
                        <td>
                          <a class="btn btn-success btn-sm" href="payment.php?shipment_id=<?php echo (int)$p['shipment_id']; ?>">
                            <i class="fas fa-credit-card me-1"></i>Pay Now
                          </a>
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
      <div class="col-lg-6">
        <div class="card mb-4">
          <div class="card-header"><strong><i class="fas fa-check-circle me-2"></i>Completed Payments</strong></div>
          <div class="card-body">
            <?php if (empty($completed)): ?>
              <div class="text-center text-muted py-3">No completed payments</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Tracking</th>
                      <th>Amount</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($completed as $p): ?>
                      <tr>
                        <td><span class="fw-bold"><?php echo htmlspecialchars($p['tracking_number']); ?></span></td>
                        <td>
                          <?php
                          $amt = (float)$p['amount'];
                          $cur = $p['currency'] ?: 'MWK';
                          echo htmlspecialchars($cur) . ' ' . number_format($amt, 2);
                          ?>
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
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>