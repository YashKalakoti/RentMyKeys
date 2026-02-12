<?php
// Debug at the very beginning of the file
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Form submitted\n", FILE_APPEND);
    file_put_contents('booking_debug.log', "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
}
session_start();
require_once "includes/functions.php";
include 'db.php';

// Check if vehicle ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$vehicle_id = $_GET['id'];

// Get vehicle details
$vehicle = getVehicleById($conn, $vehicle_id);

if (!$vehicle) {
    header("Location: index.php");
    exit();
}

// Get vehicle images
$images = getVehicleImages($conn, $vehicle_id);

// Get owner details
$owner = getUserById($conn, $vehicle['user_id']);

// Calculate rating
$rating_sql = "SELECT AVG(r.rating) as avg_rating, COUNT(r.review_id) as review_count 
              FROM reviews r 
              JOIN bookings b ON r.booking_id = b.booking_id 
              WHERE b.vehicle_id = ?";
$stmt = $conn->prepare($rating_sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$rating_result = $stmt->get_result();
$rating_data = $rating_result->fetch_assoc();
$avg_rating = is_null($rating_data['avg_rating']) ? 0 : round($rating_data['avg_rating'], 1);
$review_count = $rating_data['review_count'];

// Handle booking form submission
$booking_errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_vehicle'])) {
    // Check if user is logged in
    
    
    // Get form data
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    
    // Validate dates
    if (empty($start_date)) {
        $booking_errors[] = "Start date is required";
    }
    
    if (empty($end_date)) {
        $booking_errors[] = "End date is required";
    }
    
    if ($start_date > $end_date) {
        $booking_errors[] = "End date must be after start date";
    }
    
    if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $booking_errors[] = "Start date cannot be in the past";
    }
    
    // Check if vehicle is available for selected dates
    if (!isVehicleAvailable($conn, $vehicle_id, $start_date, $end_date)) {
        $booking_errors[] = "Vehicle is not available for the selected dates";
    }
    
    // Create booking if no errors
    if (empty($booking_errors)) {
        $user_id = $_SESSION['user_id'];
        $days = calculateDays($start_date, $end_date);
        $total_price = $vehicle['price_per_day'] * $days;
        
        // Create booking with 'confirmed' status instead of 'pending'
        $stmt = $conn->prepare("INSERT INTO bookings (vehicle_id, customer_id, start_date, end_date, total_price, status, payment_status) VALUES (?, ?, ?, ?, ?, 'confirmed', 'pending')");
        $stmt->bind_param("iissd", $vehicle_id, $user_id, $start_date, $end_date, $total_price);
        
        if ($stmt->execute()) {
            // Redirect to my-bookings.php with success message
            header("Location: my-bookings.php?booking_success=true");
            exit();
        } else {
            $booking_errors[] = "Failed to create booking: " . $stmt->error;
        }
    }
}
// Add this code right after the if statement that processes the booking form
// Place it around line 69, after the section that starts with:
// if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_vehicle'])) {

// At the end of that block, before moving on to the reviews section, add:
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_vehicle'])) {
        echo "<div style='background: rgba(0,0,0,0.8); color: white; padding: 20px; margin: 20px; border-radius: 10px;'>";
        echo "<h3>Booking Debug Information</h3>";
        echo "Form submitted: YES<br>";
        echo "User logged in: " . (isLoggedIn() ? "YES" : "NO") . "<br>";
        echo "User ID: " . ($_SESSION['user_id'] ?? "Not set") . "<br>";
        echo "Vehicle ID: " . $vehicle_id . "<br>";
        echo "Start date: " . ($start_date ?? "Not set") . "<br>";
        echo "End date: " . ($end_date ?? "Not set") . "<br>";
        
        echo "Booking errors: ";
        if (empty($booking_errors)) {
            echo "None<br>";
        } else {
            echo "<ul>";
            foreach ($booking_errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
        }
        
        // Try a direct query to see all bookings
        echo "Last 5 bookings in database:<br>";
        $debug_sql = "SELECT * FROM bookings ORDER BY booking_id DESC LIMIT 5";
        $debug_result = $conn->query($debug_sql);
        if ($debug_result && $debug_result->num_rows > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Vehicle</th><th>Customer</th><th>Dates</th><th>Status</th></tr>";
            while ($row = $debug_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['booking_id'] . "</td>";
                echo "<td>" . $row['vehicle_id'] . "</td>";
                echo "<td>" . $row['customer_id'] . "</td>";
                echo "<td>" . $row['start_date'] . " to " . $row['end_date'] . "</td>";
                echo "<td>" . $row['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No bookings found in the database or error running query.<br>";
            echo "SQL Error: " . $conn->error;
        }
        
        echo "</div>";
    }

// Get reviews
$reviews_sql = "SELECT r.*, b.customer_id, u.full_name, u.profile_picture 
               FROM reviews r 
               JOIN bookings b ON r.booking_id = b.booking_id 
               JOIN users u ON b.customer_id = u.user_id 
               WHERE b.vehicle_id = ? 
               ORDER BY r.created_at DESC";
$stmt = $conn->prepare($reviews_sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $vehicle['title']; ?> - RentMyKeys</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
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
        input[type="date"],
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
        input[type="date"]:focus,
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
        
        /* Image gallery */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: 400px 200px;
            gap: 10px;
        }

        .main-image {
            grid-column: span 4;
            grid-row: span 1;
        }

        .thumbnail {
            grid-column: span 1;
            grid-row: span 1;
            position: relative;
            overflow: hidden;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .thumbnail:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        .thumbnail::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(59, 130, 246, 0.1);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .thumbnail:hover::after {
            opacity: 1;
        }

        /* Rating stars animation */
        .star-rating {
            transition: all 0.3s ease;
        }
        
        .star-rating:hover {
            transform: scale(1.05);
            filter: brightness(1.2);
        }
        
        /* Vehicle specs cards */
        .spec-card {
            transition: all 0.3s ease;
            transform: translateY(0);
        }
        
        .spec-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.2);
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
        
        /* Similar vehicle cards */
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

        @media (max-width: 768px) {
            .image-gallery {
                grid-template-rows: 300px repeat(4, 100px);
            }
            
            .main-image {
                grid-column: span 4;
                grid-row: span 1;
            }
            
            .thumbnail {
                grid-column: span 1;
                grid-row: span 1;
            }
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
                        <a href="register.php" class="gradient-border inline-block">
                            <button class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out">
                                Register
                            </button>
                        </a>
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
    <div class="relative z-10 py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Booking Errors -->
            <?php if (!empty($booking_errors)): ?>
                <div class="glass-card rounded-lg p-6 mb-8 bg-red-900/30 border border-red-500/30">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-red-300">There were errors with your booking</h3>
                            <div class="mt-2 text-red-200">
                                <ul class="list-disc list-inside">
                                    <?php foreach ($booking_errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Vehicle Details -->
            <div class="glass-card rounded-2xl overflow-hidden mb-8" style="background: rgba(17, 25, 51, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(34, 46, 66, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
                <!-- Image Gallery -->
                <div class="p-6" x-data="{ activeImage: '<?php echo isset($images[0]) ? $images[0]['image_url'] : 'assets/images/vehicle-placeholder.jpg'; ?>' }">
                    <?php if (empty($images)): ?>
                        <div class="aspect-[16/9] w-full overflow-hidden rounded-xl">
                            <img src="assets/images/vehicle-placeholder.jpg" alt="<?php echo $vehicle['title']; ?>" class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-4 aspect-[16/9] w-full overflow-hidden rounded-xl gradient-border">
                                <img :src="activeImage" alt="<?php echo $vehicle['title']; ?>" class="w-full h-full object-cover transition-all duration-500 hover:scale-105">
                            </div>
                            <?php foreach (array_slice($images, 0, 4) as $key => $image): ?>
                                <div class="aspect-[16/9] overflow-hidden rounded-lg cursor-pointer hover:opacity-80 transition-all duration-300 thumbnail" @click="activeImage = '<?php echo $image['image_url']; ?>'">
                                    <img src="<?php echo $image['image_url']; ?>" alt="<?php echo $vehicle['title']; ?> - Image <?php echo $key + 1; ?>" class="w-full h-full object-cover">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Vehicle Information -->
                <div class="p-6 border-t border-gray-800/30">
                    <div class="flex flex-col md:flex-row justify-between items-start mb-4">
                        <div>
                            <div class="flex items-center mb-2">
                                <span class="bg-primary/30 border border-primary/30 text-white text-sm px-3 py-1 rounded-full mr-2">
                                    <?php echo ucfirst($vehicle['vehicle_type']); ?>
                                </span>
                                <div class="flex items-center text-yellow-400">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $avg_rating): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 star-rating" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 star-rating" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 star-rating" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                            </svg>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <span class="ml-2 text-white"><?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)</span>
                                </div>
                            </div>
                            <h1 class="text-3xl font-bold gradient-text mb-2"><?php echo $vehicle['title']; ?></h1>
                            <p class="text-gray-400 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <?php echo $vehicle['address']; ?>, <?php echo $vehicle['city']; ?>, <?php echo $vehicle['state']; ?>, <?php echo $vehicle['zip_code']; ?>
                            </p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <p class="text-3xl font-bold gradient-text">
                                ₹<?php echo number_format($vehicle['price_per_day']); ?><span class="text-sm text-gray-400">/day</span>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Vehicle Specifications -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <?php if ($vehicle['brand']): ?>
                            <div class="glass-card rounded-lg p-4 spec-card">
                                <p class="text-gray-400 text-sm mb-1">Brand</p>
                                <p class="text-white font-medium"><?php echo $vehicle['brand']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle['model']): ?>
                            <div class="glass-card rounded-lg p-4 spec-card">
                                <p class="text-gray-400 text-sm mb-1">Model</p>
                                <p class="text-white font-medium"><?php echo $vehicle['model']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle['year']): ?>
                            <div class="glass-card rounded-lg p-4 spec-card">
                                <p class="text-gray-400 text-sm mb-1">Year</p>
                                <p class="text-white font-medium"><?php echo $vehicle['year']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle['color']): ?>
                            <div class="glass-card rounded-lg p-4 spec-card">
                                <p class="text-gray-400 text-sm mb-1">Color</p>
                                <p class="text-white font-medium"><?php echo $vehicle['color']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle['seats']): ?>
                            <div class="glass-card rounded-lg p-4 spec-card">
                                <p class="text-gray-400 text-sm mb-1">Seats</p>
                                <p class="text-white font-medium"><?php echo $vehicle['seats']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle['fuel_type']): ?>
                            <div class="glass-card rounded-lg p-4 spec-card">
                                <p class="text-gray-400 text-sm mb-1">Fuel Type</p>
                                <p class="text-white font-medium"><?php echo $vehicle['fuel_type']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle['transmission']): ?>
                            <div class="glass-card rounded-lg p-4 spec-card">
                                <p class="text-gray-400 text-sm mb-1">Transmission</p>
                                <p class="text-white font-medium"><?php echo $vehicle['transmission']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle['mileage']): ?>
                            <div class="glass-card rounded-lg p-4 spec-card">
                                <p class="text-gray-400 text-sm mb-1">Mileage</p>
                                <p class="text-white font-medium"><?php echo number_format($vehicle['mileage']); ?> km</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold gradient-text mb-4">Description</h2>
                        <p class="text-gray-300"><?php echo nl2br($vehicle['description']); ?></p>
                    </div>
                    
                    <!-- Owner Information -->
                    <div class="flex flex-col md:flex-row items-start justify-between mb-8">
                        <div class="flex items-center">
                            <div class="gradient-border rounded-full h-12 w-12 inline-block">
                                <img src="<?php echo $owner['profile_picture'] ?? 'assets/images/default-avatar.png'; ?>" alt="<?php echo $owner['full_name']; ?>" class="h-12 w-12 rounded-full object-cover">
                            </div>
                            <div class="ml-4">
                                <p class="text-white font-medium"><?php echo $owner['full_name']; ?></p>
                                <p class="text-gray-400 text-sm">Vehicle Owner</p>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <a href="contact-owner.php?id=<?php echo $owner['user_id']; ?>" class="action-button bg-gray-800/50 hover:bg-gray-700/50 text-white py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                Contact Owner
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Booking Form and Reviews Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Booking Form -->
                <div class="md:col-span-1 order-2 md:order-1">
                    <div class="glass-card rounded-2xl p-6 sticky top-24" style="background: rgba(17, 25, 51, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(34, 46, 66, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
                        <h2 class="text-xl font-semibold gradient-text mb-4">Book this <?php echo ucfirst($vehicle['vehicle_type']); ?></h2>
                        <form method="POST" action="vehicle-details.php?id=<?php echo $vehicle_id; ?>">
                            <div class="mb-4">
                                <label for="start_date" class="block text-white text-sm font-medium mb-2">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="end_date" class="block text-white text-sm font-medium mb-2">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700/50 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            </div>
                            
                            <div class="mb-6">
                                <div class="flex justify-between py-3 border-t border-b border-gray-700/50 mb-3">
                                    <span class="text-gray-300">Price per day</span>
                                    <span class="text-white font-medium">₹<?php echo number_format($vehicle['price_per_day']); ?></span>
                                </div>
                                <div id="total_calculation" class="hidden">
                                    <div class="flex justify-between py-1">
                                        <span class="text-gray-300">Days</span>
                                        <span class="text-white font-medium" id="days_display">0</span>
                                    </div>
                                    <div class="flex justify-between py-3 border-t border-gray-700/50 mt-3">
                                        <span class="text-gray-300 font-medium">Total</span>
                                        <span class="text-primary text-lg font-bold gradient-text" id="total_price_display">₹0</span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!$vehicle['availability']): ?>
                                <button type="button" class="w-full bg-gray-600/50 text-white font-bold py-3 px-4 rounded-lg opacity-70 cursor-not-allowed">
                                    Currently Unavailable
                                </button>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="login.php?redirect=vehicle-details.php?id=<?php echo $vehicle_id; ?>" class="gradient-border inline-block w-full">
                                    <button type="button" class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 ease-in-out w-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                        </svg>
                                        Sign In to Book
                                    </button>
                                </a>
                            <?php elseif ($_SESSION['user_id'] == $vehicle['user_id']): ?>
                                <button type="button" class="w-full bg-gray-600/50 text-white font-bold py-3 px-4 rounded-lg opacity-70 cursor-not-allowed">
                                    This is Your Vehicle
                                </button>
                            <?php else: ?>
                                <button type="submit" name="book_vehicle" class="w-full bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Book Now
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Reviews -->
                <div class="md:col-span-2 order-1 md:order-2">
                    <div class="glass-card rounded-2xl p-6 mb-8" style="background: rgba(17, 25, 51, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(34, 46, 66, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
                        <h2 class="text-xl font-semibold gradient-text mb-4">Reviews</h2>
                        
                        <?php if (empty($reviews)): ?>
                            <div class="text-center py-8">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 animate-pulse-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <p class="text-gray-400 mt-4">No reviews yet. Be the first to book and review this vehicle!</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="glass-card rounded-xl p-4">
                                        <div class="flex items-start">
                                            <div class="gradient-border rounded-full h-10 w-10 inline-block">
                                                <img src="<?php echo $review['profile_picture'] ?? 'assets/images/default-avatar.png'; ?>" alt="<?php echo $review['full_name']; ?>" class="h-10 w-10 rounded-full object-cover">
                                            </div>
                                            <div class="flex-1 ml-4">
                                                <div class="flex justify-between items-center mb-2">
                                                    <h3 class="text-white font-medium"><?php echo $review['full_name']; ?></h3>
                                                    <span class="text-gray-400 text-sm"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                                </div>
                                                <div class="flex items-center text-yellow-400 mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['rating']): ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 star-rating" viewBox="0 0 20 20" fill="currentColor">
                                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                            </svg>
                                                        <?php else: ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 star-rating" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                            </svg>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                                <p class="text-gray-300"><?php echo $review['comment']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Similar Vehicles -->
                    <?php
                        // Get similar vehicles
                        $similar_sql = "SELECT v.*, vi.image_url 
                                        FROM vehicles v 
                                        LEFT JOIN (
                                            SELECT vehicle_id, image_url FROM vehicle_images WHERE is_primary = 1
                                            UNION
                                            SELECT vehicle_id, MIN(image_url) FROM vehicle_images GROUP BY vehicle_id
                                        ) vi ON v.vehicle_id = vi.vehicle_id 
                                        WHERE v.vehicle_type = ? AND v.vehicle_id != ? AND v.availability = 1 
                                        ORDER BY RAND() 
                                        LIMIT 2";
                        $stmt = $conn->prepare($similar_sql);
                        $stmt->bind_param("si", $vehicle['vehicle_type'], $vehicle_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $similar_vehicles = [];
                        while ($row = $result->fetch_assoc()) {
                            $similar_vehicles[] = $row;
                        }
                        
                        if (!empty($similar_vehicles)):
                    ?>
                    <div class="glass-card rounded-2xl p-6" style="background: rgba(17, 25, 51, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(34, 46, 66, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
                        <h2 class="text-xl font-semibold gradient-text mb-4">Similar Vehicles</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($similar_vehicles as $similar): ?>
                                <div class="glass-card rounded-xl overflow-hidden vehicle-card">
                                    <a href="vehicle-details.php?id=<?php echo $similar['vehicle_id']; ?>" class="block relative aspect-[16/9] overflow-hidden">
                                        <img src="<?php echo $similar['image_url'] ?? 'assets/images/vehicle-placeholder.jpg'; ?>" alt="<?php echo $similar['title']; ?>" class="w-full h-full object-cover">
                                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4">
                                            <div class="px-2 py-1 bg-gradient-to-r from-primary to-blue-500 text-white text-xs font-semibold rounded-full inline-block">
                                                <?php echo ucfirst($similar['vehicle_type']); ?>
                                            </div>
                                        </div>
                                    </a>
                                    <div class="p-4">
                                        <div class="flex justify-between items-start">
                                            <h3 class="text-lg font-semibold text-white truncate">
                                                <a href="vehicle-details.php?id=<?php echo $similar['vehicle_id']; ?>" class="hover:text-primary transition">
                                                    <?php echo $similar['title']; ?>
                                                </a>
                                            </h3>
                                            <p class="text-lg font-bold gradient-text">
                                                ₹<?php echo number_format($similar['price_per_day']); ?><span class="text-xs text-gray-400">/day</span>
                                            </p>
                                        </div>
                                        <p class="text-gray-400 text-sm mt-1 mb-2 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <?php echo $similar['city']; ?>, <?php echo $similar['state']; ?>
                                        </p>
                                        <div class="mt-4">
                                            <a href="vehicle-details.php?id=<?php echo $similar['vehicle_id']; ?>" class="gradient-border inline-block w-full">
                                                <button class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out w-full flex justify-center items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    View Details
                                                </button>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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
                            <a href="tel:+919876543210" class="text-gray-400 hover:text-primary transition duration-300">+91 9027973734</a>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <a href="mailto:info@rentmykeys.com" class="text-gray-400 hover:text-primary transition duration-300">9027973734</a>
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
            const glassCards = document.querySelectorAll('.glass-card:not(.vehicle-card):not(.spec-card)');
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
            
            // Booking calculation
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const totalCalculation = document.getElementById('total_calculation');
            const daysDisplay = document.getElementById('days_display');
            const totalPriceDisplay = document.getElementById('total_price_display');
            const pricePerDay = <?php echo $vehicle['price_per_day']; ?>;
            
            function calculateTotal() {
                if (startDate.value && endDate.value) {
                    const start = new Date(startDate.value);
                    const end = new Date(endDate.value);
                    
                    if (end >= start) {
                        const diffTime = Math.abs(end - start);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // Include both start and end days
                        const totalPrice = diffDays * pricePerDay;
                        
                        daysDisplay.textContent = diffDays;
                        totalPriceDisplay.textContent = '₹' + totalPrice.toLocaleString();
                        totalCalculation.classList.remove('hidden');
                    } else {
                        totalCalculation.classList.add('hidden');
                    }
                } else {
                    totalCalculation.classList.add('hidden');
                }
            }
            
            startDate.addEventListener('change', calculateTotal);
            endDate.addEventListener('change', calculateTotal);

            // Helper function for calculating days (used in booking process)
            function calculateDays(start, end) {
                const startDate = new Date(start);
                const endDate = new Date(end);
                const diffTime = Math.abs(endDate - startDate);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // Include both start and end days
                return diffDays;
            }
        });
    </script>
</body>
</html>