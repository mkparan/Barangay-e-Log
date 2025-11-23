<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/admin-custom.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">Barangay E-Log</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="announcements.php">Announcements</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="appointments.php">Appointments</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="duty_roster.php">Duty Roster</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="presence.php">User Presence</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="text-center">Welcome to the Admin Dashboard</h1>
        <div class="row">
            <div class="col-md-4">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-header">Announcements</div>
                    <div class="card-body">
                        <h5 class="card-title">View Announcements</h5>
                        <p class="card-text">Check the latest announcements from the barangay.</p>
                        <a href="announcements.php" class="btn btn-light">Go to Announcements</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-header">Appointments</div>
                    <div class="card-body">
                        <h5 class="card-title">Manage Appointments</h5>
                        <p class="card-text">View and manage appointments.</p>
                        <a href="appointments.php" class="btn btn-light">Go to Appointments</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-header">User Presence</div>
                    <div class="card-body">
                        <h5 class="card-title">Track User Presence</h5>
                        <p class="card-text">Monitor user presence in the barangay.</p>
                        <a href="presence.php" class="btn btn-light">Go to User Presence</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin-custom.js"></script>
</body>
</html>