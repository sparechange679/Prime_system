<?php
require_once 'config.php';
require_once 'database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['request_reset'])) {
        $email = sanitizeInput($_POST['email']);

        if (empty($email)) {
            $error = "Please enter your email address";
        } elseif (!validateEmail($email)) {
            $error = "Please enter a valid email address";
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();

                if ($db) {
                    // Check if user exists
                    $query = "SELECT user_id, full_name FROM users WHERE email = :email AND status = 'active'";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();
                    $user = $stmt->fetch();

                    if ($user) {
                        // Generate reset token
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                        // Store reset token
                        $token_query = "INSERT INTO password_resets (user_id, token, expires_at, created_at) 
                                       VALUES (:user_id, :token, :expires, NOW())";
                        $token_stmt = $db->prepare($token_query);
                        $token_stmt->bindParam(':user_id', $user['user_id']);
                        $token_stmt->bindParam(':token', $token);
                        $token_stmt->bindParam(':expires', $expires);

                        if ($token_stmt->execute()) {
                            // In a real application, send email here
                            // For now, we'll show the reset link
                            $reset_link = APP_URL . "/reset_password.php?token=" . $token;

                            $success = "Password reset instructions have been sent to your email address. 
                                      <br><br><strong>Reset Link:</strong> <a href='$reset_link' target='_blank'>$reset_link</a>
                                      <br><small class='text-muted'>This link will expire in 1 hour.</small>";

                            // Log the activity
                            logActivity($user['user_id'], 'request_password_reset', 'users', $user['user_id'], 'Password reset requested');
                        } else {
                            $error = "Failed to process reset request";
                        }
                    } else {
                        // Don't reveal if email exists or not for security
                        $success = "If an account with that email exists, password reset instructions have been sent.";
                    }
                } else {
                    $error = "Database connection failed";
                }
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = "An error occurred. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <!-- Logo and Title -->
                        <div class="text-center mb-4">
                            <i class="fas fa-key fa-3x text-warning mb-3"></i>
                            <h2 class="h4 text-dark">Forgot Password?</h2>
                            <p class="text-muted">Enter your email to reset your password</p>
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

                        <!-- Password Reset Form -->
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                        required>
                                </div>
                                <div class="form-text">We'll send you a link to reset your password.</div>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="request_reset" class="btn btn-warning btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <a href="login.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-3">
                    <small class="text-muted">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
                    </small>
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