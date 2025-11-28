<?php
require_once 'config.php';
require_once 'database.php';
require_once 'includes/FileHandler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$filepath = '';
$originalName = '';

// Get file ID from URL
if (isset($_GET['id'])) {
    $file_id = (int)$_GET['id'];

    try {
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            // Get file information
            $query = "SELECT sd.*, s.tracking_number, c.company_name 
                     FROM shipment_documents sd 
                     JOIN shipments s ON sd.shipment_id = s.shipment_id 
                     JOIN clients c ON s.client_id = c.client_id 
                     WHERE sd.document_id = :file_id";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':file_id', $file_id);
            $stmt->execute();
            $document = $stmt->fetch();

            if ($document) {
                // Check if user has permission to download this file
                $canDownload = false;

                switch ($_SESSION['role']) {
                    case 'admin':
                        // Admin can download any file
                        $canDownload = true;
                        break;

                    case 'agent':
                        // Agent can download files from their shipments
                        if ($document['agent_id'] == $_SESSION['user_id']) {
                            $canDownload = true;
                        }
                        break;

                    case 'client':
                        // Client can download files from their own shipments
                        if ($document['client_id'] == $_SESSION['user_id']) {
                            $canDownload = true;
                        }
                        break;

                    case 'keeper':
                        // Keeper can download verification documents
                        if ($document['document_type'] === 'verification') {
                            $canDownload = true;
                        }
                        break;
                }

                if ($canDownload) {
                    $filepath = $document['file_path'];
                    $originalName = $document['original_filename'];

                    // Log the download
                    logActivity(
                        $_SESSION['user_id'],
                        'download_document',
                        'shipment_documents',
                        $file_id,
                        "Downloaded: $originalName for shipment: " . $document['tracking_number']
                    );
                } else {
                    $error = "You don't have permission to download this file";
                }
            } else {
                $error = "File not found";
            }
        } else {
            $error = "Database connection failed";
        }
    } catch (Exception $e) {
        error_log("Download error: " . $e->getMessage());
        $error = "An error occurred while processing your request";
    }
} else {
    $error = "No file specified";
}

// Handle the download
if (empty($error) && !empty($filepath)) {
    $fileHandler = new FileHandler();

    if ($fileHandler->fileExists($filepath)) {
        // Download the file
        if ($fileHandler->downloadFile($filepath, $originalName)) {
            exit(); // File was sent, stop execution
        } else {
            $error = "Failed to download file";
        }
    } else {
        $error = "File not found on server";
    }
}

// If we get here, there was an error
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Error - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5 text-center">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h4 class="text-danger">Download Error</h4>
                        <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>

                        <hr class="my-4">

                        <div class="d-flex gap-2 justify-content-center">
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Go Back
                            </a>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>