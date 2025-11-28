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
$token_valid = false;
$user_id = null;

// Validate reset token
if (isset($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);

    try {
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            // Check if token exists and is valid
            $query = "SELECT pr.*, u.email, u.full_name 
                     FROM password_resets pr 
                     JOIN users u ON pr.user_id = u.user_id 
                     WHERE pr.token = :token AND pr.expires_at > NOW() AND pr.used = 0";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            $reset_data = $stmt->fetch();

            if ($reset_data) {
                $token_valid = true;
                $user_id = $reset_data['user_id'];
            } else {
                $error = "Invalid or expired reset token";
            }
        } else {
            $error = "Database connection failed";
        }
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
} else {
    $error = "No reset token provided";
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token_valid) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password) || empty($confirm_password)) {
        $error = "Both password fields are required";
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();

            if ($db) {
                // Begin transaction
                $db->beginTransaction();

                try {
                    // Update password
                    $update_query = "UPDATE users SET password = :password, updated_at = NOW() WHERE user_id = :user_id";
                    $update_stmt = $db->prepare($update_query);
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $update_stmt->bindParam(':password', $hashed_password);
                    $update_stmt->bindParam(':user_id', $user_id);

                    if ($update_stmt->execute()) {
                        // Mark token as used
                        $mark_used_query = "UPDATE password_resets SET used = 1, used_at = NOW() WHERE token = :token";
                        $mark_used_stmt = $db->prepare($mark_used_query);
                        $mark_used_stmt->bindParam(':token', $token);
                        $mark_used_stmt->execute();

                        // Commit transaction
                        $db->commit();

                        // Log the activity
                        logActivity($user_id, 'reset_password', 'users', $user_id, 'Password reset completed');

                        $success = "Password has been reset successfully! You can now login with your new password.";
                        $token_valid = false; // Hide the form
                    } else {
                        throw new Exception("Failed to update password");
                    }
                } catch (Exception $e) {
                    // Rollback transaction
                    $db->rollBack();
                    throw $e;
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
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
                            <i class="fas fa-lock fa-3x text-success mb-3"></i>
                            <h2 class="h4 text-dark">Reset Password</h2>
                            <p class="text-muted">Enter your new password</p>
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

                        <?php if ($token_valid && !$success): ?>
                            <!-- Password Reset Form -->
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password"
                                            minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                                    <div class="invalid-feedback">Please enter a new password.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback">Please confirm your new password.</div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-2"></i>Reset Password
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <hr class="my-4">

                        <div class="text-center">
                            <a href="login.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sign-in-alt me-1"></i>Back to Login
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

        // Password toggle
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>

</html>