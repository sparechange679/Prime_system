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

// Get documents for agent's shipments
try {
    $query = "SELECT d.*, s.tracking_number, s.goods_description, s.declared_value,
                     c.company_name, c.contact_person, c.phone, c.email
              FROM shipment_documents d 
              JOIN shipments s ON d.shipment_id = s.shipment_id 
              JOIN clients c ON s.client_id = c.client_id 
              WHERE s.agent_id = :user_id 
              ORDER BY d.uploaded_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading documents: " . $e->getMessage();
    $documents = [];
}

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $document_id = (int)$_POST['document_id'];
    $verification_status = $_POST['verification_status'];
    $verification_notes = trim($_POST['verification_notes']);

    if (empty($verification_status)) {
        $verification_error = "Please select verification status";
    } else {
        try {
            // Update document verification status
            $update_query = "UPDATE shipment_documents 
                           SET verification_status = :status, 
                               verification_notes = :notes,
                               verified_by = :verified_by,
                               verified_at = NOW()
                           WHERE document_id = :document_id";

            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $verification_status);
            $update_stmt->bindParam(':notes', $verification_notes);
            $update_stmt->bindParam(':verified_by', $user_id);
            $update_stmt->bindParam(':document_id', $document_id);

            if ($update_stmt->execute()) {
                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                              VALUES (:user_id, 'verify_document', 'shipment_documents', :document_id, :details)";
                $log_stmt = $db->prepare($log_query);
                $log_details = "Document verified with status: $verification_status";
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':document_id', $document_id);
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();

                $verification_success = "Document verification updated successfully!";

                // Refresh the page to show updated data
                header("Location: agent_documents.php");
                exit();
            } else {
                $verification_error = "Failed to update verification status";
            }
        } catch (PDOException $e) {
            $verification_error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Review - Prime Cargo Limited</title>
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
                    <i class="fas fa-arrow-left me-2"></i>Back to Agent Dashboard
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
                            <i class="fas fa-file-alt me-2 text-primary"></i>
                            Document Review
                        </h2>
                        <p class="card-text">Review and verify client document submissions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($verification_success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $verification_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($verification_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $verification_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Documents List -->
        <?php if (empty($documents)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card text-center">
                        <div class="card-body py-5">
                            <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                            <h5 class="card-title">No Documents Found</h5>
                            <p class="card-text">No documents have been uploaded for your shipments yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($documents as $document): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="mb-0">
                                            <i class="fas fa-file me-2"></i>
                                            <?php echo ucwords(str_replace('_', ' ', $document['document_type'])); ?>
                                        </h6>
                                        <small class="text-muted">
                                            Shipment: <?php echo htmlspecialchars($document['tracking_number']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <span class="badge bg-<?php echo $document['verification_status'] === 'verified' ? 'success' : ($document['verification_status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst($document['verification_status'] ?? 'pending'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Document Details</h6>
                                        <p class="mb-1"><strong>File:</strong> <?php echo htmlspecialchars($document['file_name']); ?></p>
                                        <p class="mb-1"><strong>Size:</strong> <?php echo number_format($document['file_size'] / 1024, 2); ?> KB</p>
                                        <p class="mb-1"><strong>Uploaded:</strong> <?php echo date('M d, Y H:i', strtotime($document['uploaded_at'])); ?></p>
                                        <?php if ($document['verification_notes']): ?>
                                            <p class="mb-1"><strong>Notes:</strong> <?php echo htmlspecialchars($document['verification_notes']); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <h6>Shipment Information</h6>
                                        <p class="mb-1"><strong>Client:</strong> <?php echo htmlspecialchars($document['company_name']); ?></p>
                                        <p class="mb-1"><strong>Goods:</strong> <?php echo htmlspecialchars(substr($document['goods_description'], 0, 50)) . '...'; ?></p>
                                        <p class="mb-1"><strong>Value:</strong> $<?php echo number_format($document['declared_value'], 2); ?></p>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <a href="download_document.php?id=<?php echo $document['document_id']; ?>"
                                            class="btn btn-outline-primary me-2">
                                            <i class="fas fa-download me-2"></i>Download
                                        </a>
                                        <a href="view_document.php?id=<?php echo $document['document_id']; ?>"
                                            class="btn btn-outline-info me-2">
                                            <i class="fas fa-eye me-2"></i>View
                                        </a>
                                    </div>

                                    <div class="col-md-6">
                                        <button class="btn btn-outline-success"
                                            data-bs-toggle="modal"
                                            data-bs-target="#verificationModal<?php echo $document['document_id']; ?>">
                                            <i class="fas fa-check-circle me-2"></i>Verify Document
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verification Modal -->
                <div class="modal fade" id="verificationModal<?php echo $document['document_id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Verify Document</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="document_id" value="<?php echo $document['document_id']; ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Verification Status</label>
                                        <select name="verification_status" class="form-select" required>
                                            <option value="">Select Status</option>
                                            <option value="verified">Verified</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="pending">Pending Review</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Verification Notes</label>
                                        <textarea name="verification_notes" class="form-control" rows="3"
                                            placeholder="Add any notes about the verification..."><?php echo htmlspecialchars($document['verification_notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Update Verification</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>