<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: ../../main-page/login.php");
    exit();
}

require_once '../../config/database.php';

$adminId = $_SESSION['user_id'];

try {
    // Fetch admin's profile data
    $query = "SELECT full_name, profile_image FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $adminProfile = $result->fetch_assoc();

    // Fetch total doctors count
    $query = "SELECT COUNT(*) as total FROM users WHERE account_type = 'Doctor'";
    $result = $conn->query($query);
    $totalDoctors = $result->fetch_assoc()['total'];

    // Fetch total patients count
    $query = "SELECT COUNT(*) as total FROM users WHERE account_type = 'Patient'";
    $result = $conn->query($query);
    $totalPatients = $result->fetch_assoc()['total'];

    // Fetch total appointments count
    $query = "SELECT COUNT(*) as total FROM appointments";
    $result = $conn->query($query);
    $totalAppointments = $result->fetch_assoc()['total'];

    // Fetch recent appointments with proper JOIN conditions
    $query = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                p.full_name as patient_name,
                d.full_name as doctor_name,
                dd.specialization
              FROM appointments a
              INNER JOIN users p ON a.patient_id = p.user_id
              INNER JOIN users d ON a.doctor_id = d.user_id
              INNER JOIN doctor_details dd ON d.user_id = dd.user_id
              ORDER BY a.appointment_date DESC, a.appointment_time DESC
              LIMIT 5";
              
    $result = $conn->query($query);
    
    if ($result === false) {
        throw new Exception("Error fetching appointments: " . $conn->error);
    }
    
    $recentAppointments = [];
    while ($row = $result->fetch_assoc()) {
        $recentAppointments[] = $row;
    }

} catch (Exception $e) {
    error_log("Error fetching admin dashboard data: " . $e->getMessage());
    $adminProfile = ['full_name' => 'Admin', 'profile_image' => 'default.png'];
    $totalDoctors = $totalPatients = $totalAppointments = 0;
    $recentAppointments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" href="images/logo.png" type="my_logo">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap');

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
            padding: 20px;
            text-align: center;
        }

        .logo img {
            width: 40px;
            height: auto;
        }

        .nav-menu {
            flex-grow: 1;
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
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
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
        }

        .welcome-section {
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-card h3 {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
        }

        .recent-appointments {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .recent-appointments h2 {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 20px;
        }

        .appointment-list {
            display: grid;
            gap: 15px;
        }

        .appointment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .appointment-info {
            flex: 1;
        }

        .appointment-info h4 {
            color: #1f2937;
            margin-bottom: 5px;
        }

        .appointment-info p {
            color: #6b7280;
            font-size: 14px;
        }

        .appointment-status {
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

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <img src="images/logo.png" alt="Logo">
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-item active">
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
                <a href="appointments.php" class="nav-item">
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
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($adminProfile['full_name']); ?></h1>
                    <p><?php echo date("l, F j, Y"); ?></p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Doctors</h3>
                    <div class="number"><?php echo $totalDoctors; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Patients</h3>
                    <div class="number"><?php echo $totalPatients; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Appointments</h3>
                    <div class="number"><?php echo $totalAppointments; ?></div>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="recent-appointments">
                <h2>Recent Appointments</h2>
                <div class="appointment-list">
                    <?php if (empty($recentAppointments)): ?>
                        <p>No recent appointments</p>
                    <?php else: ?>
                        <?php foreach ($recentAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-info">
                                    <h4><?php echo htmlspecialchars($appointment['patient_name']); ?> with Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h4>
                                    <p><?php echo date("F j, Y g:i A", strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?></p>
                                </div>
                                <span class="appointment-status status-<?php echo strtolower($appointment['status']); ?>">
                                    <?php echo htmlspecialchars($appointment['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.createElement('div');
            menuToggle.className = 'menu-toggle';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            document.querySelector('.main-content').prepend(menuToggle);

            menuToggle.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('active');
            });
        });
    </script>
</body>
</html> 