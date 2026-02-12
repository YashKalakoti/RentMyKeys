<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Get featured vehicles
$sql = "SELECT v.*, vi.image_url 
        FROM vehicles v 
        LEFT JOIN (
            SELECT vehicle_id, image_url FROM vehicle_images WHERE is_primary = 1
            UNION
            SELECT vehicle_id, MIN(image_url) FROM vehicle_images GROUP BY vehicle_id
        ) vi ON v.vehicle_id = vi.vehicle_id 
        WHERE v.availability = 1 
        ORDER BY v.created_at DESC 
        LIMIT 8";
$result = $conn->query($sql);
$featured_vehicles = [];
while ($row = $result->fetch_assoc()) {
    $featured_vehicles[] = $row;
}

// Get vehicle types
$sql = "SELECT vehicle_type, COUNT(*) as count FROM vehicles GROUP BY vehicle_type";
$result = $conn->query($sql);
$vehicle_types = [];
while ($row = $result->fetch_assoc()) {
    $vehicle_types[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentMyKeys - Rent Vehicles Near You</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js for interactions -->
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
    <!-- GSAP for animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
    <!-- Custom Tailwind configurations -->
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
                        'spin-slow': 'spin 15s linear infinite',
                        'bounce-slow': 'bounce 3s infinite',
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
        
        .glow-3 {
            top: 40%;
            right: 30%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.2) 0%, rgba(217, 70, 0, 0.05) 70%);
            animation: pulse 12s infinite alternate;
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
            background: rgba(0, 0, 0, 0.9); /* Black with 90% opacity */
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
            position: relative;
        }

        .pulsing-logo {
            animation: pulse-logo 2s infinite ease-in-out;
        }

        @keyframes pulse-logo {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .pulsing-text {
            animation: pulse-text 1.5s infinite ease-in-out;
        }

        @keyframes pulse-text {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Glass card effects */
        .glass-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.7) 0%, rgba(17, 24, 39, 0.4) 100%);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .glass-card-hover {
            transition: all 0.3s ease;
        }
        
        .glass-card-hover:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.3);
            border: 1px solid rgba(99, 102, 241, 0.4);
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Gradient buttons */
        .gradient-button {
            background: linear-gradient(45deg, #3B82F6, #6366F1);
            background-size: 200% 100%;
            transition: all 0.5s ease;
        }

        .gradient-button:hover {
            background-position: 100% 0;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        /* Neon borders */
        .neon-border {
            position: relative;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .neon-border::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #3B82F6, #8B5CF6, #EC4899, #3B82F6);
            background-size: 400% 400%;
            z-index: -1;
            border-radius: 0.6rem;
            animation: border-animation 6s linear infinite;
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

        /* Shiny effect */
        .shiny {
            position: relative;
            overflow: hidden;
        }

        .shiny::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.1) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            transform: rotate(30deg);
            animation: shine 6s infinite linear;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) rotate(30deg); }
            100% { transform: translateX(100%) rotate(30deg); }
        }

        /* 3D card effect */
        .card-3d {
            transition: transform 0.5s ease;
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .card-3d:hover {
            transform: rotateX(5deg) rotateY(5deg);
        }

        /* Circle animation */
        .circle-animation {
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.4) 0%, rgba(30, 64, 175, 0) 70%);
            animation: pulse-circle 4s infinite ease-in-out;
        }

        @keyframes pulse-circle {
            0% { transform: scale(0.8); opacity: 0.7; }
            50% { transform: scale(1.2); opacity: 0.9; }
            100% { transform: scale(0.8); opacity: 0.7; }
        }

        /* Nav button */
        .nav-button {
            background: rgba(59, 130, 246, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(99, 102, 241, 0.3);
            transition: all 0.3s ease;
        }
        
        .nav-button:hover {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(99, 102, 241, 0.5);
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="bg-black text-white min-h-screen overflow-x-hidden">
    <!-- Page Loader -->
    <div id="page-loader" class="page-loader">
        <div class="loader-content">
            <div class="mx-auto w-24 h-24 mb-4 flex justify-center items-center">
                <img src="assets/images/lol.png" alt="RentMyKeys Logo" class="pulsing-logo w-full h-auto">
            </div>
            <div class="mt-4 text-center">
                <p class="text-lg font-medium pulsing-text">Loading...</p>
            </div>
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

    <!-- Main content -->
    <div class="relative z-10 min-h-screen">
        <!-- Navbar -->
        <nav class="glass-card sticky top-0 z-50 border-b border-gray-800/30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20">
                    <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0 flex items-center">
    <img class="h-12 w-auto mr-2" src="assets/images/lol.png" alt="RentMyKeys">
                    </a>
                        <div class="hidden md:ml-8 md:flex md:items-center md:space-x-6">
                            <a href="index.php" class="text-white hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Home</a>
                            <a href="search.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Search</a>
                            <a href="about.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">About</a>
                            <a href="contact.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Contact</a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="relative mr-4" x-data="{ open: false }">
                            <button @click="open = !open" class="text-gray-300 hover:text-primary p-2 transition duration-300">
                                <span>English</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 z-50 glass-card" style="display: none;">
                                <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">English</a>
                                <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">Spanish</a>
                                <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">French</a>
                                <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">German</a>
                            </div>
                        </div>
                        <?php if (isLoggedIn()): ?>
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="flex items-center text-white focus:outline-none group">
                                    <div class="h-10 w-10 rounded-full overflow-hidden border-2 border-primary/50 group-hover:border-primary transition-all duration-300">
                                        <img class="h-full w-full object-cover" src="<?php echo isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] ? $_SESSION['profile_picture'] : 'assets/images/default-avatar.png'; ?>" alt="Profile">
                                    </div>
                                    <span class="ml-2 hidden md:block group-hover:text-primary transition-colors duration-300"><?php echo $_SESSION['full_name']; ?></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1 group-hover:text-primary transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 glass-card rounded-md shadow-lg py-1 z-50" style="display: none;">
                                    <a href="<?php echo isRenter() ? 'dashboard-renter.php' : 'dashboard-customer.php'; ?>" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">Dashboard</a>
                                    <a href="profile.php" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">Profile</a>
                                    <a href="my-bookings.php" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">My Bookings</a>
                                    <?php if (isRenter()): ?>
                                        <a href="my-vehicles.php" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">My Vehicles</a>
                                    <?php endif; ?>
                                    <a href="logout.php" class="block px-4 py-2 text-sm text-white hover:bg-primary/20 transition duration-300">Sign Out</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="login.php" class="text-white hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition duration-300 relative overflow-hidden group">
                                <span class="relative z-10">Sign In</span>
                                <span class="absolute inset-0 bg-primary/10 rounded-md scale-0 group-hover:scale-100 transition-transform duration-300"></span>
                            </a>
                            <a href="register.php" class="neon-border ml-3">
                                <div class="gradient-button text-white px-4 py-2 rounded-md text-sm font-medium">Register</div>
                            </a>
                        <?php endif; ?>
                        
                        <!-- Mobile menu button -->
                        <div class="flex items-center md:hidden ml-4">
                            <button type="button" class="nav-button rounded-full p-2 flex items-center justify-center" aria-controls="mobile-menu" aria-expanded="false" id="mobile-menu-button">
                                <span class="sr-only">Open main menu</span>
                                <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
                    <a href="index.php" class="text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Home</a>
                    <a href="search.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Search</a>
                    <a href="about.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">About</a>
                    <a href="contact.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Contact</a>
                    <?php if (!isLoggedIn()): ?>
                        <a href="login.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Sign In</a>
                        <a href="register.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="py-16 md:py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <div class="absolute inset-0 z-0">
                <div class="absolute w-96 h-96 rounded-full bg-primary/20 filter blur-3xl -top-48 -left-48 animate-pulse-slow"></div>
                <div class="absolute w-96 h-96 rounded-full bg-accent/20 filter blur-3xl -bottom-48 -right-48 animate-pulse-slow"></div>
            </div>
            <div class="max-w-7xl mx-auto relative z-10">
                <div class="glass-card rounded-3xl overflow-hidden shadow-xl">
                    <div class="flex flex-col md:flex-row">
                        <div class="md:w-1/2 p-8 md:p-12 flex flex-col justify-center">
                            <h1 class="text-4xl md:text-5xl font-bold mb-4 hero-title">
                                <span class="gradient-text">Rent Your Vehicle.</span>
                                <span class="block text-white mt-2">Earn Money.</span>
                            </h1>
                            <p class="text-gray-300 text-lg mb-8">List your vehicle or find the perfect ride for your next adventure. RentMyKeys connects vehicle owners with people who need a ride.</p>
                            <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                                <a href="search.php" class="neon-border">
                                    <button class="gradient-button text-white font-bold py-3 px-6 rounded-lg flex items-center justify-center w-full">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Find a Vehicle
                                    </button>
                                </a>
                                <?php if (isLoggedIn() && isRenter()): ?>
                                    <a href="add-vehicle.php" class="group">
                                        <button class="w-full border border-primary hover:border-white text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center bg-transparent hover:bg-primary/20">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            List Your Vehicle
                                        </button>
                                    </a>
                                <?php else: ?>
                                    <a href="register.php?type=renter" class="group">
                                        <button class="w-full border border-primary hover:border-white text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center bg-transparent hover:bg-primary/20">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Become a Renter
                                        </button>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="md:w-1/2 relative">
                            <div class="absolute inset-0 z-0 flex items-center justify-center">
                                <div class="w-80 h-80 circle-animation opacity-50"></div>
                            </div>
                            <div class="relative z-10 hero-car-container h-full flex items-center justify-center" id="hero-car">
                                <img src="assets/images/car.png" alt="Luxury Car" class="w-full h-auto max-h-full object-contain transition-all duration-500 animate-float">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Search Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
            <div class="absolute inset-0 z-0">
                <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-gradient-to-br from-primary/30 to-transparent rounded-full filter blur-xl"></div>
                <div class="absolute bottom-1/4 right-1/4 w-64 h-64 bg-gradient-to-br from-accent/30 to-transparent rounded-full filter blur-xl"></div>
            </div>
            <div class="max-w-7xl mx-auto relative z-10">
                <div class="glass-card rounded-2xl p-8 md:p-10 shadow-lg transform hover:scale-[1.01] transition-all duration-300">
                    <h2 class="text-2xl md:text-3xl font-bold gradient-text mb-8 text-center">Find Your Perfect Ride</h2>
                    <form action="search.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="space-y-2">
        <label for="location" class="block text-sm font-medium text-gray-300">Location</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <input type="text" id="location" name="location" placeholder="City, Address or Zip Code" class="block w-full pl-10 pr-3 py-3 border border-gray-700/50 rounded-lg bg-gray-900/50 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300">
        </div>
    </div>
    
    <div class="space-y-2">
        <label for="start_date" class="block text-sm font-medium text-gray-300">Start Date</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <input type="date" id="start_date" name="start_date" class="block w-full pl-10 pr-3 py-3 border border-gray-700/50 rounded-lg bg-gray-900/50 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300" min="<?php echo date('Y-m-d'); ?>">
        </div>
    </div>
    
    <div class="space-y-2">
        <label for="end_date" class="block text-sm font-medium text-gray-300">End Date</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <input type="date" id="end_date" name="end_date" class="block w-full pl-10 pr-3 py-3 border border-gray-700/50 rounded-lg bg-gray-900/50 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
        </div>
    </div>
    
    <div class="space-y-2">
        <label for="vehicle_type" class="block text-sm font-medium text-gray-300">Vehicle Type</label>
        <div class="relative">
            <select id="vehicle_type" name="vehicle_type" class="block w-full py-3 px-3 border border-gray-700/50 rounded-lg bg-gray-900/50 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 appearance-none">
                <option value="">All Types</option>
                <option value="car">Car</option>
                <option value="motorcycle">Motorcycle</option>
                <option value="scooter">Scooter</option>
                <option value="bicycle">Bicycle</option>
                <option value="other">Other</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                <svg class="h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>
    </div>
    
    <div class="md:col-span-4">
        <div class="neon-border rounded-lg">
            <button type="submit" class="w-full gradient-button text-white font-bold py-3 px-4 rounded-lg flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Search Vehicles
            </button>
        </div>
    </div>
</form>
                </div>
            </div>
        </section>

        <!-- Featured Vehicles Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
            <div class="absolute inset-0 z-0">
                <div class="absolute top-1/3 right-1/4 w-64 h-64 bg-gradient-to-br from-accent/20 to-transparent rounded-full filter blur-xl"></div>
            </div>
            <div class="max-w-7xl mx-auto relative z-10">
                <div class="flex justify-between items-center mb-10">
                    <h2 class="text-2xl md:text-3xl font-bold gradient-text">Featured Vehicles</h2>
                    <a href="search.php" class="text-primary hover:text-accent transition duration-300 flex items-center group">
                        View All
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php if (empty($featured_vehicles)): ?>
                        <div class="col-span-full text-center py-10">
                            <p class="text-gray-300 text-lg">No vehicles available at the moment.</p>
                            <?php if (isRenter()): ?>
                                <a href="add-vehicle.php" class="inline-block mt-4 neon-border rounded-lg">
                                    <div class="gradient-button text-white font-bold py-2 px-4 rounded-lg">
                                        Be the first to list a vehicle
                                    </div>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($featured_vehicles as $vehicle): ?>
                            <div class="glass-card card-3d rounded-xl overflow-hidden shadow-lg">
                                <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="block relative aspect-[16/9] overflow-hidden shiny">
                                    <img src="<?php echo $vehicle['image_url'] ?? 'assets/images/vehicle-placeholder.jpg'; ?>" alt="<?php echo $vehicle['title']; ?>" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4">
                                        <div class="px-2 py-1 bg-gradient-to-r from-primary to-accent text-white text-xs font-semibold rounded-full inline-block">
                                            <?php echo ucfirst($vehicle['vehicle_type']); ?>
                                        </div>
                                    </div>
                                </a>
                                <div class="p-5">
                                    <div class="flex justify-between items-start">
                                        <h3 class="text-lg font-semibold truncate">
                                            <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="text-white hover:text-primary transition-colors duration-300">
                                                <?php echo $vehicle['title']; ?>
                                            </a>
                                        </h3>
                                        <p class="text-lg font-bold gradient-text">
                                            ₹<?php echo number_format($vehicle['price_per_day']); ?><span class="text-xs text-gray-300 ml-1">/day</span>
                                        </p>
                                    </div>
                                    <p class="text-gray-300 text-sm mt-1 mb-2 flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <?php echo $vehicle['city']; ?>, <?php echo $vehicle['state']; ?>
                                    </p>
                                    <div class="flex flex-wrap text-xs text-gray-300 mt-2 space-x-2">
                                        <?php if ($vehicle['year']): ?>
                                            <span class="flex items-center px-2 py-1 rounded-md bg-primary/10 border border-primary/20">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <?php echo $vehicle['year']; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($vehicle['transmission']): ?>
                                            <span class="flex items-center px-2 py-1 rounded-md bg-accent/10 border border-accent/20">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <?php echo $vehicle['transmission']; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($vehicle['fuel_type']): ?>
                                            <span class="flex items-center px-2 py-1 rounded-md bg-primary/10 border border-primary/20">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                <?php echo $vehicle['fuel_type']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-4">
                                        <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="bg-gray-800/60 hover:bg-primary/30 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out w-full inline-block text-center border border-primary/20 backdrop-blur-sm relative overflow-hidden group">
                                            <span class="relative z-10">View Details</span>
                                            <span class="absolute inset-0 bg-gradient-to-r from-primary/10 to-accent/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
            <div class="absolute inset-0 z-0">
                <div class="absolute bottom-1/3 left-1/4 w-64 h-64 bg-gradient-to-br from-primary/20 to-transparent rounded-full filter blur-xl"></div>
            </div>
            <div class="max-w-7xl mx-auto relative z-10">
                <h2 class="text-2xl md:text-3xl font-bold gradient-text mb-10 text-center">Browse by Type</h2>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                    <a href="search.php?vehicle_type=car" class="glass-card glass-card-hover rounded-xl p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center bg-gradient-to-br from-primary/30 to-primary/10 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Cars</h3>
                        <p class="text-gray-300 text-sm mt-1">
                            <?php 
                                $carCount = 0;
                                foreach ($vehicle_types as $type) {
                                    if ($type['vehicle_type'] == 'car') {
                                        $carCount = $type['count'];
                                        break;
                                    }
                                }
                                echo $carCount . ' ' . ($carCount == 1 ? 'Vehicle' : 'Vehicles');
                            ?>
                        </p>
                    </a>

                    <a href="search.php?vehicle_type=motorcycle" class="glass-card glass-card-hover rounded-xl p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center bg-gradient-to-br from-accent/30 to-accent/10 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Motorcycles</h3>
                        <p class="text-gray-300 text-sm mt-1">
                            <?php 
                                $motoCount = 0;
                                foreach ($vehicle_types as $type) {
                                    if ($type['vehicle_type'] == 'motorcycle') {
                                        $motoCount = $type['count'];
                                        break;
                                    }
                                }
                                echo $motoCount . ' ' . ($motoCount == 1 ? 'Vehicle' : 'Vehicles');
                            ?>
                        </p>
                    </a>

                    <a href="search.php?vehicle_type=scooter" class="glass-card glass-card-hover rounded-xl p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center bg-gradient-to-br from-primary/30 to-primary/10 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Scooters</h3>
                        <p class="text-gray-300 text-sm mt-1">
                            <?php 
                                $scooterCount = 0;
                                foreach ($vehicle_types as $type) {
                                    if ($type['vehicle_type'] == 'scooter') {
                                        $scooterCount = $type['count'];
                                        break;
                                    }
                                }
                                echo $scooterCount . ' ' . ($scooterCount == 1 ? 'Vehicle' : 'Vehicles');
                            ?>
                        </p>
                    </a>

                    <a href="search.php?vehicle_type=bicycle" class="glass-card glass-card-hover rounded-xl p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center bg-gradient-to-br from-accent/30 to-accent/10 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Bicycles</h3>
                        <p class="text-gray-300 text-sm mt-1">
                            <?php 
                                $bicycleCount = 0;
                                foreach ($vehicle_types as $type) {
                                    if ($type['vehicle_type'] == 'bicycle') {
                                        $bicycleCount = $type['count'];
                                        break;
                                    }
                                }
                                echo $bicycleCount . ' ' . ($bicycleCount == 1 ? 'Vehicle' : 'Vehicles');
                            ?>
                        </p>
                    </a>

                    <a href="search.php?vehicle_type=other" class="glass-card glass-card-hover rounded-xl p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center bg-gradient-to-br from-primary/30 to-primary/10 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Other</h3>
                        <p class="text-gray-300 text-sm mt-1">
                            <?php 
                                $otherCount = 0;
                                foreach ($vehicle_types as $type) {
                                    if ($type['vehicle_type'] == 'other') {
                                        $otherCount = $type['count'];
                                        break;
                                    }
                                }
                                echo $otherCount . ' ' . ($otherCount == 1 ? 'Vehicle' : 'Vehicles');
                            ?>
                        </p>
                    </a>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
            <div class="absolute inset-0 z-0">
                <div class="absolute top-1/4 right-1/4 w-64 h-64 bg-gradient-to-br from-primary/20 to-transparent rounded-full filter blur-xl"></div>
            </div>
            <div class="max-w-7xl mx-auto relative z-10">
                <div class="glass-card rounded-2xl p-8 md:p-12 shadow-lg">
                    <h2 class="text-2xl md:text-3xl font-bold gradient-text text-center mb-12">How RentMyKeys Works</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="text-center">
                            <div class="w-20 h-20 mx-auto mb-6 flex items-center justify-center bg-gradient-to-br from-primary/30 to-primary/10 rounded-full relative">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <div class="absolute -right-1 -top-1 bg-gradient-to-r from-primary to-accent rounded-full w-6 h-6 flex items-center justify-center text-white font-bold text-sm">
                                    1
                                </div>
                            </div>
                            <h3 class="text-xl font-semibold text-white mb-4">Search & Book</h3>
                            <p class="text-gray-300">Find the perfect vehicle for your needs, book it for your desired dates, and complete payment securely.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-20 h-20 mx-auto mb-6 flex items-center justify-center bg-gradient-to-br from-accent/30 to-accent/10 rounded-full relative">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                <div class="absolute -right-1 -top-1 bg-gradient-to-r from-primary to-accent rounded-full w-6 h-6 flex items-center justify-center text-white font-bold text-sm">
                                    2
                                </div>
                            </div>
                            <h3 class="text-xl font-semibold text-white mb-4">Pick Up & Go</h3>
                            <p class="text-gray-300">Meet the vehicle owner at the designated location, verify the vehicle, and start your journey.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-20 h-20 mx-auto mb-6 flex items-center justify-center bg-gradient-to-br from-primary/30 to-primary/10 rounded-full relative">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                </svg>
                                <div class="absolute -right-1 -top-1 bg-gradient-to-r from-primary to-accent rounded-full w-6 h-6 flex items-center justify-center text-white font-bold text-sm">
                                    3
                                </div>
                            </div>
                            <h3 class="text-xl font-semibold text-white mb-4">Return & Review</h3>
                            <p class="text-gray-300">Return the vehicle in the same condition, and share your experience with the community.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Become a Renter CTA -->
        <?php if (!isLoggedIn() || !isRenter()): ?>
        <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
            <div class="absolute inset-0 z-0">
                <div class="absolute bottom-1/4 left-1/4 w-64 h-64 bg-gradient-to-br from-accent/20 to-transparent rounded-full filter blur-xl"></div>
            </div>
            <div class="max-w-7xl mx-auto relative z-10">
                <div class="glass-card rounded-3xl overflow-hidden shadow-xl">
                    <div class="flex flex-col md:flex-row-reverse">
                        <div class="md:w-1/2 p-8 md:p-12 flex flex-col justify-center backdrop-blur-lg relative">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/20 rounded-full filter blur-3xl -z-10"></div>
                            <div class="absolute bottom-0 left-0 w-64 h-64 bg-accent/20 rounded-full filter blur-3xl -z-10"></div>
                            
                            <h2 class="text-3xl md:text-4xl font-bold gradient-text mb-4">Rent Out Your Vehicle</h2>
                            <p class="text-gray-300 text-lg mb-8">Turn your idle vehicle into a money-making asset. Join thousands of vehicle owners who earn extra income by renting out their vehicles.</p>
                            <div class="space-y-4">
                                <div class="flex items-start group">
                                    <div class="h-6 w-6 rounded-full bg-primary/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span class="text-white group-hover:text-primary transition-colors duration-300">Earn up to ₹30,000 per month</span>
                                </div>
                                <div class="flex items-start group">
                                    <div class="h-6 w-6 rounded-full bg-accent/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-accent/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span class="text-white group-hover:text-accent transition-colors duration-300">Secure payment processing</span>
                                </div>
                                <div class="flex items-start group">
                                    <div class="h-6 w-6 rounded-full bg-primary/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span class="text-white group-hover:text-primary transition-colors duration-300">24/7 customer support</span>
                                </div>
                            </div>
                            <div class="mt-8">
                                <a href="register.php?type=renter" class="neon-border inline-block">
                                    <button class="gradient-button text-white font-bold py-3 px-6 rounded-lg">
                                        Become a Renter
                                    </button>
                                </a>
                            </div>
                        </div>
                        <div class="md:w-1/2 relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-black to-transparent z-10"></div>
                            <img src="assets/images/group.jpg" alt="Earn Money" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="py-12 px-4 sm:px-6 lg:px-8 border-t border-gray-800/30 mt-12">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div>
                        <a href="index.php" class="flex items-center mb-4 group">
                            <div class="relative h-12 w-12 mr-2 overflow-hidden rounded-full shiny">
                                <img class="h-full w-full object-contain transform group-hover:scale-110 transition-transform duration-300" src="assets/images/lol.png" alt="RentMyKeys">
                            </div>
                            <span class="ml-2 text-2xl font-bold gradient-text">RentMyKeys</span>
                        </a>
                        <p class="text-gray-300 mb-4">Rent vehicles easily or earn money by listing your vehicle.</p>
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
                            <li><a href="index.php" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Home</a></li>
                            <li><a href="search.php" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Search Vehicles</a></li>
                            <li><a href="about.php" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">About Us</a></li>
                            <li><a href="contact.php" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Contact Us</a></li>
                            <li><a href="register.php?type=renter" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Become a Renter</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold gradient-text mb-4">Vehicle Types</h3>
                        <ul class="space-y-2">
                            <li><a href="search.php?vehicle_type=car" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Cars</a></li>
                            <li><a href="search.php?vehicle_type=motorcycle" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Motorcycles</a></li>
                            <li><a href="search.php?vehicle_type=scooter" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Scooters</a></li>
                            <li><a href="search.php?vehicle_type=bicycle" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Bicycles</a></li>
                            <li><a href="search.php?vehicle_type=other" class="text-gray-300 hover:text-primary transition-colors duration-300 relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Other Vehicles</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold gradient-text mb-4">Contact Us</h3>
                        <ul class="space-y-2">
                            <li class="flex items-start group">
                                <div class="h-6 w-6 rounded-full bg-primary/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/40">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 group-hover:text-primary transition-colors duration-300">Lovely Professional University, Phagwara</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="h-6 w-6 rounded-full bg-accent/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-accent/40">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 group-hover:text-accent transition-colors duration-300">+91 9027973734</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="h-6 w-6 rounded-full bg-primary/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/40">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 group-hover:text-primary transition-colors duration-300">yashkalakotibackup@gmail.com</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-800/30 mt-8 pt-8 text-center">
                    <p class="text-gray-300">&copy; <?php echo date('Y'); ?> RentMyKeys. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>

    <!-- Page transition script & 3D effects -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Page loader animation
            const loader = document.getElementById('page-loader');
            const progress = document.getElementById('loader-progress');
            
            // Show loader
            loader.classList.add('active');
            
            // Animate progress
            let width = 0;
            const progressInterval = setInterval(function() {
                if (width >= 100) {
                    clearInterval(progressInterval);
                    setTimeout(function() {
                        loader.classList.remove('active');
                    }, 300);
                } else {
                    width += 5;
                    if (progress) progress.style.width = width + '%';
                }
            }, 50);
            
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
            
            // 3D card effect for featured vehicles
            const cards = document.querySelectorAll('.card-3d');
            
            cards.forEach(card => {
                card.addEventListener('mousemove', e => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    
                    const angleX = (y - centerY) / 30;
                    const angleY = (centerX - x) / 30;
                    
                    card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
                });
            });
            
            // Hero car floating effect
            if (typeof gsap !== 'undefined') {
                const heroCar = document.getElementById('hero-car');
                if (heroCar) {
                    gsap.to(heroCar, {
                        y: 15,
                        rotation: 1,
                        duration: 2,
                        ease: "sine.inOut",
                        repeat: -1,
                        yoyo: true
                    });
                }
            }
        });
    </script>
</body>
</html>