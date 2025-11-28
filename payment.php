<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get shipment ID from URL
$shipment_id = isset($_GET['shipment_id']) ? (int)$_GET['shipment_id'] : 0;

if (!$shipment_id) {
    header("Location: track_shipment.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get shipment details
try {
    $query = "SELECT s.*, c.company_name FROM shipments s 
              JOIN clients c ON s.client_id = c.client_id 
              WHERE s.shipment_id = :shipment_id AND c.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':shipment_id', $shipment_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        header("Location: track_shipment.php");
        exit();
    }

    $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading shipment: " . $e->getMessage();
}

// Load pending payment created by agent declaration (amount in MWK, status='pending')
$pending_payment = null;
try {
    $pp = $db->prepare("SELECT * FROM payments WHERE shipment_id = :sid AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $pp->bindParam(':sid', $shipment_id, PDO::PARAM_INT);
    $pp->execute();
    $pending_payment = $pp->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading payment: " . $e->getMessage();
}

// Handle payment confirmation using the pending payment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    if (empty($payment_method)) {
        $payment_error = "Please select payment method";
    } elseif (!$pending_payment) {
        $payment_error = "No pending payment found for this shipment.";
    } else {
        try {
            $db->beginTransaction();
            // Complete pending payment
            $upd = $db->prepare("UPDATE payments SET status = 'completed', payment_method = :pm, updated_at = NOW() WHERE payment_id = :pid");
            $upd->bindValue(':pm', $payment_method);
            $upd->bindValue(':pid', (int)$pending_payment['payment_id'], PDO::PARAM_INT);
            $upd->execute();

            // Update shipment status to completed
            $update_query = "UPDATE shipments SET status = 'completed', updated_at = NOW() WHERE shipment_id = :shipment_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':shipment_id', $shipment_id, PDO::PARAM_INT);
            $update_stmt->execute();

            $db->commit();
            $payment_success = "Payment completed successfully! Your shipment is ready for collection.";
        } catch (PDOException $e) {
            $db->rollBack();
            $payment_error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Prime Cargo Limited</title>
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
                <a class="nav-link text-white" href="track_shipment.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Tracking
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment for Shipment</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($payment_success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $payment_success; ?>
                            </div>
                            <a href="track_shipment.php" class="btn btn-primary">Back to Tracking</a>
                        <?php else: ?>
                            <!-- Shipment Info -->
                            <div class="alert alert-info">
                                <h6>Shipment Details:</h6>
                                <p class="mb-1"><strong>Tracking:</strong> <?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
                                <p class="mb-1"><strong>Goods:</strong> <?php echo htmlspecialchars($shipment['goods_description']); ?></p>
                                <?php if ($pending_payment): ?>
                                    <p class="mb-0"><strong>Amount Due:</strong> MWK <?php echo number_format((float)$pending_payment['amount'], 2); ?></p>
                                <?php else: ?>
                                    <p class="mb-0 text-danger"><strong>No pending amount found.</strong></p>
                                <?php endif; ?>
                            </div>

                            <!-- Payment Form -->
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method" class="form-select" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mobile_money">Mobile Money</option>
                                    </select>
                                </div>
                                <?php if ($pending_payment): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Amount (MWK)</label>
                                        <input type="text" class="form-control" value="<?php echo number_format((float)$pending_payment['amount'], 2); ?>" readonly>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($payment_error)): ?>
                                    <div class="alert alert-danger"><?php echo $payment_error; ?></div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>Complete Payment
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>