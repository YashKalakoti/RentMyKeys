<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Check if user is logged in and is a customer
requireLogin();
if (!isCustomer()) {
    header("Location: dashboard-renter.php");
    exit();
}

// Get customer information
$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);

// Get customer's bookings
$bookings_sql = "SELECT b.*, v.title, v.vehicle_type, v.price_per_day, 
                u.full_name as owner_name, u.profile_picture as owner_picture 
                FROM bookings b 
                JOIN vehicles v ON b.vehicle_id = v.vehicle_id 
                JOIN users u ON v.user_id = u.user_id 
                WHERE b.customer_id = ? 
                ORDER BY b.created_at DESC";
$stmt = $conn->prepare($bookings_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

// Get recent bookings
$recent_bookings = array_slice($bookings, 0, 5);

// Get statistics
// Total bookings
$total_bookings = count($bookings);

// Active bookings
$active_bookings = 0;
foreach ($bookings as $booking) {
    if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed') {
        $active_bookings++;
    }
}

// Total spent
$total_spent = 0;
foreach ($bookings as $booking) {
    if ($booking['status'] != 'cancelled') {
        $total_spent += $booking['total_price'];
    }
}

// Featured vehicles for recommendations
$featured_sql = "SELECT v.*, vi.image_url 
                FROM vehicles v 
                LEFT JOIN (
                    SELECT vehicle_id, image_url FROM vehicle_images WHERE is_primary = 1
                    UNION
                    SELECT vehicle_id, MIN(image_url) FROM vehicle_images GROUP BY vehicle_id
                ) vi ON v.vehicle_id = vi.vehicle_id 
                WHERE v.availability = 1 
                ORDER BY RAND() 
                LIMIT 6";
$result = $conn->query($featured_sql);
$featured_vehicles = [];
while ($row = $result->fetch_assoc()) {
    $featured_vehicles[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - RentMyKeys</title>
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
        input[type="email"] {
            background: rgba(17, 24, 39, 0.5);
            border: 1px solid rgba(75, 85, 99, 0.3);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        input[type="text"]:focus, 
        input[type="password"]:focus,
        input[type="email"]:focus {
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
        
        /* Enhanced stats cards */
        .stats-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);
        }
        
        .stats-card:hover::after {
            opacity: 1;
        }
        
        /* Enhanced table */
        .enhanced-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .enhanced-table tr {
            transition: all 0.2s ease;
        }
        
        .enhanced-table tr:hover {
            background: rgba(59, 130, 246, 0.1);
        }
        
        /* Vehicle card enhancements */
        .vehicle-card {
            overflow: hidden;
            transition: all 0.4s ease;
            position: relative;
        }
        
        .vehicle-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, rgba(59, 130, 246, 0.5), rgba(139, 92, 246, 0.5), rgba(236, 72, 153, 0.5));
            z-index: -1;
            border-radius: 0.85rem;
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .vehicle-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.3), 0 0 15px rgba(59, 130, 246, 0.2);
        }
        
        .vehicle-card:hover::before {
            opacity: 1;
            animation: border-animation 4s linear infinite;
        }
        
        .vehicle-card img {
            transition: transform 0.6s ease;
        }
        
        .vehicle-card:hover img {
            transform: scale(1.1);
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
                <img src="assets/images/logo.png" alt="RentMyKeys Logo" class="spinning-logo w-full h-auto">
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
                        <img class="h-10 w-auto transition transform group-hover:scale-110" src="assets/images/logo.png" alt="RentMyKeys">
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
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="nav-button flex items-center text-white px-3 py-1 rounded-full">
                            <img class="h-8 w-8 rounded-full object-cover" src="<?php echo isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] ? $_SESSION['profile_picture'] : 'assets/images/default-avatar.png'; ?>" alt="Profile">
                            <span class="ml-2 hidden md:block"><?php echo $_SESSION['full_name']; ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 glass-card rounded-md shadow-lg py-1 z-50" style="display: none;">
                            <a href="dashboard-customer.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">Dashboard</a>
                            <a href="profile.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">Profile</a>
                            <a href="my-bookings.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">My Bookings</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800/50 transition duration-300">Sign Out</a>
                        </div>
                    </div>
                    
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
    <div class="relative z-10 py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Welcome section -->
        <div class="relative z-10 py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Welcome section -->
        <div class="glass-card rounded-2xl p-6 mb-8" style="background: rgba(17, 25, 51, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(34, 46, 66, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-3/4">
                    <h1 class="text-3xl font-bold gradient-text mb-2">Welcome back, <?php echo $_SESSION['full_name']; ?>!</h1>
                    <p class="text-gray-500">Find and book the perfect vehicle for your needs.</p>
                </div>
                <div class="md:w-1/4 flex justify-center md:justify-end mt-4 md:mt-0">
                    <a href="search.php">
                        <button class="bg-gradient-to-r from-blue-900 to-gray-900 hover:from-blue-950 hover:to-black text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Find Vehicles
                        </button>
                    </a>
                </div>
            </div>
        </div>
            
            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="glass-card rounded-xl p-6 stats-card">
                    <div class="flex items-center">
                        <div class="bg-primary/20 rounded-full p-3 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Total Bookings</p>
                            <h3 class="text-2xl font-bold gradient-text"><?php echo $total_bookings; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="glass-card rounded-xl p-6 stats-card">
                    <div class="flex items-center">
                        <div class="bg-primary/20 rounded-full p-3 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Active Bookings</p>
                            <h3 class="text-2xl font-bold gradient-text"><?php echo $active_bookings; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="glass-card rounded-xl p-6 stats-card">
                    <div class="flex items-center">
                        <div class="bg-primary/20 rounded-full p-3 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m
                                0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Total Spent</p>
                            <h3 class="text-2xl font-bold gradient-text">₹<?php echo number_format($total_spent, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Bookings -->
            <div class="glass-card rounded-2xl p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold gradient-text">Recent Bookings</h2>
                    <a href="my-bookings.php" class="text-primary hover:text-blue-400 transition flex items-center action-button px-3 py-1 rounded-full">
                        View All
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
                
                <?php if (empty($recent_bookings)): ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 animate-pulse-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-gray-400 mt-4">You haven't made any bookings yet.</p>
                        <a href="search.php" class="gradient-border inline-block mt-4">
                            <button class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Find Vehicles to Book
                            </button>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full enhanced-table">
                            <thead>
                                <tr class="text-left text-gray-400 border-b border-gray-700/50">
                                    <th class="pb-3 font-medium">Vehicle</th>
                                    <th class="pb-3 font-medium">Owner</th>
                                    <th class="pb-3 font-medium">Dates</th>
                                    <th class="pb-3 font-medium">Amount</th>
                                    <th class="pb-3 font-medium">Status</th>
                                    <th class="pb-3 font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <tr class="border-b border-gray-700/30 text-white">
                                        <td class="py-4">
                                            <div class="flex items-center">
                                                <span class="bg-primary/30 border border-primary/30 text-white text-xs px-2 py-1 rounded-full mr-2"><?php echo ucfirst($booking['vehicle_type']); ?></span>
                                                <?php echo $booking['title']; ?>
                                            </div>
                                        </td>
                                        <td class="py-4">
                                            <div class="flex items-center">
                                                <div class="gradient-border rounded-full h-8 w-8 inline-block">
                                                    <img src="<?php echo $booking['owner_picture'] ?? 'assets/images/default-avatar.png'; ?>" class="h-8 w-8 rounded-full object-cover" alt="Owner">
                                                </div>
                                                <span class="ml-2"><?php echo $booking['owner_name']; ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4">
                                            <div class="text-sm">
                                                <div><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></div>
                                                <div class="text-gray-400">to</div>
                                                <div><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></div>
                                            </div>
                                        </td>
                                        <td class="py-4 font-semibold">₹<?php echo number_format($booking['total_price'], 2); ?></td>
                                        <td class="py-4">
                                            <?php
                                            $status_color = '';
                                            $status_bg = '';
                                            switch ($booking['status']) {
                                                case 'pending':
                                                    $status_color = 'bg-gradient-to-r from-yellow-500 to-yellow-600';
                                                    $status_bg = 'bg-yellow-500/10 border border-yellow-500/30';
                                                    break;
                                                case 'confirmed':
                                                    $status_color = 'bg-gradient-to-r from-green-500 to-green-600';
                                                    $status_bg = 'bg-green-500/10 border border-green-500/30';
                                                    break;
                                                case 'cancelled':
                                                    $status_color = 'bg-gradient-to-r from-red-500 to-red-600';
                                                    $status_bg = 'bg-red-500/10 border border-red-500/30';
                                                    break;
                                                case 'completed':
                                                    $status_color = 'bg-gradient-to-r from-blue-500 to-blue-600';
                                                    $status_bg = 'bg-blue-500/10 border border-blue-500/30';
                                                    break;
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_bg; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4">
                                            <a href="booking-details.php?id=<?php echo $booking['booking_id']; ?>" class="text-primary hover:text-blue-400 transition action-button px-3 py-1 rounded-full inline-block">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recommended Vehicles -->
            <div class="glass-card rounded-2xl p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold gradient-text">Recommended Vehicles</h2>
                    <a href="search.php" class="text-primary hover:text-blue-400 transition flex items-center action-button px-3 py-1 rounded-full">
                        View All
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
                
                <?php if (empty($featured_vehicles)): ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 animate-pulse-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        <p class="text-gray-400 mt-4">No vehicles available at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($featured_vehicles as $vehicle): ?>
                            <div class="glass-card rounded-xl overflow-hidden vehicle-card">
                                <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="block relative aspect-[16/9] overflow-hidden">
                                    <img src="<?php echo $vehicle['image_url'] ?? 'assets/images/vehicle-placeholder.jpg'; ?>" alt="<?php echo $vehicle['title']; ?>" class="w-full h-full object-cover">
                                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4">
                                        <div class="px-2 py-1 bg-gradient-to-r from-primary to-blue-500 text-white text-xs font-semibold rounded-full inline-block">
                                            <?php echo ucfirst($vehicle['vehicle_type']); ?>
                                        </div>
                                    </div>
                                </a>
                                <div class="p-4">
                                    <div class="flex justify-between items-start">
                                        <h3 class="text-lg font-semibold text-white truncate">
                                            <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="hover:text-primary transition">
                                                <?php echo $vehicle['title']; ?>
                                            </a>
                                        </h3>
                                        <p class="text-lg font-bold gradient-text">
                                            ₹<?php echo number_format($vehicle['price_per_day']); ?><span class="text-xs text-gray-400">/day</span>
                                        </p>
                                    </div>
                                    <p class="text-gray-400 text-sm mt-1 mb-2 flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <?php echo $vehicle['city']; ?>, <?php echo $vehicle['state']; ?>
                                    </p>
                                    <div class="flex flex-wrap text-xs text-gray-400 mt-2 space-x-2">
                                        <?php if ($vehicle['year']): ?>
                                            <span class="flex items-center bg-gray-800/50 px-2 py-1 rounded-full">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <?php echo $vehicle['year']; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($vehicle['transmission']): ?>
                                            <span class="flex items-center bg-gray-800/50 px-2 py-1 rounded-full">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <?php echo $vehicle['transmission']; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($vehicle['fuel_type']): ?>
                                            <span class="flex items-center bg-gray-800/50 px-2 py-1 rounded-full">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                <?php echo $vehicle['fuel_type']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-4">
                                        <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="gradient-border inline-block w-full">
                                            <button class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out w-full">
                                                Book Now
                                            </button>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="py-12 px-4 sm:px-6 lg:px-8 border-t border-gray-800/30 mt-12">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <a href="index.php" class="flex items-center mb-4 group">
                        <img class="h-10 w-auto transition transform group-hover:scale-110" src="assets/images/logo.png" alt="RentMyKeys">
                        <span class="ml-2 text-xl font-bold gradient-text">RentMyKeys</span>
                    </a>
                    <p class="text-gray-400 mb-4">Rent vehicles easily or earn money by listing your vehicle.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-primary transition bg-gray-800/30 p-2 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z" />
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-primary transition bg-gray-800/30 p-2 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z" />
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-primary transition bg-gray-800/30 p-2 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                            </svg>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold gradient-text mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-primary transition">Home</a></li>
                        <li><a href="search.php" class="text-gray-400 hover:text-primary transition">Search Vehicles</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-primary transition">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-primary transition">Contact Us</a></li>
                        <li><a href="register.php?type=renter" class="text-gray-400 hover:text-primary transition">Become a Renter</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold gradient-text mb-4">Vehicle Types</h3>
                    <ul class="space-y-2">
                        <li><a href="search.php?vehicle_type=car" class="text-gray-400 hover:text-primary transition">Cars</a></li>
                        <li><a href="search.php?vehicle_type=motorcycle" class="text-gray-400 hover:text-primary transition">Motorcycles</a></li>
                        <li><a href="search.php?vehicle_type=scooter" class="text-gray-400 hover:text-primary transition">Scooters</a></li>
                        <li><a href="search.php?vehicle_type=bicycle" class="text-gray-400 hover:text-primary transition">Bicycles</a></li>
                        <li><a href="search.php?vehicle_type=other" class="text-gray-400 hover:text-primary transition">Other Vehicles</a></li>
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
                            <span class="text-gray-400">123 Main Street, City, Country</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span class="text-gray-400">+91 9876543210</span>
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
    </script>
</body>
</html>