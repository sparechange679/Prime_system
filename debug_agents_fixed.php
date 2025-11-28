<?php
require_once 'config.php';
require_once 'database.php';

echo "<h2>Testing Fixed Agent Query</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<p>✅ Database connection successful</p>";

    // Test the FIXED query (without manifest_number)
    $agents_query = "SELECT user_id, full_name, email, tpin_number 
                     FROM users 
                     WHERE role = 'agent' AND status = 'active' 
                     ORDER BY full_name";

    echo "<p><strong>Fixed Query:</strong> " . htmlspecialchars($agents_query) . "</p>";

    $stmt = $db->prepare($agents_query);
    $stmt->execute();
    $active_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Result:</strong> Found " . count($active_agents) . " active agents</p>";

    if (empty($active_agents)) {
        echo "<p>❌ Still no active agents found</p>";
    } else {
        echo "<p>✅ Active agents found:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>TPIN</th></tr>";
        foreach ($active_agents as $agent) {
            echo "<tr>";
            echo "<td>{$agent['user_id']}</td>";
            echo "<td>{$agent['full_name']}</td>";
            echo "<td>{$agent['email']}</td>";
            echo "<td>" . ($agent['tpin_number'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
