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

// Get date range parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today

// Get system statistics for the date range
try {
    // Total shipments in date range
    $shipments_query = "SELECT COUNT(*) as total,
                               SUM(declared_value) as total_value,
                               SUM(tax_amount) as total_tax,
                               AVG(declared_value) as avg_value,
                               AVG(tax_amount) as avg_tax
                        FROM shipments 
                        WHERE created_at BETWEEN :date_from AND :date_to";
    $stmt = $db->prepare($shipments_query);
    $stmt->bindParam(':date_from', $date_from . ' 00:00:00');
    $stmt->bindParam(':date_to', $date_to . ' 23:59:59');
    $stmt->execute();
    $shipment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Status breakdown
    $status_query = "SELECT status, COUNT(*) as count
                     FROM shipments 
                     WHERE created_at BETWEEN :date_from AND :date_to
                     GROUP BY status";
    $stmt = $db->prepare($status_query);
    $stmt->bindParam(':date_from', $date_from . ' 00:00:00');
    $stmt->bindParam(':date_to', $date_to . ' 23:59:59');
    $stmt->execute();
    $status_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agent performance
    $agent_query = "SELECT u.full_name, u.email,
                           COUNT(s.shipment_id) as total_shipments,
                           SUM(s.declared_value) as total_value,
                           SUM(s.tax_amount) as total_tax,
                           AVG(s.declared_value) as avg_value
                    FROM users u 
                    LEFT JOIN shipments s ON u.user_id = s.agent_id 
                        AND s.created_at BETWEEN :date_from AND :date_to
                    WHERE u.role = 'agent'
                    GROUP BY u.user_id
                    ORDER BY total_shipments DESC";
    $stmt = $db->prepare($agent_query);
    $stmt->bindParam(':date_from', $date_from . ' 00:00:00');
    $stmt->bindParam(':date_to', $date_to . ' 23:59:59');
    $stmt->execute();
    $agent_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Client activity
    $client_query = "SELECT c.company_name, c.contact_person,
                            COUNT(s.shipment_id) as total_shipments,
                            SUM(s.declared_value) as total_value,
                            MAX(s.created_at) as last_shipment
                     FROM clients c 
                     LEFT JOIN shipments s ON c.client_id = s.client_id 
                         AND s.created_at BETWEEN :date_from AND :date_to
                     GROUP BY c.client_id
                     ORDER BY total_shipments DESC
                     LIMIT 10";
    $stmt = $db->prepare($client_query);
    $stmt->bindParam(':date_from', $date_from . ' 00:00:00');
    $stmt->bindParam(':date_to', $date_to . ' 23:59:59');
    $stmt->execute();
    $client_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly trends (last 12 months)
    $trends_query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                            COUNT(*) as shipments,
                            SUM(declared_value) as value,
                            SUM(tax_amount) as tax
                     FROM shipments 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                     GROUP BY month
                     ORDER BY month DESC";
    $stmt = $db->prepare($trends_query);
    $stmt->execute();
    $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top goods categories
    $goods_query = "SELECT goods_description, COUNT(*) as count, SUM(declared_value) as total_value
                    FROM shipments 
                    WHERE created_at BETWEEN :date_from AND :date_to
                    GROUP BY goods_description
                    ORDER BY count DESC
                    LIMIT 10";
    $stmt = $db->prepare($goods_query);
    $stmt->bindParam(':date_from', $date_from . ' 00:00:00');
    $stmt->bindParam(':date_to', $date_to . ' 23:59:59');
    $stmt->execute();
    $top_goods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading statistics: " . $e->getMessage();
    $shipment_stats = $status_breakdown = $agent_performance = $client_activity = $monthly_trends = $top_goods = [];
}

// Calculate percentages for status breakdown
$total_shipments = $shipment_stats['total'] ?? 0;
foreach ($status_breakdown as &$status) {
    $status['percentage'] = $total_shipments > 0 ? round(($status['count'] / $total_shipments) * 100, 1) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-chart-bar me-2"></i>
                            Reports & Analytics
                        </h2>
                        <p class="card-text">Comprehensive system statistics and performance insights</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Date Range</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Generate Report
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                        <h3 class="card-title"><?php echo number_format($shipment_stats['total'] ?? 0); ?></h3>
                        <p class="card-text text-muted">Total Shipments</p>
                        <small class="text-muted"><?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?></small>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-3x text-success mb-3"></i>
                        <h3 class="card-title">$<?php echo number_format($shipment_stats['total_value'] ?? 0, 2); ?></h3>
                        <p class="card-text text-muted">Total Declared Value</p>
                        <small class="text-muted">Avg: $<?php echo number_format($shipment_stats['avg_value'] ?? 0, 2); ?></small>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-calculator fa-3x text-warning mb-3"></i>
                        <h3 class="card-title">$<?php echo number_format($shipment_stats['total_tax'] ?? 0, 2); ?></h3>
                        <p class="card-text text-muted">Total Tax Collected</p>
                        <small class="text-muted">Avg: $<?php echo number_format($shipment_stats['avg_tax'] ?? 0, 2); ?></small>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-percentage fa-3x text-info mb-3"></i>
                        <h3 class="card-title"><?php echo $shipment_stats['total_value'] > 0 ? round(($shipment_stats['total_tax'] / $shipment_stats['total_value']) * 100, 1) : 0; ?>%</h3>
                        <p class="card-text text-muted">Tax Rate</p>
                        <small class="text-muted">Overall Average</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Status Distribution Chart -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Shipment Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Trends Chart -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Trends (Last 12 Months)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendsChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agent Performance -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Agent Performance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($agent_performance)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                                <h6>No Agent Data Available</h6>
                                <p class="text-muted">Agent performance data will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Agent</th>
                                            <th>Email</th>
                                            <th>Shipments</th>
                                            <th>Total Value</th>
                                            <th>Total Tax</th>
                                            <th>Avg Value</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agent_performance as $agent): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($agent['full_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $agent['total_shipments']; ?></span>
                                                </td>
                                                <td>$<?php echo number_format($agent['total_value'] ?? 0, 2); ?></td>
                                                <td>$<?php echo number_format($agent['total_tax'] ?? 0, 2); ?></td>
                                                <td>$<?php echo number_format($agent['avg_value'] ?? 0, 2); ?></td>
                                                <td>
                                                    <?php
                                                    $performance = $agent['total_shipments'] > 0 ?
                                                        ($agent['total_shipments'] >= 10 ? 'Excellent' : ($agent['total_shipments'] >= 5 ? 'Good' : 'Fair')) : 'No Activity';
                                                    $badge_class = $agent['total_shipments'] > 0 ?
                                                        ($agent['total_shipments'] >= 10 ? 'success' : ($agent['total_shipments'] >= 5 ? 'warning' : 'secondary')) : 'light';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                                        <?php echo $performance; ?>
                                                    </span>
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

        <!-- Client Activity -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Top Client Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($client_activity)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h6>No Client Data Available</h6>
                                <p class="text-muted">Client activity data will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Company</th>
                                            <th>Contact Person</th>
                                            <th>Shipments</th>
                                            <th>Total Value</th>
                                            <th>Last Shipment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($client_activity as $client): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($client['company_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($client['contact_person']); ?></td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $client['total_shipments']; ?></span>
                                                </td>
                                                <td>$<?php echo number_format($client['total_value'] ?? 0, 2); ?></td>
                                                <td>
                                                    <?php if ($client['last_shipment']): ?>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, Y', strtotime($client['last_shipment'])); ?>
                                                        </small>
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

        <!-- Top Goods Categories -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-box me-2"></i>Top Goods Categories</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_goods)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                <h6>No Goods Data Available</h6>
                                <p class="text-muted">Goods category data will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Goods Description</th>
                                            <th>Shipment Count</th>
                                            <th>Total Value</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_goods as $goods): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($goods['goods_description'], 0, 60)) . '...'; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $goods['count']; ?></span>
                                                </td>
                                                <td>$<?php echo number_format($goods['total_value'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $percentage = $total_shipments > 0 ? round(($goods['count'] / $total_shipments) * 100, 1) : 0;
                                                    echo $percentage . '%';
                                                    ?>
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
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function ($s) {
                            return ucwords(str_replace('_', ' ', $s['status']));
                        }, $status_breakdown)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($status_breakdown, 'count')); ?>,
                    backgroundColor: [
                        '#28a745', '#ffc107', '#17a2b8', '#6c757d',
                        '#007bff', '#dc3545', '#6f42c1', '#fd7e14'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function ($t) {
                            return date('M Y', strtotime($t['month'] . '-01'));
                        }, $monthly_trends)); ?>,
                datasets: [{
                    label: 'Shipments',
                    data: <?php echo json_encode(array_column($monthly_trends, 'shipments')); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Value (K)',
                    data: <?php echo json_encode(array_map(function ($t) {
                                return round($t['value'] / 1000, 1);
                            }, $monthly_trends)); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Shipments'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Value (K USD)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
</body>

</html>