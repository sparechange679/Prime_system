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

// Handle TPIN assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['assign_agent_tpin'])) {
        // Handle agent TPIN assignment
        $agent_id = (int)$_POST['agent_id'];
        $tpin_number = trim($_POST['tpin_number']);
        $tpin_notes = trim($_POST['tpin_notes']);

        if (empty($tpin_number)) {
            $tpin_error = "TPIN number is required";
        } else {
            try {
                // Check if TPIN number already exists
                $check_query = "SELECT COUNT(*) as count FROM tpin_assignments WHERE tpin_number = :tpin_number";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':tpin_number', $tpin_number);
                $check_stmt->execute();

                if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                    $tpin_error = "TPIN number already exists";
                } else {
                    // Insert new TPIN for agent
                    $insert_query = "INSERT INTO tpin_assignments (agent_id, tpin_number, status, notes, created_at) 
                                   VALUES (:agent_id, :tpin_number, 'active', :notes, NOW())";

                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':agent_id', $agent_id);
                    $insert_stmt->bindParam(':tpin_number', $tpin_number);
                    $insert_stmt->bindParam(':notes', $tpin_notes);

                    if ($insert_stmt->execute()) {
                        // Log the activity
                        logActivity(
                            $user_id,
                            'assign_agent_tpin',
                            'tpin_assignments',
                            $db->lastInsertId(),
                            "Assigned TPIN $tpin_number to agent ID: $agent_id"
                        );

                        $tpin_success = "Agent TPIN assigned successfully!";
                        header("Location: admin_tpin_management.php");
                        exit();
                    } else {
                        $tpin_error = "Failed to assign agent TPIN";
                    }
                }
            } catch (PDOException $e) {
                $tpin_error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Generate next TPIN number
function generateTPINNumber()
{
    $year = date('Y');
    $month = date('m');
    return "TPIN-{$year}-{$month}-" . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPIN Management - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-shield-alt me-2"></i>Prime Cargo Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="admin_dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Admin Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-id-card me-2"></i>
                            TPIN Management
                        </h2>
                        <p class="card-text">Assign and manage Tax Payer Identification Numbers for agents</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($tpin_success)): ?>
            <div class="alert alert-success"><?php echo $tpin_success; ?></div>
        <?php endif; ?>

        <?php if (isset($tpin_error)): ?>
            <div class="alert alert-danger"><?php echo $tpin_error; ?></div>
        <?php endif; ?>

        <!-- Agent TPIN Assignment -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Assign TPIN to Agent</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="agent_id" class="form-label">Select Agent <span class="text-danger">*</span></label>
                                <select name="agent_id" id="agent_id" class="form-select" required>
                                    <option value="">Choose an agent...</option>
                                    <?php
                                    // Get all active agents
                                    try {
                                        $agents_query = "SELECT user_id, full_name, email FROM users WHERE role = 'agent' AND status = 'active' ORDER BY full_name";
                                        $agents_stmt = $db->prepare($agents_query);
                                        $agents_stmt->execute();
                                        $agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($agents as $agent) {
                                            echo "<option value='{$agent['user_id']}'>{$agent['full_name']} ({$agent['email']})</option>";
                                        }
                                    } catch (PDOException $e) {
                                        echo "<option value=''>Error loading agents</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="tpin_number" class="form-label">TPIN Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="tpin_number" id="tpin_number" class="form-control"
                                        value="<?php echo generateTPINNumber(); ?>" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generateNewTPIN(this)">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <div class="form-text">Format: TPIN-YYYY-MM-XXXX</div>
                            </div>

                            <div class="col-12">
                                <label for="tpin_notes" class="form-label">Notes (Optional)</label>
                                <textarea name="tpin_notes" id="tpin_notes" class="form-control" rows="3"
                                    placeholder="Add any notes about this TPIN assignment..."></textarea>
                            </div>

                            <div class="col-12">
                                <button type="submit" name="assign_agent_tpin" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i>Assign TPIN to Agent
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agent TPINs -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Agent TPINs</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get all agent TPINs
                        try {
                            $agent_tpins_query = "SELECT t.*, u.full_name as agent_name, u.email as agent_email 
                                                 FROM tpin_assignments t 
                                                 JOIN users u ON t.agent_id = u.user_id 
                                                 ORDER BY t.created_at DESC";
                            $agent_tpins_stmt = $db->prepare($agent_tpins_query);
                            $agent_tpins_stmt->execute();
                            $agent_tpins = $agent_tpins_stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $agent_tpins = [];
                        }
                        ?>

                        <?php if (empty($agent_tpins)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-id-card fa-3x text-muted mb-3"></i>
                                <h6>No Agent TPINs Assigned</h6>
                                <p class="text-muted">Assign TPINs to agents using the form above.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>TPIN Number</th>
                                            <th>Agent</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                            <th>Date Assigned</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agent_tpins as $tpin): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-success fs-6">
                                                        <?php echo htmlspecialchars($tpin['tpin_number']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($tpin['agent_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($tpin['agent_email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $tpin['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($tpin['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($tpin['notes']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($tpin['notes']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i', strtotime($tpin['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary"
                                                        onclick="viewTPINDetails(<?php echo $tpin['tpin_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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

        <!-- TPIN Statistics -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <i class="fas fa-id-card fa-3x text-success mb-3"></i>
                        <h3 class="card-title"><?php echo count($agent_tpins); ?></h3>
                        <p class="card-text text-muted">Total TPINs Assigned</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card text-center border-primary">
                    <div class="card-body">
                        <i class="fas fa-user-tie fa-3x text-primary mb-3"></i>
                        <h3 class="card-title">
                            <?php
                            try {
                                $agents_query = "SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND status = 'active'";
                                $agents_stmt = $db->prepare($agents_query);
                                $agents_stmt->execute();
                                echo $agents_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            } catch (PDOException $e) {
                                echo '0';
                            }
                            ?>
                        </h3>
                        <p class="card-text text-muted">Active Agents</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h3 class="card-title">
                            <?php
                            try {
                                $agents_query = "SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND status = 'active'";
                                $agents_stmt = $db->prepare($agents_query);
                                $agents_stmt->execute();
                                $total_agents = $agents_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                echo max(0, $total_agents - count($agent_tpins));
                            } catch (PDOException $e) {
                                echo '0';
                            }
                            ?>
                        </h3>
                        <p class="card-text text-muted">Agents Without TPIN</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generateNewTPIN(button) {
            const input = button.parentElement.querySelector('input');
            const year = new Date().getFullYear();
            const month = String(new Date().getMonth() + 1).padStart(2, '0');
            const random = String(Math.floor(Math.random() * 9000) + 1000);
            input.value = `TPIN-${year}-${month}-${random}`;
        }

        function viewTPINDetails(tpinId) {
            // You can implement a modal or redirect to show TPIN details
            alert('TPIN Details functionality can be implemented here');
        }
    </script>
</body>

</html>