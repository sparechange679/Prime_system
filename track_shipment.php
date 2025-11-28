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

// Get shipment ID from URL if provided
$selected_shipment_id = isset($_GET['shipment_id']) ? (int)$_GET['shipment_id'] : null;

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get client's shipments with tracking information
try {
    if ($selected_shipment_id) {
        // Get specific shipment
        $query = "SELECT s.*, 
                         u.full_name as agent_name, u.email as agent_email,
                         k.full_name as keeper_name, k.email as keeper_email,
                         c.company_name
                  FROM shipments s 
                  JOIN clients c ON s.client_id = c.client_id 
                  LEFT JOIN users u ON s.agent_id = u.user_id 
                  LEFT JOIN users k ON s.keeper_id = k.user_id 
                  WHERE c.user_id = :user_id AND s.shipment_id = :shipment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':shipment_id', $selected_shipment_id);
        $stmt->execute();
        $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get all client shipments
        $query = "SELECT s.*, 
                         u.full_name as agent_name, u.email as agent_email,
                         k.full_name as keeper_name, k.email as keeper_email,
                         c.company_name
                  FROM shipments s 
                  JOIN clients c ON s.client_id = c.client_id 
                  LEFT JOIN users u ON s.agent_id = u.user_id 
                  LEFT JOIN users k ON s.keeper_id = k.user_id 
                  WHERE c.user_id = :user_id 
                  ORDER BY s.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error loading shipments: " . $e->getMessage();
    $shipments = [];
}

// Get shipment status timeline
function getStatusTimeline($status)
{
    $timeline = [
        'pending' => [
            'icon' => 'fas fa-clock',
            'title' => 'Shipment Created',
            'description' => 'Shipment has been created and is awaiting document submission',
            'status' => 'completed'
        ],
        'under_verification' => [
            'icon' => 'fas fa-search',
            'title' => 'Under Verification',
            'description' => 'Keeper is verifying goods against submitted documents',
            'status' => 'completed'
        ],
        'verified' => [
            'icon' => 'fas fa-check-circle',
            'title' => 'Verification Complete',
            'description' => 'Goods have been verified and documents are approved',
            'status' => 'completed'
        ],
        'under_clearance' => [
            'icon' => 'fas fa-cogs',
            'title' => 'Under Clearance',
            'description' => 'Agent is processing clearance with customs authorities',
            'status' => 'completed'
        ],
        'manifest_issued' => [
            'icon' => 'fas fa-file-contract',
            'title' => 'Manifest Issued',
            'description' => 'MRA manifest number has been issued for clearance',
            'status' => 'completed'
        ],
        'clearance_approved' => [
            'icon' => 'fas fa-shield-check',
            'title' => 'Clearance Approved',
            'description' => 'Customs clearance has been approved by MRA',
            'status' => 'completed'
        ],
        'release_issued' => [
            'icon' => 'fas fa-truck',
            'title' => 'Release Issued',
            'description' => 'Release order has been issued and shipment is ready for collection',
            'status' => 'completed'
        ],
        'completed' => [
            'icon' => 'fas fa-flag-checkered',
            'title' => 'Completed',
            'description' => 'Shipment has been successfully delivered and completed',
            'status' => 'completed'
        ]
    ];

    return $timeline;
}

// Get status progress percentage
function getStatusProgress($status)
{
    $statusOrder = ['pending', 'under_verification', 'verified', 'under_clearance', 'manifest_issued', 'clearance_approved', 'release_issued', 'completed'];
    $currentIndex = array_search($status, $statusOrder);
    return $currentIndex !== false ? round((($currentIndex + 1) / count($statusOrder)) * 100) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Shipments - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e9ecef;
            border: 3px solid #fff;
            box-shadow: 0 0 0 3px #e9ecef;
        }

        .timeline-item.completed::before {
            background: #28a745;
            box-shadow: 0 0 0 3px #28a745;
        }

        .timeline-item.current::before {
            background: #007bff;
            box-shadow: 0 0 0 3px #007bff;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: 10px;
            top: 20px;
            width: 2px;
            height: calc(100% + 10px);
            background: #e9ecef;
        }

        .timeline-item:last-child::after {
            display: none;
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }

        .shipment-card {
            transition: transform 0.2s;
        }

        .shipment-card:hover {
            transform: translateY(-2px);
        }
    </style>
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
                        <a class="nav-link" href="new_shipment.php">
                            <i class="fas fa-plus me-1"></i>New Shipment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="track_shipment.php">
                            <i class="fas fa-search me-1"></i>Track Shipments
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
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="card-title">
                                    <i class="fas fa-search me-2 text-primary"></i>
                                    <?php echo $selected_shipment_id ? 'Shipment Details' : 'Track Your Shipments'; ?>
                                </h2>
                                <p class="card-text">Monitor the progress of your cargo clearance at Chileka Airport</p>
                            </div>
                            <?php if ($selected_shipment_id): ?>
                                <a href="track_shipment.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to All Shipments
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Shipments List -->
        <?php if (empty($shipments)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card text-center">
                        <div class="card-body py-5">
                            <i class="fas fa-ship fa-4x text-muted mb-3"></i>
                            <h5 class="card-title">No Shipments Found</h5>
                            <p class="card-text">You haven't created any shipments yet.</p>
                            <a href="new_shipment.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Your First Shipment
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if ($selected_shipment_id && count($shipments) == 1): ?>
                <!-- Detailed Single Shipment View -->
                <?php $shipment = $shipments[0]; ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shipment-card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">
                                            <i class="fas fa-ship me-2 text-primary"></i>
                                            Shipment: <?php echo htmlspecialchars($shipment['tracking_number']); ?>
                                        </h5>
                                        <small class="text-muted">
                                            Created: <?php echo date('M d, Y', strtotime($shipment['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <span class="badge bg-<?php echo $shipment['status'] === 'completed' ? 'success' : ($shipment['status'] === 'pending' ? 'warning' : 'info'); ?> status-badge">
                                            <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Shipment Details -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-box me-2"></i>Goods Description</h6>
                                        <p class="mb-2"><?php echo htmlspecialchars($shipment['goods_description']); ?></p>

                                        <h6><i class="fas fa-map-marker-alt me-2"></i>Origin & Destination</h6>
                                        <p class="mb-2">
                                            <strong>From:</strong> <?php echo htmlspecialchars($shipment['origin_country']); ?><br>
                                            <strong>To:</strong> <?php echo htmlspecialchars($shipment['destination_port']); ?>
                                        </p>

                                        <h6><i class="fas fa-dollar-sign me-2"></i>Value & Tax</h6>
                                        <p class="mb-2">
                                            <strong>Declared Value:</strong> $<?php echo number_format($shipment['declared_value'], 2); ?><br>
                                            <?php if ($shipment['tax_amount']): ?>
                                                <strong>Tax Amount:</strong> $<?php echo number_format($shipment['tax_amount'], 2); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <div class="col-md-6">
                                        <h6><i class="fas fa-calendar me-2"></i>Important Dates</h6>
                                        <p class="mb-2">
                                            <strong>Expected Arrival:</strong> <?php echo date('M d, Y', strtotime($shipment['arrival_date'])); ?><br>
                                            <?php if ($shipment['expected_clearance_date']): ?>
                                                <strong>Expected Clearance:</strong> <?php echo date('M d, Y', strtotime($shipment['expected_clearance_date'])); ?>
                                            <?php endif; ?>
                                        </p>

                                        <h6><i class="fas fa-users me-2"></i>Assigned Personnel</h6>
                                        <p class="mb-2">
                                            <?php if ($shipment['agent_name']): ?>
                                                <strong>Agent:</strong> <?php echo htmlspecialchars($shipment['agent_name']); ?><br>
                                            <?php else: ?>
                                                <strong>Agent:</strong> <span class="text-muted">Not assigned yet</span><br>
                                            <?php endif; ?>

                                            <?php if ($shipment['keeper_name']): ?>
                                                <strong>Keeper:</strong> <?php echo htmlspecialchars($shipment['keeper_name']); ?>
                                            <?php else: ?>
                                                <strong>Keeper:</strong> <span class="text-muted">Not assigned yet</span>
                                            <?php endif; ?>
                                        </p>

                                        <?php if ($shipment['manifest_number']): ?>
                                            <h6><i class="fas fa-file-contract me-2"></i>MRA Numbers</h6>
                                            <p class="mb-2">
                                                <strong>Manifest:</strong> <?php echo htmlspecialchars($shipment['manifest_number']); ?><br>
                                                <?php if ($shipment['tpin_number']): ?>
                                                    <strong>TPIN:</strong> <?php echo htmlspecialchars($shipment['tpin_number']); ?>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Clearance Progress</h6>
                                        <span class="text-muted"><?php echo getStatusProgress($shipment['status']); ?>% Complete</span>
                                    </div>
                                    <div class="progress progress-bar-custom">
                                        <div class="progress-bar bg-success" role="progressbar"
                                            style="width: <?php echo getStatusProgress($shipment['status']); ?>%"
                                            aria-valuenow="<?php echo getStatusProgress($shipment['status']); ?>"
                                            aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>

                                <!-- Timeline -->
                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="mb-3"><i class="fas fa-route me-2"></i>Clearance Timeline</h6>
                                        <?php
                                        $timeline = getStatusTimeline($shipment['status']);
                                        $statusOrder = ['pending', 'under_verification', 'verified', 'under_clearance', 'manifest_issued', 'clearance_approved', 'release_issued', 'completed'];
                                        $currentStatus = $shipment['status'];
                                        ?>

                                        <?php foreach ($statusOrder as $index => $status): ?>
                                            <?php
                                            $isCompleted = array_search($status, $statusOrder) <= array_search($currentStatus, $statusOrder);
                                            $isCurrent = $status === $currentStatus;
                                            $timelineItem = $timeline[$status];
                                            ?>
                                            <div class="timeline-item <?php echo $isCompleted ? ($isCurrent ? 'current' : 'completed') : ''; ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <i class="<?php echo $timelineItem['icon']; ?> me-2"></i>
                                                            <?php echo $timelineItem['title']; ?>
                                                        </h6>
                                                        <p class="mb-0 text-muted"><?php echo $timelineItem['description']; ?></p>
                                                    </div>
                                                    <?php if ($isCompleted): ?>
                                                        <span class="badge bg-<?php echo $isCurrent ? 'primary' : 'success'; ?>">
                                                            <?php echo $isCurrent ? 'Current' : 'Completed'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <a href="upload_document.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                            class="btn btn-outline-primary me-2">
                                            <i class="fas fa-upload me-2"></i>Upload Documents
                                        </a>
                                        <a href="view_document.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                            class="btn btn-outline-info me-2">
                                            <i class="fas fa-eye me-2"></i>View Documents
                                        </a>
                                        <?php if ($shipment['status'] === 'release_issued'): ?>
                                            <a href="payment.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                                class="btn btn-success me-2">
                                                <i class="fas fa-credit-card me-2"></i>Make Payment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Multiple Shipments List View -->
                <?php foreach ($shipments as $shipment): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shipment-card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0">
                                                <i class="fas fa-ship me-2 text-primary"></i>
                                                Shipment: <?php echo htmlspecialchars($shipment['tracking_number']); ?>
                                            </h5>
                                            <small class="text-muted">
                                                Created: <?php echo date('M d, Y', strtotime($shipment['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <span class="badge bg-<?php echo $shipment['status'] === 'completed' ? 'success' : ($shipment['status'] === 'pending' ? 'warning' : 'info'); ?> status-badge">
                                                <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
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
                                            <h6>Status Information</h6>
                                            <p class="mb-1"><strong>Current Status:</strong> <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?></p>
                                            <p class="mb-1"><strong>Agent:</strong> <?php echo $shipment['agent_name'] ? htmlspecialchars($shipment['agent_name']) : '<span class="text-muted">Not assigned</span>'; ?></p>
                                            <p class="mb-1"><strong>Progress:</strong> <?php echo getStatusProgress($shipment['status']); ?>% Complete</p>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <a href="track_shipment.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                                class="btn btn-outline-primary me-2">
                                                <i class="fas fa-search me-2"></i>Track Details
                                            </a>
                                            <a href="upload_document.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                                class="btn btn-outline-success me-2">
                                                <i class="fas fa-upload me-2"></i>Upload Documents
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>

</html>