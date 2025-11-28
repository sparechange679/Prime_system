<?php
require_once 'config.php';
require_once 'database.php';

echo "Adding missing columns to shipments table...\n";

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        echo "Database connection successful.\n";

        // Add expected_clearance_date column
        echo "Adding expected_clearance_date column...\n";
        $sql = "ALTER TABLE `shipments` ADD COLUMN `expected_clearance_date` date NULL AFTER `destination_port`";

        if ($db->exec($sql)) {
            echo "✓ expected_clearance_date column added successfully.\n";
        } else {
            echo "✗ Failed to add expected_clearance_date column.\n";
        }

        // Add notes column
        echo "Adding notes column...\n";
        $sql = "ALTER TABLE `shipments` ADD COLUMN `notes` text NULL AFTER `expected_clearance_date`";

        if ($db->exec($sql)) {
            echo "✓ notes column added successfully.\n";
        } else {
            echo "✗ Failed to add notes column.\n";
        }

        // Verify the table structure
        echo "\nVerifying table structure...\n";
        $stmt = $db->prepare("DESCRIBE shipments");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Current shipments table columns:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']}: {$col['Type']}\n";
        }

        echo "\nAll columns added successfully!\n";
    } else {
        echo "Failed to connect to database.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
