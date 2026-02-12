<?php
// Add these lines at the very top of your file (after <?php)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "includes/functions.php";
include 'db.php';

// Check if user is logged in and is a renter
requireLogin();
if (!isRenter()) {
    header("Location: index.php");
    exit();
}

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $vehicle_type = sanitize($_POST['vehicle_type']);
    $brand = sanitize($_POST['brand']);
    $model = sanitize($_POST['model']);
    $year = sanitize($_POST['year']);
    $license_plate = sanitize($_POST['license_plate']);
    $color = sanitize($_POST['color']);
    $seats = isset($_POST['seats']) ? sanitize($_POST['seats']) : null;
    $fuel_type = isset($_POST['fuel_type']) ? sanitize($_POST['fuel_type']) : null;
    $transmission = isset($_POST['transmission']) ? sanitize($_POST['transmission']) : null;
    $mileage = isset($_POST['mileage']) ? sanitize($_POST['mileage']) : null;
    $price_per_day = sanitize($_POST['price_per_day']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $zip_code = sanitize($_POST['zip_code']);
    
    // Validate input
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
    
    if (empty($year) || !is_numeric($year)) {
        $errors[] = "Valid year is required";
    }
    
    if (empty($license_plate)) {
        $errors[] = "License plate number is required";
    }
    
    if (empty($color)) {
        $errors[] = "Color is required";
    }
    
    if (empty($price_per_day) || !is_numeric($price_per_day)) {
        $errors[] = "Valid price per day is required";
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
    
    // Check if at least one image is uploaded
    if (!isset($_FILES['vehicle_images']) || empty($_FILES['vehicle_images']['name'][0])) {
        $errors[] = "At least one vehicle image is required";
    }
    
    // Add vehicle if no errors
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert vehicle into database
            $stmt = $conn->prepare("INSERT INTO vehicles (user_id, title, description, vehicle_type, brand, model, year, license_plate, color, seats, fuel_type, transmission, mileage, price_per_day, address, city, state, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssissdsssss", 
                $_SESSION['user_id'], 
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
                $zip_code
            );
            
            $stmt->execute();
            $vehicle_id = $stmt->insert_id;
            
            // Upload vehicle images
            $image_count = count($_FILES['vehicle_images']['name']);
            for ($i = 0; $i < $image_count; $i++) {
                if ($_FILES['vehicle_images']['error'][$i] == 0) {
                    // Create file array for the current image
                    $file = array(
                        'name' => $_FILES['vehicle_images']['name'][$i],
                        'type' => $_FILES['vehicle_images']['type'][$i],
                        'tmp_name' => $_FILES['vehicle_images']['tmp_name'][$i],
                        'error' => $_FILES['vehicle_images']['error'][$i],
                        'size' => $_FILES['vehicle_images']['size'][$i]
                    );
                    
                    $image_path = uploadImage($file, "uploads/vehicles/");
                    
                    if ($image_path) {
                        // Set first image as primary
                        $is_primary = ($i == 0) ? 1 : 0;
                        
                        $stmt = $conn->prepare("INSERT INTO vehicle_images (vehicle_id, image_url, is_primary) VALUES (?, ?, ?)");
                        $stmt->bind_param("isi", $vehicle_id, $image_path, $is_primary);
                        $stmt->execute();
                    } else {
                        throw new Exception("Failed to upload image #" . ($i + 1));
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vehicle - RentMyKeys</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#1E40AF',
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
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        /* Base background style */
        body {
            background-color: #050714;
            overflow-x: hidden;
        }

        /* Improved background animation */
        @keyframes floatBackground {
            0% { transform: translate(0%, 0%) scale(1.15); opacity: 0.8; }
            25% { transform: translate(-3%, -2%) scale(1.2); opacity: 0.7; }
            50% { transform: translate(1%, -4%) scale(1.25); opacity: 0.9; }
            75% { transform: translate(-3%, 1%) scale(1.2); opacity: 0.7; }
            100% { transform: translate(2%, 2%) scale(1.15); opacity: 0.8; }
        }

        .background-animation {
            animation: floatBackground 40s ease-in-out infinite alternate;
            filter: blur(8px);
        }

        /* Enhanced glow effects */
        .glow {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.6;
        }

        .glow-1 {
            background: radial-gradient(circle, rgba(59, 130, 246, 0.4) 0%, rgba(29, 78, 216, 0.2) 70%);
            top: 15%;
            left: 10%;
            animation: pulse 12s infinite alternate;
        }

        .glow-2 {
            background: radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, rgba(91, 33, 182, 0.2) 70%);
            bottom: 10%;
            right: 15%;
            animation: pulse 14s infinite alternate-reverse;
        }

        .glow-3 {
            background: radial-gradient(circle, rgba(236, 72, 153, 0.3) 0%, rgba(190, 24, 93, 0.2) 70%);
            top: 40%;
            right: 5%;
            animation: pulse 16s infinite alternate;
        }

        .glow-4 {
            background: radial-gradient(circle, rgba(16, 185, 129, 0.25) 0%, rgba(5, 150, 105, 0.15) 70%);
            bottom: 30%;
            left: 15%;
            width: 250px;
            height: 250px;
            animation: pulse 18s infinite alternate-reverse;
        }

        @keyframes pulse {
            0% { opacity: 0.5; transform: scale(3.5); }
            50% { opacity: 0.7; transform: scale(4.5); }
            100% { opacity: 0.5; transform: scale(3.5); }
        }

        /* Enhanced glass card effect */
        .glass-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.5) 0%, rgba(59, 130, 246, 0.1) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(99, 102, 241, 0.2);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.2),
                0 0 10px rgba(59, 130, 246, 0.1),
                0 0 20px rgba(59, 130, 246, 0.05);
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(90deg, #60a5fa, #a78bfa, #f472b6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Improved gradient border */
        .gradient-border {
            position: relative;
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .gradient-border::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #3B82F6, #a78bfa, #f472b6, #3B82F6);
            background-size: 300% 300%;
            z-index: -1;
            border-radius: 0.85rem;
            animation: border-animation 8s linear infinite;
        }

        @keyframes border-animation {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        /* Enhanced form inputs */
        input[type="text"], 
        input[type="password"],
        input[type="email"],
        input[type="number"],
        select,
        textarea {
            background: rgba(17, 24, 39, 0.5);
            border: 1px solid rgba(75, 85, 99, 0.3);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        input[type="text"]:focus, 
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.3);
            background: rgba(17, 24, 39, 0.7);
        }

        /* Enhanced buttons */
        .nav-button {
            background: rgba(59, 130, 246, 0.15);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(99, 102, 241, 0.3);
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .nav-button:hover {
            background: rgba(59, 130, 246, 0.25);
            border: 1px solid rgba(99, 102, 241, 0.6);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }

        /* Submit button enhancement */
        button[type="submit"] {
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
            border: none;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }

        button[type="submit"]:hover {
            background: linear-gradient(90deg, #2563eb, #7c3aed);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        /* Additional floating elements */
        .floating-orb {
            position: absolute;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
            z-index: 1;
            pointer-events: none;
        }

        .orb-1 {
            top: 20%;
            left: 20%;
            animation: float-orb 15s infinite ease-in-out;
        }

        .orb-2 {
            top: 60%;
            right: 30%;
            width: 30px;
            height: 30px;
            animation: float-orb 18s infinite ease-in-out reverse;
        }

        .orb-3 {
            bottom: 15%;
            left: 40%;
            width: 40px;
            height: 40px;
            animation: float-orb 20s infinite ease-in-out;
        }

        @keyframes float-orb {
            0%, 100% { transform: translate(0, 0); }
            25% { transform: translate(30px, -20px); }
            50% { transform: translate(10px, 30px); }
            75% { transform: translate(-20px, 10px); }
        }
        
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
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
        
        /* Form section enhancements */
        .form-section {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .form-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.2);
        }
        
        /* Divider line */
        .divider {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 2rem 0;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            height: 1px;
            background: rgba(99, 102, 241, 0.2);
        }
        
        .divider span {
            position: relative;
            padding: 0 1rem;
            background: #050714;
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* File upload area styling */
        .file-upload-area {
            border: 2px dashed rgba(99, 102, 241, 0.3);
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover {
            border-color: rgba(99, 102, 241, 0.6);
            background: rgba(59, 130, 246, 0.05);
        }
        
        /* Centered OR divider */
        .or-divider {
            position: relative;
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
        }
        
        .or-divider::before {
            content: '';
            flex-grow: 1;
            height: 1px;
            background: rgba(75, 85, 99, 0.5);
            margin-right: 1rem;
        }
        
        .or-divider::after {
            content: '';
            flex-grow: 1;
            height: 1px;
            background: rgba(75, 85, 99, 0.5);
            margin-left: 1rem;
        }
        
        .or-divider-text {
            color: rgba(156, 163, 175, 0.8);
            padding: 0 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Action buttons */
        .action-button {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1;
        }
        
        .action-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .action-button:hover::before {
            opacity: 1;
        }
        
        .action-button:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
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
    <div class="glow glow-3"></div>
    <div class="glow glow-4"></div>
    
    <!-- Floating orbs -->
    <div class="floating-orb orb-1"></div>
    <div class="floating-orb orb-2"></div>
    <div class="floating-orb orb-3"></div>
    
    <!-- Navbar -->
    <nav class="glass-card sticky top-0 z-50 border-b border-gray-800/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0 flex items-center group">
                        <img class="h-10 w-auto transition transform group-hover:scale-110" src="assets/images/lol.png" alt="RentMyKeys">
                        <span class="ml-2 text-xl font-bold gradient-text">RentMyKeys</span>
                    </a>
                    <div class="hidden md:ml-6 md:flex md:items-center md:space-x-4">
                        <a href="index.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition duration-300 ease-in-out">Home</a>
                        <a href="search.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition duration-300 ease-in-out">Search</a>
                        <a href="about.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition duration-300 ease-in-out">About</a>
                        <a href="contact.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition duration-300 ease-in-out">Contact</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="relative mr-4" x-data="{ open: false }">
                        <button @click="open = !open" class="nav-button text-gray-300 hover:text-primary px-3 py-1 rounded-full">
                            <span>English</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-dark rounded-md shadow-lg py-1 z-50 glass-card" style="display: none;">
                            <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">English</a>
                            <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">Spanish</a>
                            <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">French</a>
                            <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">German</a>
                        </div>
                    </div>
                    <?php if (isLoggedIn()): ?>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="nav-button flex items-center text-white px-3 py-1 rounded-full">
                                <img class="h-8 w-8 rounded-full object-cover" src="<?php echo isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] ? $_SESSION['profile_picture'] : 'assets/images/default-avatar.png'; ?>" alt="Profile">
                                <span class="ml-2 hidden md:block"><?php echo $_SESSION['full_name']; ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 glass-card rounded-md shadow-lg py-1 z-50" style="display: none;">
                                <a href="<?php echo isRenter() ? 'dashboard-renter.php' : 'dashboard-customer.php'; ?>" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">Dashboard</a>
                                <a href="profile.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">Profile</a>
                                <a href="my-bookings.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">My Bookings</a>
                                <?php if (isRenter()): ?>
                                    <a href="my-vehicles.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">My Vehicles</a>
                                <?php endif; ?>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">Sign Out</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="nav-button text-white hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition duration-300 ease-in-out mr-2">Sign In</a>
                        <a href="register.php" class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-300 ease-in-out">Register</a>
                    <?php endif; ?>
                    
                    <!-- Mobile menu button -->
                    <div class="flex items-center md:hidden ml-4">
                        <button type="button" class="nav-button text-gray-400 hover:text-white px-2 py-1 rounded-full" aria-controls="mobile-menu" aria-expanded="false" id="mobile-menu-button">
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
    <div class="relative z-10 py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold gradient-text mb-4">List Your Vehicle</h1>
                <p class="text-xl text-gray-400 max-w-2xl mx-auto">Share yourvehicle details and start earning passive income. We handle the logistics, you collect the payments.</p>
            </div>
            
            <?php if ($success): ?>
                <div class="glass-card rounded-lg p-6 mb-8 bg-green-900/30 border border-green-500/30">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-green-300">Vehicle Listed Successfully!</h3>
                            <div class="mt-2 text-green-200">
                                <p>Your vehicle has been listed successfully. It will be available for customers to book.</p>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-4">
                                <a href="my-vehicles.php" class="gradient-border inline-block">
                                    <button class="bg-gradient-to-r from-green-600 to-green-800 hover:from-green-700 hover:to-green-900 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                        </svg>
                                        View My Vehicles
                                    </button>
                                </a>
                                <a href="add-vehicle.php" class="gradient-border inline-block">
                                    <button class="bg-gradient-to-r from-blue-900 to-gray-900 hover:from-blue-950 hover:to-black text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add Another Vehicle
                                    </button>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="glass-card rounded-lg p-6 mb-8 bg-red-900/30 border border-red-500/30">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-red-300">There were errors with your submission</h3>
                                <div class="mt-2 text-red-200">
                                    <ul class="list-disc list-inside">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="glass-card rounded-2xl overflow-hidden" style="background: rgba(17, 25, 51, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(34, 46, 66, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
                    <form method="POST" action="add-vehicle.php" enctype="multipart/form-data" class="p-8">
                        <div class="space-y-8">
                            <!-- Basic Information -->
                            <div class="form-section">
                                <h2 class="text-xl font-semibold gradient-text mb-5">Basic Information</h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="title" class="block text-white text-sm font-medium mb-2">Vehicle Title*</label>
                                        <input type="text" id="title" name="title" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. 2022 Honda Civic" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                                        <p class="mt-1 text-sm text-gray-400">A catchy title will attract more customers</p>
                                    </div>
                                    
                                    <div>
                                        <label for="vehicle_type" class="block text-white text-sm font-medium mb-2">Vehicle Type*</label>
                                        <select id="vehicle_type" name="vehicle_type" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" required>
                                            <option value="" disabled <?php echo !isset($_POST['vehicle_type']) ? 'selected' : ''; ?>>Select vehicle type</option>
                                            <option value="car" <?php echo isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'car' ? 'selected' : ''; ?>>Car</option>
                                            <option value="motorcycle" <?php echo isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                            <option value="scooter" <?php echo isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'scooter' ? 'selected' : ''; ?>>Scooter</option>
                                            <option value="bicycle" <?php echo isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'bicycle' ? 'selected' : ''; ?>>Bicycle</option>
                                            <option value="other" <?php echo isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label for="description" class="block text-white text-sm font-medium mb-2">Description*</label>
                                        <textarea id="description" name="description" rows="4" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Describe your vehicle, its features, condition, etc." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vehicle Details -->
                            <div class="form-section">
                                <h2 class="text-xl font-semibold gradient-text mb-5">Vehicle Details</h2>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label for="brand" class="block text-white text-sm font-medium mb-2">Brand*</label>
                                        <input type="text" id="brand" name="brand" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. Honda, Toyota" value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="model" class="block text-white text-sm font-medium mb-2">Model*</label>
                                        <input type="text" id="model" name="model" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. Civic, Corolla" value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : ''; ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="year" class="block text-white text-sm font-medium mb-2">Year*</label>
                                        <input type="number" id="year" name="year" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. 2022" min="1900" max="<?php echo date('Y') + 1; ?>" value="<?php echo isset($_POST['year']) ? htmlspecialchars($_POST['year']) : ''; ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="license_plate" class="block text-white text-sm font-medium mb-2">License Plate*</label>
                                        <input type="text" id="license_plate" name="license_plate" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. ABC1234" value="<?php echo isset($_POST['license_plate']) ? htmlspecialchars($_POST['license_plate']) : ''; ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="color" class="block text-white text-sm font-medium mb-2">Color*</label>
                                        <input type="text" id="color" name="color" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. Black, Red" value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : ''; ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="seats" class="block text-white text-sm font-medium mb-2">Seats</label>
                                        <input type="number" id="seats" name="seats" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. 5" min="1" value="<?php echo isset($_POST['seats']) ? htmlspecialchars($_POST['seats']) : ''; ?>">
                                    </div>
                                    
                                    <div>
                                        <label for="fuel_type" class="block text-white text-sm font-medium mb-2">Fuel Type</label>
                                        <select id="fuel_type" name="fuel_type" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary">
                                            <option value="" <?php echo !isset($_POST['fuel_type']) || $_POST['fuel_type'] == '' ? 'selected' : ''; ?>>Select fuel type</option>
                                            <option value="Petrol" <?php echo isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                                            <option value="Diesel" <?php echo isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                                            <option value="Electric" <?php echo isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                                            <option value="Hybrid" <?php echo isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                            <option value="CNG" <?php echo isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'CNG' ? 'selected' : ''; ?>>CNG</option>
                                            <option value="LPG" <?php echo isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'LPG' ? 'selected' : ''; ?>>LPG</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="transmission" class="block text-white text-sm font-medium mb-2">Transmission</label>
                                        <select id="transmission" name="transmission" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary">
                                            <option value="" <?php echo !isset($_POST['transmission']) || $_POST['transmission'] == '' ? 'selected' : ''; ?>>Select transmission</option>
                                            <option value="Automatic" <?php echo isset($_POST['transmission']) && $_POST['transmission'] == 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                                            <option value="Manual" <?php echo isset($_POST['transmission']) && $_POST['transmission'] == 'Manual' ? 'selected' : ''; ?>>Manual</option>
                                            <option value="CVT" <?php echo isset($_POST['transmission']) && $_POST['transmission'] == 'CVT' ? 'selected' : ''; ?>>CVT</option>
                                            <option value="Semi-Automatic" <?php echo isset($_POST['transmission']) && $_POST['transmission'] == 'Semi-Automatic' ? 'selected' : ''; ?>>Semi-Automatic</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                    <label for="mileage" class="block text-white text-sm font-medium mb-2">Mileage (km)</label>
                                        <input type="number" id="mileage" name="mileage" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. 25000" min="0" step="0.01" value="<?php echo isset($_POST['mileage']) ? htmlspecialchars($_POST['mileage']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pricing -->
                            <div class="form-section">
                                <h2 class="text-xl font-semibold gradient-text mb-5">Pricing</h2>
                                <div>
                                    <label for="price_per_day" class="block text-white text-sm font-medium mb-2">Price Per Day (₹)*</label>
                                    <input type="number" id="price_per_day" name="price_per_day" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. 1500" min="1" step="0.01" value="<?php echo isset($_POST['price_per_day']) ? htmlspecialchars($_POST['price_per_day']) : ''; ?>" required>
                                    <p class="mt-1 text-sm text-gray-400">Set a competitive price to attract more customers</p>
                                </div>
                            </div>
                            
                            <!-- Location -->
                            <div class="form-section">
                                <h2 class="text-xl font-semibold gradient-text mb-5">Location</h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2">
                                        <label for="address" class="block text-white text-sm font-medium mb-2">Address*</label>
                                        <input type="text" id="address" name="address" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. 123 Main Street" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="city" class="block text-white text-sm font-medium mb-2">City*</label>
                                        <input type="text" id="city" name="city" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. Mumbai" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="state" class="block text-white text-sm font-medium mb-2">State*</label>
                                        <input type="text" id="state" name="state" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. Maharashtra" value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="zip_code" class="block text-white text-sm font-medium mb-2">ZIP Code*</label>
                                        <input type="text" id="zip_code" name="zip_code" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="e.g. 400001" value="<?php echo isset($_POST['zip_code']) ? htmlspecialchars($_POST['zip_code']) : ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vehicle Images -->
                            <div class="form-section">
                                <h2 class="text-xl font-semibold gradient-text mb-5">Vehicle Images*</h2>
                                <div class="file-upload-area rounded-lg p-6 text-center bg-gray-900/20 hover:bg-gray-900/30 transition-all duration-300">
                                    <div class="mb-4">
                                        <svg class="mx-auto h-12 w-12 text-primary opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <input type="file" id="vehicle_images" name="vehicle_images[]" class="hidden" accept="image/*" multiple onchange="displayFileNames()">
                                        <label for="vehicle_images" class="cursor-pointer gradient-border inline-block">
                                            <span class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-block">
                                                Choose Images
                                            </span>
                                        </label>
                                        <p class="mt-3 text-sm text-gray-400">Upload clear images of your vehicle (max 5 images, JPG, PNG or GIF)</p>
                                        <p class="mt-1 text-sm text-gray-400">First image will be used as the main image</p>
                                    </div>
                                    <div id="file-names" class="mt-4 text-left"></div>
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="form-section">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="terms" name="terms" type="checkbox" class="h-5 w-5 text-primary focus:ring-primary border-gray-700 rounded bg-gray-900/50" required>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="terms" class="text-gray-300">
                                            I agree to the <a href="#" class="text-primary hover:text-blue-400 transition duration-300 underline">Terms and Conditions</a> and <a href="#" class="text-primary hover:text-blue-400 transition duration-300 underline">Privacy Policy</a>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div>
                                <div class="relative flex items-center justify-center my-6">
                                    <div class="absolute inset-0 flex items-center">
                                        <div class="w-full border-t border-gray-700/60"></div>
                                    </div>
                                    <div class="relative flex justify-center text-sm">
                                        <span class="px-4 bg-[#050714] text-gray-400">READY TO LIST</span>
                                    </div>
                                </div>
                                <button type="submit" class="w-full bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    List My Vehicle
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-12 px-4 sm:px-6 lg:px-8 border-t border-gray-800/30 mt-12">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <a href="index.php" class="flex items-center mb-4 group">
                        <img class="h-10 w-auto transition transform group-hover:scale-110" src="assets/images/lol.png" alt="RentMyKeys">
                        <span class="ml-2 text-xl font-bold gradient-text">RentMyKeys</span>
                    </a>
                    <p class="text-gray-400 mb-4">Rent vehicles easily or earn money by listing your vehicle.</p>
                    <div class="flex space-x-4">
                        <a href="https://facebook.com" target="_blank" class="text-gray-400 hover:text-primary transition duration-300 bg-gray-800/30 hover:bg-gray-700/40 p-2 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z" />
                            </svg>
                        </a>
                        <a href="https://twitter.com" target="_blank" class="text-gray-400 hover:text-primary transition duration-300 bg-gray-800/30 hover:bg-gray-700/40 p-2 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z" />
                            </svg>
                        </a>
                        <a href="https://instagram.com" target="_blank" class="text-gray-400 hover:text-primary transition duration-300 bg-gray-800/30 hover:bg-gray-700/40 p-2 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                            </svg>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold gradient-text mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-primary transition duration-300">Home</a></li>
                        <li><a href="search.php" class="text-gray-400 hover:text-primary transition duration-300">Search Vehicles</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-primary transition duration-300">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-primary transition duration-300">Contact Us</a></li>
                        <li><a href="register.php?type=renter" class="text-gray-400 hover:text-primary transition duration-300">Become a Renter</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold gradient-text mb-4">Vehicle Types</h3>
                    <ul class="space-y-2">
                        <li><a href="search.php?vehicle_type=car" class="text-gray-400 hover:text-primary transition duration-300">Cars</a></li>
                        <li><a href="search.php?vehicle_type=motorcycle" class="text-gray-400 hover:text-primary transition duration-300">Motorcycles</a></li>
                        <li><a href="search.php?vehicle_type=scooter" class="text-gray-400 hover:text-primary transition duration-300">Scooters</a></li>
                        <li><a href="search.php?vehicle_type=bicycle" class="text-gray-400 hover:text-primary transition duration-300">Bicycles</a></li>
                        <li><a href="search.php?vehicle_type=other" class="text-gray-400 hover:text-primary transition duration-300">Other Vehicles</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold gradient-text mb-4">Contact Us</h3>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <a href="https://maps.google.com" target="_blank" class="text-gray-400 hover:text-primary transition duration-300">Lovely Professional University, Phagwara</a>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <a href="tel:+919027973734" class="text-gray-400 hover:text-primary transition duration-300">+91 9027973734</a>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <a href="mailto:yashkalakotibackup@gmail.com" class="text-gray-400 hover:text-primary transition duration-300">yashkalakotibackup@gmail.com</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800/30 mt-8 pt-8 text-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> RentMyKeys. All rights reserved.</p>
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
                
                if (target && target.href && 
                    !target.href.includes('#') && 
                    !target.target && 
                    !target.hasAttribute('download') &&
                    target.origin === window.location.origin) {
                    
                    e.preventDefault();
                    loader.classList.add('active');
                    
                    // Make sure we're actually navigating after the timeout
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
            
            // Add floating orbs dynamically
            const createFloatingOrbs = () => {
                const container = document.body;
                for (let i = 0; i < 5; i++) {
                    const orb = document.createElement('div');
                    orb.className = 'floating-orb';
                    
                    // Random size
                    const size = Math.random() * 30 + 20;
                    orb.style.width = `${size}px`;
                    orb.style.height = `${size}px`;
                    
                    // Random position
                    orb.style.top = `${Math.random() * 90}%`;
                    orb.style.left = `${Math.random() * 90}%`;
                    
                    // Random animation duration
                    const duration = Math.random() * 10 + 15;
                    orb.style.animation = `float-orb ${duration}s infinite ease-in-out ${i % 2 ? 'alternate' : 'alternate-reverse'}`;
                    
                    container.appendChild(orb);
                }
            };
            
            // Call the function to create floating orbs
            createFloatingOrbs();
            
            // Add hover effects for glass cards
            const glassCards = document.querySelectorAll('.glass-card:not(.vehicle-card):not(.stats-card)');
            glassCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 25px -5px rgba(59, 130, 246, 0.3)';
                    this.style.borderColor = 'rgba(99, 102, 241, 0.4)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                    this.style.borderColor = '';
                });
            });
        });
        
        // Add parallax effect to background
        document.addEventListener('mousemove', function(e) {
            const moveX = (e.clientX / window.innerWidth) * 10;
            const moveY = (e.clientY / window.innerHeight) * 10;
            
            const background = document.querySelector('.background-animation');
            if (background) {
                background.style.transform = `translate(${-moveX}px, ${-moveY}px) scale(1.15)`;
            }
            
            // Also move the glow elements slightly
            const glows = document.querySelectorAll('.glow');
            glows.forEach((glow, index) => {
                const factor = index * 0.1 + 0.5;
                glow.style.transform = `translate(${moveX * factor}px, ${moveY * factor}px) scale(3.5)`;
            });
        });
        
        // Display file names after selection
        function displayFileNames() {
            const input = document.getElementById('vehicle_images');
            const fileNamesDiv = document.getElementById('file-names');
            fileNamesDiv.innerHTML = '';
            
            if (input.files.length > 0) {
                const fileList = document.createElement('ul');
                fileList.className = 'space-y-2 mt-4';
                
                for (let i = 0; i < input.files.length; i++) {
                    const listItem = document.createElement('li');
                    listItem.className = 'flex items-center bg-gray-800/30 text-gray-300 p-2 rounded-lg';
                    
                    // Add file icon
                    const icon = document.createElement('span');
                    icon.className = 'mr-2 text-primary';
                    icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>';
                    listItem.appendChild(icon);
                    
                    // Add file name
                    const nameSpan = document.createElement('span');
                    nameSpan.className = 'flex-1';
                    nameSpan.textContent = input.files[i].name;
                    listItem.appendChild(nameSpan);
                    
                    // Add badge for main image
                    if (i === 0) {
                        const badge = document.createElement('span');
                        badge.className = 'ml-2 bg-primary/20 text-primary text-xs px-2 py-1 rounded-full';
                        badge.textContent = 'Main Image';
                        listItem.appendChild(badge);
                    }
                    
                    fileList.appendChild(listItem);
                }
                
                fileNamesDiv.appendChild(fileList);
            }
        }
    </script>
</body>
</html>