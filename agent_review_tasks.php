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

// Get agent's assigned tasks (shipments) from admin
try {
    $query = "SELECT s.*, c.company_name 
              FROM shipments s 
              LEFT JOIN clients c ON s.client_id = c.client_id 
              WHERE s.agent_id = :user_id 
              ORDER BY s.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading tasks: " . $e->getMessage();
    $tasks = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Tasks - Prime Cargo Limited</title>
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
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
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
                            <i class="fas fa-tasks me-2 text-primary"></i>
                            Tasks Assigned by Admin
                        </h2>
                        <p class="card-text">List of tasks sent to you by the administrator</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Simple Tasks List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Assigned Tasks (<?php echo count($tasks); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                <h5>No Tasks Assigned</h5>
                                <p class="text-muted">You haven't been assigned any tasks by the administrator yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($tasks as $task): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <i class="fas fa-ship me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($task['tracking_number']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1">
                                            <strong>Client:</strong> <?php echo htmlspecialchars($task['company_name'] ?? 'N/A'); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Goods:</strong> <?php echo htmlspecialchars(substr($task['goods_description'], 0, 100)) . (strlen($task['goods_description']) > 100 ? '...' : ''); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Value:</strong> <?php echo number_format($task['declared_value'], 2); ?> <?php echo htmlspecialchars($task['currency'] ?? 'MWK'); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?php echo $task['status'] === 'pending' ? 'warning' : ($task['status'] === 'under_verification' ? 'info' : ($task['status'] === 'under_clearance' ? 'primary' : ($task['status'] === 'cleared' ? 'success' : 'danger'))); ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                            <?php if ($task['admin_notes']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-sticky-note me-1"></i>
                                                    Admin Notes: <?php echo htmlspecialchars(substr($task['admin_notes'], 0, 50)) . (strlen($task['admin_notes']) > 50 ? '...' : ''); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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