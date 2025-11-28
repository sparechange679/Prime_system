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

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get client information
try {
    $query = "SELECT c.* FROM clients c WHERE c.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading client information: " . $e->getMessage();
}

// Handle shipment creation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $goods_description = trim($_POST['goods_description']);
    $goods_weight = !empty($_POST['goods_weight']) ? (float)$_POST['goods_weight'] : null;
    $origin_country = trim($_POST['origin_country']);
    $destination_port = trim($_POST['destination_port']);
    $arrival_date = $_POST['arrival_date'];
    $expected_clearance_date = $_POST['expected_clearance_date'];
    $notes = trim($_POST['notes']);

    // Validate input
    if (
        empty($goods_description) ||
        empty($origin_country) || empty($destination_port) || empty($arrival_date)
    ) {
        $creation_error = "All required fields must be filled";
    } else {
        try {
            // Generate unique tracking number
            $tracking_number = 'PC' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Create shipment record
            $query = "INSERT INTO shipments (tracking_number, client_id, status, goods_description, 
                     declared_value, origin_country, destination_port, arrival_date, 
                     expected_clearance_date, notes, created_at) 
                     VALUES (:tracking_number, :client_id, 'pending', :goods_description, 
                     0.00, :origin_country, :destination_port, 
                     :arrival_date, :expected_clearance_date, :notes, NOW())";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':tracking_number', $tracking_number);
            $stmt->bindParam(':client_id', $client['client_id']);
            $stmt->bindParam(':goods_description', $goods_description);
            $stmt->bindParam(':origin_country', $origin_country);
            $stmt->bindParam(':destination_port', $destination_port);
            $stmt->bindParam(':arrival_date', $arrival_date);
            $stmt->bindParam(':expected_clearance_date', $expected_clearance_date);
            $stmt->bindParam(':notes', $notes);

            if ($stmt->execute()) {
                $shipment_id = $db->lastInsertId();

                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                              VALUES (:user_id, 'create_shipment', 'shipments', :shipment_id, :details)";
                $log_stmt = $db->prepare($log_query);
                $log_details = "Created new shipment: $tracking_number - $goods_description";
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':shipment_id', $shipment_id);
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();

                // Notify all admins and keepers about the new shipment
                try {
                    // Fetch recipients (admins and keepers)
                    $recipients_stmt = $db->prepare("SELECT user_id, role, full_name FROM users WHERE role IN ('admin','keeper') AND status = 'active'");
                    $recipients_stmt->execute();
                    $recipients = $recipients_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($recipients) {
                        $subject = 'New Shipment Submitted — ' . $tracking_number;
                        $content = 'A new shipment has been submitted by client ' . ($client['company_name'] ?? '') . " (Tracking: $tracking_number).";
                        foreach ($recipients as $r) {
                            // Insert message row
                            $msg = $db->prepare("INSERT INTO messages (sender_id, recipient_id, shipment_id, subject, content, message_type, created_at) 
                                                 VALUES (:sender_id, :recipient_id, :shipment_id, :subject, :content, 'update', NOW())");
                            $msg->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
                            $msg->bindParam(':recipient_id', $r['user_id'], PDO::PARAM_INT);
                            $msg->bindParam(':shipment_id', $shipment_id, PDO::PARAM_INT);
                            $msg->bindParam(':subject', $subject);
                            $msg->bindParam(':content', $content);
                            $msg->execute();

                            // Optional: create a notification entry
                            if (class_exists('PDO')) {
                                $notif = $db->prepare("INSERT INTO notifications (user_id, title, message, type, related_table, related_id, created_at) 
                                                       VALUES (:uid, :title, :message, 'info', 'shipments', :sid, NOW())");
                                $title = 'New Shipment: ' . $tracking_number;
                                $notif->bindParam(':uid', $r['user_id'], PDO::PARAM_INT);
                                $notif->bindParam(':title', $title);
                                $notif->bindParam(':message', $content);
                                $notif->bindParam(':sid', $shipment_id, PDO::PARAM_INT);
                                $notif->execute();
                            }
                        }
                    }
                } catch (PDOException $eNotify) {
                    // Non-blocking: if notifications fail, still proceed
                }

                $creation_success = "Shipment created successfully! Tracking Number: <strong>$tracking_number</strong>";

                // Redirect to documents page after 3 seconds
                header("refresh:3;url=upload_document.php?shipment_id=$shipment_id");
            } else {
                $creation_error = "Failed to create shipment. Please try again.";
            }
        } catch (PDOException $e) {
            $creation_error = "Database error: " . $e->getMessage();
        }
    }
}

// Common countries for import/export
$countries = [
    'China',
    'India',
    'South Africa',
    'Zambia',
    'Tanzania',
    'Mozambique',
    'Zimbabwe',
    'Botswana',
    'Kenya',
    'Uganda',
    'Ethiopia',
    'Nigeria',
    'United States',
    'United Kingdom',
    'Germany',
    'France',
    'Italy',
    'Japan',
    'South Korea',
    'Australia',
    'Brazil',
    'Canada'
];


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Shipment - Prime Cargo Limited</title>
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="documents.php">
                            <i class="fas fa-file-alt me-1"></i>Documents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="new_shipment.php">
                            <i class="fas fa-plus me-1"></i>New Shipment
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
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
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-plus-circle me-2 text-primary"></i>
                            Create New Shipment
                        </h2>
                        <p class="card-text">Submit a new cargo shipment for clearance at Chileka Airport</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($creation_success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $creation_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($creation_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $creation_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Shipment Creation Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-ship me-2"></i>Shipment Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="shipmentForm">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="goods_description" class="form-label">Goods Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="goods_description" name="goods_description" rows="3"
                                        placeholder="Detailed description of the goods being imported/exported" required><?php echo isset($_POST['goods_description']) ? htmlspecialchars($_POST['goods_description']) : ''; ?></textarea>
                                    <div class="form-text">Provide a detailed description including materials, purpose, and specifications</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="goods_weight" class="form-label">Goods Weight (kg)</label>
                                    <input type="number" class="form-control" id="goods_weight" name="goods_weight"
                                        step="0.01" min="0" placeholder="0.00">
                                    <div class="form-text">Approximate weight of the goods (optional)</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="origin_country" class="form-label">Origin Country <span class="text-danger">*</span></label>
                                    <select class="form-select" id="origin_country" name="origin_country" required>
                                        <option value="">Select Origin Country</option>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo $country; ?>" <?php echo (isset($_POST['origin_country']) && $_POST['origin_country'] == $country) ? 'selected' : ''; ?>>
                                                <?php echo $country; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="destination_port" class="form-label">Destination Port <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="destination_port" name="destination_port"
                                        value="<?php echo isset($_POST['destination_port']) ? htmlspecialchars($_POST['destination_port']) : 'Blantyre Chileka Airport'; ?>" required>
                                    <div class="form-text">Usually Blantyre Chileka Airport for Prime Cargo</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="arrival_date" class="form-label">Expected Arrival Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="arrival_date" name="arrival_date"
                                        value="<?php echo isset($_POST['arrival_date']) ? htmlspecialchars($_POST['arrival_date']) : ''; ?>" required>
                                    <div class="form-text">When the goods are expected to arrive at Chileka Airport</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="expected_clearance_date" class="form-label">Expected Clearance Date</label>
                                    <input type="date" class="form-control" id="expected_clearance_date" name="expected_clearance_date"
                                        value="<?php echo isset($_POST['expected_clearance_date']) ? htmlspecialchars($_POST['expected_clearance_date']) : ''; ?>">
                                    <div class="form-text">When you expect the goods to be cleared (optional)</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">Additional Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"
                                        placeholder="Any additional information, special requirements, or notes about the shipment"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                                    <div class="form-text">Include any special handling requirements, customs considerations, or other relevant information</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-ship me-2"></i>Create Shipment
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary btn-lg ms-2">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Information Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Important Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Tips for Faster Clearance:</h6>
                            <ul class="mb-0">
                                <li>Provide accurate and detailed goods descriptions</li>
                                <li>Include materials, purpose, and specifications</li>
                                <li>Ensure all required documents are ready</li>
                                <li>Submit shipment details well before arrival</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Required Documents:</h6>
                            <ul class="mb-0">
                                <li>Commercial Invoice</li>
                                <li>Bill of Landing</li>
                                <li>Packing List</li>
                                <li>Certificate of Origin</li>
                            </ul>
                        </div>

                        <div class="card bg-light">
                            <div class="card-body">
                                <h6><i class="fas fa-clock me-2"></i>Processing Times:</h6>
                                <p class="mb-1"><strong>Standard:</strong> 3-5 business days</p>
                                <p class="mb-1"><strong>Express:</strong> 1-2 business days</p>
                                <p class="mb-0"><strong>Complex:</strong> 5-7 business days</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        // Auto-calculate expected clearance date (5 business days after arrival)
        document.getElementById('arrival_date').addEventListener('change', function() {
            const arrivalDate = new Date(this.value);
            if (arrivalDate) {
                const clearanceDate = new Date(arrivalDate);
                clearanceDate.setDate(clearanceDate.getDate() + 7); // Add 7 days for business days

                // Format date for input field
                const year = clearanceDate.getFullYear();
                const month = String(clearanceDate.getMonth() + 1).padStart(2, '0');
                const day = String(clearanceDate.getDate()).padStart(2, '0');

                document.getElementById('expected_clearance_date').value = `${year}-${month}-${day}`;
            }
        });

        // Form validation
        document.getElementById('shipmentForm').addEventListener('submit', function(e) {
            const goodsDescription = document.getElementById('goods_description').value.trim();

            if (goodsDescription.length < 20) {
                e.preventDefault();
                alert('Goods description must be at least 20 characters long for proper customs processing.');
                return false;
            }
        });
    </script>
</body>

</html>