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

// Get agent's pending shipments
try {
    $query = "SELECT s.*, c.company_name, c.contact_person, c.tax_number
              FROM shipments s 
              JOIN clients c ON s.client_id = c.client_id 
              WHERE s.agent_id = :user_id AND s.status IN ('pending')
              ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading shipments: " . $e->getMessage();
    $shipments = [];
}

// HS Tariff codes with tax rates
$tariff_codes = [
    '8471.30.00' => ['description' => 'Portable digital automatic data processing machines', 'rate' => 0.15],
    '5208.52.00' => ['description' => 'Woven fabrics of cotton', 'rate' => 0.25],
    '8432.80.00' => ['description' => 'Agricultural machinery', 'rate' => 0.10],
    '8517.13.00' => ['description' => 'Smartphones', 'rate' => 0.20],
    '8528.72.00' => ['description' => 'Color television receivers', 'rate' => 0.25],
    '8708.99.00' => ['description' => 'Motor vehicle parts', 'rate' => 0.15]
];

// Currency exchange rates to Malawi Kwacha (MWK)
$exchange_rates = [
    'USD' => 1700,    // 1 USD = 1,700 MWK
    'EUR' => 1850,    // 1 EUR = 1,850 MWK
    'GBP' => 2150,    // 1 GBP = 2,150 MWK
    'CNY' => 235,     // 1 CNY = 235 MWK
    'INR' => 20,      // 1 INR = 20 MWK
    'ZAR' => 90,      // 1 ZAR = 90 MWK
    'KES' => 12,      // 1 KES = 12 MWK
    'NGN' => 1.2,     // 1 NGN = 1.2 MWK
    'GHS' => 140,     // 1 GHS = 140 MWK
    'UGX' => 0.45     // 1 UGX = 0.45 MWK
];

// Handle declaration submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipment_id = (int)$_POST['shipment_id'];
    $tariff_code = trim($_POST['tariff_code']);
    $currency = trim($_POST['currency']);
    $declared_value = (float)$_POST['declared_value'];

    if (empty($tariff_code) || empty($currency) || empty($declared_value)) {
        $declaration_error = "Tariff code, currency, and declared value are required";
    } elseif (!isset($exchange_rates[$currency])) {
        $declaration_error = "Invalid currency selected";
    } else {
        try {
            // Calculate taxes in original currency
            $tariff_rate = $tariff_codes[$tariff_code]['rate'] ?? 0.20;
            $import_duty = $declared_value * $tariff_rate;
            $vat_amount = ($declared_value + $import_duty) * 0.165;
            $total_tax = $import_duty + $vat_amount;

            // Convert to MWK for storage
            $exchange_rate = $exchange_rates[$currency];
            $total_tax_mwk = $total_tax * $exchange_rate;

            // Update shipment
            $update_query = "UPDATE shipments 
                           SET tariff_number = :tariff_code,
                               tax_amount = :tax_amount,
                               status = 'under_clearance',
                               updated_at = NOW()
                           WHERE shipment_id = :shipment_id";

            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':tariff_code', $tariff_code);
            $update_stmt->bindParam(':tax_amount', $total_tax_mwk);
            $update_stmt->bindParam(':shipment_id', $shipment_id);

            if ($update_stmt->execute()) {
                $declaration_success = "Declaration submitted! Tax: " . $currency . " " . number_format($total_tax, 2) . " / MWK " . number_format($total_tax_mwk, 2);
                header("Location: agent_clearance.php");
                exit();
            }
        } catch (PDOException $e) {
            $declaration_error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Processing - Prime Cargo Limited</title>
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
                            <i class="fas fa-shield-check me-2 text-primary"></i>
                            MRA Customs Declaration Processing
                        </h2>
                        <p class="card-text">Submit detailed declarations and process clearance for shipments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($declaration_success)): ?>
            <div class="alert alert-success"><?php echo $declaration_success; ?></div>
        <?php endif; ?>

        <?php if (isset($declaration_error)): ?>
            <div class="alert alert-danger"><?php echo $declaration_error; ?></div>
        <?php endif; ?>

        <!-- Shipments List -->
        <?php if (empty($shipments)): ?>
            <div class="card text-center">
                <div class="card-body py-5">
                    <i class="fas fa-ship fa-4x text-muted mb-3"></i>
                    <h5>No Pending Shipments</h5>
                    <p>All shipments are either cleared or not ready for declaration.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($shipments as $shipment): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-contract me-2"></i>
                            Declaration Form - Shipment: <?php echo htmlspecialchars($shipment['tracking_number']); ?>
                        </h5>
                        <small class="text-muted">
                            Client: <?php echo htmlspecialchars($shipment['company_name']); ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="shipment_id" value="<?php echo $shipment['shipment_id']; ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">HS Tariff Code <span class="text-danger">*</span></label>
                                    <select name="tariff_code" class="form-select" required>
                                        <option value="">Select Tariff Code</option>
                                        <?php foreach ($tariff_codes as $code => $info): ?>
                                            <option value="<?php echo $code; ?>">
                                                <?php echo $code; ?> - <?php echo $info['description']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Currency <span class="text-danger">*</span></label>
                                    <select name="currency" class="form-select" required>
                                        <option value="">Select Currency</option>
                                        <?php foreach ($exchange_rates as $code => $rate): ?>
                                            <option value="<?php echo $code; ?>">
                                                <?php echo $code; ?> (<?php echo $code === 'USD' ? '$' : ($code === 'EUR' ? '€' : ($code === 'GBP' ? '£' : $code)); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Declared Value <span class="text-danger">*</span></label>
                                    <input type="number" name="declared_value" class="form-control"
                                        step="0.01" min="0" value="<?php echo $shipment['declared_value']; ?>" required>
                                    <div class="form-text">Enter value in the selected currency</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Goods Description</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($shipment['goods_description']); ?>" readonly>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Client</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($shipment['company_name']); ?>" readonly>
                                    </div>
                                </div>

                                <div class="text-center mt-3">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Declaration to MRA
                                    </button>
                                </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>