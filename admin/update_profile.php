<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: ../../main-page/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['user_id'];
    $fullName = $_POST['full_name'];
    $email = $_POST['email'];
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Update basic info
        $query = "UPDATE users SET full_name = ?, email = ? WHERE user_id = ? AND account_type = 'Admin'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $fullName, $email, $adminId);
        $stmt->execute();
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
            }
            
            if ($_FILES['profile_image']['size'] > $maxSize) {
                throw new Exception("File size too large. Maximum size is 5MB.");
            }
            
            $uploadDir = '../../images/admins/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $newFileName = 'admin_' . $adminId . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                // Update profile image in database
                $query = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $newFileName, $adminId);
                $stmt->execute();
            }
        }
        
        // Handle password update if provided
        if (!empty($currentPassword) && !empty($newPassword) && !empty($confirmPassword)) {
            // Verify current password
            $query = "SELECT password FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception("Current password is incorrect.");
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception("New passwords do not match.");
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $hashedPassword, $adminId);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Redirect back to profile page with success message
        $_SESSION['success_message'] = "Profile updated successfully.";
        header("Location: profile.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: profile.php");
        exit();
    }
} else {
    // If not POST request, redirect to profile page
    header("Location: profile.php");
    exit();
}
?> 