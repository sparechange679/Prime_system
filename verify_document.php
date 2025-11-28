<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is a keeper
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'keeper') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get document ID from URL
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$document_id) {
    header("Location: documents.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get document details
try {
    $query = "SELECT d.*, 
                     s.tracking_number AS shipment_reference,
                     s.goods_description AS shipment_description,
                     s.agent_id AS agent_id,
                     c.company_name, c.contact_person,
                     cu.phone AS phone, cu.email AS email,
                     u.full_name AS agent_name
              FROM shipment_documents d 
              JOIN shipments s ON d.shipment_id = s.shipment_id 
              JOIN clients c ON s.client_id = c.client_id 
              LEFT JOIN users cu ON c.user_id = cu.user_id
              LEFT JOIN users u ON s.agent_id = u.user_id 
              WHERE d.document_id = :document_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':document_id', $document_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        $error = "Document not found.";
    } else {
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error loading document: " . $e->getMessage();
}

// Handle verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $verification_status = $_POST['verification_status'];
    $verification_notes = trim($_POST['verification_notes']);
    $goods_verified = isset($_POST['goods_verified']) ? 1 : 0;
    $quantity_match = isset($_POST['quantity_match']) ? 1 : 0;
    $condition_match = isset($_POST['condition_match']) ? 1 : 0;

    if (empty($verification_status)) {
        $verification_error = "Please select verification status";
    } else {
        try {
            // Map status to verified flag (1 for verified, 0 otherwise)
            $verified_flag = ($verification_status === 'verified') ? 1 : 0;

            // Update document verification fields
            $update_query = "UPDATE shipment_documents 
                           SET verified = :verified, 
                               verification_notes = :notes,
                               verified_by = :verified_by,
                               verified_at = NOW()
                           WHERE document_id = :document_id";

            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':verified', $verified_flag, PDO::PARAM_INT);
            $update_stmt->bindParam(':notes', $verification_notes);
            $update_stmt->bindParam(':verified_by', $user_id);
            $update_stmt->bindParam(':document_id', $document_id);

            if ($update_stmt->execute()) {
                // Insert verification log aligned with schema
                $verification_status_row = ($verification_status === 'verified') ? 'completed' : (($verification_status === 'rejected') ? 'failed' : 'pending');
                $documents_verified = 1; // Document was reviewed in this flow
                $shipment_id_for_log = isset($document['shipment_id']) ? (int)$document['shipment_id'] : 0;

                if ($shipment_id_for_log > 0) {
                    $verification_query = "INSERT INTO verification (shipment_id, keeper_id, verification_date, goods_verified, documents_verified, verification_notes, status) 
                                           VALUES (:shipment_id, :keeper_id, NOW(), :goods_verified, :documents_verified, :verification_notes, :status)";

                    $verification_stmt = $db->prepare($verification_query);
                    $verification_stmt->bindParam(':shipment_id', $shipment_id_for_log, PDO::PARAM_INT);
                    $verification_stmt->bindParam(':keeper_id', $user_id, PDO::PARAM_INT);
                    $verification_stmt->bindParam(':goods_verified', $goods_verified, PDO::PARAM_INT);
                    $verification_stmt->bindParam(':documents_verified', $documents_verified, PDO::PARAM_INT);
                    $verification_stmt->bindParam(':verification_notes', $verification_notes);
                    $verification_stmt->bindParam(':status', $verification_status_row);
                    $verification_stmt->execute();
                }

                // Notify responsible agent (if assigned)
                try {
                    // Ensure we have fresh document/shipment context
                    if (!isset($document)) {
                        $docStmt = $db->prepare("SELECT d.*, s.agent_id, s.tracking_number FROM shipment_documents d JOIN shipments s ON d.shipment_id = s.shipment_id WHERE d.document_id = :did LIMIT 1");
                        $docStmt->bindParam(':did', $document_id, PDO::PARAM_INT);
                        $docStmt->execute();
                        $document = $docStmt->fetch(PDO::FETCH_ASSOC) ?: $document;
                    }
                    $agentRecipientId = (int)($document['agent_id'] ?? 0);
                    $shipmentId = (int)($document['shipment_id'] ?? 0);
                    if ($agentRecipientId > 0 && $shipmentId > 0) {
                        $subject = 'Document Verification — ' . ($document['tracking_number'] ?? $document['shipment_reference'] ?? '');
                        $content = 'Document "' . ($document['original_filename'] ?? 'file') . '" for shipment ' . ($document['tracking_number'] ?? $document['shipment_reference'] ?? '') . ' has been marked as ' . strtoupper($verification_status) . ".\n\nNotes: " . ($verification_notes ?: 'None');
                        $msg = $db->prepare("INSERT INTO messages (sender_id, recipient_id, shipment_id, subject, content, message_type, created_at) VALUES (:sender_id, :recipient_id, :shipment_id, :subject, :content, :type, NOW())");
                        $type = 'update';
                        $msg->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
                        $msg->bindParam(':recipient_id', $agentRecipientId, PDO::PARAM_INT);
                        $msg->bindParam(':shipment_id', $shipmentId, PDO::PARAM_INT);
                        $msg->bindParam(':subject', $subject);
                        $msg->bindParam(':content', $content);
                        $msg->bindParam(':type', $type);
                        $msg->execute();
                    }
                } catch (PDOException $eMsg) {
                    // Do not block verification on notification failure
                }

                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id) 
                              VALUES (:user_id, 'verify_document', 'shipment_documents', :document_id)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':document_id', $document_id);
                $log_stmt->execute();

                $verification_success = "Document verified successfully!";

                // Redirect after 2 seconds
                header("refresh:2;url=documents.php");
            } else {
                $verification_error = "Error updating verification status";
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
    <title>Verify Document - Prime Cargo System</title>
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
                    <li class="nav-item">
                        <a class="nav-link" href="verifications.php">
                            <i class="fas fa-check-circle me-1"></i>Verifications
                        </a>
                    </li>
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
                            <i class="fas fa-check-circle me-2"></i>Verify Document
                        </h1>
                        <p class="text-muted mb-0">Verify cargo document against goods received</p>
                    </div>
                    <a href="documents.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Documents
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($verification_success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $verification_success; ?>
                <p class="mb-0 mt-2">Redirecting to documents page...</p>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Document Information -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>Document Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Document Name</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($document['original_filename']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Document Type</label>
                                    <p class="mb-0">
                                        <span class="badge bg-info"><?php echo htmlspecialchars($document['document_type']); ?></span>
                                    </p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Shipment Reference</label>
                                    <p class="mb-0">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($document['shipment_reference']); ?></span>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">File Size</label>
                                    <p class="mb-0"><?php echo number_format($document['file_size'] / 1024, 1); ?> KB</p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Description</label>
                                <p class="mb-0"><?php echo htmlspecialchars($document['description'] ?: 'No description provided'); ?></p>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Uploaded By</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($document['company_name']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Upload Date</label>
                                    <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($document['uploaded_at'])); ?></p>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex">
                                <a href="view_document.php?id=<?php echo $document['document_id']; ?>"
                                    class="btn btn-outline-primary" target="_blank">
                                    <i class="fas fa-eye me-2"></i>View Document
                                </a>
                                <a href="download_document.php?id=<?php echo $document['document_id']; ?>"
                                    class="btn btn-outline-success">
                                    <i class="fas fa-download me-2"></i>Download
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Verification Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>Verification Form
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($verification_error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $verification_error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="verificationForm">
                            <div class="mb-3">
                                <label for="verification_status" class="form-label">Verification Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="verification_status" name="verification_status" required>
                                    <option value="">Select Status</option>
                                    <option value="verified">Verified - Document matches goods</option>
                                    <option value="rejected">Rejected - Document does not match goods</option>
                                    <option value="pending">Pending - Requires additional review</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Goods Verification Checklist</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="goods_verified" name="goods_verified">
                                    <label class="form-check-label" for="goods_verified">
                                        Goods have been physically received and inspected
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="quantity_match" name="quantity_match">
                                    <label class="form-check-label" for="quantity_match">
                                        Quantity matches the document specifications
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="condition_match" name="condition_match">
                                    <label class="form-check-label" for="condition_match">
                                        Condition of goods matches the document description
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="verification_notes" class="form-label">Verification Notes</label>
                                <textarea class="form-control" id="verification_notes" name="verification_notes" rows="4"
                                    placeholder="Provide detailed notes about the verification process, any discrepancies found, or additional observations..."></textarea>
                                <div class="form-text">
                                    Include specific details about what was verified and any issues found.
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle me-2"></i>Complete Verification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Shipment Information -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-ship me-2"></i>Shipment Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-warning mb-0"><?php echo htmlspecialchars($error); ?></div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Shipment Reference</label>
                                <p class="mb-0">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($document['shipment_reference']); ?></span>
                                </p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Description</label>
                                <p class="mb-0"><?php echo htmlspecialchars($document['shipment_description']); ?></p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Client</label>
                                <p class="mb-0"><?php echo htmlspecialchars($document['company_name']); ?></p>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($document['contact_person']); ?><br>
                                    <?php echo htmlspecialchars($document['phone']); ?><br>
                                    <?php echo htmlspecialchars($document['email']); ?>
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Assigned Agent</label>
                                <p class="mb-0"><?php echo htmlspecialchars($document['agent_name'] ?? ''); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Verification Guidelines -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Verification Guidelines
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6><i class="fas fa-check-circle text-success me-2"></i>What to Verify</h6>
                            <ul class="list-unstyled small text-muted">
                                <li>• Physical presence of goods</li>
                                <li>• Quantity and specifications</li>
                                <li>• Condition and quality</li>
                                <li>• Document accuracy</li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Red Flags</h6>
                            <ul class="list-unstyled small text-muted">
                                <li>• Missing or damaged goods</li>
                                <li>• Quantity mismatches</li>
                                <li>• Poor quality items</li>
                                <li>• Suspicious packaging</li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <h6><i class="fas fa-clock text-info me-2"></i>Process</h6>
                            <p class="small text-muted mb-0">
                                1. Inspect goods thoroughly<br>
                                2. Compare with documents<br>
                                3. Note any discrepancies<br>
                                4. Complete verification form
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        // Form validation
        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            const status = document.getElementById('verification_status').value;
            const notes = document.getElementById('verification_notes').value.trim();

            if (!status) {
                e.preventDefault();
                alert('Please select a verification status');
                return;
            }

            if (status === 'rejected' && !notes) {
                e.preventDefault();
                alert('Please provide notes when rejecting a document');
                document.getElementById('verification_notes').focus();
                return;
            }

            if (!confirm('Are you sure you want to complete this verification? This action cannot be undone.')) {
                e.preventDefault();
            }
        });

        // Auto-save notes to localStorage
        document.getElementById('verification_notes').addEventListener('input', function() {
            localStorage.setItem('verification_notes_<?php echo $document_id; ?>', this.value);
        });

        // Load saved notes
        window.addEventListener('load', function() {
            const savedNotes = localStorage.getItem('verification_notes_<?php echo $document_id; ?>');
            if (savedNotes) {
                document.getElementById('verification_notes').value = savedNotes;
            }
        });
    </script>
</body>

</html>