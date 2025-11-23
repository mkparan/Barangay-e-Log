# Elog Barangay Project

## Overview
The Elog Barangay project is a web application designed to manage barangay-related activities, including announcements, appointments, and user management. The application is built using PHP and utilizes Bootstrap for a responsive and modern user interface.

## Project Structure
```
elog_barangay
├── admin
│   ├── announcements.php
│   ├── appointment_action.php
│   ├── appointments.php
│   ├── duty_roster.php
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   └── presence.php
├── inc
│   ├── auth.php
│   ├── config.php
│   ├── db.php
│   ├── functions.php
│   └── setup_admin.php
├── public
│   ├── appointment_submit.php
│   ├── dashboard.php
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── register.php
│   ├── assets
│   │   ├── css
│   │   │   ├── bootstrap.min.css
│   │   │   └── custom.css
│   │   └── js
│   │       ├── bootstrap.bundle.min.js
│   │       └── custom.js
│   └── uploads
├── assets
│   ├── css
│   │   ├── bootstrap.min.css
│   │   └── admin-custom.css
│   └── js
│       ├── bootstrap.bundle.min.js
│       └── admin-custom.js
└── README.md
```

## Features
- **Admin Panel**: Manage announcements, appointments, and user presence with a user-friendly interface.
- **User Authentication**: Secure login and registration processes for users.
- **Responsive Design**: Utilizes Bootstrap for a mobile-friendly experience.
- **File Uploads**: Allows users to upload necessary documents.

## Setup Instructions
1. **Clone the Repository**: Download or clone the project repository to your local machine.
2. **Install XAMPP**: Ensure you have XAMPP installed to run the PHP application.
3. **Move Project to XAMPP**: Place the `elog_barangay` folder in the `htdocs` directory of your XAMPP installation.
4. **Start XAMPP**: Launch XAMPP and start the Apache server.
5. **Access the Application**: Open your web browser and navigate to `http://localhost/elog_barangay/public/index.php` to access the application.

## Usage Guidelines
- **Admin Login**: Use the admin credentials to access the admin panel.
- **User Registration**: New users can register through the public-facing registration page.
- **Manage Appointments**: Admins can view and manage appointments through the admin interface.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.

## License
This project is licensed under the MIT License. See the LICENSE file for more details.