<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is a renter
function isRenter() {
    return isLoggedIn() && $_SESSION['user_type'] == 'renter';
}

// Check if user is a customer
function isCustomer() {
    return isLoggedIn() && $_SESSION['user_type'] == 'customer';
}

// Redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Sanitize input data
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Upload image and return the path
function uploadImage($file, $directory = "uploads/") {
    // Create directory if it doesn't exist
    if (!file_exists($directory)) {
        if (!mkdir($directory, 0777, true)) {
            error_log("Failed to create directory: " . $directory);
            return false;
        }
        // Explicitly set permissions after creation
        chmod($directory, 0777);
    }
    
    $target_dir = $directory;
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $newFileName = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $newFileName;
    
    // Check if image file is a actual image or fake image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        error_log("File is not an image.");
        return false;
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        error_log("File is too large.");
        return false;
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        error_log("Invalid file format: " . $imageFileType);
        return false;
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $target_file;
    } else {
        $error = error_get_last();
        error_log("Failed to move uploaded file. Error: " . ($error ? $error['message'] : 'Unknown error'));
        return false;
    }
}

// Get user by ID
function getUserById($conn, $userId) {
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get vehicle by ID
function getVehicleById($conn, $vehicleId) {
    $sql = "SELECT v.*, u.full_name, u.email, u.phone_number 
            FROM vehicles v 
            JOIN users u ON v.user_id = u.user_id 
            WHERE v.vehicle_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get vehicle images
function getVehicleImages($conn, $vehicleId) {
    $sql = "SELECT * FROM vehicle_images WHERE vehicle_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    
    return $images;
}

// Get primary image for a vehicle
function getVehiclePrimaryImage($conn, $vehicleId) {
    $sql = "SELECT image_url FROM vehicle_images WHERE vehicle_id = ? AND is_primary = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['image_url'];
    }
    
    // If no primary image, get the first image
    $sql = "SELECT image_url FROM vehicle_images WHERE vehicle_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['image_url'];
    }
    
    return "images/no-image.jpg"; // Default image
}

// Format price
function formatPrice($price) {
    return '₹' . number_format($price, 2);
}

// Calculate days between two dates
function calculateDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    return $interval->days + 1; // Include both start and end days
}

// Check if a vehicle is available for a specific date range
function isVehicleAvailable($conn, $vehicleId, $startDate, $endDate) {
    $sql = "SELECT COUNT(*) as count FROM bookings 
            WHERE vehicle_id = ? 
            AND status IN ('pending', 'confirmed') 
            AND ((start_date BETWEEN ? AND ?) 
                OR (end_date BETWEEN ? AND ?) 
                OR (start_date <= ? AND end_date >= ?))";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssss", $vehicleId, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] == 0;
}
?>