<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get document ID from URL
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$document_id) {
    header("Location: documents.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

try {
    // Get document details
    $query = "SELECT * FROM shipment_documents WHERE document_id = :document_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':document_id', $document_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        header("Location: documents.php?error=Document not found");
        exit();
    }

    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete the physical file
    if (file_exists($document['file_path'])) {
        unlink($document['file_path']);
    }

    // Delete from database
    $delete_query = "DELETE FROM shipment_documents WHERE document_id = :document_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':document_id', $document_id);
    $delete_stmt->execute();

    // Log the deletion
    $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id) 
                  VALUES (:user_id, 'delete_document', 'shipment_documents', :document_id)";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->bindParam(':user_id', $user_id);
    $log_stmt->bindParam(':document_id', $document_id);
    $log_stmt->execute();

    header("Location: documents.php?success=Document deleted successfully");
    exit();
} catch (PDOException $e) {
    header("Location: documents.php?error=Error deleting document");
    exit();
}
