<?php
session_start();
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($username) || empty($password) || empty($role)) {
        header("Location: login.php?error=All fields are required");
        exit();
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        // Get user with role
        $query = "SELECT u.*, r.role_name 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.role_id 
                  WHERE u.username = :username AND r.role_name = :role AND u.is_active = 1";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':role', $role);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // For demo purposes, accept any password (in real system, use password_verify)
            if ($password === 'admin123' || password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

                // Log the login activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id) 
                              VALUES (:user_id, 'login', 'users', :user_id)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':user_id', $user['user_id']);
                $log_stmt->execute();

                header("Location: dashboard.php");
                exit();
            } else {
                header("Location: login.php?error=Invalid password");
                exit();
            }
        } else {
            header("Location: login.php?error=Invalid username or role");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: login.php?error=System error. Please try again.");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
