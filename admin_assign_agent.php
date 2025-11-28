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
$shipment_id = (int)($_GET['shipment_id'] ?? 0);

if (!$shipment_id) {
    header("Location: admin_shipment_management.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get shipment details
try {
    $shipment_query = "SELECT s.*, c.company_name, c.contact_person 
                       FROM shipments s 
                       LEFT JOIN clients c ON s.client_id = c.client_id 
                       WHERE s.shipment_id = :shipment_id";
    $stmt = $db->prepare($shipment_query);
    $stmt->bindParam(':shipment_id', $shipment_id);
    $stmt->execute();
    $shipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shipment) {
        header("Location: admin_shipment_management.php");
        exit();
    }
} catch (PDOException $e) {
    header("Location: admin_shipment_management.php");
    exit();
}

// Get active agents only
try {
    $agents_query = "SELECT user_id, full_name, email, tpin_number 
                     FROM users 
                     WHERE role = 'agent' AND status = 'active' 
                     ORDER BY full_name";
    $stmt = $db->prepare($agents_query);
    $stmt->execute();
    $active_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_agents = [];
}

// Handle agent assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_agent'])) {
    $agent_id = (int)$_POST['agent_id'];
    $assignment_notes = trim($_POST['assignment_notes']);

    if ($agent_id > 0) {
        try {
            // Update shipment with agent assignment
            $assign_query = "UPDATE shipments 
                            SET agent_id = :agent_id,
                                status = 'under_verification',
                                admin_notes = :admin_notes,
                                updated_at = NOW()
                            WHERE shipment_id = :shipment_id";

            $assign_stmt = $db->prepare($assign_query);
            $assign_stmt->bindParam(':agent_id', $agent_id);
            $assign_stmt->bindParam(':admin_notes', $assignment_notes);
            $assign_stmt->bindParam(':shipment_id', $shipment_id);

            if ($assign_stmt->execute()) {
                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                              VALUES (:user_id, 'assign_agent', 'shipments', :shipment_id, :details)";
                $log_stmt = $db->prepare($log_query);
                $log_details = "Agent assigned to shipment {$shipment['tracking_number']}";
                if ($assignment_notes) $log_details .= " - Notes: $assignment_notes";
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':shipment_id', $shipment_id);
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();

                $success_message = "Agent assigned successfully!";
                header("Location: admin_shipment_management.php?success=agent_assigned");
                exit();
            } else {
                $error_message = "Failed to assign agent";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Please select a valid agent";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Agent - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">Prime Cargo Admin</a>
            <a class="nav-link text-white" href="admin_shipment_management.php">← Back</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-user-plus me-2"></i>Assign Agent to Shipment</h4>
            </div>
            <div class="card-body">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <h5>Shipment: <?php echo htmlspecialchars($shipment['tracking_number']); ?></h5>
                <p><strong>Client:</strong> <?php echo htmlspecialchars($shipment['company_name']); ?></p>

                <?php if (empty($active_agents)): ?>
                    <div class="alert alert-warning">No active agents available.</div>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Select Agent</label>
                            <select name="agent_id" class="form-select" required>
                                <option value="">Choose an active agent...</option>
                                <?php foreach ($active_agents as $agent): ?>
                                    <option value="<?php echo $agent['user_id']; ?>">
                                        <?php echo htmlspecialchars($agent['full_name']); ?>
                                        <?php if ($agent['tpin_number']): ?>
                                            (TPIN: <?php echo htmlspecialchars($agent['tpin_number']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="assignment_notes" class="form-control" rows="3"></textarea>
                        </div>

                        <button type="submit" name="assign_agent" class="btn btn-success">Assign Agent</button>
                        <a href="admin_shipment_management.php" class="btn btn-secondary">Cancel</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>