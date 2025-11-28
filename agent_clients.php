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

$clients = [];
try {
    $query = "SELECT c.client_id, c.company_name, c.contact_person,
                     COUNT(s.shipment_id) AS total_shipments,
                     SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                     SUM(CASE WHEN s.status = 'under_clearance' THEN 1 ELSE 0 END) AS clearance_count,
                     SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                     MAX(s.updated_at) AS last_updated
              FROM clients c
              JOIN shipments s ON s.client_id = c.client_id
              WHERE s.agent_id = :agent_id
              GROUP BY c.client_id, c.company_name, c.contact_person
              ORDER BY c.company_name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':agent_id', $user_id);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $clients = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Clients - Prime Cargo Limited</title>
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
                                <i class="fas fa-users me-2 text-info"></i>Your Clients
                            </h2>
                            <p class="card-text text-muted mb-0">Clients with shipments assigned to you</p>
                        </div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Client List</h5>
            </div>
            <div class="card-body">
                <?php if (empty($clients)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <div>No clients found for your assigned shipments.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Contact Person</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Pending</th>
                                    <th class="text-center">Under Clearance</th>
                                    <th class="text-center">Completed</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $c): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($c['company_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($c['contact_person'] ?: '—'); ?></td>
                                        <td class="text-center"><span class="badge bg-secondary"><?php echo (int)$c['total_shipments']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-warning"><?php echo (int)$c['pending_count']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-info"><?php echo (int)$c['clearance_count']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo (int)$c['completed_count']; ?></span></td>
                                        <td><small class="text-muted"><?php echo $c['last_updated'] ? date('M d, Y H:i', strtotime($c['last_updated'])) : '—'; ?></small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="agent_dashboard.php" class="btn btn-outline-primary" title="View Shipments">
                                                    <i class="fas fa-ship"></i>
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