<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Check if user is logged in
requireLogin();

// Check if booking ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid booking ID.";
    header("Location: my-bookings.php");
    exit();
}

$booking_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get the booking details with joins to get all related information
$booking_sql = "SELECT b.*, 
                v.title, v.vehicle_type, v.brand, v.model, v.year, v.color, v.fuel_type, v.transmission, v.price_per_day, v.user_id as owner_id,
                v.address, v.city, v.state, v.zip_code,
                cu.full_name as customer_name, cu.profile_picture as customer_picture, cu.email as customer_email, cu.phone_number as customer_phone,
                ow.full_name as owner_name, ow.profile_picture as owner_picture, ow.email as owner_email, ow.phone_number as owner_phone,
                (SELECT image_url FROM vehicle_images WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_image,
                (SELECT COUNT(*) FROM reviews WHERE booking_id = b.booking_id) as has_review
                FROM bookings b 
                JOIN vehicles v ON b.vehicle_id = v.vehicle_id 
                JOIN users cu ON b.customer_id = cu.user_id 
                JOIN users ow ON v.user_id = ow.user_id 
                WHERE b.booking_id = ? AND (b.customer_id = ? OR v.user_id = ?)";
                
$stmt = $conn->prepare($booking_sql);
$stmt->bind_param("iii", $booking_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if booking exists and belongs to the user
if ($result->num_rows != 1) {
    $_SESSION['error'] = "Booking not found or you don't have permission to view it.";
    header("Location: my-bookings.php");
    exit();
}

$booking = $result->fetch_assoc();

// Calculate days and check if the dates are in the future
$start_date = new DateTime($booking['start_date']);
$end_date = new DateTime($booking['end_date']);
$now = new DateTime();
$days = $start_date->diff($end_date)->days + 1; // Including both start and end days
$is_future = $start_date > $now;

// Get review if it exists
$review = null;
if ($booking['has_review'] > 0) {
    $review_sql = "SELECT * FROM reviews WHERE booking_id = ? LIMIT 1";
    $stmt = $conn->prepare($review_sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $review_result = $stmt->get_result();
    if ($review_result->num_rows > 0) {
        $review = $review_result->fetch_assoc();
    }
}

// Handle status update for vehicle owners
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status']) && $user_id == $booking['owner_id']) {
    $new_status = sanitize($_POST['status']);
    $valid_statuses = ['confirmed', 'cancelled', 'completed'];
    
    if (in_array($new_status, $valid_statuses)) {
        $update_sql = "UPDATE bookings SET status = ?, updated_at = NOW() WHERE booking_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $booking_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Booking status has been updated successfully.";
            // Refresh the page to show updated status
            header("Location: booking-details.php?id=$booking_id");
            exit();
        } else {
            $_SESSION['error'] = "Failed to update booking status. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Invalid status provided.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - RentMyKeys</title>
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
        select {
            background: rgba(17, 24, 39, 0.5);
            border: 1px solid rgba(75, 85, 99, 0.3);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        input[type="text"]:focus, 
        input[type="password"]:focus,
        input[type="email"]:focus,
        select:focus {
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

        /* Rating stars */
        .stars {
            display: inline-flex;
            margin-right: 0.5rem;
        }
        
        .stars .star {
            color: #d1d5db;
        }
        
        .stars .star.filled {
            color: #fbbf24;
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
            100% { transform: rotate(360deg);}
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
                    <a href="my-bookings.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition duration-300 ease-in-out mr-2">Back to Bookings</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="relative z-10 py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="glass-card rounded-lg p-4 mb-6 bg-green-900/30 border border-green-500/30 flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-green-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-white"><?php echo $_SESSION['success']; ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="glass-card rounded-lg p-4 mb-6 bg-red-900/30 border border-red-500/30 flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-red-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="text-white"><?php echo $_SESSION['error']; ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Booking Header -->
            <div class="glass-card rounded-2xl p-6 mb-8">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold gradient-text mb-2">Booking #<?php echo str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT); ?></h1>
                        <p class="text-gray-400">Created on <?php echo date('F d, Y', strtotime($booking['created_at'])); ?></p>
                    </div>
                    
                    <?php
                        // Determine the status style
                        $status_color = '';
                        $status_bg = '';
                        $status_icon = '';
                        
                        switch ($booking['status']) {
                            case 'pending':
                                $status_color = 'text-yellow-500';
                                $status_bg = 'bg-yellow-500/10 border border-yellow-500/30';
                                $status_icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                                break;
                            case 'confirmed':
                                $status_color = 'text-green-500';
                                $status_bg = 'bg-green-500/10 border border-green-500/30';
                                $status_icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                                break;
                            case 'cancelled':
                                $status_color = 'text-red-500';
                                $status_bg = 'bg-red-500/10 border border-red-500/30';
                                $status_icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                                break;
                            case 'completed':
                                $status_color = 'text-blue-500';
                                $status_bg = 'bg-blue-500/10 border border-blue-500/30';
                                $status_icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';
                                break;
                        }
                    ?>
                    
                    <div class="mt-3 md:mt-0 flex items-center px-4 py-2 rounded-full text-sm font-medium <?php echo $status_bg; ?> <?php echo $status_color; ?>">
                        <?php echo $status_icon; ?>
                        <?php echo ucfirst($booking['status']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Left Column - Booking Details -->
                <div class="md:col-span-2">
                    <!-- Vehicle Info -->
                    <div class="glass-card rounded-2xl mb-8 overflow-hidden">
                        <div class="relative h-64">
                            <img 
                                src="<?php echo isset($booking['primary_image']) ? $booking['primary_image'] : 'assets/images/vehicle-placeholder.jpg'; ?>" 
                                alt="<?php echo $booking['title']; ?>" 
                                class="w-full h-full object-cover"
                            >
                            <div class="absolute top-4 left-4">
                                <div class="px-2.5 py-1.5 bg-gradient-to-r from-primary to-blue-500 text-white text-xs font-semibold rounded-full inline-block">
                                    <?php echo ucfirst($booking['vehicle_type']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <h2 class="text-2xl font-bold text-white mb-2"><?php echo $booking['title']; ?></h2>
                            
                            <div class="flex flex-wrap gap-4 mb-6">
                                <?php if ($booking['brand']): ?>
                                <div class="glass-card rounded-lg p-2 bg-gray-800/30 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                    </svg>
                                    <span><?php echo $booking['brand']; ?> <?php echo $booking['model']; ?> (<?php echo $booking['year']; ?>)</span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($booking['color']): ?>
                                <div class="glass-card rounded-lg p-2 bg-gray-800/30 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                    </svg>
                                    <span><?php echo $booking['color']; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($booking['fuel_type']): ?>
                                <div class="glass-card rounded-lg p-2 bg-gray-800/30 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    <span><?php echo $booking['fuel_type']; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($booking['transmission']): ?>
                                <div class="glass-card rounded-lg p-2 bg-gray-800/30 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span><?php echo $booking['transmission']; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="glass-card rounded-lg p-2 bg-gray-800/30 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span><?php echo $booking['address']; ?>, <?php echo $booking['city']; ?>, <?php echo $booking['state']; ?></span>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center mt-4">
                                <a href="vehicle-details.php?id=<?php echo $booking['vehicle_id']; ?>" class="text-primary hover:text-blue-400 transition flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    View Vehicle Details
                                </a>
                                
                                <div>
                                    <span class="text-gray-400">Price Per Day:</span>
                                    <span class="text-lg font-bold gradient-text">₹<?php echo number_format($booking['price_per_day'], 2); ?></span>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- Booking Details -->
                    <div class="glass-card rounded-2xl mb-8 p-6">
                        <h2 class="text-xl font-bold gradient-text mb-4">Booking Details</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-gray-400 mb-2">Booking Period</h3>
                                <div class="glass-card rounded-lg p-4 bg-gray-800/30">
                                    <div class="flex items-center mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="font-medium">Start Date:</span>
                                        <span class="ml-2"><?php echo date('F d, Y', strtotime($booking['start_date'])); ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="font-medium">End Date:</span>
                                        <span class="ml-2"><?php echo date('F d, Y', strtotime($booking['end_date'])); ?></span>
                                    </div>
                                    <div class="border-t border-gray-700/50 mt-3 pt-3 flex justify-between">
                                        <span>Duration:</span>
                                        <span class="font-medium"><?php echo $days; ?> <?php echo $days > 1 ? 'days' : 'day'; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="text-gray-400 mb-2">Payment Information</h3>
                                <div class="glass-card rounded-lg p-4 bg-gray-800/30">
                                    <div class="flex justify-between mb-2">
                                        <span>Days:</span>
                                        <span><?php echo $days; ?></span>
                                    </div>
                                    <div class="flex justify-between mb-2">
                                        <span>Price Per Day:</span>
                                        <span>₹<?php echo number_format($booking['price_per_day'], 2); ?></span>
                                    </div>
                                    <div class="border-t border-gray-700/50 mt-3 pt-3 flex justify-between">
                                        <span class="font-medium">Total Price:</span>
                                        <span class="font-bold text-lg gradient-text">₹<?php echo number_format($booking['total_price'], 2); ?></span>
                                    </div>
                                    <div class="mt-3 flex justify-between text-sm">
                                        <span>Payment Status:</span>
                                        <?php if ($booking['payment_status'] === 'paid'): ?>
                                            <span class="text-green-500">Paid</span>
                                        <?php else: ?>
                                            <span class="text-yellow-500">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Review section (if completed) -->
                    <?php if ($booking['status'] === 'completed' && $user_id == $booking['customer_id']): ?>
                        <div class="glass-card rounded-2xl p-6 mb-8">
                            <h2 class="text-xl font-bold gradient-text mb-4">Review</h2>
                            
                            <?php if ($review): ?>
                                <!-- Display existing review -->
                                <div class="glass-card rounded-lg p-4 bg-gray-800/30">
                                    <div class="flex items-center mb-3">
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $review['rating']): ?>
                                                    <span class="star filled">★</span>
                                                <?php else: ?>
                                                    <span class="star">★</span>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-sm text-gray-400"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                    </div>
                                    <p class="text-gray-300"><?php echo nl2br($review['comment']); ?></p>
                                </div>
                            <?php else: ?>
                                <!-- Prompt to add a review -->
                                <div class="text-center py-4">
                                    <p class="text-gray-400 mb-4">Share your experience and help others make better choices.</p>
                                    <a href="add-review.php?booking_id=<?php echo $booking['booking_id']; ?>" class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                        </svg>
                                        Add Your Review
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column - Sidebar -->
                <div class="md:col-span-1">
                    <!-- Actions -->
                    <div class="glass-card rounded-2xl p-6 mb-8">
                        <h2 class="text-xl font-bold gradient-text mb-4">Actions</h2>
                        
                        <div class="flex flex-col space-y-3">
                            <a href="my-bookings.php" class="bg-gray-800 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                Back to Bookings
                            </a>
                            
                            <a href="vehicle-details.php?id=<?php echo $booking['vehicle_id']; ?>" class="bg-gray-800 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                View Vehicle
                            </a>
                            
                            <?php if ($booking['status'] === 'pending' && $user_id == $booking['customer_id']): ?>
                                <a href="cancel-booking.php?id=<?php echo $booking['booking_id']; ?>" class="bg-red-800 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Cancel Booking
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] === 'completed' && $user_id == $booking['customer_id'] && !$review): ?>
                                <a href="add-review.php?booking_id=<?php echo $booking['booking_id']; ?>" class="bg-yellow-800 hover:bg-yellow-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                    Add Review
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Status Update for Vehicle Owners -->
                        <?php if ($user_id == $booking['owner_id'] && ($booking['status'] === 'pending' || $booking['status'] === 'confirmed')): ?>
                            <div class="mt-6 pt-6 border-t border-gray-800/30">
                                <h3 class="text-white font-medium mb-3">Update Status</h3>
                                <form method="POST" action="">
                                    <div class="mb-4">
                                        <select 
                                            name="status" 
                                            class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        >
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <option value="confirmed">Confirm Booking</option>
                                                <option value="cancelled">Cancel Booking</option>
                                            <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                <option value="completed">Mark as Completed</option>
                                                <option value="cancelled">Cancel Booking</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_status" class="w-full bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out">
                                        Update Status
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Person Information Box -->
                    <?php if ($user_id == $booking['owner_id']): ?>
                        <!-- Show customer info to the owner -->
                        <div class="glass-card rounded-2xl p-6 mb-8">
                            <h2 class="text-xl font-bold gradient-text mb-4">Customer Information</h2>
                            
                            <div class="flex items-center mb-4">
                                <div class="gradient-border rounded-full h-14 w-14 inline-block">
                                    <img src="<?php echo $booking['customer_picture'] ? $booking['customer_picture'] : 'assets/images/default-avatar.png'; ?>" alt="<?php echo $booking['customer_name']; ?>" class="h-14 w-14 rounded-full object-cover">
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-white"><?php echo $booking['customer_name']; ?></h3>
                                    <p class="text-gray-400 text-sm">Customer</p>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <?php if ($booking['customer_email']): ?>
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-gray-300"><?php echo $booking['customer_email']; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($booking['customer_phone']): ?>
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span class="text-gray-300"><?php echo $booking['customer_phone']; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <a href="contact-customer.php?id=<?php echo $booking['customer_id']; ?>" class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white text-center font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out block">
                                        Contact Customer
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Show owner info to the customer -->
                        <div class="glass-card rounded-2xl p-6 mb-8">
                            <h2 class="text-xl font-bold gradient-text mb-4">Owner Information</h2>
                            
                            <div class="flex items-center mb-4">
                                <div class="gradient-border rounded-full h-14 w-14 inline-block">
                                    <img src="<?php echo $booking['owner_picture'] ? $booking['owner_picture'] : 'assets/images/default-avatar.png'; ?>" alt="<?php echo $booking['owner_name']; ?>" class="h-14 w-14 rounded-full object-cover">
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-white"><?php echo $booking['owner_name']; ?></h3>
                                    <p class="text-gray-400 text-sm">Vehicle Owner</p>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <?php if ($booking['owner_email'] && ($booking['status'] === 'confirmed' || $booking['status'] === 'completed')): ?>
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-gray-300"><?php echo $booking['owner_email']; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($booking['owner_phone'] && ($booking['status'] === 'confirmed' || $booking['status'] === 'completed')): ?>
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span class="text-gray-300"><?php echo $booking['owner_phone']; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <a href="contact-owner.php?id=<?php echo $booking['owner_id']; ?>" class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white text-center font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out block">
                                        Contact Owner
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Helpful Information Box -->
                    <div class="glass-card rounded-2xl p-6">
                        <h2 class="text-xl font-bold gradient-text mb-4">Helpful Information</h2>
                        
                        <?php if ($booking['status'] === 'pending'): ?>
                            <div class="glass-card rounded-lg p-4 bg-yellow-900/20 border border-yellow-500/30 mb-4">
                                <h3 class="flex items-center text-yellow-400 font-medium mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Booking Status: Pending
                                </h3>
                                <?php if ($user_id == $booking['customer_id']): ?>
                                    <p class="text-gray-400 text-sm">Your booking is currently pending approval from the vehicle owner. You will be notified once it's confirmed.</p>
                                <?php else: ?>
                                    <p class="text-gray-400 text-sm">This booking is waiting for your approval. Please review the details and update the status.</p>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                            <div class="glass-card rounded-lg p-4 bg-green-900/20 border border-green-500/30 mb-4">
                                <h3 class="flex items-center text-green-400 font-medium mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Booking Status: Confirmed
                                </h3>
                                <p class="text-gray-400 text-sm">This booking has been confirmed. Contact information is now available to facilitate communication.</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_future && ($booking['status'] === 'confirmed' || $booking['status'] === 'pending')): ?>
                            <div class="mb-4">
                                <h3 class="text-white font-medium mb-2">Before Your Trip</h3>
                                <ul class="list-disc list-inside text-gray-400 text-sm space-y-1">
                                    <li>Ensure you have a valid driver's license</li>
                                    <li>Check vehicle details and pickup location</li>
                                    <li>Contact the owner for any specific instructions</li>
                                    <li>Be on time for pickup and return</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h3 class="text-white font-medium mb-2">Need Help?</h3>
                            <p class="text-gray-400 text-sm mb-3">If you have any questions or need assistance with this booking, please contact our customer support.</p>
                            <a href="contact.php" class="bg-gray-800 hover:bg-gray-700 text-white text-center font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out block">
                                Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
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
                        <a href="#" class="text-gray-400 hover:text-primary transition duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z" />
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-primary transition duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z" />
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-primary transition duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
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
                            <span class="text-gray-400">Lovely Professional University, Phagwara</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span class="text-gray-400">+91 9027973734</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span class="text-gray-400">info@rentmykeys.com</span>
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
        });
    </script>
</body>
</html>