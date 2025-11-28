<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get agent's shipments with client info
try {
    $query = "SELECT s.*, c.company_name, c.contact_person, c.phone, c.email, c.user_id as client_user_id
              FROM shipments s 
              JOIN clients c ON s.client_id = c.client_id 
              WHERE s.agent_id = :user_id 
              ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading shipments: " . $e->getMessage();
    $shipments = [];
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipment_id = isset($_POST['shipment_id']) ? (int)$_POST['shipment_id'] : 0;
    $message_subject = trim($_POST['message_subject']);
    $message_content = trim($_POST['message_content']);
    $message_type = $_POST['message_type'];

    if (empty($message_subject) || empty($message_content)) {
        $message_error = "Subject and message content are required";
    } elseif ($shipment_id <= 0) {
        $message_error = "Please select a shipment.";
    } else {
        try {
            // Validate shipment belongs to this agent and get expected client user
            $chk = $db->prepare("SELECT s.shipment_id, c.user_id AS client_user_id
                                  FROM shipments s
                                  JOIN clients c ON s.client_id = c.client_id
                                  WHERE s.shipment_id = :sid AND s.agent_id = :aid
                                  LIMIT 1");
            $chk->bindParam(':sid', $shipment_id, PDO::PARAM_INT);
            $chk->bindParam(':aid', $user_id, PDO::PARAM_INT);
            $chk->execute();
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new PDOException('Invalid shipment selection.');
            }
            // Ensure recipient matches the shipment's client user
            $recipient_id = isset($_POST['client_user_id']) ? (int)$_POST['client_user_id'] : 0;
            if ($recipient_id !== (int)$row['client_user_id']) {
                throw new PDOException('Selected client does not match the shipment.');
            }
            // Insert message into database
            $insert_query = "INSERT INTO messages (sender_id, recipient_id, shipment_id, subject, content, message_type, created_at) 
                           VALUES (:sender_id, :recipient_id, :shipment_id, :subject, :content, :message_type, NOW())";

            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':sender_id', $user_id);
            $insert_stmt->bindParam(':recipient_id', $recipient_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':shipment_id', $shipment_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':subject', $message_subject);
            $insert_stmt->bindParam(':content', $message_content);
            $insert_stmt->bindParam(':message_type', $message_type);

            if ($insert_stmt->execute()) {
                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                              VALUES (:user_id, 'send_message', 'messages', :message_id, :details)";
                $log_stmt = $db->prepare($log_query);
                $log_details = "Message sent to client: $message_subject";
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':message_id', $db->lastInsertId());
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();

                $message_success = "Message sent successfully to client!";

                // Refresh the page
                header("Location: agent_messaging.php");
                exit();
            } else {
                $message_error = "Failed to send message";
            }
        } catch (PDOException $e) {
            $message_error = "Database error: " . $e->getMessage();
        }
    }
}

// Get sent messages (agent -> client)
try {
    $sent_query = "SELECT m.*, c.company_name, s.tracking_number 
                   FROM messages m 
                   JOIN clients c ON m.recipient_id = c.user_id 
                   JOIN shipments s ON m.shipment_id = s.shipment_id 
                   WHERE m.sender_id = :user_id 
                   ORDER BY m.created_at DESC";
    $sent_stmt = $db->prepare($sent_query);
    $sent_stmt->bindParam(':user_id', $user_id);
    $sent_stmt->execute();
    $sent_messages = $sent_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sent_messages = [];
}

// Get received messages (admin -> agent or client -> agent)
try {
    $received_query = "SELECT m.*, u.full_name as sender_name, s.tracking_number, c.company_name
                       FROM messages m 
                       JOIN users u ON m.sender_id = u.user_id 
                       LEFT JOIN shipments s ON m.shipment_id = s.shipment_id
                       LEFT JOIN clients c ON s.client_id = c.client_id
                       WHERE m.recipient_id = :user_id 
                       ORDER BY m.created_at DESC";
    $recv_stmt = $db->prepare($received_query);
    $recv_stmt->bindParam(':user_id', $user_id);
    $recv_stmt->execute();
    $received_messages = $recv_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $received_messages = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Messaging - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="agent_dashboard.php">
                <i class="fas fa-ship me-2"></i>Prime Cargo Limited
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="agent_dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Agent Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-comments me-2 text-primary"></i>
                            Client Messaging
                        </h2>
                        <p class="card-text">Send messages and updates to your clients</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($message_success)): ?>
            <div class="alert alert-success"><?php echo $message_success; ?></div>
        <?php endif; ?>

        <?php if (isset($message_error)): ?>
            <div class="alert alert-danger"><?php echo $message_error; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Send Message Section -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Send Message to Client</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Select Shipment <span class="text-danger">*</span></label>
                                    <select name="shipment_id" class="form-select" required onchange="updateClientInfo(this.value)">
                                        <option value="">Choose Shipment</option>
                                        <?php foreach ($shipments as $shipment): ?>
                                            <option value="<?php echo $shipment['shipment_id']; ?>"
                                                data-client="<?php echo $shipment['client_user_id']; ?>"
                                                data-company="<?php echo htmlspecialchars($shipment['company_name']); ?>"
                                                data-tracking="<?php echo $shipment['tracking_number']; ?>">
                                                <?php echo $shipment['tracking_number']; ?> - <?php echo htmlspecialchars($shipment['company_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Message Type</label>
                                    <select name="message_type" class="form-select">
                                        <option value="update">Status Update</option>
                                        <option value="document_request">Document Request</option>
                                        <option value="clearance_update">Clearance Update</option>
                                        <option value="payment_reminder">Payment Reminder</option>
                                        <option value="general">General Information</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Client Information</label>
                                <div id="clientInfo" class="alert alert-info">
                                    <p class="mb-1"><strong>Company:</strong> <span id="companyName">Select a shipment first</span></p>
                                    <p class="mb-1"><strong>Tracking:</strong> <span id="trackingNumber">-</span></p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message Subject <span class="text-danger">*</span></label>
                                <input type="text" name="message_subject" class="form-control"
                                    placeholder="Enter message subject" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message Content <span class="text-danger">*</span></label>
                                <textarea name="message_content" class="form-control" rows="5"
                                    placeholder="Type your message here..." required></textarea>
                            </div>

                            <input type="hidden" name="client_user_id" id="clientUserId">

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Templates Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Templates</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Status Update</h6>
                            <button class="btn btn-outline-secondary btn-sm mb-2" onclick="useTemplate('status')">
                                Use Template
                            </button>
                            <small class="text-muted d-block">
                                "Your shipment [TRACKING] status has been updated to [STATUS]. We'll keep you informed of any further developments."
                            </small>
                        </div>

                        <div class="mb-3">
                            <h6>Document Request</h6>
                            <button class="btn btn-outline-secondary btn-sm mb-2" onclick="useTemplate('document')">
                                Use Template
                            </button>
                            <small class="text-muted d-block">
                                "Please provide additional documents for shipment [TRACKING] to proceed with clearance. Required: [DOCUMENTS]"
                            </small>
                        </div>

                        <div class="mb-3">
                            <h6>Clearance Update</h6>
                            <button class="btn btn-outline-secondary btn-sm mb-2" onclick="useTemplate('clearance')">
                                Use Template
                            </button>
                            <small class="text-muted d-block">
                                "Clearance processing for shipment [TRACKING] is in progress. Estimated completion: [DATE]"
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages History (Sent / Received Tabs) -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Messages</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab">
                                    Sent (<?php echo count($sent_messages); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="received-tab" data-bs-toggle="tab" data-bs-target="#received" type="button" role="tab">
                                    Received (<?php echo count($received_messages ?? []); ?>)
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content mt-3">
                            <div class="tab-pane fade show active" id="sent" role="tabpanel">
                                <?php if (empty($sent_messages)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                                        <h6>No Sent Messages</h6>
                                        <p class="text-muted">Messages you send will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($sent_messages as $message): ?>
                                        <div class="border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                                <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1 text-muted">To: <?php echo htmlspecialchars($message['company_name']); ?> | Shipment: <?php echo htmlspecialchars($message['tracking_number']); ?></p>
                                            <p class="mb-1"><?php echo htmlspecialchars($message['content']); ?></p>
                                            <span class="badge bg-<?php echo $message['message_type'] === 'update' ? 'info' : 'secondary'; ?>"><?php echo ucfirst($message['message_type']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="tab-pane fade" id="received" role="tabpanel">
                                <?php if (empty($received_messages)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h6>No Received Messages</h6>
                                        <p class="text-muted">Messages sent to you will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($received_messages as $message): ?>
                                        <div class="border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                                <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1 text-muted">From: <?php echo htmlspecialchars($message['sender_name']); ?><?php if ($message['tracking_number']): ?> | Shipment: <?php echo htmlspecialchars($message['tracking_number']); ?><?php endif; ?></p>
                                            <p class="mb-1"><?php echo htmlspecialchars($message['content']); ?></p>
                                            <span class="badge bg-<?php echo $message['message_type'] === 'update' ? 'info' : 'secondary'; ?>"><?php echo ucfirst($message['message_type']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateClientInfo(shipmentId) {
            const select = document.querySelector('select[name="shipment_id"]');
            const option = select.options[select.selectedIndex];

            if (shipmentId) {
                document.getElementById('companyName').textContent = option.getAttribute('data-company');
                document.getElementById('trackingNumber').textContent = option.getAttribute('data-tracking');
                document.getElementById('clientUserId').value = option.getAttribute('data-client');
            } else {
                document.getElementById('companyName').textContent = 'Select a shipment first';
                document.getElementById('trackingNumber').textContent = '-';
                document.getElementById('clientUserId').value = '';
            }
        }

        function useTemplate(type) {
            const tracking = document.getElementById('trackingNumber').textContent;
            const subject = document.querySelector('input[name="message_subject"]');
            const content = document.querySelector('textarea[name="message_content"]');

            if (tracking === '-') {
                alert('Please select a shipment first');
                return;
            }

            switch (type) {
                case 'status':
                    subject.value = `Status Update - Shipment ${tracking}`;
                    content.value = `Dear Client,\n\nYour shipment ${tracking} status has been updated. We'll keep you informed of any further developments.\n\nBest regards,\nPrime Cargo Team`;
                    break;
                case 'document':
                    subject.value = `Document Request - Shipment ${tracking}`;
                    content.value = `Dear Client,\n\nPlease provide additional documents for shipment ${tracking} to proceed with clearance. Required documents will be specified by our team.\n\nBest regards,\nPrime Cargo Team`;
                    break;
                case 'clearance':
                    subject.value = `Clearance Update - Shipment ${tracking}`;
                    content.value = `Dear Client,\n\nClearance processing for shipment ${tracking} is in progress. We will notify you once completed.\n\nBest regards,\nPrime Cargo Team`;
                    break;
            }
        }
    </script>
</body>

</html>