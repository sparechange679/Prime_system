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

// Get system statistics
try {
    // Total shipments
    $shipments_query = "SELECT COUNT(*) as total FROM shipments";
    $stmt = $db->prepare($shipments_query);
    $stmt->execute();
    $total_shipments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Pending clearance
    $pending_query = "SELECT COUNT(*) as total FROM shipments WHERE status = 'under_clearance'";
    $stmt = $db->prepare($pending_query);
    $stmt->execute();
    $pending_clearance = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // New client submissions (pending)
    $new_submissions_query = "SELECT COUNT(*) as total FROM shipments WHERE status = 'pending'";
    $stmt = $db->prepare($new_submissions_query);
    $stmt->execute();
    $new_submissions_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total clients
    $clients_query = "SELECT COUNT(*) as total FROM clients";
    $stmt = $db->prepare($clients_query);
    $stmt->execute();
    $total_clients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total agents
    $agents_query = "SELECT COUNT(*) as total FROM users WHERE role = 'agent'";
    $stmt = $db->prepare($agents_query);
    $stmt->execute();
    $total_agents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total keepers
    $keepers_query = "SELECT COUNT(*) as total FROM users WHERE role = 'keeper'";
    $stmt = $db->prepare($keepers_query);
    $stmt->execute();
    $total_keepers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Recent activities
    $activities_query = "SELECT al.*, u.full_name, u.role 
                        FROM activity_log al 
                        JOIN users u ON al.user_id = u.user_id 
                        ORDER BY al.created_at DESC 
                        LIMIT 10";
    $stmt = $db->prepare($activities_query);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread notifications for admin
    $notifications_query = "SELECT * FROM notifications WHERE user_id = :user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
    $notif_stmt = $db->prepare($notifications_query);
    $notif_stmt->bindParam(':user_id', $user_id);
    $notif_stmt->execute();
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading statistics: " . $e->getMessage();
    $total_shipments = $pending_clearance = $new_submissions_count = $total_clients = $total_agents = $total_keepers = 0;
    $recent_activities = [];
    $notifications = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .clickable-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .clickable-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        }

        .clickable-card:hover .card-body {
            background-color: #f8f9fa;
        }

        .clickable-card:hover .text-info {
            color: #0056b3 !important;
        }

        .clickable-card:hover .fas {
            transform: scale(1.1);
            transition: transform 0.3s ease;
        }

        .clickable-header:hover {
            opacity: 0.9;
            text-decoration: underline !important;
        }

        .clickable-header:hover .fa-external-link-alt {
            transform: translateX(2px);
            transition: transform 0.2s ease;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-shield-alt me-2"></i>Prime Cargo Admin
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-2"></i><?php echo htmlspecialchars($full_name); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user-cog me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="admin_settings.php"><i class="fas fa-cogs me-2"></i>Settings</a></li>
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
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-shield-alt me-2"></i>
                            Admin Dashboard
                        </h2>
                        <p class="card-text">Manage Prime Cargo system and MRA officer functions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <a href="admin_shipment_management.php" class="text-decoration-none">
                    <div class="card text-center border-0 shadow-sm clickable-card">
                        <div class="card-body">
                            <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                            <h3 class="card-title text-primary"><?php echo $total_shipments; ?></h3>
                            <p class="card-text text-muted">Total Shipments</p>
                            <small class="text-primary"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-2 mb-3">
                <a href="admin_shipment_management.php?status=under_clearance" class="text-decoration-none">
                    <div class="card text-center border-0 shadow-sm clickable-card">
                        <div class="card-body">
                            <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                            <h3 class="card-title text-warning"><?php echo $pending_clearance; ?></h3>
                            <p class="card-text text-muted">Pending Clearance</p>
                            <small class="text-warning"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-2 mb-3">
                <a href="admin_shipment_management.php?status=pending" class="text-decoration-none">
                    <div class="card text-center border-0 shadow-sm clickable-card">
                        <div class="card-body">
                            <i class="fas fa-ship fa-3x text-info mb-3"></i>
                            <h3 class="card-title text-info"><?php echo $new_submissions_count; ?></h3>
                            <p class="card-text text-muted">New Submissions</p>
                            <small class="text-info"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-2 mb-3">
                <a href="admin_user_management.php?role=client" class="text-decoration-none">
                    <div class="card text-center border-0 shadow-sm clickable-card">
                        <div class="card-body">
                            <i class="fas fa-users fa-3x text-success mb-3"></i>
                            <h3 class="card-title text-success"><?php echo $total_clients; ?></h3>
                            <p class="card-text text-muted">Total Clients</p>
                            <small class="text-success"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <a href="admin_user_management.php?role=agent" class="text-decoration-none">
                                    <div class="clickable-card">
                                        <i class="fas fa-user-tie fa-3x text-info mb-3"></i>
                                        <h3 class="card-title text-info"><?php echo $total_agents; ?></h3>
                                        <p class="card-text text-muted">Total Agents</p>
                                        <small class="text-info"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="admin_user_management.php?role=keeper" class="text-decoration-none">
                                    <div class="clickable-card">
                                        <i class="fas fa-user-shield fa-3x text-secondary mb-3"></i>
                                        <h3 class="card-title text-secondary"><?php echo $total_keepers; ?></h3>
                                        <p class="card-text text-muted">Total Keepers</p>
                                        <small class="text-secondary"><i class="fas fa-mouse-pointer me-1"></i>Click to view</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- MRA Officer Functions -->
                            <div class="col-md-6 mb-3">
                                <h6 class="text-primary"><i class="fas fa-shield-check me-2"></i>MRA Officer Functions</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_manifest_management.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-file-contract me-2"></i>Manage Manifests
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_tpin_management.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-id-card me-2"></i>Manage TPINs
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_release_orders.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-check-circle me-2"></i>Release Orders
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_clearance_approval.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-thumbs-up me-2"></i>Clearance Approval
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- System Management -->
                            <div class="col-md-6 mb-3">
                                <h6 class="text-success"><i class="fas fa-cogs me-2"></i>System Management</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_user_management.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-user-plus me-2"></i>Register Users
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_user_management.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-users-cog me-2"></i>User Management
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_shipment_management.php" class="btn btn-outline-dark w-100">
                                            <i class="fas fa-ship me-2"></i>Shipment Management
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_reports.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="admin_activity_logs.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-history me-2"></i>Activity Logs
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Client Submissions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <a href="admin_shipment_management.php?status=pending" class="text-white text-decoration-none clickable-header">
                                <i class="fas fa-ship me-2"></i>New Client Submissions
                                <span class="badge bg-light text-primary ms-2" id="newSubmissionsCount">0</span>
                                <i class="fas fa-external-link-alt ms-2" style="font-size: 0.8em;"></i>
                            </a>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get new client submissions (pending shipments)
                        try {
                            $new_submissions_query = "SELECT s.*, c.company_name, c.contact_person, c.phone, c.email,
                                                           (SELECT COUNT(*) FROM shipment_documents WHERE shipment_id = s.shipment_id) as doc_count
                                                    FROM shipments s 
                                                    JOIN clients c ON s.client_id = c.client_id 
                                                    WHERE s.status = 'pending' 
                                                    ORDER BY s.created_at DESC 
                                                    LIMIT 10";
                            $stmt = $db->prepare($new_submissions_query);
                            $stmt->execute();
                            $new_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $new_submissions = [];
                        }
                        ?>

                        <?php if (empty($new_submissions)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h6>No New Submissions</h6>
                                <p class="text-muted">All client submissions have been processed.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tracking #</th>
                                            <th>Client</th>
                                            <th>Goods Description</th>
                                            <th>Documents</th>
                                            <th>Value</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($new_submissions as $shipment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($shipment['tracking_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($shipment['company_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($shipment['contact_person']); ?><br>
                                                            <?php echo htmlspecialchars($shipment['phone']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($shipment['goods_description'], 0, 50)); ?>
                                                    <?php if (strlen($shipment['goods_description']) > 50): ?>...<?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $shipment['doc_count'] > 0 ? 'success' : 'warning'; ?>">
                                                        <?php echo $shipment['doc_count']; ?> docs
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong>$<?php echo number_format($shipment['declared_value'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i', strtotime($shipment['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="admin_shipment_management.php?shipment_id=<?php echo $shipment['shipment_id']; ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </a>
                                                        <a href="admin_shipment_management.php?status=pending"
                                                            class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-list me-1"></i>All Pending
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="admin_shipment_management.php?status=pending" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>View All Pending Submissions
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (!empty($notifications)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-bell me-2"></i>Recent Notifications
                                <span class="badge bg-danger ms-2"><?php echo count($notifications); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="alert alert-<?php echo $notification['type']; ?> alert-dismissible fade show mb-2" role="alert">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            onclick="markNotificationAsRead(<?php echo $notification['notification_id']; ?>)"></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent System Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h6>No Recent Activities</h6>
                                <p class="text-muted">System activities will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <span class="badge bg-<?php echo $activity['role'] === 'admin' ? 'danger' : ($activity['role'] === 'agent' ? 'primary' : 'secondary'); ?>">
                                                                <?php echo ucfirst($activity['role']); ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($activity['details'], 0, 100)); ?>
                                                    <?php if (strlen($activity['details']) > 100): ?>...<?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markNotificationAsRead(notificationId) {
            // Send AJAX request to mark notification as read
            fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'notification_id=' + notificationId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the notification from the DOM
                        const notificationElement = document.querySelector(`[onclick="markNotificationAsRead(${notificationId})"]`).closest('.alert');
                        notificationElement.remove();

                        // Update notification count
                        const countBadge = document.querySelector('.badge.bg-danger');
                        if (countBadge) {
                            const currentCount = parseInt(countBadge.textContent);
                            if (currentCount > 1) {
                                countBadge.textContent = currentCount - 1;
                            } else {
                                // Hide notifications section if no more notifications
                                const notificationsSection = document.querySelector('.row.mb-4');
                                if (notificationsSection) {
                                    notificationsSection.style.display = 'none';
                                }
                            }
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Update new submissions count badge
        function updateNewSubmissionsCount() {
            const countBadge = document.getElementById('newSubmissionsCount');
            if (countBadge) {
                const count = <?php echo count($new_submissions); ?>;
                countBadge.textContent = count;

                // Hide the section if no new submissions
                if (count === 0) {
                    const submissionsSection = countBadge.closest('.row.mb-4');
                    if (submissionsSection) {
                        submissionsSection.style.display = 'none';
                    }
                }
            }
        }

        // Initialize count badges when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateNewSubmissionsCount();

            // Add tooltips to clickable elements
            const clickableCards = document.querySelectorAll('.clickable-card');
            clickableCards.forEach(card => {
                // Determine the appropriate tooltip based on the card content
                const cardText = card.querySelector('.card-text').textContent.toLowerCase();
                let tooltip = '';

                if (cardText.includes('total shipments')) {
                    tooltip = 'Click to view all shipments in the system';
                } else if (cardText.includes('pending clearance')) {
                    tooltip = 'Click to view shipments under clearance';
                } else if (cardText.includes('new submissions')) {
                    tooltip = 'Click to view all pending shipments';
                } else if (cardText.includes('total clients')) {
                    tooltip = 'Click to view all client accounts';
                } else if (cardText.includes('total agents')) {
                    tooltip = 'Click to view all agent accounts';
                } else if (cardText.includes('total keepers')) {
                    tooltip = 'Click to view all keeper accounts';
                } else {
                    tooltip = 'Click to view details';
                }

                card.setAttribute('title', tooltip);
            });

            const clickableHeaders = document.querySelectorAll('.clickable-header');
            clickableHeaders.forEach(header => {
                header.setAttribute('title', 'Click to view all pending shipments');
            });
        });
    </script>
</body>

</html>