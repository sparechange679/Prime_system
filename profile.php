<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get user and client information
try {
    $query = "SELECT u.*, c.* FROM users u 
              JOIN clients c ON u.user_id = c.user_id 
              WHERE u.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $company_name = trim($_POST['company_name']);
    $business_type = trim($_POST['business_type']);
    $tax_number = trim($_POST['tax_number']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $update_error = "First name, last name, and email are required";
    } else {
        try {
            // Update user information
            $update_user = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                           email = :email, phone = :phone WHERE user_id = :user_id";
            $stmt = $db->prepare($update_user);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            // Update client information
            $update_client = "UPDATE clients SET company_name = :company_name, business_type = :business_type,
                             tax_number = :tax_number, address = :address, city = :city WHERE user_id = :user_id";
            $stmt = $db->prepare($update_client);
            $stmt->bindParam(':company_name', $company_name);
            $stmt->bindParam(':business_type', $business_type);
            $stmt->bindParam(':tax_number', $tax_number);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':city', $city);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            $update_success = "Profile updated successfully!";

            // Update session
            $_SESSION['full_name'] = $first_name . ' ' . $last_name;
        } catch (PDOException $e) {
            $update_error = "Update failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Prime Cargo Limited</title>
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
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-user-cog me-2"></i>My Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($update_success)): ?>
                            <div class="alert alert-success"><?php echo $update_success; ?></div>
                        <?php endif; ?>

                        <?php if (isset($update_error)): ?>
                            <div class="alert alert-danger"><?php echo $update_error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <h6 class="mb-3">Personal Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control"
                                        value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control"
                                        value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3">Company Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" name="company_name" class="form-control"
                                        value="<?php echo htmlspecialchars($user['company_name']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Business Type</label>
                                    <input type="text" name="business_type" class="form-control"
                                        value="<?php echo htmlspecialchars($user['business_type']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax Number</label>
                                    <input type="text" name="tax_number" class="form-control"
                                        value="<?php echo htmlspecialchars($user['tax_number']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control"
                                        value="<?php echo htmlspecialchars($user['city']); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>