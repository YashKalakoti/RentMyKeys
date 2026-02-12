<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Process filter parameters
$location = isset($_GET['location']) ? sanitize($_GET['location']) : '';
$vehicle_type = isset($_GET['vehicle_type']) ? sanitize($_GET['vehicle_type']) : '';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : 10000;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build the query
$query = "SELECT v.*, vi.image_url 
          FROM vehicles v 
          LEFT JOIN (
              SELECT vehicle_id, image_url FROM vehicle_images WHERE is_primary = 1
              UNION
              SELECT vehicle_id, MIN(image_url) FROM vehicle_images GROUP BY vehicle_id
          ) vi ON v.vehicle_id = vi.vehicle_id 
          WHERE v.availability = 1";
$params = array();
$types = "";

if (!empty($vehicle_type)) {
    $query .= " AND v.vehicle_type = ?";
    $params[] = $vehicle_type;
    $types .= "s";
}

if (!empty($location)) {
    $query .= " AND (v.address LIKE ? OR v.city LIKE ? OR v.state LIKE ? OR v.zip_code LIKE ?)";
    $location_param = "%$location%";
    $params[] = $location_param;
    $params[] = $location_param;
    $params[] = $location_param;
    $params[] = $location_param;
    $types .= "ssss";
}

if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND v.vehicle_id NOT IN (
                SELECT b.vehicle_id FROM bookings b 
                WHERE b.status IN ('pending', 'confirmed') 
                AND ((b.start_date BETWEEN ? AND ?) 
                OR (b.end_date BETWEEN ? AND ?) 
                OR (b.start_date <= ? AND b.end_date >= ?))
                )";
    $params[] = $start_date;
    $params[] = $end_date;
    $params[] = $start_date;
    $params[] = $end_date;
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ssssss";
}

$query .= " AND v.price_per_day BETWEEN ? AND ?";
$params[] = $min_price;
$params[] = $max_price;
$types .= "dd";

// Sort the results
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY v.price_per_day ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY v.price_per_day DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY v.created_at ASC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY v.created_at DESC";
        break;
}

// Count total results (for pagination)
$count_query = str_replace("v.*, vi.image_url", "COUNT(*) as total", $query);
$count_query = preg_replace('/ORDER BY.*$/i', '', $count_query);

$stmt = $conn->prepare($count_query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_count = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_count / $limit);

// Add limit and offset for pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Execute the query with pagination
$stmt = $conn->prepare($query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$vehicles = [];
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}

// Get distinct vehicle types for filter
$vehicle_types_query = "SELECT DISTINCT vehicle_type FROM vehicles";
$vehicle_types_result = $conn->query($vehicle_types_query);
$vehicle_types = [];
while ($row = $vehicle_types_result->fetch_assoc()) {
    $vehicle_types[] = $row['vehicle_type'];
}

// Get min and max prices for filter
$price_query = "SELECT MIN(price_per_day) as min_price, MAX(price_per_day) as max_price FROM vehicles";
$price_result = $conn->query($price_query);
$price_data = $price_result->fetch_assoc();
$db_min_price = floor($price_data['min_price']);
$db_max_price = ceil($price_data['max_price']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Vehicles - RentMyKeys</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js for interactions -->
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
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
        
        .glow {
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background-color: rgba(59, 130, 246, 0.15);
            filter: blur(50px);
            z-index: 0;
        }
        
        .glow-1 {
            top: 20%;
            left: 10%;
            animation: pulse 8s infinite alternate;
        }
        
        .glow-2 {
            bottom: 10%;
            right: 20%;
            animation: pulse 10s infinite alternate-reverse;
        }
        
        @keyframes pulse {
            0% { opacity: 0.7; transform: scale(4); }
            100% { opacity: 0.6; transform: scale(4); }
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
        
        /* Glass card effect */
        .glass-card {
            background-color: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(99, 102, 241, 0.1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        /* Range slider styles */
        input[type="range"] {
            -webkit-appearance: none;
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: #374151;
            outline: none;
        }

        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #3B82F6;
            cursor: pointer;
        }

        input[type="range"]::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #3B82F6;
            cursor: pointer;
            border: none;
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
    <nav class="glass-card sticky top-0 z-50 border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0 flex items-center">
                        <img class="h-10 w-auto" src="assets/images/lol.png" alt="RentMyKeys">
                    </a>
                    <div class="hidden md:ml-6 md:flex md:items-center md:space-x-4">
                        <a href="index.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="search.php" class="text-white hover:text-primary px-3 py-2 rounded-md text-sm font-medium border-b-2 border-primary">Search</a>
                        <a href="about.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <a href="contact.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Contact</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="relative mr-4" x-data="{ open: false }">
                        <button @click="open = !open" class="text-gray-300 hover:text-primary p-2">
                            <span>English</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-dark rounded-md shadow-lg py-1 z-50 glass-card" style="display: none;">
                            <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">English</a>
                            <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Spanish</a>
                            <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">French</a>
                            <a href="#" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">German</a>
                        </div>
                    </div>
                    <?php if (isLoggedIn()): ?>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center text-white focus:outline-none">
                                <img class="h-8 w-8 rounded-full object-cover" src="<?php echo isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] ? $_SESSION['profile_picture'] : 'assets/images/default-avatar.png'; ?>" alt="Profile">
                                <span class="ml-2 hidden md:block"><?php echo $_SESSION['full_name']; ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 glass-card rounded-md shadow-lg py-1 z-50" style="display: none;">
                                <a href="<?php echo isRenter() ? 'dashboard-renter.php' : 'dashboard-customer.php'; ?>" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Dashboard</a>
                                <a href="profile.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Profile</a>
                                <a href="my-bookings.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">My Bookings</a>
                                <?php if (isRenter()): ?>
                                    <a href="my-vehicles.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">My Vehicles</a>
                                <?php endif; ?>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Sign Out</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-white hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Sign In</a>
                        <a href="register.php" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md text-sm font-medium transition duration-300 ease-in-out">Register</a>
                    <?php endif; ?>
                    
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
            <div class="px-2 pt-2 pb-3 space-y-1 glass-card border-t border-gray-800">
                <a href="index.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Home</a>
                <a href="search.php" class="text-white block px-3 py-2 rounded-md text-base font-medium bg-gray-800">Search</a>
                <a href="about.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">About</a>
                <a href="contact.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Contact</a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="relative z-10 py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">Search Vehicles</h1>
                <p class="text-gray-400">Find the perfect vehicle for your next adventure</p>
            </div>
            
            <!-- Search Form and Filters -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-8">
                <!-- Filters (Desktop: Left Sidebar, Mobile: Top) -->
                <div class="lg:col-span-1 order-2 lg:order-1">
                    <div class="glass-card rounded-2xl p-6 sticky top-24">
                        <h2 class="text-xl font-semibold text-white mb-6">Filters</h2>
                        <form action="search.php" method="GET" id="filterForm">
                            <!-- Location -->
                            <div class="mb-6">
                                <label for="location" class="block text-white text-sm font-medium mb-2">Location</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" id="location" name="location" placeholder="City, Address or Zip Code" class="block w-full pl-10 pr-3 py-2 border border-gray-700 rounded-md bg-gray-900 text-white placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary" value="<?php echo $location; ?>">
                                </div>
                            </div>
                            
                            <!-- Date Range -->
                            <div class="mb-6">
                                <label for="start_date" class="block text-white text-sm font-medium mb-2">Start Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <input type="date" id="start_date" name="start_date" class="block w-full pl-10 pr-3 py-2 border border-gray-700 rounded-md bg-gray-900 text-white placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $start_date; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="end_date" class="block text-white text-sm font-medium mb-2">End Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <input type="date" id="end_date" name="end_date" class="block w-full pl-10 pr-3 py-2 border border-gray-700 rounded-md bg-gray-900 text-white placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo $end_date; ?>">
                                </div>
                            </div>
                            
                            <!-- Vehicle Type -->
                            <div class="mb-6">
                                <label class="block text-white text-sm font-medium mb-3">Vehicle Type</label>
                                <div class="space-y-2">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="vehicle_type" value="" class="form-radio h-4 w-4 text-primary focus:ring-primary border-gray-700 bg-gray-900" <?php echo $vehicle_type === '' ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-gray-300">All Types</span>
                                    </label>
                                    <?php foreach ($vehicle_types as $type): ?>
                                        <label class="inline-flex items-center block">
                                            <input type="radio" name="vehicle_type" value="<?php echo $type; ?>" class="form-radio h-4 w-4 text-primary focus:ring-primary border-gray-700 bg-gray-900" <?php echo $vehicle_type === $type ? 'checked' : ''; ?>>
                                            <span class="ml-2 text-gray-300"><?php echo ucfirst($type); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Price Range -->
                            <div class="mb-6">
                                <label class="block text-white text-sm font-medium mb-3">Price Range (per day)</label>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-gray-400">₹<?php echo $min_price; ?></span>
                                    <span class="text-gray-400">₹<?php echo $max_price; ?></span>
                                </div>
                                <div class="relative pt-1">
                                    <input type="range" id="min_price" name="min_price" min="<?php echo $db_min_price; ?>" max="<?php echo $db_max_price; ?>" value="<?php echo $min_price; ?>" class="mb-4">
                                    <input type="range" id="max_price" name="max_price" min="<?php echo $db_min_price; ?>" max="<?php echo $db_max_price; ?>" value="<?php echo $max_price; ?>">
                                </div>
                            </div>
                            
                            <!-- Sort By -->
                            <div class="mb-6">
                                <label for="sort" class="block text-white text-sm font-medium mb-2">Sort By</label>
                                <select id="sort" name="sort" class="block w-full px-4 py-2 border border-gray-700 rounded-md bg-gray-900 text-white placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary">
                                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                </select>
                            </div>
                            
                            <!-- Apply Filters Button -->
                            <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-bold py-3 px-4 rounded-lg transition duration-300 ease-in-out">
                                Apply Filters
                            </button>
                            
                            <!-- Reset Filters -->
                            <a href="search.php" class="w-full block text-center mt-3 text-gray-400 hover:text-white">
                                Reset Filters
                            </a>
                        </form>
                    </div>
                </div>
                
                <!-- Search Results -->
                <div class="lg:col-span-3 order-1 lg:order-2">
                    <!-- Results Summary -->
                    <div class="glass-card rounded-2xl p-6 mb-6">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                            <div>
                                <h2 class="text-xl font-semibold text-white">
                                    <?php echo $total_count; ?> Vehicles Found
                                    <?php if (!empty($location)): ?>
                                        <span class="text-gray-400 text-base font-normal">in "<?php echo $location; ?>"</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($vehicle_type)): ?>
                                        <span class="text-gray-400 text-base font-normal">• <?php echo ucfirst($vehicle_type); ?></span>
                                    <?php endif; ?>
                                </h2>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <div class="flex items-center">
                                    <span class="text-gray-400 mr-2">View:</span>
                                    <button class="bg-primary text-white p-2 rounded-l-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                        </svg>
                                    </button>
                                    <button class="bg-gray-800 text-gray-400 p-2 rounded-r-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Results -->
                    <?php if (empty($vehicles)): ?>
                        <div class="glass-card rounded-2xl p-12 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h3 class="text-xl font-semibold text-white mb-2">No vehicles found</h3>
                            <p class="text-gray-400 mb-6">Try adjusting your filters or search criteria.</p>
                            <a href="search.php" class="bg-primary hover:bg-secondary text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-block">
                                Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            <?php foreach ($vehicles as $vehicle): ?>
                                <div class="glass-card rounded-xl overflow-hidden transition-transform duration-300 hover:scale-105 hover:shadow-lg hover:shadow-primary/20">
                                    <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="block relative aspect-[16/9] overflow-hidden">
                                        <img src="<?php echo $vehicle['image_url'] ?? 'assets/images/vehicle-placeholder.jpg'; ?>" alt="<?php echo $vehicle['title']; ?>" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4">
                                            <div class="px-2 py-1 bg-primary text-white text-xs font-semibold rounded-full inline-block">
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
                                            <p class="text-lg font-bold text-primary">
                                                ₹<?php echo number_format($vehicle['price_per_day']); ?><span class="text-xs text-gray-400">/day</span>
                                            </p>
                                        </div>
                                        <p class="text-gray-400 text-sm mt-1 mb-2 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <?php echo $vehicle['city']; ?>, <?php echo $vehicle['state']; ?>
                                        </p>
                                        <div class="flex flex-wrap text-xs text-gray-400 mt-2 space-x-2">
                                            <?php if ($vehicle['year']): ?>
                                                <span class="flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <?php echo $vehicle['year']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($vehicle['transmission']): ?>
                                                <span class="flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    <?php echo $vehicle['transmission']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($vehicle['fuel_type']): ?>
                                                <span class="flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                    <?php echo $vehicle['fuel_type']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-4">
                                            <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="bg-primary hover:bg-secondary text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out w-full inline-block text-center">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="flex justify-center mt-8">
                                <div class="glass-card rounded-lg flex">
                                    <?php
                                    // Generate pagination links
                                    $current_url = strtok($_SERVER["REQUEST_URI"], '?');
                                    $query_params = $_GET;
                                    
                                    // Previous page link
                                    if ($page > 1) {
                                        $query_params['page'] = $page - 1;
                                        $prev_url = $current_url . '?' . http_build_query($query_params);
                                        echo '<a href="' . $prev_url . '" class="px-4 py-2 border-r border-gray-700 text-gray-300 hover:bg-gray-800">Previous</a>';
                                    } else {
                                        echo '<span class="px-4 py-2 border-r border-gray-700 text-gray-600 cursor-not-allowed">Previous</span>';
                                    }
                                    
                                    // Page numbers
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        $query_params['page'] = $i;
                                        $page_url = $current_url . '?' . http_build_query($query_params);
                                        
                                        if ($i == $page) {
                                            echo '<span class="px-4 py-2 border-r border-gray-700 bg-primary text-white">' . $i . '</span>';
                                        } else {
                                            echo '<a href="' . $page_url . '" class="px-4 py-2 border-r border-gray-700 text-gray-300 hover:bg-gray-800">' . $i . '</a>';
                                        }
                                    }
                                    
                                    // Next page link
                                    if ($page < $total_pages) {
                                        $query_params['page'] = $page + 1;
                                        $next_url = $current_url . '?' . http_build_query($query_params);
                                        echo '<a href="' . $next_url . '" class="px-4 py-2 text-gray-300 hover:bg-gray-800">Next</a>';
                                    } else {
                                        echo '<span class="px-4 py-2 text-gray-600 cursor-not-allowed">Next</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="py-12 px-4 sm:px-6 lg:px-8 border-t border-gray-800 mt-12">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <a href="index.php" class="flex items-center mb-4">
                        <img class="h-10 w-auto" src="assets/images/lol.png" alt="RentMyKeys">
                        <span class="ml-2 text-xl font-bold text-white">RentMyKeys</span>
                    </a>
                    <p class="text-gray-400 mb-4">Rent vehicles easily or earn money by listing your vehicle.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-primary transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z" />
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-primary transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z" />
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-primary transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                            </svg>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-primary transition">Home</a></li>
                        <li><a href="search.php" class="text-gray-400 hover:text-primary transition">Search Vehicles</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-primary transition">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-primary transition">Contact Us</a></li>
                        <li><a href="register.php?type=renter" class="text-gray-400 hover:text-primary transition">Become a Renter</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Vehicle Types</h3>
                    <ul class="space-y-2">
                        <li><a href="search.php?vehicle_type=car" class="text-gray-400 hover:text-primary transition">Cars</a></li>
                        <li><a href="search.php?vehicle_type=motorcycle" class="text-gray-400 hover:text-primary transition">Motorcycles</a></li>
                        <li><a href="search.php?vehicle_type=scooter" class="text-gray-400 hover:text-primary transition">Scooters</a></li>
                        <li><a href="search.php?vehicle_type=bicycle" class="text-gray-400 hover:text-primary transition">Bicycles</a></li>
                        <li><a href="search.php?vehicle_type=other" class="text-gray-400 hover:text-primary transition">Other Vehicles</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Contact Us</h3>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-gray-400">123 Main Street, City, Country</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span class="text-gray-400">+91 9876543210</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span class="text-gray-400">info@rentmykeys.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center">
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
            
            // Price range slider
            const minPriceInput = document.getElementById('min_price');
            const maxPriceInput = document.getElementById('max_price');
            
            minPriceInput.addEventListener('input', function() {
                if (parseInt(minPriceInput.value) > parseInt(maxPriceInput.value)) {
                    maxPriceInput.value = minPriceInput.value;
                }
                updatePriceLabels();
            });
            
            maxPriceInput.addEventListener('input', function() {
                if (parseInt(maxPriceInput.value) < parseInt(minPriceInput.value)) {
                    minPriceInput.value = maxPriceInput.value;
                }
                updatePriceLabels();
            });
            
            function updatePriceLabels() {
                document.querySelector('.flex.items-center.justify-between.mb-2 span:first-child').textContent = '₹' + minPriceInput.value;
                document.querySelector('.flex.items-center.justify-between.mb-2 span:last-child').textContent = '₹' + maxPriceInput.value;
            }
        });
    </script>
</body>
</html>