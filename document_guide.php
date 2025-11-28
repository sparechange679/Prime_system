<?php
require_once 'config.php';
require_once 'database.php';
require_once 'document_types.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Requirements Guide - Prime Cargo Limited</title>
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
                        <h2><i class="fas fa-book me-2"></i>Document Requirements Guide</h2>
                        <p class="text-muted">Complete guide to documents required for cargo clearance</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Important Notice -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Important:</strong> This guide outlines all documents that may be required for cargo clearance.
            Required documents are marked with red badges. Upload all required documents to avoid clearance delays.
        </div>

        <!-- Document Categories -->
        <?php foreach ($DOCUMENT_TYPES as $category => $category_data): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h4 class="mb-0">
                        <i class="fas fa-folder me-2 text-primary"></i>
                        <?php echo htmlspecialchars($category_data['title']); ?>
                        <?php if ($category_data['required']): ?>
                            <span class="badge bg-danger ms-2">Required Category</span>
                        <?php endif; ?>
                    </h4>
                    <p class="text-muted mb-0 mt-1"><?php echo htmlspecialchars($category_data['description']); ?></p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($category_data['types'] as $doc_type => $doc_data): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">
                                                <?php echo htmlspecialchars($doc_data['name']); ?>
                                            </h6>
                                            <?php if ($doc_data['required']): ?>
                                                <span class="badge bg-danger">Required</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Optional</span>
                                            <?php endif; ?>
                                        </div>

                                        <p class="card-text small text-muted">
                                            <?php echo htmlspecialchars($doc_data['description']); ?>
                                        </p>

                                        <div class="mt-auto">
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <small class="text-muted">File Types</small>
                                                    <div class="badge bg-light text-dark">
                                                        <?php echo strtoupper(implode(', ', $doc_data['file_types'])); ?>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Max Size</small>
                                                    <div class="badge bg-light text-dark">
                                                        <?php echo $doc_data['max_size']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Upload Guidelines -->
        <div class="row mt-4">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success"><i class="fas fa-check-circle me-2"></i>Best Practices</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Ensure documents are clear and legible</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Use high-quality scans or photos</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Include all required information</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Verify document authenticity</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Keep original documents safe</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Common Issues</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-times text-danger me-2"></i>Blurry or unclear documents</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Missing required information</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Expired certificates</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Incorrect file formats</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Files too large</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Processing Times</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="text-primary">Document Review</h6>
                            <p class="small text-muted mb-1">Standard: 1-2 business days</p>
                            <p class="small text-muted mb-1">Express: Same day (additional fee)</p>
                        </div>

                        <div class="mb-3">
                            <h6 class="text-primary">Customs Clearance</h6>
                            <p class="small text-muted mb-1">Standard: 3-5 business days</p>
                            <p class="small text-muted mb-1">Express: 1-2 business days</p>
                        </div>

                        <div class="mb-3">
                            <h6 class="text-primary">Release Order</h6>
                            <p class="small text-muted mb-1">Standard: 1 business day</p>
                            <p class="small text-muted mb-1">Express: Same day</p>
                        </div>

                        <div class="alert alert-info small">
                            <i class="fas fa-info-circle me-1"></i>
                            Processing times may vary based on document complexity and customs workload.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-headset me-2"></i>Need Help?</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <i class="fas fa-phone fa-2x text-primary mb-2"></i>
                                <h6>Call Us</h6>
                                <p class="text-muted">+265 1 234 567</p>
                                <p class="small text-muted">Mon-Fri: 8:00 AM - 5:00 PM</p>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-envelope fa-2x text-primary mb-2"></i>
                                <h6>Email Support</h6>
                                <p class="text-muted">support@primecargo.mw</p>
                                <p class="small text-muted">Response within 24 hours</p>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-comments fa-2x text-primary mb-2"></i>
                                <h6>Live Chat</h6>
                                <p class="text-muted">Available on dashboard</p>
                                <p class="small text-muted">Real-time assistance</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>