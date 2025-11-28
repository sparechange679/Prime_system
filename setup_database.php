<?php

/**
 * Database Setup Script for Prime Cargo System
 * Run this file to set up your database
 */

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL server (without database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>🔧 Prime Cargo System - Database Setup</h2>";
    echo "<div style='font-family: monospace; background: #f8f9fa; padding: 20px; border-radius: 5px;'>";

    // Read and execute the SQL schema
    $sql_file = 'database_schema.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);

        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        echo "<h3>📋 Executing Database Setup...</h3>";

        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(--|#|\/\*)/', $statement)) {
                try {
                    $pdo->exec($statement);
                    echo "✅ " . substr($statement, 0, 50) . "...<br>";
                } catch (PDOException $e) {
                    echo "❌ Error: " . $e->getMessage() . "<br>";
                }
            }
        }

        echo "<br><h3>🎉 Database Setup Complete!</h3>";
        echo "<p><strong>Database:</strong> prime_system</p>";
        echo "<p><strong>Tables Created:</strong></p>";
        echo "<ul>";
        echo "<li>roles</li>";
        echo "<li>users</li>";
        echo "<li>clients</li>";
        echo "<li>shipments</li>";
        echo "<li>shipment_documents</li>";
        echo "<li>verification</li>";
        echo "<li>payments</li>";
        echo "<li>activity_log</li>";
        echo "</ul>";

        echo "<p><strong>Sample Users Created:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Admin:</strong> username: admin, password: admin123</li>";
        echo "<li><strong>Agent 1:</strong> username: agent1, password: admin123</li>";
        echo "<li><strong>Agent 2:</strong> username: agent2, password: admin123</li>";
        echo "<li><strong>Keeper:</strong> username: keeper1, password: admin123</li>";
        echo "<li><strong>Client 1:</strong> username: client1, password: admin123</li>";
        echo "<li><strong>Client 2:</strong> username: client2, password: admin123</li>";
        echo "</ul>";

        echo "<p><strong>Next Steps:</strong></p>";
        echo "<ol>";
        echo "<li>Test the login system with the sample users above</li>";
        echo "<li>Check that the dashboard loads correctly</li>";
        echo "<li>Verify that role-based access is working</li>";
        echo "</ol>";

        echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Go to Login Page</a></p>";
    } else {
        echo "<h3>❌ Error: database_schema.sql file not found!</h3>";
        echo "<p>Please ensure the database_schema.sql file exists in the same directory.</p>";
    }

    echo "</div>";
} catch (PDOException $e) {
    echo "<h3>❌ Database Connection Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration:</p>";
    echo "<ul>";
    echo "<li>Make sure MySQL/MariaDB is running</li>";
    echo "<li>Verify username and password are correct</li>";
    echo "<li>Ensure the database server is accessible</li>";
    echo "</ul>";
}
