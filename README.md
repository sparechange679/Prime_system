# 🚢 Prime Cargo Limited - Automated Clearance System

## 📋 Project Overview

Prime Cargo Limited is a logistics company established in 2010, located at Blantyre Chileka Airport, Malawi. This system provides automated cargo clearance services, working under the Malawi Revenue Authority (MRA) to handle various cargo shipments.

## 🎯 System Features

### ✅ **Completed Features**
- **User Authentication System** with role-based access
- **Multi-Role Support**: Admin, Agent, Keeper, Client
- **Dashboard** with role-specific statistics and navigation
- **Session Management** and security features
- **Activity Logging** for audit trails
- **Responsive UI** with Bootstrap and custom styling

### 🚧 **In Development**
- Document Management System
- Shipment Tracking
- Payment Processing
- Agent Workflow Management
- Keeper Verification System
- Tax Calculation Engine
- Real-time Communication System

## 🏗️ **Current System Architecture**

```
Prime Cargo System/
├── assets/
│   ├── css/
│   │   └── style.css          # Custom styling
│   └── js/
│       └── script.js          # Frontend functionality
├── database_schema.sql        # Complete database structure
├── setup_database.php         # Database setup script
├── index.php                  # Entry point (redirects to login)
├── login.php                  # User authentication
├── auth.php                   # Authentication logic
├── dashboard.php              # Main dashboard
├── logout.php                 # Session termination
├── database.php               # Database connection
└── README.md                  # This file
```

## 🗄️ **Database Schema**

The system uses MySQL with the following core tables:

- **`users`** - User accounts and authentication
- **`roles`** - User role definitions
- **`clients`** - Client company information
- **`shipments`** - Cargo shipment details
- **`shipment_documents`** - Document management
- **`verification`** - Keeper verification records
- **`payments`** - Payment tracking
- **`activity_log`** - System audit trail

## 🚀 **Quick Start Guide**

### **Prerequisites**
- WAMP/XAMPP server running
- MySQL/MariaDB database
- PHP 7.4+ support

### **Step 1: Database Setup**
1. Ensure your MySQL server is running
2. Open your browser and navigate to: `http://localhost/Prime_system/setup_database.php`
3. This will automatically create all required tables and sample data

### **Step 2: Test the System**
1. Navigate to: `http://localhost/Prime_system/`
2. Use the sample login credentials below

### **Step 3: Sample Users**
| Role | Username | Password | Description |
|------|----------|----------|-------------|
| Admin | `admin` | `admin123` | Full system access |
| Agent | `agent1` | `admin123` | Cargo clearance agent |
| Keeper | `keeper1` | `admin123` | Warehouse verification |
| Client | `client1` | `admin123` | Business client |

## 🔧 **System Workflow**

### **Client Process**
1. **Register/Login** → Access client dashboard
2. **Submit Documents** → Upload required cargo documents
3. **Track Progress** → Monitor shipment clearance status
4. **Make Payment** → Pay clearance fees
5. **Collect Goods** → Receive cleared shipment

### **Agent Process**
1. **Review Documents** → Verify client submissions
2. **MRA Integration** → Get manifest and TPIN numbers
3. **Clearance Processing** → Handle customs procedures
4. **Status Updates** → Keep clients informed
5. **Release Orders** → Coordinate final delivery

### **Keeper Process**
1. **Goods Verification** → Check arrived cargo
2. **Document Verification** → Verify against submitted docs
3. **Status Reporting** → Update verification status
4. **Quality Control** → Ensure compliance

## 📱 **User Interface**

- **Responsive Design** - Works on all devices
- **Role-Based Dashboards** - Customized for each user type
- **Modern UI/UX** - Bootstrap 5 with custom styling
- **Interactive Elements** - JavaScript enhancements
- **Professional Branding** - Prime Cargo Limited identity

## 🔒 **Security Features**

- **Session Management** - Secure user sessions
- **Role-Based Access Control** - Restricted functionality
- **Password Hashing** - Secure credential storage
- **Activity Logging** - Complete audit trail
- **SQL Injection Protection** - Prepared statements

## 🚧 **Next Development Priorities**

1. **Document Management System** - File upload and storage
2. **Shipment Creation** - New shipment workflow
3. **Status Tracking** - Real-time progress updates
4. **Payment Integration** - Secure payment processing
5. **MRA API Integration** - Automated manifest/TPIN retrieval
6. **Notification System** - Email/SMS updates
7. **Reporting Dashboard** - Analytics and insights

## 🐛 **Troubleshooting**

### **Common Issues**
- **Database Connection Error**: Check MySQL service and credentials
- **Asset Loading Issues**: Verify assets folder structure
- **Login Problems**: Ensure database tables are created
- **Permission Errors**: Check file permissions

### **Support**
- Check the activity logs for error details
- Verify database connectivity
- Ensure all required files are present

## 📊 **System Requirements**

- **Server**: Apache/Nginx with PHP support
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **PHP**: Version 7.4 or higher
- **Browser**: Modern browsers with JavaScript enabled
- **Storage**: Minimum 100MB for system files

## 🔄 **Version History**

- **v1.0.0** - Core authentication and dashboard system
- **v1.1.0** - Database schema and sample data
- **v1.2.0** - Asset organization and documentation

---

## 📞 **Contact & Support**

**Prime Cargo Limited**  
📍 Blantyre Chileka Airport, Malawi  
📧 info@primecargo.mw  
📱 +265 123 456 789  

---

*This system is designed to streamline cargo clearance processes and improve operational efficiency for Prime Cargo Limited.*
