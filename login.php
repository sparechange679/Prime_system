<?php
require_once 'config.php';
require_once 'database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header("Location: admin_dashboard.php");
            break;
        case 'agent':
            header("Location: agent_dashboard.php");
            break;
        case 'client':
            header("Location: dashboard.php");
            break;
        case 'keeper':
            header("Location: verify_document.php");
            break;
        default:
            header("Location: dashboard.php");
    }
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } elseif (!validateEmail($email)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();

            if ($db) {
                // Check if user exists and is active
                $query = "SELECT u.*, c.company_name, c.contact_person 
                         FROM users u 
                         LEFT JOIN clients c ON u.user_id = c.user_id 
                         WHERE u.email = :email AND u.status = 'active'";

                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Check if account is locked
                    if (isset($user['login_attempts']) && $user['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
                        $last_attempt = strtotime($user['last_login_attempt'] ?? '1970-01-01');
                        if (time() - $last_attempt < LOGIN_LOCKOUT_TIME) {
                            $remaining_time = LOGIN_LOCKOUT_TIME - (time() - $last_attempt);
                            $error = "Account temporarily locked. Try again in " . ceil($remaining_time / 60) . " minutes.";
                        } else {
                            // Reset login attempts after lockout period
                            $reset_query = "UPDATE users SET login_attempts = 0 WHERE user_id = :user_id";
                            $reset_stmt = $db->prepare($reset_query);
                            $reset_stmt->bindParam(':user_id', $user['user_id']);
                            $reset_stmt->execute();
                        }
                    }

                    if (empty($error)) {
                        // Reset login attempts on successful login
                        $reset_query = "UPDATE users SET login_attempts = 0, last_login = NOW() WHERE user_id = :user_id";
                        $reset_stmt = $db->prepare($reset_query);
                        $reset_stmt->bindParam(':user_id', $user['user_id']);
                        $reset_stmt->execute();

                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['login_time'] = time();

                        // Set additional session data for clients
                        if ($user['role'] === 'client' && isset($user['company_name'])) {
                            $_SESSION['company_name'] = $user['company_name'];
                            $_SESSION['contact_person'] = $user['contact_person'];
                        }

                        // Log successful login
                        logActivity($user['user_id'], 'login', 'users', $user['user_id'], 'User logged in successfully');

                        // Notify admin when agents log in
                        if ($user['role'] === 'agent') {
                            notifyAdmin(
                                'Agent Login Alert',
                                "Agent {$user['full_name']} ({$user['email']}) has logged into the system.",
                                'info',
                                'users',
                                $user['user_id']
                            );
                        }

                        // Redirect based on role
                        switch ($user['role']) {
                            case 'admin':
                                header("Location: admin_dashboard.php");
                                break;
                            case 'agent':
                                header("Location: agent_dashboard.php");
                                break;
                            case 'client':
                                header("Location: dashboard.php");
                                break;
                            case 'keeper':
                                header("Location: verify_document.php");
                                break;
                            default:
                                header("Location: dashboard.php");
                        }
                        exit();
                    }
                } else {
                    // Increment login attempts
                    if ($user) {
                        $attempts = ($user['login_attempts'] ?? 0) + 1;
                        $update_query = "UPDATE users SET login_attempts = :attempts, last_login_attempt = NOW() WHERE user_id = :user_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':attempts', $attempts);
                        $update_stmt->bindParam(':user_id', $user['user_id']);
                        $update_stmt->execute();

                        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                            $error = "Too many failed attempts. Account locked for " . (LOGIN_LOCKOUT_TIME / 60) . " minutes.";
                        } else {
                            $remaining = LOGIN_MAX_ATTEMPTS - $attempts;
                            $error = "Invalid email or password. {$remaining} attempts remaining.";
                        }
                    } else {
                        $error = "Invalid email or password";
                    }
                }
            } else {
                $error = "Database connection failed";
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
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
    <title>Login - <?php echo APP_NAME; ?></title>
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
                            <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                            <h2 class="h4 text-dark"><?php echo APP_NAME; ?></h2>
                            <p class="text-muted">Automated Cargo Clearance System</p>
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

                        <!-- Login Form -->
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
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password is required.</div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>
                            </div>
                        </form>

                        <!-- Links -->
                        <div class="text-center mt-4">
                            <a href="forgot_password.php" class="text-decoration-none">
                                <i class="fas fa-key me-1"></i>Forgot Password?
                            </a>
                        </div>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-0">Need to ship cargo?</p>
                            <p class="text-muted small mb-2">Create a client account to submit cargo for clearance</p>
                            <a href="register.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-plus me-1"></i>Create Client Account
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
        document.getElementById('togglePassword').addEventListener('click', function() {
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
    </script>
</body>

</html>