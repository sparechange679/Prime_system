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

// Get filter parameters
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
$params = [];

if ($user_filter) {
    $where_conditions[] = "al.user_id = :user_id";
    $params[':user_id'] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "al.action = :action";
    $params[':action'] = $action_filter;
}

if ($table_filter) {
    $where_conditions[] = "al.table_name = :table_name";
    $params[':table_name'] = $table_filter;
}

if ($date_from) {
    $where_conditions[] = "al.created_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $where_conditions[] = "al.created_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
try {
    $count_query = "SELECT COUNT(*) as total FROM activity_log al $where_clause";
    $stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $per_page);
} catch (PDOException $e) {
    $total_records = 0;
    $total_pages = 0;
}

// Get activity logs with filters and pagination
try {
    $logs_query = "SELECT al.*, u.full_name, u.role, u.email
                    FROM activity_log al 
                    JOIN users u ON al.user_id = u.user_id 
                    $where_clause
                    ORDER BY al.created_at DESC 
                    LIMIT :offset, :per_page";
    $stmt = $db->prepare($logs_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity_logs = [];
}

// Get all users for filter
try {
    $users_query = "SELECT user_id, full_name, role FROM users ORDER BY full_name";
    $stmt = $db->prepare($users_query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

// Get all actions for filter
try {
    $actions_query = "SELECT DISTINCT action FROM activity_log ORDER BY action";
    $stmt = $db->prepare($actions_query);
    $stmt->execute();
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $actions = [];
}

// Get all tables for filter
try {
    $tables_query = "SELECT DISTINCT table_name FROM activity_log ORDER BY table_name";
    $stmt = $db->prepare($tables_query);
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tables = [];
}

// Get activity statistics
$total_activities = $total_records;
$unique_users = count(array_unique(array_column($activity_logs, 'user_id')));
$unique_actions = count(array_unique(array_column($activity_logs, 'action')));
$unique_tables = count(array_unique(array_column($activity_logs, 'table_name')));

// Handle export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['Date', 'User', 'Role', 'Action', 'Table', 'Record ID', 'Details']);

    // Get all logs for export (without pagination)
    try {
        $export_query = "SELECT al.*, u.full_name, u.role
                        FROM activity_log al 
                        JOIN users u ON al.user_id = u.user_id 
                        $where_clause
                        ORDER BY al.created_at DESC";
        $stmt = $db->prepare($export_query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $export_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($export_logs as $log) {
            fputcsv($output, [
                $log['created_at'],
                $log['full_name'],
                $log['role'],
                $log['action'],
                $log['table_name'],
                $log['record_id'],
                $log['details']
            ]);
        }
    } catch (PDOException $e) {
        // Handle error silently for export
    }

    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Prime Cargo Limited</title>
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
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-history me-2"></i>
                            Activity Logs
                        </h2>
                        <p class="card-text">Complete audit trail of all system activities and user actions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-list fa-3x text-primary mb-3"></i>
                        <h3 class="card-title"><?php echo number_format($total_activities); ?></h3>
                        <p class="card-text text-muted">Total Activities</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-success mb-3"></i>
                        <h3 class="card-title"><?php echo $unique_users; ?></h3>
                        <p class="card-text text-muted">Active Users</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-cogs fa-3x text-warning mb-3"></i>
                        <h3 class="card-title"><?php echo $unique_actions; ?></h3>
                        <p class="card-text text-muted">Action Types</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-database fa-3x text-info mb-3"></i>
                        <h3 class="card-title"><?php echo $unique_tables; ?></h3>
                        <p class="card-text text-muted">Data Tables</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">User</label>
                                <select name="user" class="form-select">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>" <?php echo $user_filter == $user['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Action</label>
                                <select name="action" class="form-select">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo $action['action']; ?>" <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                                            <?php echo ucwords(str_replace('_', ' ', $action['action'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Table</label>
                                <select name="table" class="form-select">
                                    <option value="">All Tables</option>
                                    <?php foreach ($tables as $table): ?>
                                        <option value="<?php echo $table['table_name']; ?>" <?php echo $table_filter === $table['table_name'] ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($table['table_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <div>
                                <?php if ($user_filter || $action_filter || $table_filter || $date_from || $date_to): ?>
                                    <a href="admin_activity_logs.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times me-2"></i>Clear Filters
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>"
                                    class="btn btn-success btn-sm">
                                    <i class="fas fa-download me-2"></i>Export CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Logs Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Activity Logs</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activity_logs)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h6>No Activity Logs Found</h6>
                                <p class="text-muted">No activities match the current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Table</th>
                                            <th>Record ID</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activity_logs as $log): ?>
                                            <tr>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($log['full_name']); ?></strong>
                                                        <br>
                                                        <span class="badge bg-<?php
                                                                                echo $log['role'] === 'admin' ? 'danger' : ($log['role'] === 'agent' ? 'primary' : ($log['role'] === 'client' ? 'success' : 'secondary'));
                                                                                ?>">
                                                            <?php echo ucfirst($log['role']); ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucwords(str_replace('_', ' ', $log['action'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst($log['table_name']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($log['record_id']): ?>
                                                        <span class="badge bg-light text-dark">
                                                            #<?php echo $log['record_id']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($log['details']); ?>">
                                                        <?php echo htmlspecialchars($log['details']); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Activity logs pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                    <i class="fas fa-chevron-left"></i> Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                    Next <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>

                                <div class="text-center text-muted">
                                    <small>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?>
                                        of <?php echo number_format($total_records); ?> activities
                                    </small>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>