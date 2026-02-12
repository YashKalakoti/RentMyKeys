<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Check if user is logged in
requireLogin();

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);

// Get user's bookings
$bookings_sql = "SELECT b.*, v.title, v.vehicle_type, v.price_per_day, v.brand, v.model, v.year, 
                u.full_name as owner_name, u.profile_picture as owner_picture,
                (SELECT image_url FROM vehicle_images WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_image
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

// Filter bookings by status if requested
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$filtered_bookings = [];

if ($status_filter !== 'all') {
    foreach ($bookings as $booking) {
        if ($booking['status'] === $status_filter) {
            $filtered_bookings[] = $booking;
        }
    }
} else {
    $filtered_bookings = $bookings;
}

// Calculate statistics
$total_bookings = count($bookings);
$pending_bookings = 0;
$confirmed_bookings = 0;
$completed_bookings = 0;
$cancelled_bookings = 0;
$total_spent = 0;

foreach ($bookings as $booking) {
    switch ($booking['status']) {
        case 'pending':
            $pending_bookings++;
            break;
        case 'confirmed':
            $confirmed_bookings++;
            break;
        case 'completed':
            $completed_bookings++;
            $total_spent += $booking['total_price'];
            break;
        case 'cancelled':
            $cancelled_bookings++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - RentMyKeys</title>
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
        
        /* Booking card enhancements */
        .booking-card {
            overflow: hidden;
            transition: all 0.4s ease;
            position: relative;
        }
        
        .booking-card::before {
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
        
        .booking-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.3), 0 0 15px rgba(59, 130, 246, 0.2);
        }
        
        .booking-card:hover::before {
            opacity: 1;
            animation: border-animation 4s linear infinite;
        }
        
        .booking-card img {
            transition: transform 0.6s ease;
        }
        
        .booking-card:hover img {
            transform: scale(1.1);
        }
        
        /* Filter tabs */
        .filter-tab {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .filter-tab::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .filter-tab:hover::after,
        .filter-tab.active::after {
            width: 100%;
        }
        
        .filter-tab.active {
            color: white;
            background: rgba(59, 130, 246, 0.2);
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
            <!-- Page header -->
            <div class="glass-card rounded-2xl p-6 mb-8" style="background: rgba(17, 25, 51, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(34, 46, 66, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
                <h1 class="text-3xl font-bold gradient-text mb-2">My Bookings</h1>
                <p class="text-gray-400">View and manage all your vehicle bookings in one place.</p>
            </div>
            
            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="glass-card rounded-xl p-6 stats-card">
                    <div class="flex items-center">
                        <div class="bg-primary/20 rounded-full p-3 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
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
                        <div class="bg-yellow-500/20 rounded-full p-3 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Pending / Confirmed</p>
                            <h3 class="text-2xl font-bold" style="background: linear-gradient(90deg, #F59E0B, #FBBF24); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">
                                <?php echo $pending_bookings + $confirmed_bookings; ?>
                            </h3>
                        </div>
                    </div>
                </div>
                
                <div class="glass-card rounded-xl p-6 stats-card">
                    <div class="flex items-center">
                        <div class="bg-green-500/20 rounded-full p-3 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Completed</p>
                            <h3 class="text-2xl font-bold" style="background: linear-gradient(90deg, #10B981, #34D399); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">
                            <?php echo $completed_bookings; ?>
                            </h3>
                        </div>
                    </div>
                </div>
                
                <div class="glass-card rounded-xl p-6 stats-card">
                    <div class="flex items-center">
                        <div class="bg-blue-500/20 rounded-full p-3 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Total Spent</p>
                            <h3 class="text-2xl font-bold" style="background: linear-gradient(90deg, #3B82F6, #60A5FA); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">
                                ₹<?php echo number_format($total_spent, 2); ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter tabs -->
            <div class="glass-card rounded-2xl p-6 mb-8">
                <div class="flex overflow-x-auto space-x-4 pb-2">
                    <a href="my-bookings.php" class="filter-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $status_filter === 'all' ? 'active' : 'text-gray-400'; ?>">
                        All Bookings
                    </a>
                    <a href="my-bookings.php?status=pending" class="filter-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $status_filter === 'pending' ? 'active' : 'text-gray-400'; ?>">
                        <span class="inline-block w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>
                        Pending
                    </a>
                    <a href="my-bookings.php?status=confirmed" class="filter-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $status_filter === 'confirmed' ? 'active' : 'text-gray-400'; ?>">
                        <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                        Confirmed
                    </a>
                    <a href="my-bookings.php?status=completed" class="filter-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $status_filter === 'completed' ? 'active' : 'text-gray-400'; ?>">
                        <span class="inline-block w-2 h-2 rounded-full bg-blue-500 mr-2"></span>
                        Completed
                    </a>
                    <a href="my-bookings.php?status=cancelled" class="filter-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $status_filter === 'cancelled' ? 'active' : 'text-gray-400'; ?>">
                        <span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                        Cancelled
                    </a>
                </div>
            </div>
            
            <!-- Bookings list -->
            <?php if (empty($filtered_bookings)): ?>
                <div class="glass-card rounded-2xl p-8 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 animate-pulse-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    <h3 class="mt-4 text-xl font-medium text-gray-300">No bookings found</h3>
                    <?php if ($status_filter !== 'all'): ?>
                        <p class="mt-2 text-gray-400">No <?php echo $status_filter; ?> bookings found. Try changing the filter.</p>
                    <?php else: ?>
                        <p class="mt-2 text-gray-400">You haven't made any bookings yet.</p>
                    <?php endif; ?>
                    <a href="search.php" class="gradient-border inline-block mt-6">
                        <button class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Find Vehicles to Rent
                        </button>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($filtered_bookings as $booking): ?>
                        <?php
                        // Determine the status color and badge style
                        $status_color = '';
                        $status_bg = '';
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
                        <div class="glass-card rounded-xl overflow-hidden booking-card">
                            <div class="flex flex-col md:flex-row">
                                <div class="md:w-2/5 relative overflow-hidden">
                                    <img src="<?php echo isset($booking['primary_image']) ? $booking['primary_image'] : 'assets/images/vehicle-placeholder.jpg'; ?>" alt="<?php echo $booking['title']; ?>" class="w-full h-full md:h-64 object-cover">
                                    <div class="absolute top-4 left-4">
                                        <div class="px-2 py-1 bg-gradient-to-r from-primary to-blue-500 text-white text-xs font-semibold rounded-full inline-block">
                                            <?php echo ucfirst($booking['vehicle_type']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="md:w-3/5 p-6">
                                    <div class="flex flex-col h-full">
                                        <div>
                                            <div class="flex justify-between items-start mb-4">
                                                <h3 class="text-xl font-semibold text-white">
                                                    <a href="vehicle-details.php?id=<?php echo $booking['vehicle_id']; ?>" class="hover:text-primary transition duration-300">
                                                        <?php echo $booking['title']; ?>
                                                    </a>
                                                </h3>
                                                <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $status_bg; ?> <?php echo $status_color; ?>">
                                                    <?php echo $status_icon; ?>
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-4 text-sm">
                                                <div class="flex items-center text-gray-400">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <div>
                                                        <span class="block"><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                                                        <span class="block text-xs text-gray-500">to</span>
                                                        <span class="block"><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex items-center text-gray-400">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                                    </svg>
                                                    <span class="font-semibold text-white">₹<?php echo number_format($booking['total_price'], 2); ?></span>
                                                </div>
                                            </div>
                                            
                                            <!-- Vehicle Details -->
                                            <div class="grid grid-cols-2 gap-2 text-sm mb-4">
                                                <?php if ($booking['brand']): ?>
                                                    <div class="flex items-center text-gray-400">
                                                        <span class="bg-gray-800/50 px-2 py-1 rounded-full flex items-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                                            </svg>
                                                            <?php echo $booking['brand']; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($booking['model']): ?>
                                                    <div class="flex items-center text-gray-400">
                                                        <span class="bg-gray-800/50 px-2 py-1 rounded-full flex items-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                                            </svg>
                                                            <?php echo $booking['model']; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($booking['year']): ?>
                                                    <div class="flex items-center text-gray-400">
                                                        <span class="bg-gray-800/50 px-2 py-1 rounded-full flex items-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                            <?php echo $booking['year']; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="flex items-center text-gray-400">
                                                    <span class="bg-gray-800/50 px-2 py-1 rounded-full flex items-center">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                                        </svg>
                                                        ₹<?php echo number_format($booking['price_per_day']); ?>/day
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Owner information -->
                                            <div class="flex items-center mb-4">
                                                <div class="flex items-center text-gray-400 text-sm">
                                                    <div class="gradient-border rounded-full h-8 w-8 inline-block">
                                                        <img src="<?php echo $booking['owner_picture'] ?? 'assets/images/default-avatar.png'; ?>" class="h-8 w-8 rounded-full object-cover" alt="Owner">
                                                    </div>
                                                    <span class="ml-2">Rented from: <span class="text-white"><?php echo $booking['owner_name']; ?></span></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Action buttons -->
                                        <div class="mt-auto pt-4 flex flex-wrap gap-2">
                                            <a href="booking-details.php?id=<?php echo $booking['booking_id']; ?>" class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex-1 text-center flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                View Details
                                            </a>
                                            
                                            <a href="vehicle-details.php?id=<?php echo $booking['vehicle_id']; ?>" class="bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                                View Vehicle
                                            </a>
                                            
                                            <?php if ($booking['status'] === 'confirmed' || $booking['status'] === 'completed'): ?>
                                                <a href="contact-owner.php?id=<?php echo $booking['user_id']; ?>" class="bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                    </svg>
                                                    Contact Owner
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <a href="cancel-booking.php?id=<?php echo $booking['booking_id']; ?>" class="bg-red-800 hover:bg-red-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center" onclick="return confirm('Are you sure you want to cancel this booking?');">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Cancel
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] === 'completed' && !hasReview($conn, $booking['booking_id'])): ?>
                                                <a href="add-review.php?booking_id=<?php echo $booking['booking_id']; ?>" class="bg-gradient-to-r from-yellow-500 to-amber-500 hover:from-yellow-600 hover:to-amber-600 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                    </svg>
                                                    Add Review
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Search more vehicles section -->
            <div class="glass-card rounded-2xl p-8 mt-8 text-center">
                <h2 class="text-2xl font-bold gradient-text mb-4">Looking for more vehicles?</h2>
                <p class="text-gray-400 mb-6">Browse our extensive collection of vehicles and find your perfect match.</p>
                <a href="search.php" class="gradient-border inline-block">
                    <button class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out flex items-center mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Search Vehicles
                    </button>
                </a>
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
                        <a href="#" class="text-gray-400 hover:text-primary transition-colors duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z" />
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-primary transition-colors duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z" />
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-primary transition-colors duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                            </svg>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold gradient-text mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-primary transition-colors duration-300">Home</a></li>
                        <li><a href="search.php" class="text-gray-400 hover:text-primary transition-colors duration-300">Search Vehicles</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-primary transition-colors duration-300">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-primary transition-colors duration-300">Contact Us</a></li>
                        <li><a href="register.php?type=renter" class="text-gray-400 hover:text-primary transition-colors duration-300">Become a Renter</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold gradient-text mb-4">Vehicle Types</h3>
                    <ul class="space-y-2">
                        <li><a href="search.php?vehicle_type=car" class="text-gray-400 hover:text-primary transition-colors duration-300">Cars</a></li>
                        <li><a href="search.php?vehicle_type=motorcycle" class="text-gray-400 hover:text-primary transition-colors duration-300">Motorcycles</a></li>
                        <li><a href="search.php?vehicle_type=scooter" class="text-gray-400 hover:text-primary transition-colors duration-300">Scooters</a></li>
                        <li><a href="search.php?vehicle_type=bicycle" class="text-gray-400 hover:text-primary transition-colors duration-300">Bicycles</a></li>
                        <li><a href="search.php?vehicle_type=other" class="text-gray-400 hover:text-primary transition-colors duration-300">Other Vehicles</a></li>
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
            const glassCards = document.querySelectorAll('.glass-card:not(.booking-card):not(.stats-card)');
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
        });
    </script>
    
    <?php
    // Helper function to check if user has already reviewed a booking
    function hasReview($conn, $booking_id) {
        $review_sql = "SELECT COUNT(*) as count FROM reviews WHERE booking_id = ?";
        $stmt = $conn->prepare($review_sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] > 0;
    }
    ?>
</body>
</html>