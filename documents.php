<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get documents based on role (aligned to current schema)
try {
    if ($role == 'admin') {
        $query = "SELECT d.*, s.tracking_number, c.company_name, u.full_name AS agent_name
                  FROM shipment_documents d
                  JOIN shipments s ON d.shipment_id = s.shipment_id
                  JOIN clients c ON s.client_id = c.client_id
                  LEFT JOIN users u ON s.agent_id = u.user_id
                  ORDER BY d.uploaded_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
    } elseif ($role == 'agent') {
        $query = "SELECT d.*, s.tracking_number, c.company_name
                  FROM shipment_documents d
                  JOIN shipments s ON d.shipment_id = s.shipment_id
                  JOIN clients c ON s.client_id = c.client_id
                  WHERE s.agent_id = :user_id
                  ORDER BY d.uploaded_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    } elseif ($role == 'keeper') {
        $query = "SELECT d.*, s.tracking_number, c.company_name
                  FROM shipment_documents d
                  JOIN shipments s ON d.shipment_id = s.shipment_id
                  JOIN clients c ON s.client_id = c.client_id
                  WHERE d.verified = 0
                  ORDER BY d.uploaded_at ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
    } else {
        // Client: by default show ALL documents for their shipments.
        // Optional filter mine=1 shows only documents they personally uploaded.
        $mineOnly = isset($_GET['mine']) && $_GET['mine'] == '1';
        if ($mineOnly) {
            $query = "SELECT d.*, s.tracking_number
                      FROM shipment_documents d
                      JOIN shipments s ON d.shipment_id = s.shipment_id
                      WHERE s.client_id = (SELECT client_id FROM clients WHERE user_id = :user_id)
                        AND d.uploaded_by = :user_id
                      ORDER BY d.uploaded_at DESC";
        } else {
            $query = "SELECT d.*, s.tracking_number
                      FROM shipment_documents d
                      JOIN shipments s ON d.shipment_id = s.shipment_id
                      WHERE s.client_id = (SELECT client_id FROM clients WHERE user_id = :user_id)
                      ORDER BY d.uploaded_at DESC";
        }
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }

    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Role-aware counters for cards (independent of table filter)
    // Defaults
    $count_total = 0;
    $count_pending = 0;
    $count_verified = 0;
    $count_recent7 = 0;

    if ($role == 'admin') {
        $q_total = $db->query("SELECT COUNT(*) AS c FROM shipment_documents");
        $count_total = (int)$q_total->fetch(PDO::FETCH_ASSOC)['c'];
        $q_pending = $db->query("SELECT COUNT(*) AS c FROM shipment_documents WHERE verified = 0");
        $count_pending = (int)$q_pending->fetch(PDO::FETCH_ASSOC)['c'];
        $q_verified = $db->query("SELECT COUNT(*) AS c FROM shipment_documents WHERE verified = 1");
        $count_verified = (int)$q_verified->fetch(PDO::FETCH_ASSOC)['c'];
        $q_recent = $db->query("SELECT COUNT(*) AS c FROM shipment_documents WHERE uploaded_at >= (NOW() - INTERVAL 7 DAY)");
        $count_recent7 = (int)$q_recent->fetch(PDO::FETCH_ASSOC)['c'];
    } elseif ($role == 'agent') {
        $q_total = $db->prepare("SELECT COUNT(*) AS c FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE s.agent_id = :uid");
        $q_total->bindParam(':uid', $user_id);
        $q_total->execute();
        $count_total = (int)$q_total->fetch(PDO::FETCH_ASSOC)['c'];

        $q_pending = $db->prepare("SELECT COUNT(*) AS c FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE s.agent_id = :uid AND d.verified = 0");
        $q_pending->bindParam(':uid', $user_id);
        $q_pending->execute();
        $count_pending = (int)$q_pending->fetch(PDO::FETCH_ASSOC)['c'];

        $q_verified = $db->prepare("SELECT COUNT(*) AS c FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE s.agent_id = :uid AND d.verified = 1");
        $q_verified->bindParam(':uid', $user_id);
        $q_verified->execute();
        $count_verified = (int)$q_verified->fetch(PDO::FETCH_ASSOC)['c'];

        $q_recent = $db->prepare("SELECT COUNT(*) AS c FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE s.agent_id = :uid AND d.uploaded_at >= (NOW() - INTERVAL 7 DAY)");
        $q_recent->bindParam(':uid', $user_id);
        $q_recent->execute();
        $count_recent7 = (int)$q_recent->fetch(PDO::FETCH_ASSOC)['c'];
    } elseif ($role == 'keeper') {
        // Keeper: show totals across all documents for counters, even if table lists only pending
        $q_total = $db->query("SELECT COUNT(*) AS c FROM shipment_documents");
        $count_total = (int)$q_total->fetch(PDO::FETCH_ASSOC)['c'];
        $q_pending = $db->query("SELECT COUNT(*) AS c FROM shipment_documents WHERE verified = 0");
        $count_pending = (int)$q_pending->fetch(PDO::FETCH_ASSOC)['c'];
        $q_verified = $db->query("SELECT COUNT(*) AS c FROM shipment_documents WHERE verified = 1");
        $count_verified = (int)$q_verified->fetch(PDO::FETCH_ASSOC)['c'];
        $q_recent = $db->query("SELECT COUNT(*) AS c FROM shipment_documents WHERE uploaded_at >= (NOW() - INTERVAL 7 DAY)");
        $count_recent7 = (int)$q_recent->fetch(PDO::FETCH_ASSOC)['c'];
    } else {
        // Client: counts for their shipments
        $q_total = $db->prepare("SELECT COUNT(*) AS c FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE s.client_id = (SELECT client_id FROM clients WHERE user_id = :uid)");
        $q_total->bindParam(':uid', $user_id);
        $q_total->execute();
        $count_total = (int)$q_total->fetch(PDO::FETCH_ASSOC)['c'];

        $q_pending = $db->prepare("SELECT COUNT(*) AS c FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE s.client_id = (SELECT client_id FROM clients WHERE user_id = :uid) AND d.verified = 0");
        $q_pending->bindParam(':uid', $user_id);
        $q_pending->execute();
        $count_pending = (int)$q_pending->fetch(PDO::FETCH_ASSOC)['c'];

        $q_verified = $db->prepare("SELECT COUNT(*) AS c FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE s.client_id = (SELECT client_id FROM clients WHERE user_id = :uid) AND d.verified = 1");
        $q_verified->bindParam(':uid', $user_id);
        $q_verified->execute();
        $count_verified = (int)$q_verified->fetch(PDO::FETCH_ASSOC)['c'];

        $q_recent = $db->prepare("SELECT COUNT(*) AS c FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE s.client_id = (SELECT client_id FROM clients WHERE user_id = :uid) AND d.uploaded_at >= (NOW() - INTERVAL 7 DAY)");
        $q_recent->bindParam(':uid', $user_id);
        $q_recent->execute();
        $count_recent7 = (int)$q_recent->fetch(PDO::FETCH_ASSOC)['c'];
    }
} catch (PDOException $e) {
    $error = "Error loading documents: " . $e->getMessage();
    $documents = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - Prime Cargo System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-ship me-2"></i>Prime Cargo System
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
                        <a class="nav-link active" href="documents.php">
                            <i class="fas fa-file-alt me-1"></i>Documents
                        </a>
                    </li>
                    <?php if ($role == 'admin' || $role == 'agent'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-ship me-1"></i>Shipments
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($role == 'client'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-plus me-1"></i>New Shipment
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($full_name); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-file-alt me-2"></i>Document Management
                        </h1>
                        <p class="text-muted mb-0">Manage cargo documents and verification</p>
                    </div>
                    <?php if ($role == 'client'): ?>
                        <div class="d-flex gap-2">
                            <?php $mineOnly = isset($_GET['mine']) && $_GET['mine'] == '1'; ?>
                            <a href="documents.php" class="btn btn-outline-secondary <?php echo !$mineOnly ? 'active' : ''; ?>">
                                <i class="fas fa-list me-2"></i>All (my shipments)
                            </a>
                            <a href="documents.php?mine=1" class="btn btn-outline-secondary <?php echo $mineOnly ? 'active' : ''; ?>">
                                <i class="fas fa-user me-2"></i>My uploads
                            </a>
                            <a href="upload_document.php" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Document
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo (int)$count_total; ?></h4>
                                <p class="mb-0">Total Documents</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <?php echo (int)$count_pending; ?>
                                </h4>
                                <p class="mb-0">Pending Verification</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <?php echo (int)$count_verified; ?>
                                </h4>
                                <p class="mb-0">Verified</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <?php echo (int)$count_recent7; ?>
                                </h4>
                                <p class="mb-0">Uploaded (7 days)</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-cogs fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    <?php
                    if ($role == 'admin') echo 'All Documents';
                    elseif ($role == 'agent') echo 'My Shipment Documents';
                    elseif ($role == 'keeper') echo 'Documents Pending Verification';
                    else echo 'My Documents';
                    ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (empty($documents)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No documents found</h5>
                        <p class="text-muted">
                            <?php if ($role == 'client'): ?>
                                Start by uploading your first cargo document
                            <?php else: ?>
                                No documents are currently available for your role
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Document</th>
                                    <th>Shipment</th>
                                    <?php if ($role == 'admin' || $role == 'agent'): ?>
                                        <th>Client</th>
                                    <?php endif; ?>
                                    <?php if ($role == 'admin'): ?>
                                        <th>Agent</th>
                                    <?php endif; ?>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($doc['original_filename']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo number_format($doc['file_size'] / 1024, 1); ?> KB</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">#<?php echo htmlspecialchars($doc['tracking_number']); ?></span>
                                        </td>
                                        <?php if ($role == 'admin' || $role == 'agent'): ?>
                                            <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                                        <?php endif; ?>
                                        <?php if ($role == 'admin'): ?>
                                            <td><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($doc['document_type']); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $isVerified = (int)($doc['verified'] ?? 0) === 1;
                                            $status_class = $isVerified ? 'bg-success' : 'bg-warning';
                                            $status_icon = $isVerified ? 'check-circle' : 'clock';
                                            $status_text = $isVerified ? 'Verified' : 'Pending';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($doc['uploaded_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank"
                                                    class="btn btn-sm btn-outline-primary" title="Open">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" download
                                                    class="btn btn-sm btn-outline-success" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php if ($role == 'keeper' && (int)($doc['verified'] ?? 0) === 0): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                                        onclick="verifyDocument(<?php echo $doc['document_id']; ?>)" title="Verify">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($role == 'admin'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteDocument(<?php echo $doc['document_id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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
    <script src="assets/js/script.js"></script>
    <script>
        function verifyDocument(documentId) {
            if (confirm('Are you sure you want to verify this document?')) {
                window.location.href = 'verify_document.php?id=' + documentId;
            }
        }

        function deleteDocument(documentId) {
            if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                window.location.href = 'delete_document.php?id=' + documentId;
            }
        }
    </script>
</body>

</html>