<?php
require_once '../../config/database.php';

// Function to get total doctors count
function getTotalDoctors($conn) {
    $query = "SELECT COUNT(*) as total FROM users WHERE account_type = 'Doctor'";
    $result = $conn->query($query);
    return $result->fetch_assoc()['total'];
}

// Function to get total patients count
function getTotalPatients($conn) {
    $query = "SELECT COUNT(*) as total FROM users WHERE account_type = 'Patient'";
    $result = $conn->query($query);
    return $result->fetch_assoc()['total'];
}

// Function to get today's appointments count
function getTodayAppointments($conn) {
    $query = "SELECT COUNT(*) as total FROM appointments 
              WHERE DATE(appointment_date) = CURDATE() 
              AND status = 'scheduled'";
    $result = $conn->query($query);
    return $result->fetch_assoc()['total'];
}

// Function to get system status
function getSystemStatus($conn) {
    // Check if database is connected
    if ($conn->ping()) {
        return "Healthy";
    }
    return "Unhealthy";
}

// Get all statistics
$stats = [
    'total_doctors' => getTotalDoctors($conn),
    'total_patients' => getTotalPatients($conn),
    'today_appointments' => getTodayAppointments($conn),
    'system_status' => getSystemStatus($conn)
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($stats);
?> 