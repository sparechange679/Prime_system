<?php
require_once 'config.php';
require_once 'database.php';

echo "<h2>Debug: Testing Agent Query from admin_assign_agent.php</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<p>✅ Database connection successful</p>";

    // Test the exact query from admin_assign_agent.php
    $agents_query = "SELECT user_id, full_name, email, manifest_number, tpin_number 
                     FROM users 
                     WHERE role = 'agent' AND status = 'active' 
                     ORDER BY full_name";

    echo "<p><strong>Query:</strong> " . htmlspecialchars($agents_query) . "</p>";

    $stmt = $db->prepare($agents_query);
    $stmt->execute();
    $active_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Result:</strong> Found " . count($active_agents) . " active agents</p>";

    if (empty($active_agents)) {
        echo "<p>❌ No active agents found - this explains the error!</p>";

        // Let's check what's in the users table
        echo "<h3>Debug: Checking users table structure</h3>";
        $debug_query = "SELECT user_id, full_name, email, role, status, manifest_number, tpin_number FROM users WHERE role = 'agent'";
        $debug_stmt = $db->prepare($debug_query);
        $debug_stmt->execute();
        $debug_agents = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Manifest</th><th>TPIN</th></tr>";
        foreach ($debug_agents as $agent) {
            echo "<tr>";
            echo "<td>{$agent['user_id']}</td>";
            echo "<td>{$agent['full_name']}</td>";
            echo "<td>{$agent['email']}</td>";
            echo "<td>{$agent['role']}</td>";
            echo "<td>{$agent['status']}</td>";
            echo "<td>" . ($agent['manifest_number'] ?? 'NULL') . "</td>";
            echo "<td>" . ($agent['tpin_number'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>✅ Active agents found:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Manifest</th><th>TPIN</th></tr>";
        foreach ($active_agents as $agent) {
            echo "<tr>";
            echo "<td>{$agent['user_id']}</td>";
            echo "<td>{$agent['full_name']}</td>";
            echo "<td>{$agent['email']}</td>";
            echo "<td>" . ($agent['manifest_number'] ?? 'NULL') . "</td>";
            echo "<td>" . ($agent['tpin_number'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
