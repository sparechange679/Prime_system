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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $full_name = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $company_name = sanitizeInput($_POST['company_name']);
        $contact_person = sanitizeInput($_POST['contact_person']);
        $address = sanitizeInput($_POST['address']);
        $city = sanitizeInput($_POST['city']);
        $country = sanitizeInput($_POST['country']);

        // Validation
        if (empty($full_name) || empty($email) || empty($password) || empty($company_name)) {
            $error = "All required fields must be filled";
        } elseif (!validateEmail($email)) {
            $error = "Please enter a valid email address";
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();

                if ($db) {
                    // Check if email already exists
                    $check_query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':email', $email);
                    $check_stmt->execute();

                    if ($check_stmt->fetch()['count'] > 0) {
                        $error = "Email address is already registered";
                    } else {
                        // Begin transaction
                        $db->beginTransaction();

                        try {
                            // Insert user
                            $user_query = "INSERT INTO users (full_name, email, phone, password, role, status, created_at) 
                                          VALUES (:full_name, :email, :phone, :password, 'client', 'active', NOW())";

                            $user_stmt = $db->prepare($user_query);
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                            $user_stmt->bindParam(':full_name', $full_name);
                            $user_stmt->bindParam(':email', $email);
                            $user_stmt->bindParam(':phone', $phone);
                            $user_stmt->bindParam(':password', $hashed_password);

                            if ($user_stmt->execute()) {
                                $user_id = $db->lastInsertId();

                                // Insert client details
                                $client_query = "INSERT INTO clients (user_id, company_name, contact_person, address, city, country, created_at) 
                                               VALUES (:user_id, :company_name, :contact_person, :address, :city, :country, NOW())";

                                $client_stmt = $db->prepare($client_query);
                                $client_stmt->bindParam(':user_id', $user_id);
                                $client_stmt->bindParam(':company_name', $company_name);
                                $client_stmt->bindParam(':contact_person', $contact_person);
                                $client_stmt->bindParam(':address', $address);
                                $client_stmt->bindParam(':city', $city);
                                $client_stmt->bindParam(':country', $country);

                                if ($client_stmt->execute()) {
                                    // Commit transaction
                                    $db->commit();

                                    // Log activity
                                    logActivity($user_id, 'register', 'users', $user_id, 'New client registered: ' . $company_name);

                                    $success = "Registration successful! You can now login with your email and password.";

                                    // Clear form data
                                    $_POST = array();
                                } else {
                                    throw new Exception("Failed to create client profile");
                                }
                            } else {
                                throw new Exception("Failed to create user account");
                            }
                        } catch (Exception $e) {
                            // Rollback transaction
                            $db->rollBack();
                            throw $e;
                        }
                    }
                } else {
                    $error = "Database connection failed";
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = "An error occurred during registration. Please try again.";
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
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-4">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <!-- Logo and Title -->
                        <div class="text-center mb-4">
                            <i class="fas fa-ship fa-3x text-primary mb-3"></i>
                            <h2 class="h4 text-dark"><?php echo APP_NAME; ?></h2>
                            <p class="text-muted">Client Registration</p>
                            <div class="alert alert-info alert-sm">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Client Accounts Only:</strong> This registration is for cargo owners who need clearance services. Agents and Keepers are registered by administrators.
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

                        <!-- Registration Form -->
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <!-- Personal Information -->
                            <h5 class="mb-3"><i class="fas fa-user me-2"></i>Personal Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                        value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                        required>
                                    <div class="invalid-feedback">Please enter your full name.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                        required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="country" class="form-label">Country</label>
                                    <select class="form-select" id="country" name="country">
                                        <option value="">Select Country</option>
                                        <option value="Malawi" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Malawi') ? 'selected' : ''; ?>>Malawi</option>
                                        <option value="Zambia" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Zambia') ? 'selected' : ''; ?>>Zambia</option>
                                        <option value="Zimbabwe" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Zimbabwe') ? 'selected' : ''; ?>>Zimbabwe</option>
                                        <option value="Tanzania" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Tanzania') ? 'selected' : ''; ?>>Tanzania</option>
                                        <option value="Mozambique" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Mozambique') ? 'selected' : ''; ?>>Mozambique</option>
                                        <option value="South Africa" <?php echo (isset($_POST['country']) && $_POST['country'] === 'South Africa') ? 'selected' : ''; ?>>South Africa</option>
                                        <option value="Other" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Company Information -->
                            <h5 class="mb-3 mt-4"><i class="fas fa-building me-2"></i>Company Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="company_name" name="company_name"
                                        value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>"
                                        required>
                                    <div class="invalid-feedback">Please enter your company name.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="contact_person" class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person"
                                        value="<?php echo isset($_POST['contact_person']) ? htmlspecialchars($_POST['contact_person']) : ''; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city"
                                        value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                </div>
                            </div>

                            <!-- Account Security -->
                            <h5 class="mb-3 mt-4"><i class="fas fa-lock me-2"></i>Account Security</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password"
                                            minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                                    <div class="invalid-feedback">Please enter a password.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback">Please confirm your password.</div>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and
                                    <a href="#" class="text-decoration-none">Privacy Policy</a>
                                </label>
                                <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-0">Already have an account?</p>
                            <a href="login.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-sign-in-alt me-1"></i>Sign In
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

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
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