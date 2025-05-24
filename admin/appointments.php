<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: ../../main-page/login.php");
    exit();
}

// Fetch all appointments with related details
try {
    $query = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.reason,
                a.created_at,
                p.full_name as patient_name,
                p.profile_image as patient_image,
                d.full_name as doctor_name,
                d.profile_image as doctor_image,
                dd.specialization
              FROM appointments a
              INNER JOIN users p ON a.patient_id = p.user_id
              INNER JOIN users d ON a.doctor_id = d.user_id
              INNER JOIN doctor_details dd ON d.user_id = dd.user_id
              ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        throw new Exception("Error executing query: " . $conn->error);
    }
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $appointments = [];
}

// Function to format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Function to format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Scheduled':
            return 'status-scheduled';
        case 'Completed':
            return 'status-completed';
        case 'Cancelled':
            return 'status-cancelled';
        case 'No-show':
            return 'status-no-show';
        default:
            return 'status-scheduled';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management</title>
    <link rel="icon" href="images/logo.png" type="my_logo">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Grand+Hotel&family=Jost:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Winky+Sans:ital,wght@0,300..900;1,300..900&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: #f5f9ff;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background-color: white;
            border-right: 1px solid #e0e7f1;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            width: 40px;
            height: auto;
            margin-top: 7px;
            margin-left: 20px;
        }

        .logo-text {
            color: #3498db;
            font-weight: bold;
            font-style: italic;
            font-size: 24px;
            margin-left: 5px;
        }

        .nav-menu {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #1f2937;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-item.active {
            background-color: #f0f5ff;
            color: #3498db;
            border-left: 3px solid #3498db;
            font-weight: 500;
        }

        .nav-item:hover:not(.active) {
            background-color: #f8fafc;
            color: #3498db;
        }

        .nav-item i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
            color: #3498db;
        }

        .user-profile {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            border-top: 1px solid #e0e7f1;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e5e7eb;
            margin-right: 10px;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex-grow: 1;
        }

        .user-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 14px;
        }

        .user-role {
            color: #666;
            font-size: 12px;
        }

        .logout-btn {
            display: block;
            margin: 15px 20px;
            padding: 10px;
            border: 1px solid #ef4444;
            border-radius: 6px;
            color: #ef4444;
            background-color: white;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background-color: #fef2f2;
        }

        .logout-btn i {
            margin-right: 8px;
        }

        /* Main Content Styles */
        .main-content {
            flex-grow: 1;
            margin-left: 260px;
        }

        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 30px;
            background-color: white;
            border-bottom: 1px solid #e0e7f1;
        }

        .menu-toggle {
            display: none;
            font-size: 20px;
            color: #3498db;
            cursor: pointer;
            margin-right: 15px;
        }

        .search-container {
            flex-grow: 1;
            max-width: 500px;
            position: relative;
        }

        .search-container i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
        }

        .search-input {
            width: 100%;
            padding: 8px 10px 8px 35px;
            border: 1px solid #e0e7f1;
            border-radius: 20px;
            background-color: #f8fafc;
            outline: none;
            transition: all 0.3s;
        }

        .search-input:focus {
            background-color: white;
            border-color: #bfdbfe;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .notification-container {
            display: flex;
            align-items: center;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            position: relative;
            color: #3498db;
            background-color: transparent;
            transition: all 0.3s;
        }

        .icon-btn:hover {
            background-color: #f0f5ff;
            color: #3498db;
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-dropdown {
            display: flex;
            align-items: center;
            margin-left: 15px;
        }

        .user-dropdown img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .user-name-display {
            font-weight: 500;
            color: #1f2937;
        }

        /* Content Area */
        .content {
            padding: 30px;
        }

        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .welcome-text h1 {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 5px;
        }

        /* Appointment Container Styles */
        .appointment-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e7f1;
        }

        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #1f2937;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-scheduled {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .status-completed {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-no-show {
            background-color: #fef3c7;
            color: #92400e;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .view-btn {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .edit-btn {
            background-color: #fef3c7;
            color: #92400e;
        }

        .delete-btn {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .action-btn:hover {
            opacity: 0.8;
        }

        .appointment-details {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        .filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #e0e7f1;
            border-radius: 6px;
            background-color: white;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }

        .filter-btn:hover:not(.active) {
            background-color: #f8fafc;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .search-container {
                max-width: none;
            }

            .filter-container {
                flex-wrap: wrap;
            }

            .filter-btn {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <a href="">
                    <img src="images/logo.png" alt="">
                </a>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="doctors.php" class="nav-item">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>Doctors</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-users"></i>
                    <span>Patients</span>
                </a>
                <a href="appointments.php" class="nav-item active">
                    <i class="fa-regular fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fa-solid fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <img src="images/id.png" alt="Admin">
                </div>
                <div class="user-info">
                    <div class="user-name">Admin</div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            <a href="../../main-page/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Sign Out
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar">
                <div class="menu-toggle" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search appointments...">
                </div>
                <div class="notification-container">
                    <div class="icon-btn">
                        <i class="far fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </div>
                    <div class="user-dropdown">
                        <img src="images/id.png" alt="Admin">
                        <span class="user-name-display">Admin</span>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1>Appointments</h1>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="filter-container">
                    <button class="filter-btn active" data-status="all">All</button>
                    <button class="filter-btn" data-status="Scheduled">Scheduled</button>
                    <button class="filter-btn" data-status="Completed">Completed</button>
                    <button class="filter-btn" data-status="Cancelled">Cancelled</button>
                    <button class="filter-btn" data-status="No-show">No-show</button>
                </div>

                <!-- Appointment Management Interface -->
                <div class="appointment-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                            <tr data-status="<?php echo htmlspecialchars($appointment['status']); ?>">
                                <td>
                                    <div><?php echo formatDate($appointment['appointment_date']); ?></div>
                                    <div class="appointment-details">
                                        <?php echo formatTime($appointment['appointment_time']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo htmlspecialchars($appointment['patient_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($appointment['patient_name']); ?>" 
                                             class="user-avatar">
                                        <div>
                                            <div><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo htmlspecialchars($appointment['doctor_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($appointment['doctor_name']); ?>" 
                                             class="user-avatar">
                                        <div>
                                            <div><?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                            <div class="appointment-details">
                                                <?php echo htmlspecialchars($appointment['specialization']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($appointment['reason']); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo getStatusBadgeClass($appointment['status']); ?>">
                                        <?php echo htmlspecialchars($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view-btn" onclick="viewAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit-btn" onclick="editAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete-btn" onclick="deleteAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar functionality
        document.getElementById('menu-toggle').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        // Close sidebar when clicking outside on small screens
        document.addEventListener('click', function (event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menu-toggle');

            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                !menuToggle.contains(event.target) &&
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        });

        // Adjust sidebar on window resize
        window.addEventListener('resize', function () {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        });

        // Search functionality
        document.querySelector('.search-input').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const patientName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const doctorName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const reason = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                
                if (patientName.includes(searchTerm) || 
                    doctorName.includes(searchTerm) || 
                    reason.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                const status = this.dataset.status;
                const rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    if (status === 'all' || row.dataset.status === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        // Appointment management functions
        function viewAppointment(appointmentId) {
            // Implement view functionality
            console.log('View appointment:', appointmentId);
        }

        function editAppointment(appointmentId) {
            // Implement edit functionality
            console.log('Edit appointment:', appointmentId);
        }

        function deleteAppointment(appointmentId) {
            if (confirm('Are you sure you want to delete this appointment?')) {
                // Implement delete functionality
                console.log('Delete appointment:', appointmentId);
            }
        }
    </script>
</body>

</html> 