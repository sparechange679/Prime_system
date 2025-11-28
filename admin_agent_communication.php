<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get parameters from URL
$selected_agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;
$selected_shipment_id = isset($_GET['shipment_id']) ? (int)$_GET['shipment_id'] : null;

// Get all agents
try {
    $agents_query = "SELECT u.*, 
                           COUNT(s.shipment_id) as active_shipments,
                           MAX(s.created_at) as last_shipment_date
                    FROM users u 
                    LEFT JOIN shipments s ON u.user_id = s.agent_id AND s.status NOT IN ('completed', 'cancelled')
                    WHERE u.role = 'agent' 
                    GROUP BY u.user_id
                    ORDER BY u.full_name ASC";
    $stmt = $db->prepare($agents_query);
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $agents = [];
}

// Get shipments for selected agent
$agent_shipments = [];
if ($selected_agent_id) {
    try {
        $shipments_query = "SELECT s.*, c.company_name, c.contact_person
                           FROM shipments s 
                           JOIN clients c ON s.client_id = c.client_id 
                           WHERE s.agent_id = :agent_id 
                           ORDER BY s.created_at DESC";
        $stmt = $db->prepare($shipments_query);
        $stmt->bindParam(':agent_id', $selected_agent_id);
        $stmt->execute();
        $agent_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $agent_shipments = [];
    }
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recipient_id = (int)$_POST['recipient_id'];
    $shipment_id = !empty($_POST['shipment_id']) ? (int)$_POST['shipment_id'] : null;
    $subject = trim($_POST['subject']);
    $content = trim($_POST['content']);
    $message_type = $_POST['message_type'];

    if (empty($recipient_id) || empty($subject) || empty($content)) {
        $message_error = "All fields are required";
    } elseif (empty($shipment_id)) {
        $message_error = "Please select a shipment.";
    } else {
        try {
            // Validate that shipment exists
            $chk = $db->prepare("SELECT 1 FROM shipments WHERE shipment_id = :sid LIMIT 1");
            $chk->bindParam(':sid', $shipment_id, PDO::PARAM_INT);
            $chk->execute();
            if ($chk->rowCount() === 0) {
                throw new PDOException('Invalid shipment selected.');
            }
            $insert_query = "INSERT INTO messages (sender_id, recipient_id, shipment_id, subject, content, message_type, created_at) 
                             VALUES (:sender_id, :recipient_id, :shipment_id, :subject, :content, :message_type, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':sender_id', $user_id);
            $insert_stmt->bindParam(':recipient_id', $recipient_id);
            $insert_stmt->bindParam(':shipment_id', $shipment_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':subject', $subject);
            $insert_stmt->bindParam(':content', $content);
            $insert_stmt->bindParam(':message_type', $message_type);

            if ($insert_stmt->execute()) {
                // Log the activity
                $log_query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                              VALUES (:user_id, 'send_message', 'messages', :message_id, :details)";
                $log_stmt = $db->prepare($log_query);
                $message_id = $db->lastInsertId();
                $log_details = "Message sent to agent: $subject";
                $log_stmt->bindParam(':user_id', $user_id);
                $log_stmt->bindParam(':message_id', $message_id);
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();

                $message_success = "Message sent successfully!";

                // Redirect to clear form
                $redirect_url = "admin_agent_communication.php";
                if ($selected_agent_id) $redirect_url .= "?agent_id=$selected_agent_id";
                if ($selected_shipment_id) $redirect_url .= ($selected_agent_id ? "&" : "?") . "shipment_id=$selected_shipment_id";
                header("Location: $redirect_url");
                exit();
            } else {
                $message_error = "Failed to send message";
            }
        } catch (PDOException $e) {
            $message_error = "Database error: " . $e->getMessage();
        }
    }
}

// Get sent messages (admin to agent)
try {
    $sent_query = "SELECT m.*, u.full_name as recipient_name, u.email as recipient_email,
                          s.tracking_number, c.company_name
                   FROM messages m 
                   JOIN users u ON m.recipient_id = u.user_id 
                   LEFT JOIN shipments s ON m.shipment_id = s.shipment_id
                   LEFT JOIN clients c ON s.client_id = c.client_id
                   WHERE m.sender_id = :admin_id 
                   ORDER BY m.created_at DESC 
                   LIMIT 20";
    $stmt = $db->prepare($sent_query);
    $stmt->bindParam(':admin_id', $user_id);
    $stmt->execute();
    $sent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sent_messages = [];
}

// Get received messages (agent to admin)
try {
    $received_query = "SELECT m.*, u.full_name as sender_name, u.email as sender_email,
                              s.tracking_number, c.company_name
                       FROM messages m 
                       JOIN users u ON m.sender_id = u.user_id 
                       LEFT JOIN shipments s ON m.shipment_id = s.shipment_id
                       LEFT JOIN clients c ON s.client_id = c.client_id
                       WHERE m.recipient_id = :admin_id 
                       ORDER BY m.created_at DESC 
                       LIMIT 20";
    $stmt = $db->prepare($received_query);
    $stmt->bindParam(':admin_id', $user_id);
    $stmt->execute();
    $received_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $received_messages = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Communication - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-shield-alt me-2"></i>Prime Cargo Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="admin_dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Admin Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-comments me-2"></i>
                            Agent Communication
                        </h2>
                        <p class="card-text">Direct messaging system for communicating with agents</p>
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
            <!-- Send Message Form -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Send Message to Agent</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Select Agent <span class="text-danger">*</span></label>
                                <select name="recipient_id" class="form-select" required onchange="loadAgentShipments(this.value)">
                                    <option value="">Choose an agent...</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['user_id']; ?>"
                                            <?php echo $selected_agent_id == $agent['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['full_name']); ?>
                                            (<?php echo $agent['active_shipments']; ?> active shipments)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Link to Shipment <span class="text-danger">*</span></label>
                                <select name="shipment_id" class="form-select" id="shipmentSelect" required>
                                    <option value="">Choose a shipment...</option>
                                    <?php if ($selected_agent_id && !empty($agent_shipments)): ?>
                                        <?php foreach ($agent_shipments as $shipment): ?>
                                            <option value="<?php echo $shipment['shipment_id']; ?>"
                                                <?php echo $selected_shipment_id == $shipment['shipment_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($shipment['tracking_number']); ?> -
                                                <?php echo htmlspecialchars($shipment['company_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message Type</label>
                                <select name="message_type" class="form-select">
                                    <option value="general">General</option>
                                    <option value="update">Status Update</option>
                                    <option value="document_request">Document Request</option>
                                    <option value="clearance_update">Clearance Update</option>
                                    <option value="payment_reminder">Payment Reminder</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control" required
                                    placeholder="Enter message subject...">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message Content <span class="text-danger">*</span></label>
                                <textarea name="content" class="form-control" rows="5" required
                                    placeholder="Type your message here..."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </div>
                        </form>

                        <!-- Quick Message Templates -->
                        <div class="mt-3">
                            <h6><i class="fas fa-lightbulb me-2"></i>Quick Templates</h6>
                            <div class="btn-group-vertical w-100" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm mb-1"
                                    onclick="useTemplate('Document Request', 'Please provide the following documents to proceed with clearance: Commercial Invoice, Packing List, and Bill of Lading.')">
                                    Document Request
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm mb-1"
                                    onclick="useTemplate('Status Update', 'Your shipment status has been updated. Please check the system for the latest information.')">
                                    Status Update
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm mb-1"
                                    onclick="useTemplate('Payment Reminder', 'Please ensure all outstanding payments are settled to avoid clearance delays.')">
                                    Payment Reminder
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message History -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Messages</h5>
                    </div>
                    <div class="card-body">
                        <!-- Tabs for Sent/Received -->
                        <ul class="nav nav-tabs" id="messageTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab">
                                    Sent (<?php echo count($sent_messages); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="received-tab" data-bs-toggle="tab" data-bs-target="#received" type="button" role="tab">
                                    Received (<?php echo count($received_messages); ?>)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-3" id="messageTabsContent">
                            <!-- Sent Messages -->
                            <div class="tab-pane fade show active" id="sent" role="tabpanel">
                                <?php if (empty($sent_messages)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                                        <h6>No Sent Messages</h6>
                                        <p class="text-muted">Messages you send will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="message-list">
                                        <?php foreach ($sent_messages as $message): ?>
                                            <div class="card mb-2">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, H:i', strtotime($message['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-2"><?php echo htmlspecialchars($message['content']); ?></p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            To: <?php echo htmlspecialchars($message['recipient_name']); ?>
                                                        </small>
                                                        <?php if ($message['shipment_id']): ?>
                                                            <span class="badge bg-info">
                                                                <?php echo htmlspecialchars($message['tracking_number']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Received Messages -->
                            <div class="tab-pane fade" id="received" role="tabpanel">
                                <?php if (empty($received_messages)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h6>No Received Messages</h6>
                                        <p class="text-muted">Messages from agents will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="message-list">
                                        <?php foreach ($received_messages as $message): ?>
                                            <div class="card mb-2">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, H:i', strtotime($message['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-2"><?php echo htmlspecialchars($message['content']); ?></p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            From: <?php echo htmlspecialchars($message['sender_name']); ?>
                                                        </small>
                                                        <?php if ($message['shipment_id']): ?>
                                                            <span class="badge bg-info">
                                                                <?php echo htmlspecialchars($message['tracking_number']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
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
        function loadAgentShipments(agentId) {
            if (agentId) {
                window.location.href = `admin_agent_communication.php?agent_id=${agentId}`;
            }
        }

        function useTemplate(subject, content) {
            document.querySelector('input[name="subject"]').value = subject;
            document.querySelector('textarea[name="content"]').value = content;
        }

        // Auto-select shipment if coming from shipment context
        <?php if ($selected_shipment_id): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const shipmentSelect = document.getElementById('shipmentSelect');
                if (shipmentSelect) {
                    shipmentSelect.value = '<?php echo $selected_shipment_id; ?>';
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>