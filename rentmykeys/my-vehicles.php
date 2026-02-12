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

// Get user's vehicles
$user_id = $_SESSION['user_id'];
$vehicles = [];

// Handle search/filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query with filters
$query = "SELECT v.*, COUNT(DISTINCT b.booking_id) as bookings_count 
          FROM vehicles v 
          LEFT JOIN bookings b ON v.vehicle_id = b.vehicle_id 
          WHERE v.user_id = ?";

$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $query .= " AND (v.title LIKE ? OR v.brand LIKE ? OR v.model LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($type_filter)) {
    $query .= " AND v.vehicle_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    if ($status_filter === 'available') {
        $query .= " AND v.availability = 1";
    } else if ($status_filter === 'unavailable') {
        $query .= " AND v.availability = 0";
    }
}

$query .= " GROUP BY v.vehicle_id ORDER BY v.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get primary image for each vehicle
    $img_stmt = $conn->prepare("SELECT image_url FROM vehicle_images WHERE vehicle_id = ? AND is_primary = 1 LIMIT 1");
    $img_stmt->bind_param("i", $row['vehicle_id']);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result();
    
    if ($img_result->num_rows > 0) {
        $row['primary_image'] = $img_result->fetch_assoc()['image_url'];
    } else {
        // Try to get any image if no primary image
        $img_stmt = $conn->prepare("SELECT image_url FROM vehicle_images WHERE vehicle_id = ? LIMIT 1");
        $img_stmt->bind_param("i", $row['vehicle_id']);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        
        if ($img_result->num_rows > 0) {
            $row['primary_image'] = $img_result->fetch_assoc()['image_url'];
        } else {
            $row['primary_image'] = 'assets/images/vehicle-placeholder.jpg';
        }
    }
    
    // Get active bookings count
    $booking_stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM bookings WHERE vehicle_id = ? AND status IN ('pending', 'confirmed')");
    $booking_stmt->bind_param("i", $row['vehicle_id']);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();
    $row['active_bookings'] = $booking_result->fetch_assoc()['active_count'];
    
    $vehicles[] = $row;
}

// Get counts for filtering
$stmt = $conn->prepare("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN availability = 1 THEN 1 ELSE 0 END) as available,
                        SUM(CASE WHEN availability = 0 THEN 1 ELSE 0 END) as unavailable,
                        SUM(CASE WHEN vehicle_type = 'car' THEN 1 ELSE 0 END) as cars,
                        SUM(CASE WHEN vehicle_type = 'motorcycle' THEN 1 ELSE 0 END) as motorcycles,
                        SUM(CASE WHEN vehicle_type = 'scooter' THEN 1 ELSE 0 END) as scooters,
                        SUM(CASE WHEN vehicle_type = 'bicycle' THEN 1 ELSE 0 END) as bicycles,
                        SUM(CASE WHEN vehicle_type = 'other' THEN 1 ELSE 0 END) as others
                        FROM vehicles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

// Handle vehicle deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $vehicle_id = (int)$_GET['delete'];
    
    // Check if vehicle belongs to the user
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $vehicle_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Check if vehicle has active bookings
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE vehicle_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->bind_param("i", $vehicle_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $active_bookings = $result->fetch_assoc()['count'];
        
        if ($active_bookings > 0) {
            $message = "Cannot delete vehicle with active bookings.";
            $messageType = "error";
        } else {
            // Get all images to delete files
            $stmt = $conn->prepare("SELECT image_url FROM vehicle_images WHERE vehicle_id = ?");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                if (file_exists($row['image_url'])) {
                    unlink($row['image_url']);
                }
            }
            
            // Delete vehicle images
            $stmt = $conn->prepare("DELETE FROM vehicle_images WHERE vehicle_id = ?");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            
            // Delete completed bookings
            $stmt = $conn->prepare("DELETE FROM bookings WHERE vehicle_id = ? AND status IN ('completed', 'cancelled')");
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            
            // Delete vehicle
            $stmt = $conn->prepare("DELETE FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $vehicle_id, $user_id);
            
            if ($stmt->execute()) {
                $message = "Vehicle deleted successfully!";
                $messageType = "success";
                
                // Refresh the page to update the list
                header("Location: my-vehicles.php");
                exit();
            } else {
                $message = "Error deleting vehicle: " . $conn->error;
                $messageType = "error";
            }
        }
    } else {
        $message = "Vehicle not found or you don't have permission to delete it.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicles - RentMyKeys</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
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
        
        /* Vehicle card hover effect */
        .vehicle-card {
            transition: all 0.3s ease;
        }
        
        .vehicle-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }
        
        /* Filter pill selected state */
        .filter-pill.selected {
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
            color: white;
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
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold gradient-text mb-2">My Vehicles</h1>
                    <p class="text-gray-400">Manage your vehicle listings and bookings</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <a href="add-vehicle.php" class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New Vehicle
                    </a>
                </div>
            </div>
            
            <?php if (isset($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900/40 border border-green-500/50 text-white' : 'bg-red-900/40 border border-red-500/50 text-white'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Search and Filter Section -->
            <div class="glass-card rounded-2xl p-6 mb-8">
                <form action="my-vehicles.php" method="GET" class="space-y-6">
                    <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
                        <div class="flex-1">
                            <div class="relative">
                                <input 
                                    type="text" 
                                    name="search" 
                                    placeholder="Search by title, brand, or model..." 
                                    class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary pr-10"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex space-x-4">
                            <select 
                                name="type" 
                                class="px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <option value="">All Types</option>
                                <option value="car" <?php echo $type_filter === 'car' ? 'selected' : ''; ?>>Cars (<?php echo $counts['cars']; ?>)</option>
                                <option value="motorcycle" <?php echo $type_filter === 'motorcycle' ? 'selected' : ''; ?>>Motorcycles (<?php echo $counts['motorcycles']; ?>)</option>
                                <option value="scooter" <?php echo $type_filter === 'scooter' ? 'selected' : ''; ?>>Scooters (<?php echo $counts['scooters']; ?>)</option>
                                <option value="bicycle" <?php echo $type_filter === 'bicycle' ? 'selected' : ''; ?>>Bicycles (<?php echo $counts['bicycles']; ?>)</option>
                                <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other (<?php echo $counts['others']; ?>)</option>
                            </select>
                            
                            <select 
                                name="status" 
                                class="px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <option value="">All Status</option>
                                <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available (<?php echo $counts['available']; ?>)</option>
                                <option value="unavailable" <?php echo $status_filter === 'unavailable' ? 'selected' : ''; ?>>Unavailable (<?php echo $counts['unavailable']; ?>)</option>
                            </select>
                            
                            <button type="submit" class="bg-primary hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out">
                                Filter
                            </button>
                            
                            <?php if (!empty($search) || !empty($type_filter) || !empty($status_filter)): ?>
                                <a href="my-vehicles.php" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Vehicles Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
                <?php if (empty($vehicles)): ?>
                    <div class="col-span-full glass-card rounded-2xl p-8 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        
                        <?php if (!empty($search) || !empty($type_filter) || !empty($status_filter)): ?>
                            <p class="text-xl font-semibold text-white mb-4">No vehicles found matching your search criteria</p>
                            <a href="my-vehicles.php" class="inline-flex items-center text-primary hover:text-blue-400 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Clear search and see all vehicles
                            </a>
                        <?php else: ?>
                            <p class="text-xl font-semibold text-white mb-4">You haven't added any vehicles yet</p>
                            <p class="text-gray-400 mb-6">Start earning by adding your first vehicle for rent</p>
                            <a href="add-vehicle.php" class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out inline-flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Your First Vehicle
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="glass-card rounded-2xl overflow-hidden vehicle-card">
                            <!-- Vehicle Image -->
                            <div class="relative aspect-[16/9] overflow-hidden">
                                <img src="<?php echo htmlspecialchars($vehicle['primary_image']); ?>" alt="<?php echo htmlspecialchars($vehicle['title']); ?>" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                                
                                <!-- Vehicle Status Badge -->
                                <div class="absolute top-4 left-4 flex space-x-2">
                                    <span class="px-2 py-1 bg-gradient-to-br from-primary/80 to-blue-600/80 text-white text-xs font-bold rounded-md backdrop-blur-sm">
                                        <?php echo ucfirst($vehicle['vehicle_type']); ?>
                                    </span>
                                    
                                    <?php if ($vehicle['availability']): ?>
                                        <span class="px-2 py-1 bg-gradient-to-br from-green-500/80 to-green-600/80 text-white text-xs font-bold rounded-md backdrop-blur-sm">
                                            Available
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-gradient-to-br from-red-500/80 to-red-600/80 text-white text-xs font-bold rounded-md backdrop-blur-sm">
                                            Unavailable
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Active Bookings Badge -->
                                <?php if ($vehicle['active_bookings'] > 0): ?>
                                    <div class="absolute top-4 right-4">
                                        <span class="px-2 py-1 bg-gradient-to-br from-amber-500/80 to-amber-600/80 text-white text-xs font-bold rounded-md backdrop-blur-sm">
                                            <?php echo $vehicle['active_bookings']; ?> Active Booking<?php echo $vehicle['active_bookings'] > 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Price Tag -->
                                <div class="absolute bottom-4 left-4">
                                    <span class="px-3 py-1.5 bg-gradient-to-r from-primary/90 to-purple-600/90 text-white font-bold rounded-lg backdrop-blur-sm">
                                        ₹<?php echo number_format($vehicle['price_per_day']); ?>/day
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Vehicle Details -->
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($vehicle['title']); ?></h3>
                                <p class="text-gray-400 mb-4 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <?php echo htmlspecialchars($vehicle['city']); ?>, <?php echo htmlspecialchars($vehicle['state']); ?>
                                </p>
                                
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <?php if (!empty($vehicle['year'])): ?>
                                        <span class="px-2 py-1 bg-gray-800/50 text-gray-300 text-xs rounded-md flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <?php echo htmlspecialchars($vehicle['year']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($vehicle['transmission'])): ?>
                                        <span class="px-2 py-1 bg-gray-800/50 text-gray-300 text-xs rounded-md flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <?php echo htmlspecialchars($vehicle['transmission']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($vehicle['fuel_type'])): ?>
                                        <span class="px-2 py-1 bg-gray-800/50 text-gray-300 text-xs rounded-md flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            <?php echo htmlspecialchars($vehicle['fuel_type']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="px-2 py-1 bg-gray-800/50 text-gray-300 text-xs rounded-md flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <?php echo $vehicle['bookings_count']; ?> Booking<?php echo $vehicle['bookings_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex space-x-2">
                                    <a href="vehicle-details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out text-center">
                                        View
                                    </a>
                                    <a href="edit-vehicle.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="flex-1 bg-primary hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out text-center">
                                        Edit
                                    </a>
                                    <button 
                                        onclick="confirmDelete(<?php echo $vehicle['vehicle_id']; ?>, '<?php echo addslashes($vehicle['title']); ?>', <?php echo $vehicle['active_bookings']; ?>)" 
                                        class="w-10 bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-2 rounded-lg transition duration-300 ease-in-out text-center"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Back Link -->
            <div class="text-center">
                <a href="dashboard-renter.php" class="inline-flex items-center text-primary hover:text-blue-400 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-75 transition-opacity"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-gray-900 rounded-lg overflow-hidden shadow-xl transform transition-all max-w-lg w-full">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-white mb-4">Delete Vehicle</h3>
                    <p class="text-gray-300 mb-2">Are you sure you want to delete:</p>
                    <p id="deleteVehicleTitle" class="text-white font-semibold mb-4"></p>
                    
                    <div id="activeBookingsWarning" class="mb-4 p-4 bg-amber-900/40 border border-amber-500/50 text-white rounded-lg hidden">
                        <p>This vehicle has active bookings. Please cancel them before deleting the vehicle.</p>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button id="cancelDelete" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition duration-300">
                            Cancel
                        </button>
                        <a id="confirmDelete" href="#" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-300">
                            Delete
                        </a>
                    </div>
                </div>
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
        });
        
        // Delete confirmation modal
        function confirmDelete(vehicleId, vehicleTitle, activeBookings) {
            const modal = document.getElementById('deleteModal');
            const titleElement = document.getElementById('deleteVehicleTitle');
            const confirmButton = document.getElementById('confirmDelete');
            const cancelButton = document.getElementById('cancelDelete');
            const warningElement = document.getElementById('activeBookingsWarning');
            
            titleElement.textContent = vehicleTitle;
            
            if (activeBookings > 0) {
                warningElement.classList.remove('hidden');
                confirmButton.classList.add('opacity-50', 'cursor-not-allowed');
                confirmButton.setAttribute('disabled', 'disabled');
                confirmButton.href = '#';
            } else {
                warningElement.classList.add('hidden');
                confirmButton.classList.remove('opacity-50', 'cursor-not-allowed');
                confirmButton.removeAttribute('disabled');
                confirmButton.href = `my-vehicles.php?delete=${vehicleId}`;
            }
            
            modal.classList.remove('hidden');
            
            cancelButton.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>