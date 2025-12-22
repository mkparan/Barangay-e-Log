# Barangay e-Log System (Version 1.0)

## Overview
The **Barangay e-Log System** is a comprehensive web-based application designed to digitize and streamline the operations of a barangay hall. It facilitates efficient management of citizen appointments, daily transactions, official duty rosters, and digital record-keeping, replacing traditional manual logbooks.

## Key Features

### 🏛️ Admin & Official Dashboard
- **Appointment Management**: Approve, decline, reschedule, or complete appointment requests.
- **Walk-in Processing**: Specialized kiosk mode for handling unscheduled visits and assigning queue numbers.
- **Duty Roster & Presence**: Track official schedules and log check-in/check-out times.
- **Transaction Logging**: Automated logging of all interactions for transparency and audit trails.
- **Announcement System**: Publish updates and news to the citizen portal.
- **User Management**: Manage official accounts and verified citizen profiles.

### 👥 Citizen Portal
- **Online Booking**: Citizens can schedule appointments for documents (Barangay Clearance, Indigency, etc.) or meetings.
- **Profile Management**: Maintain personal details, upload profile pictures, and view verification status.
- **History Tracking**: View past appointments and transaction history.
- **Real-time Updates**: View announcements and check appointment status.

### 🛠️ Technical Highlights
- **Architecture**: PHP-based backend with MySQL database (Normalized to 3NF).
- **Frontend**: Responsive Vanilla CSS/JS design for fast loading and broad compatibility.
- **Security**: Password hashing, session management, and role-based access control (RBAC).
- **Recent Updates (v1.0)**:
    - **Walk-in Support**: Modified schema to support anonymous/guest appointments.
    - **Identity Verification**: Added `is_verified` status and profile picture support for citizens.
    - **Cancellation Flow**: Enhanced status tracking with `'cancelled'` state.

## Installation & Setup
1. **Database**: Import `schema.sql` into your MySQL server (Database name: `elog_barangay`).
2. **Configuration**: Configure database credentials in `inc/db_config.php` (if applicable).
3. **Deploy**: Host the `public` and `admin` directories on a PHP-enabled server (e.g., XAMPP, Apache).

## System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web Browser (Chrome, Firefox, Edge)
