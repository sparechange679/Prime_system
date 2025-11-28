<?php
session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'document_types.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get shipment ID from URL
$shipment_id = isset($_GET['shipment_id']) ? (int)$_GET['shipment_id'] : 0;

if (!$shipment_id) {
    header("Location: dashboard.php");
    exit();
}

// Verify shipment belongs to this client
try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        $query = "SELECT s.*, c.company_name FROM shipments s 
                  JOIN clients c ON s.client_id = c.client_id 
                  WHERE s.shipment_id = :shipment_id AND c.user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':shipment_id', $shipment_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $shipment = $stmt->fetch();

        if (!$shipment) {
            header("Location: dashboard.php");
            exit();
        }
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
    $document_type = sanitizeInput($_POST['document_type']);
    $description = sanitizeInput($_POST['description']);

    if (empty($document_type)) {
        $error = "Please select a document type";
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a valid file to upload";
    } else {
        $file = $_FILES['document_file'];
        $file_name = $file['name'];
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate file type
        $allowed_types = getAllowedFileTypes();
        if (!in_array($file_ext, $allowed_types)) {
            $error = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
        } else {
            // Validate file size (max 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB in bytes
            if ($file_size > $max_size) {
                $error = "File size too large. Maximum size: 10MB";
            } else {
                try {
                    // Create upload directory if it doesn't exist
                    $upload_dir = "uploads/shipments/{$shipment_id}/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Generate unique filename
                    $unique_filename = uniqid() . '_' . time() . '.' . $file_ext;
                    $file_path = $upload_dir . $unique_filename;

                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Save document record to database
                        $query = "INSERT INTO shipment_documents (shipment_id, document_type, description, 
                                  original_filename, file_path, file_size, uploaded_by, uploaded_at) 
                                  VALUES (:shipment_id, :document_type, :description, :original_filename, 
                                  :file_path, :file_size, :uploaded_by, NOW())";

                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':shipment_id', $shipment_id);
                        $stmt->bindParam(':document_type', $document_type);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':original_filename', $file_name);
                        $stmt->bindParam(':file_path', $file_path);
                        $stmt->bindParam(':file_size', $file_size);
                        $stmt->bindParam(':uploaded_by', $user_id);

                        if ($stmt->execute()) {
                            // Log activity
                            logActivity(
                                $user_id,
                                'upload_document',
                                'shipment_documents',
                                $db->lastInsertId(),
                                "Uploaded document: $document_type for shipment #$shipment_id"
                            );

                            $success = "Document uploaded successfully!";

                            // Update shipment status if documents are submitted
                            $doc_count_query = "SELECT COUNT(*) as count FROM shipment_documents WHERE shipment_id = :shipment_id";
                            $doc_stmt = $db->prepare($doc_count_query);
                            $doc_stmt->bindParam(':shipment_id', $shipment_id);
                            $doc_stmt->execute();
                            $doc_count = $doc_stmt->fetch()['count'];

                            if ($doc_count >= 3) { // At least 3 documents uploaded
                                $update_query = "UPDATE shipments SET status = 'pending' WHERE shipment_id = :shipment_id";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->bindParam(':shipment_id', $shipment_id);
                                $update_stmt->execute();
                            }
                        } else {
                            $error = "Failed to save document record";
                        }
                    } else {
                        $error = "Failed to upload file";
                    }
                } catch (Exception $e) {
                    $error = "Upload error: " . $e->getMessage();
                }
            }
        }
    }
}

// Get uploaded documents for this shipment
$uploaded_documents = [];
try {
    if ($db) {
        $query = "SELECT * FROM shipment_documents WHERE shipment_id = :shipment_id ORDER BY uploaded_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':shipment_id', $shipment_id);
        $stmt->execute();
        $uploaded_documents = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-ship me-2"></i>Prime Cargo Limited
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
                        <a class="nav-link" href="documents.php">
                            <i class="fas fa-file-alt me-1"></i>Documents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="new_shipment.php">
                            <i class="fas fa-plus me-1"></i>New Shipment
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-2"></i>Profile</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-upload me-2"></i>Upload Documents</h2>
                        <p class="text-muted">Shipment #<?php echo $shipment['tracking_number']; ?> - <?php echo htmlspecialchars($shipment['goods_description']); ?></p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Document Upload Form -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>Upload New Document</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="document_type" class="form-label">Document Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="document_type" name="document_type" required>
                                        <option value="">Select Document Type</option>
                                        <?php foreach ($DOCUMENT_TYPES as $category => $category_data): ?>
                                            <optgroup label="<?php echo htmlspecialchars($category_data['title']); ?>">
                                                <?php foreach ($category_data['types'] as $doc_type => $doc_data): ?>
                                                    <option value="<?php echo $doc_type; ?>">
                                                        <?php echo htmlspecialchars($doc_data['name']); ?>
                                                        <?php if ($doc_data['required']): ?>
                                                            <span class="text-danger">*</span>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a document type.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="document_file" class="form-label">Document File <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="document_file" name="document_file"
                                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                    <div class="form-text">Max size: 10MB. Allowed: PDF, JPG, PNG, DOC, DOCX</div>
                                    <div class="invalid-feedback">Please select a file to upload.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"
                                    placeholder="Brief description of the document..."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="upload_document" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Upload Document
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Document Types Guide -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Document Types Guide</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($DOCUMENT_TYPES as $category => $category_data): ?>
                            <div class="mb-3">
                                <h6 class="text-primary"><?php echo htmlspecialchars($category_data['title']); ?></h6>
                                <p class="small text-muted"><?php echo htmlspecialchars($category_data['description']); ?></p>

                                <?php foreach ($category_data['types'] as $doc_type => $doc_data): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small">
                                            <?php echo htmlspecialchars($doc_data['name']); ?>
                                            <?php if ($doc_data['required']): ?>
                                                <span class="badge bg-danger ms-1">Required</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="badge bg-secondary"><?php echo $doc_data['max_size']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Uploaded Documents -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>Uploaded Documents</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($uploaded_documents)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <p>No documents uploaded yet.</p>
                                <p class="small">Upload the required documents to proceed with clearance.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Document Type</th>
                                            <th>Description</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Uploaded</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($uploaded_documents as $doc): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo ucwords(str_replace('_', ' ', $doc['document_type'])); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($doc['description'] ?: 'No description'); ?></td>
                                                <td>
                                                    <i class="fas fa-file me-2"></i>
                                                    <?php echo htmlspecialchars($doc['original_filename']); ?>
                                                </td>
                                                <td><?php echo formatFileSize($doc['file_size']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($doc['uploaded_at'])); ?></td>
                                                <td>
                                                    <?php if ($doc['verified']): ?>
                                                        <span class="badge bg-success">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
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
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>

</html>

<?php
// Helper function to format file size
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>