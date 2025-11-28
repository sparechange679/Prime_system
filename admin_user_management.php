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

// Get all users
try {
    $users_query = "SELECT u.*, 
                           COUNT(s.shipment_id) as total_shipments,
                           MAX(s.created_at) as last_activity,
                           m.manifest_number,
                           t.tpin_number
                    FROM users u 
                    LEFT JOIN shipments s ON u.user_id = s.agent_id
                    LEFT JOIN manifests m ON u.user_id = m.agent_id AND m.status = 'active'
                    LEFT JOIN tpin_assignments t ON u.user_id = t.agent_id AND t.status = 'active'
                    GROUP BY u.user_id
                    ORDER BY u.role ASC, u.created_at DESC";
    $stmt = $db->prepare($users_query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

// Handle user status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $target_user_id = (int)$_POST['user_id'];
    $new_status = $_POST['status'];

    if ($target_user_id == $user_id) {
        $status_error = "You cannot change your own status";
    } else {
        try {
            $update_query = "UPDATE users SET status = :status, updated_at = NOW() WHERE user_id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $new_status);
            $update_stmt->bindParam(':user_id', $target_user_id);

            if ($update_stmt->execute()) {
                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                              VALUES (:user_id, 'update_user_status', 'users', :target_user_id, :details)";
                $log_stmt = $db->prepare($log_query);
                $log_details = "User status updated to: $new_status";
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':target_user_id', $target_user_id);
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();

                $status_success = "User status updated successfully!";
                header("Location: admin_user_management.php");
                exit();
            } else {
                $status_error = "Failed to update user status";
            }
        } catch (PDOException $e) {
            $status_error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle user role updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role'])) {
    $target_user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];

    if ($target_user_id == $user_id) {
        $role_error = "You cannot change your own role";
    } else {
        try {
            $update_query = "UPDATE users SET role = :role, updated_at = NOW() WHERE user_id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':role', $new_role);
            $update_stmt->bindParam(':user_id', $target_user_id);

            if ($update_stmt->execute()) {
                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                              VALUES (:user_id, 'update_user_role', 'users', :target_user_id, :details)";
                $log_stmt = $db->prepare($log_query);
                $log_details = "User role updated to: $new_role";
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':target_user_id', $target_user_id);
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();

                $role_success = "User role updated successfully!";
                header("Location: admin_user_management.php");
                exit();
            } else {
                $role_error = "Failed to update user role";
            }
        } catch (PDOException $e) {
            $role_error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle new user registration (agents and keepers only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_user'])) {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Validate input
    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $register_error = "All fields are required";
    } elseif (!validateEmail($email)) {
        $register_error = "Please enter a valid email address";
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $register_error = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    } elseif (!in_array($role, ['agent', 'keeper'])) {
        $register_error = "Only agents and keepers can be registered by admin";
    } else {
        try {
            // Check if email already exists
            $check_query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $register_error = "Email address already exists";
            } else {
                // Hash password and create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insert_query = "INSERT INTO users (full_name, email, phone, password, role, status, created_at) 
                                VALUES (:full_name, :email, :phone, :password, :role, 'active', NOW())";

                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':full_name', $full_name);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':phone', $phone);
                $insert_stmt->bindParam(':password', $hashed_password);
                $insert_stmt->bindParam(':role', $role);

                if ($insert_stmt->execute()) {
                    $new_user_id = $db->lastInsertId();

                    // If registering an agent, automatically generate manifest and TPIN
                    if ($role === 'agent') {
                        try {
                            // Generate unique manifest number
                            $manifest_number = 'M' . date('Y') . str_pad($new_user_id, 6, '0', STR_PAD_LEFT);

                            // Generate unique TPIN
                            $tpin = 'T' . date('Y') . str_pad($new_user_id, 6, '0', STR_PAD_LEFT);

                            // Insert manifest
                            $manifest_query = "INSERT INTO manifests (agent_id, manifest_number, status, created_at) 
                                              VALUES (:agent_id, :manifest_number, 'active', NOW())";
                            $manifest_stmt = $db->prepare($manifest_query);
                            $manifest_stmt->bindParam(':agent_id', $new_user_id);
                            $manifest_stmt->bindParam(':manifest_number', $manifest_number);
                            $manifest_stmt->execute();

                            // Insert TPIN
                            $tpin_query = "INSERT INTO tpin_assignments (agent_id, tpin_number, status, created_at) 
                                           VALUES (:agent_id, :tpin_number, 'active', NOW())";
                            $tpin_stmt = $db->prepare($tpin_query);
                            $tpin_stmt->bindParam(':agent_id', $new_user_id);
                            $tpin_stmt->bindParam(':tpin_number', $tpin);
                            $tpin_stmt->execute();

                            // Log the activity with manifest and TPIN info
                            logActivity(
                                $user_id,
                                'register_user',
                                'users',
                                $new_user_id,
                                "Registered new agent: $full_name with Manifest: $manifest_number, TPIN: $tpin"
                            );

                            $register_success = "Agent registered successfully! Manifest: $manifest_number, TPIN: $tpin";
                        } catch (PDOException $e) {
                            // Log error but don't fail registration
                            error_log("Error generating manifest/TPIN for agent $new_user_id: " . $e->getMessage());
                            logActivity(
                                $user_id,
                                'register_user',
                                'users',
                                $new_user_id,
                                "Registered new agent: $full_name (Manifest/TPIN generation failed)"
                            );
                            $register_success = "Agent registered successfully! (Manifest/TPIN will be generated later)";
                        }
                    } else {
                        // Log the activity for non-agent users
                        logActivity($user_id, 'register_user', 'users', $new_user_id, "Registered new $role: $full_name");
                        $register_success = "User registered successfully!";
                    }

                    header("Location: admin_user_management.php");
                    exit();
                } else {
                    $register_error = "Failed to register user";
                }
            }
        } catch (PDOException $e) {
            $register_error = "Database error: " . $e->getMessage();
        }
    }
}

// Get user statistics
$total_users = count($users);
$active_users = count(array_filter($users, function ($u) {
    return $u['status'] === 'active';
}));
$inactive_users = count(array_filter($users, function ($u) {
    return $u['status'] === 'inactive';
}));
$role_counts = [];
foreach ($users as $user) {
    $role_counts[$user['role']] = ($role_counts[$user['role']] ?? 0) + 1;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Prime Cargo Limited</title>
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
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-users-cog me-2"></i>
                            User Management
                        </h2>
                        <p class="card-text">Manage all system users, roles, and permissions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($status_success)): ?>
            <div class="alert alert-success"><?php echo $status_success; ?></div>
        <?php endif; ?>

        <?php if (isset($role_success)): ?>
            <div class="alert alert-success"><?php echo $role_success; ?></div>
        <?php endif; ?>

        <?php if (isset($status_error)): ?>
            <div class="alert alert-danger"><?php echo $status_error; ?></div>
        <?php endif; ?>

        <?php if (isset($role_error)): ?>
            <div class="alert alert-danger"><?php echo $role_error; ?></div>
        <?php endif; ?>

        <?php if (isset($register_success)): ?>
            <div class="alert alert-success"><?php echo $register_success; ?></div>
        <?php endif; ?>

        <?php if (isset($register_error)): ?>
            <div class="alert alert-danger"><?php echo $register_error; ?></div>
        <?php endif; ?>

        <!-- User Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h3 class="card-title"><?php echo $total_users; ?></h3>
                        <p class="card-text text-muted">Total Users</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                        <h3 class="card-title"><?php echo $active_users; ?></h3>
                        <p class="card-text text-muted">Active Users</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                        <h3 class="card-title"><?php echo $inactive_users; ?></h3>
                        <p class="card-text text-muted">Inactive Users</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-user-shield fa-3x text-warning mb-3"></i>
                        <h3 class="card-title"><?php echo count($role_counts); ?></h3>
                        <p class="card-text text-muted">User Roles</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Register New User -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Register New User</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>

                            <div class="col-md-6">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="agent">Agent</option>
                                    <option value="keeper">Keeper</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password"
                                    minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                            </div>

                            <div class="col-12">
                                <button type="submit" name="register_user" class="btn btn-success">
                                    <i class="fas fa-user-plus me-2"></i>Register User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Distribution -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>User Role Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($role_counts as $role => $count): ?>
                                <div class="col-md-3 mb-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-<?php
                                                                echo $role === 'admin' ? 'danger' : ($role === 'agent' ? 'primary' : ($role === 'client' ? 'success' : 'secondary'));
                                                                ?> fs-6 me-2">
                                            <?php echo ucfirst($role); ?>
                                        </span>
                                        <span class="fw-bold"><?php echo $count; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Users</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h6>No Users Found</h6>
                                <p class="text-muted">No users have been registered in the system.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Shipments</th>
                                            <th>Manifest/TPIN</th>
                                            <th>Last Activity</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $user['user_id']; ?></span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                        <?php if ($user['user_id'] == $user_id): ?>
                                                            <span class="badge bg-info ms-1">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'agent' ? 'primary' : ($user['role'] === 'client' ? 'success' : 'secondary'));
                                                                            ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user['role'] === 'agent'): ?>
                                                        <span class="badge bg-info"><?php echo $user['total_shipments']; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['role'] === 'agent'): ?>
                                                        <div class="small">
                                                            <?php if ($user['manifest_number']): ?>
                                                                <div class="mb-1">
                                                                    <span class="badge bg-primary">Manifest: <?php echo htmlspecialchars($user['manifest_number']); ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ($user['tpin_number']): ?>
                                                                <div>
                                                                    <span class="badge bg-success">TPIN: <?php echo htmlspecialchars($user['tpin_number']); ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!$user['manifest_number'] && !$user['tpin_number']): ?>
                                                                <span class="text-muted">Not assigned</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php
                                                        if ($user['last_activity']) {
                                                            echo date('M d, Y H:i', strtotime($user['last_activity']));
                                                        } else {
                                                            echo 'Never';
                                                        }
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($user['user_id'] != $user_id): ?>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#statusModal<?php echo $user['user_id']; ?>">
                                                                <i class="fas fa-toggle-on"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#roleModal<?php echo $user['user_id']; ?>">
                                                                <i class="fas fa-user-edit"></i>
                                                            </button>
                                                            <a href="admin_agent_communication.php?agent_id=<?php echo $user['user_id']; ?>"
                                                                class="btn btn-sm btn-outline-info"
                                                                title="Send Message">
                                                                <i class="fas fa-comments"></i>
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
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

    <!-- Status Update Modals -->
    <?php foreach ($users as $user): ?>
        <?php if ($user['user_id'] != $user_id): ?>
            <div class="modal fade" id="statusModal<?php echo $user['user_id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update User Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <p>Update status for <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="roleModal<?php echo $user['user_id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update User Role</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <p>Update role for <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-select" required>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="agent" <?php echo $user['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
                                        <option value="client" <?php echo $user['role'] === 'client' ? 'selected' : ''; ?>>Client</option>
                                        <option value="keeper" <?php echo $user['role'] === 'keeper' ? 'selected' : ''; ?>>Keeper</option>
                                    </select>
                                </div>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> Changing user roles may affect system permissions and access.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_role" class="btn btn-warning">Update Role</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>