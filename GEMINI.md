# Prime Cargo Limited - Automated Clearance System (GEMINI Project Info)

## Project Overview

This is an automated cargo clearance system for Prime Cargo Limited, a logistics company in Malawi. The system aims to streamline cargo clearance processes under the Malawi Revenue Authority (MRA) by providing role-based access for Admins, Agents, Keepers, and Clients to manage shipments, documents, payments, and tracking.

## Technology Stack

*   **Backend**: PHP
*   **Database**: MySQL
*   **Frontend**: HTML, CSS (Bootstrap, custom styling), JavaScript
*   **Mail handling**: PHPMailer (identified in the directory structure)

## Key Features

### Completed
*   User Authentication System with role-based access
*   Multi-Role Support: Admin, Agent, Keeper, Client
*   Role-specific Dashboards
*   Session Management and security features
*   Activity Logging for audit trails
*   Responsive UI

### In Development (Planned/Partial Implementation)
*   Document Management System
*   Shipment Tracking
*   Payment Processing
*   Agent Workflow Management
*   Keeper Verification System
*   Tax Calculation Engine
*   Real-time Communication System

## Setup Instructions

1.  **Prerequisites**: WAMP/XAMPP server, MySQL/MariaDB, PHP 7.4+.
2.  **Database Setup**: Navigate to `http://localhost/Prime_system/setup_database.php` in a browser to create tables and sample data.
3.  **Access System**: Navigate to `http://localhost/Prime_system/` and use provided sample credentials (e.g., admin/admin123).

## User Roles

*   **Admin**: Full system access.
*   **Agent**: Handles cargo clearance, MRA integration, and status updates.
*   **Keeper**: Verifies goods and documents, reports status.
*   **Client**: Submits documents, tracks shipments, makes payments.

## Contact & Support

Prime Cargo Limited
📍 Blantyre Chileka Airport, Malawi
📧 info@primecargo.mw
📱 +265 123 456 789

---
This `GEMINI.md` file was generated to provide a quick reference for the project based on the existing `README.md`.
