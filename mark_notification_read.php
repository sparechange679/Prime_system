<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];
    $user_id = $_SESSION['user_id'];

    try {
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            // Mark notification as read
            $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = :notification_id AND user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':notification_id', $notification_id);
            $stmt->bindParam(':user_id', $user_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
