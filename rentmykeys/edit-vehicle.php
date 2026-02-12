<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Check if user is logged in and is a renter
requireLogin();
if (!isRenter()) {
    header("Location: dashboard-customer.php");
    exit();
}

$message = '';
$messageType = '';
$vehicle = null;
$primaryImage = null;
$additionalImages = [];

// Get vehicle ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my-vehicles.php");
    exit();
}

$vehicle_id = (int)$_GET['id'];

// Check if the vehicle exists and belongs to the current user
$stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
$stmt->bind_param("ii", $vehicle_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Vehicle not found or doesn't belong to the current user
    header("Location: my-vehicles.php");
    exit();
}

$vehicle = $result->fetch_assoc();

// Get vehicle images
$stmt = $conn->prepare("SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_primary DESC");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['is_primary']) {
        $primaryImage = $row;
    } else {
        $additionalImages[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $vehicle_type = sanitize($_POST['vehicle_type']);
    $brand = sanitize($_POST['brand']);
    $model = sanitize($_POST['model']);
    $year = (int)$_POST['year'];
    $license_plate = sanitize($_POST['license_plate']);
    $color = sanitize($_POST['color']);
    $seats = isset($_POST['seats']) ? (int)$_POST['seats'] : null;
    $fuel_type = isset($_POST['fuel_type']) ? sanitize($_POST['fuel_type']) : null;
    $transmission = isset($_POST['transmission']) ? sanitize($_POST['transmission']) : null;
    $mileage = isset($_POST['mileage']) ? (float)$_POST['mileage'] : null;
    $price_per_day = (float)$_POST['price_per_day'];
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $zip_code = sanitize($_POST['zip_code']);
    $availability = isset($_POST['availability']) ? 1 : 0;
    
    // Validate input
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($vehicle_type)) {
        $errors[] = "Vehicle type is required";
    }
    
    if (empty($brand)) {
        $errors[] = "Brand is required";
    }
    
    if (empty($model)) {
        $errors[] = "Model is required";
    }
    
    if (empty($year) || $year < 1900 || $year > date("Y") + 1) {
        $errors[] = "Please enter a valid year";
    }
    
    if (empty($license_plate)) {
        $errors[] = "License plate is required";
    }
    
    if (empty($color)) {
        $errors[] = "Color is required";
    }
    
    if (empty($price_per_day) || $price_per_day <= 0) {
        $errors[] = "Please enter a valid price per day";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($city)) {
        $errors[] = "City is required";
    }
    
    if (empty($state)) {
        $errors[] = "State is required";
    }
    
    if (empty($zip_code)) {
        $errors[] = "ZIP code is required";
    }
    
    if (empty($errors)) {
        // Update vehicle in database
        $stmt = $conn->prepare("UPDATE vehicles SET 
                               title = ?, 
                               description = ?, 
                               vehicle_type = ?, 
                               brand = ?, 
                               model = ?, 
                               year = ?, 
                               license_plate = ?, 
                               color = ?, 
                               seats = ?, 
                               fuel_type = ?, 
                               transmission = ?, 
                               mileage = ?, 
                               price_per_day = ?, 
                               address = ?, 
                               city = ?, 
                               state = ?, 
                               zip_code = ?,
                               availability = ?
                               WHERE vehicle_id = ? AND user_id = ?");
        
        $stmt->bind_param("sssssissssddssssiiii", 
                         $title, 
                         $description, 
                         $vehicle_type, 
                         $brand, 
                         $model, 
                         $year, 
                         $license_plate, 
                         $color, 
                         $seats, 
                         $fuel_type, 
                         $transmission, 
                         $mileage, 
                         $price_per_day, 
                         $address, 
                         $city, 
                         $state, 
                         $zip_code,
                         $availability,
                         $vehicle_id,
                         $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            // Handle primary image upload if provided
            if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] == 0) {
                $image_url = uploadImage($_FILES['primary_image'], "uploads/vehicles/");
                
                if ($image_url) {
                    if ($primaryImage) {
                        // Update existing primary image
                        $stmt = $conn->prepare("UPDATE vehicle_images SET image_url = ? WHERE image_id = ?");
                        $stmt->bind_param("si", $image_url, $primaryImage['image_id']);
                        $stmt->execute();
                    } else {
                        // Insert new primary image
                        $stmt = $conn->prepare("INSERT INTO vehicle_images (vehicle_id, image_url, is_primary) VALUES (?, ?, 1)");
                        $stmt->bind_param("is", $vehicle_id, $image_url);
                        $stmt->execute();
                    }
                }
            }
            
            // Handle additional images upload if provided
            if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['name'])) {
                for ($i = 0; $i < count($_FILES['additional_images']['name']); $i++) {
                    if ($_FILES['additional_images']['error'][$i] == 0) {
                        $temp_file = [
                            'name' => $_FILES['additional_images']['name'][$i],
                            'type' => $_FILES['additional_images']['type'][$i],
                            'tmp_name' => $_FILES['additional_images']['tmp_name'][$i],
                            'error' => $_FILES['additional_images']['error'][$i],
                            'size' => $_FILES['additional_images']['size'][$i]
                        ];
                        
                        $image_url = uploadImage($temp_file, "uploads/vehicles/");
                        
                        if ($image_url) {
                            $stmt = $conn->prepare("INSERT INTO vehicle_images (vehicle_id, image_url, is_primary) VALUES (?, ?, 0)");
                            $stmt->bind_param("is", $vehicle_id, $image_url);
                            $stmt->execute();
                        }
                    }
                }
            }
            
            // Redirect to vehicle details page
            $message = "Vehicle updated successfully!";
            $messageType = "success";
            
            // Refresh vehicle data
            $stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $vehicle = $result->fetch_assoc();
            
            // Refresh vehicle images
            $stmt = $conn->prepare("SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_primary DESC");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $primaryImage = null;
            $additionalImages = [];
            
            while ($row = $result->fetch_assoc()) {
                if ($row['is_primary']) {
                    $primaryImage = $row;
                } else {
                    $additionalImages[] = $row;
                }
            }
        } else {
            $message = "Error updating vehicle: " . $conn->error;
            $messageType = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $messageType = "error";
    }
}

// Delete image if requested
if (isset($_GET['delete_image']) && !empty($_GET['delete_image'])) {
    $image_id = (int)$_GET['delete_image'];
    
    // Check if image belongs to the current user's vehicle
    $stmt = $conn->prepare("SELECT vi.* FROM vehicle_images vi 
                           JOIN vehicles v ON vi.vehicle_id = v.vehicle_id 
                           WHERE vi.image_id = ? AND v.user_id = ?");
    $stmt->bind_param("ii", $image_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $image = $result->fetch_assoc();
        
        // Prevent deleting the primary image if it's the only image
        if ($image['is_primary']) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM vehicle_images WHERE vehicle_id = ?");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] <= 1) {
                $message = "Cannot delete the only image. Please upload a new primary image first.";
                $messageType = "error";
                
                // Redirect back to edit page
                header("Location: edit-vehicle.php?id=$vehicle_id");
                exit();
            }
            
            // If there are other images, make the next one primary
            $stmt = $conn->prepare("SELECT * FROM vehicle_images WHERE vehicle_id = ? AND image_id != ? LIMIT 1");
            $stmt->bind_param("ii", $vehicle_id, $image_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $new_primary = $result->fetch_assoc();
                
                $stmt = $conn->prepare("UPDATE vehicle_images SET is_primary = 1 WHERE image_id = ?");
                $stmt->bind_param("i", $new_primary['image_id']);
                $stmt->execute();
            }
        }
        
        // Delete the image
        $stmt = $conn->prepare("DELETE FROM vehicle_images WHERE image_id = ?");
        $stmt->bind_param("i", $image_id);
        
        if ($stmt->execute()) {
            // Delete the image file
            if (file_exists($image['image_url'])) {
                unlink($image['image_url']);
            }
            
            $message = "Image deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Error deleting image.";
            $messageType = "error";
        }
    }
    
    // Redirect back to edit page
    header("Location: edit-vehicle.php?id=$vehicle_id");
    exit();
}

// Set image as primary if requested
if (isset($_GET['set_primary']) && !empty($_GET['set_primary'])) {
    $image_id = (int)$_GET['set_primary'];
    
    // Check if image belongs to the current user's vehicle
    $stmt = $conn->prepare("SELECT vi.* FROM vehicle_images vi 
                           JOIN vehicles v ON vi.vehicle_id = v.vehicle_id 
                           WHERE vi.image_id = ? AND v.user_id = ?");
    $stmt->bind_param("ii", $image_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Reset all images to non-primary
        $stmt = $conn->prepare("UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = ?");
        $stmt->bind_param("i", $vehicle_id);
        $stmt->execute();
        
        // Set the selected image as primary
        $stmt = $conn->prepare("UPDATE vehicle_images SET is_primary = 1 WHERE image_id = ?");
        $stmt->bind_param("i", $image_id);
        
        if ($stmt->execute()) {
            $message = "Primary image updated.";
            $messageType = "success";
        } else {
            $message = "Error updating primary image.";
            $messageType = "error";
        }
    }
    
    // Redirect back to edit page
    header("Location: edit-vehicle.php?id=$vehicle_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vehicle - RentMyKeys</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#1E40AF',
                        accent: '#8B5CF6',
                        dark: '#111827',
                        darker: '#0F172A',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 8s infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(25px)' },
                        }
                    },
                }
            }
        }
    </script>
    <style>
        /* Background animations */
        @keyframes floatBackground {
            0% { transform: translate(0%, 0%) scale(1.1); }
            25% { transform: translate(-2%, -2%) scale(1.15); }
            50% { transform: translate(1%, -3%) scale(1.2); }
            75% { transform: translate(-3%, 1%) scale(1.15); }
            100% { transform: translate(2%, 2%) scale(1.1); }
        }
        
        .background-animation {
            animation: floatBackground 30s ease-in-out infinite alternate;
        }
        
        /* Glowing orbs */
        .glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(50px);
            z-index: 0;
        }
        
        .glow-1 {
            top: 20%;
            left: 10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, rgba(30, 64, 175, 0.1) 70%);
            animation: pulse 8s infinite alternate;
        }
        
        .glow-2 {
            bottom: 10%;
            right: 20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, rgba(91, 33, 182, 0.1) 70%);
            animation: pulse 10s infinite alternate-reverse;
        }
        
        @keyframes pulse {
            0% { opacity: 0.7; transform: scale(1); }
            100% { opacity: 0.6; transform: scale(1.5); }
        }
        
        /* Page loader */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .page-loader.active {
            opacity: 1;
            visibility: visible;
        }

        .loader-content {
            text-align: center;
            color: white;
        }

        .spinning-logo {
            animation: spin-logo 4s infinite linear;
        }

        @keyframes spin-logo {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Glass card effect */
        .glass-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.7) 0%, rgba(17, 24, 39, 0.4) 100%);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>

<body class="bg-black text-white min-h-screen">
    <!-- Page Loader -->
    <div id="page-loader" class="page-loader">
        <div class="loader-content">
            <div class="mx-auto w-24 h-24 mb-4 flex justify-center items-center">
                <img src="assets/images/lol.png" alt="RentMyKeys Logo" class="spinning-logo w-full h-auto">
            </div>
            <p>Loading...</p>
        </div>
    </div>

    <!-- Background elements -->
    <div class="fixed inset-0 z-0 overflow-hidden opacity-20">
        <img src="assets/images/background.jpg" alt="Background" class="w-[120%] h-[120%] object-cover filter blur-sm background-animation absolute -top-[10%] -left-[10%]">
    </div>
    
    <!-- Animated glow effects -->
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>
    
    <!-- Navbar -->
    <nav class="glass-card sticky top-0 z-50 border-b border-gray-800/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0 flex items-center">
                        <img class="h-10 w-auto mr-2" src="assets/images/lol.png" alt="RentMyKeys">
                        <span class="text-xl font-bold gradient-text">RentMyKeys</span>
                    </a>
                    <div class="hidden md:ml-6 md:flex md:items-center md:space-x-4">
                        <a href="index.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="search.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Search</a>
                        <a href="about.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <a href="contact.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Contact</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center text-white focus:outline-none">
                            <img class="h-8 w-8 rounded-full object-cover" src="<?php echo isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] ? $_SESSION['profile_picture'] : 'assets/images/default-avatar.png'; ?>" alt="Profile">
                            <span class="ml-2 hidden md:block"><?php echo $_SESSION['full_name']; ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 glass-card rounded-md shadow-lg py-1 z-50" style="display: none;">
                            <a href="dashboard-renter.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Dashboard</a>
                            <a href="profile.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Profile</a>
                            <a href="my-bookings.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">My Bookings</a>
                            <a href="my-vehicles.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">My Vehicles</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Sign Out</a>
                        </div>
                    </div>
                    
                    <!-- Mobile menu button -->
                    <div class="flex items-center md:hidden ml-4">
                        <button type="button" class="text-gray-400 hover:text-white focus:outline-none" aria-controls="mobile-menu" aria-expanded="false" id="mobile-menu-button">
                            <span class="sr-only">Open main menu</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu, show/hide based on menu state. -->
        <div class="md:hidden hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1 glass-card border-t border-gray-800/30">
                <a href="index.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Home</a>
                <a href="search.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Search</a>
                <a href="about.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">About</a>
                <a href="contact.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Contact</a>
            </div>
        </div>
    </nav>
    <!-- Main content -->
    <div class="relative z-10 py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="glass-card rounded-2xl p-8 shadow-lg mb-8">
                <h1 class="text-2xl font-bold gradient-text mb-6">Edit Vehicle</h1>
                
                <?php if (!empty($message)): ?>
                    <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900/40 border border-green-500/50 text-white' : 'bg-red-900/40 border border-red-500/50 text-white'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" class="space-y-8">
                    <!-- Basic Information Section -->
                    <div>
                        <h2 class="text-lg font-semibold text-primary mb-4">Basic Information</h2>
                        <div class="glass-card rounded-xl p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="title" class="block text-white text-sm font-medium mb-2">Vehicle Title*</label>
                                    <input 
                                        type="text" 
                                        id="title" 
                                        name="title" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter vehicle title"
                                        value="<?php echo htmlspecialchars($vehicle['title']); ?>"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="vehicle_type" class="block text-white text-sm font-medium mb-2">Vehicle Type*</label>
                                    <select 
                                        id="vehicle_type" 
                                        name="vehicle_type" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        required
                                    >
                                        <option value="">Select Type</option>
                                        <option value="car" <?php echo $vehicle['vehicle_type'] === 'car' ? 'selected' : ''; ?>>Car</option>
                                        <option value="motorcycle" <?php echo $vehicle['vehicle_type'] === 'motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                        <option value="scooter" <?php echo $vehicle['vehicle_type'] === 'scooter' ? 'selected' : ''; ?>>Scooter</option>
                                        <option value="bicycle" <?php echo $vehicle['vehicle_type'] === 'bicycle' ? 'selected' : ''; ?>>Bicycle</option>
                                        <option value="other" <?php echo $vehicle['vehicle_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label for="description" class="block text-white text-sm font-medium mb-2">Description*</label>
                                <textarea 
                                    id="description" 
                                    name="description" 
                                    rows="4" 
                                    class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                                    placeholder="Enter vehicle description"
                                    required
                                ><?php echo htmlspecialchars($vehicle['description']); ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="brand" class="block text-white text-sm font-medium mb-2">Brand*</label>
                                    <input 
                                        type="text" 
                                        id="brand" 
                                        name="brand" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="e.g. Toyota, Honda"
                                        value="<?php echo htmlspecialchars($vehicle['brand']); ?>"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="model" class="block text-white text-sm font-medium mb-2">Model*</label>
                                    <input 
                                        type="text" 
                                        id="model" 
                                        name="model" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="e.g. Corolla, Civic"
                                        value="<?php echo htmlspecialchars($vehicle['model']); ?>"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="year" class="block text-white text-sm font-medium mb-2">Year*</label>
                                    <input 
                                        type="number" 
                                        id="year" 
                                        name="year" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="e.g. 2020"
                                        min="1900"
                                        max="<?php echo date('Y') + 1; ?>"
                                        value="<?php echo htmlspecialchars($vehicle['year']); ?>"
                                        required
                                    >
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="license_plate" class="block text-white text-sm font-medium mb-2">License Plate*</label>
                                    <input 
                                        type="text" 
                                        id="license_plate" 
                                        name="license_plate" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter license plate"
                                        value="<?php echo htmlspecialchars($vehicle['license_plate']); ?>"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="color" class="block text-white text-sm font-medium mb-2">Color*</label>
                                    <input 
                                        type="text" 
                                        id="color" 
                                        name="color" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="e.g. Red, Blue, Black"
                                        value="<?php echo htmlspecialchars($vehicle['color']); ?>"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="seats" class="block text-white text-sm font-medium mb-2">Seats (if applicable)</label>
                                    <input 
                                        type="number" 
                                        id="seats" 
                                        name="seats" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="e.g. 5"
                                        min="1"
                                        value="<?php echo htmlspecialchars($vehicle['seats']); ?>"
                                    >
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="fuel_type" class="block text-white text-sm font-medium mb-2">Fuel Type (if applicable)</label>
                                    <select 
                                        id="fuel_type" 
                                        name="fuel_type" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="">Select Fuel Type</option>
                                        <option value="Petrol" <?php echo $vehicle['fuel_type'] === 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                                        <option value="Diesel" <?php echo $vehicle['fuel_type'] === 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                                        <option value="Electric" <?php echo $vehicle['fuel_type'] === 'Electric' ? 'selected' : ''; ?>>Electric</option>
                                        <option value="Hybrid" <?php echo $vehicle['fuel_type'] === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                        <option value="CNG" <?php echo $vehicle['fuel_type'] === 'CNG' ? 'selected' : ''; ?>>CNG</option>
                                        <option value="LPG" <?php echo $vehicle['fuel_type'] === 'LPG' ? 'selected' : ''; ?>>LPG</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="transmission" class="block text-white text-sm font-medium mb-2">Transmission (if applicable)</label>
                                    <select 
                                        id="transmission" 
                                        name="transmission" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="">Select Transmission</option>
                                        <option value="Manual" <?php echo $vehicle['transmission'] === 'Manual' ? 'selected' : ''; ?>>Manual</option>
                                        <option value="Automatic" <?php echo $vehicle['transmission'] === 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                                        <option value="Semi-Automatic" <?php echo $vehicle['transmission'] === 'Semi-Automatic' ? 'selected' : ''; ?>>Semi-Automatic</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="mileage" class="block text-white text-sm font-medium mb-2">Mileage (km)</label>
                                    <input 
                                        type="number" 
                                        id="mileage" 
                                        name="mileage" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="e.g. 50000"
                                        min="0"
                                        step="1"
                                        value="<?php echo htmlspecialchars($vehicle['mileage']); ?>"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location & Pricing Section -->
                    <div>
                        <h2 class="text-lg font-semibold text-primary mb-4">Location & Pricing</h2>
                        <div class="glass-card rounded-xl p-6 space-y-6">
                            <div>
                                <label for="address" class="block text-white text-sm font-medium mb-2">Address*</label>
                                <input 
                                    type="text" 
                                    id="address" 
                                    name="address" 
                                    class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                    placeholder="Enter pickup/drop-off address"
                                    value="<?php echo htmlspecialchars($vehicle['address']); ?>"
                                    required
                                >
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="city" class="block text-white text-sm font-medium mb-2">City*</label>
                                    <input 
                                        type="text" 
                                        id="city" 
                                        name="city" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter city"
                                        value="<?php echo htmlspecialchars($vehicle['city']); ?>"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="state" class="block text-white text-sm font-medium mb-2">State*</label>
                                    <input 
                                        type="text" 
                                        id="state" 
                                        name="state" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter state"
                                        value="<?php echo htmlspecialchars($vehicle['state']); ?>"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="zip_code" class="block text-white text-sm font-medium mb-2">ZIP Code*</label>
                                    <input 
                                        type="text" 
                                        id="zip_code" 
                                        name="zip_code" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter ZIP code"
                                        value="<?php echo htmlspecialchars($vehicle['zip_code']); ?>"
                                        required
                                    >
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="price_per_day" class="block text-white text-sm font-medium mb-2">Price Per Day (₹)*</label>
                                    <input 
                                        type="number" 
                                        id="price_per_day" 
                                        name="price_per_day" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="e.g. 1500"
                                        min="1"
                                        step="any"
                                        value="<?php echo htmlspecialchars($vehicle['price_per_day']); ?>"
                                        required
                                    >
                                </div>
                                
                                <div class="flex items-center">
                                    <div class="flex items-center h-full pt-8">
                                        <input 
                                            type="checkbox" 
                                            id="availability" 
                                            name="availability" 
                                            class="h-5 w-5 text-primary focus:ring-primary border-gray-700 rounded bg-gray-900/60"
                                            <?php echo $vehicle['availability'] ? 'checked' : ''; ?>
                                        >
                                        <label for="availability" class="ml-2 block text-sm text-white">
                                            This vehicle is available for rent
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Images Section -->
                    <div>
                        <h2 class="text-lg font-semibold text-primary mb-4">Vehicle Images</h2>
                        <div class="glass-card rounded-xl p-6 space-y-6">
                            <!-- Current Images Display -->
                            <div>
                                <h3 class="font-medium text-white mb-4">Current Images</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    <?php if ($primaryImage): ?>
                                        <div class="glass-card p-3 rounded-lg relative group">
                                            <div class="absolute top-2 right-2 z-10">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary text-white">
                                                    Primary
                                                </span>
                                            </div>
                                            <div class="aspect-w-4 aspect-h-3 mb-2">
                                                <img src="<?php echo htmlspecialchars($primaryImage['image_url']); ?>" alt="Primary Vehicle Image" class="w-full h-48 object-cover rounded-lg">
                                            </div>
                                            <div class="flex justify-between mt-2">
                                                <a href="edit-vehicle.php?id=<?php echo $vehicle_id; ?>&delete_image=<?php echo $primaryImage['image_id']; ?>" class="text-red-500 hover:text-red-400 text-sm" onclick="return confirm('Are you sure you want to delete this image?');">
                                                    Delete
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($additionalImages as $image): ?>
                                        <div class="glass-card p-3 rounded-lg relative group">
                                            <div class="aspect-w-4 aspect-h-3 mb-2">
                                                <img src="<?php echo htmlspecialchars($image['image_url']); ?>" alt="Vehicle Image" class="w-full h-48 object-cover rounded-lg">
                                            </div>
                                            <div class="flex justify-between mt-2">
                                                <a href="edit-vehicle.php?id=<?php echo $vehicle_id; ?>&set_primary=<?php echo $image['image_id']; ?>" class="text-primary hover:text-blue-400 text-sm">
                                                    Set as Primary
                                                </a>
                                                <a href="edit-vehicle.php?id=<?php echo $vehicle_id; ?>&delete_image=<?php echo $image['image_id']; ?>" class="text-red-500 hover:text-red-400 text-sm" onclick="return confirm('Are you sure you want to delete this image?');">
                                                    Delete
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($primaryImage) && empty($additionalImages)): ?>
                                        <div class="col-span-full text-center text-gray-400 py-8">
                                            <p>No images available. Please upload images of your vehicle.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Upload New Images -->
                            <div class="border-t border-gray-700/50 pt-6">
                                <h3 class="font-medium text-white mb-4">Upload New Images</h3>
                                
                                <div class="mb-6">
                                    <label class="block text-white text-sm font-medium mb-2">Primary Image</label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-700 border-dashed rounded-lg">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-400">
                                                <label for="primary_image" class="relative cursor-pointer bg-gray-900/60 rounded-md font-medium text-primary hover:text-blue-400 focus-within:outline-none">
                                                    <span>Upload a file</span>
                                                    <input id="primary_image" name="primary_image" type="file" class="sr-only" accept="image/*">
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-400">
                                                PNG, JPG, GIF up to 5MB
                                            </p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-400">This will replace the current primary image if one exists.</p>
                                </div>
                                
                                <div>
                                    <label class="block text-white text-sm font-medium mb-2">Additional Images</label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-700 border-dashed rounded-lg">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-400">
                                                <label for="additional_images" class="relative cursor-pointer bg-gray-900/60 rounded-md font-medium text-primary hover:text-blue-400 focus-within:outline-none">
                                                    <span>Upload files</span>
                                                    <input id="additional_images" name="additional_images[]" type="file" class="sr-only" accept="image/*" multiple>
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-400">
                                                PNG, JPG, GIF up to 5MB (select multiple files to upload)
                                            </p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-400">These will be added to your existing additional images.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex items-center justify-between">
                        <a href="vehicle-details.php?id=<?php echo $vehicle_id; ?>" class="inline-flex items-center text-primary hover:text-blue-400 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            View Vehicle Details
                        </a>
                        
                        <button 
                            type="submit" 
                            class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-8 rounded-lg transition duration-300 ease-in-out flex items-center"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center">
                <a href="my-vehicles.php" class="inline-flex items-center text-primary hover:text-blue-400 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to My Vehicles
                </a>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="py-12 px-4 sm:px-6 lg:px-8 border-t border-gray-800/30 mt-12">
        <div class="max-w-7xl mx-auto">
            <div class="border-t border-gray-800/30 mt-8 pt-8 text-center">
                <p class="text-gray-300">&copy; <?php echo date('Y'); ?> RentMyKeys. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Page transition script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loader = document.getElementById('page-loader');
            
            // Show loader
            loader.classList.add('active');
            
            // Hide loader after page loads
            setTimeout(function() {
                loader.classList.remove('active');
            }, 1500);
            
            // For links to other pages
            document.addEventListener('click', function(e) {
                const target = e.target.closest('a');
                
                if (target && target.href && !target.href.includes('#') && 
                    !target.target && target.href !== window.location.href) {
                    e.preventDefault();
                    loader.classList.add('active');
                    
                    setTimeout(function() {
                        window.location.href = target.href;
                    }, 1000);
                }
            });
            
            // For form submissions
            document.addEventListener('submit', function(e) {
                const form = e.target;
                
                if (!form.hasAttribute('data-ajax')) {
                    e.preventDefault();
                    loader.classList.add('active');
                    
                    setTimeout(function() {
                        form.submit();
                    }, 1000);
                }
            });

            // Mobile menu toggle
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            // Image preview
            const primaryImageInput = document.getElementById('primary_image');
            const additionalImagesInput = document.getElementById('additional_images');
            
            if (primaryImageInput) {
                primaryImageInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const preview = primaryImageInput.closest('div').querySelector('svg').parentNode;
                            preview.innerHTML = `<img src="${e.target.result}" class="mx-auto h-24 w-auto rounded-md" alt="Preview">`;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            if (additionalImagesInput) {
                additionalImagesInput.addEventListener('change', function() {
                    const files = this.files;
                    if (files.length > 0) {
                        const preview = additionalImagesInput.closest('div').querySelector('svg').parentNode;
                        let previewHTML = '<div class="flex flex-wrap justify-center">';
                        
                        for (let i = 0; i < Math.min(files.length, 4); i++) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewHTML += `<img src="${e.target.result}" class="h-16 w-auto mx-1 my-1 rounded-md" alt="Preview ${i+1}">`;
                                
                                if (i === Math.min(files.length, 4) - 1) {
                                    if (files.length > 4) {
                                        previewHTML += `<div class="flex items-center justify-center h-16 w-16 bg-gray-800 rounded-md mx-1 my-1"><span class="text-white">+${files.length - 4}</span></div>`;
                                    }
                                    previewHTML += '</div>';
                                    preview.innerHTML = previewHTML;
                                }
                            };
                            reader.readAsDataURL(files[i]);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>