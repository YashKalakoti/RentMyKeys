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

// Get the booking details
$booking_sql = "SELECT b.*, v.title, v.user_id as owner_id 
               FROM bookings b 
               JOIN vehicles v ON b.vehicle_id = v.vehicle_id 
               WHERE b.booking_id = ? AND b.customer_id = ?";
$stmt = $conn->prepare($booking_sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if booking exists and belongs to the user
if ($result->num_rows != 1) {
    $_SESSION['error'] = "Booking not found or you don't have permission to cancel it.";
    header("Location: my-bookings.php");
    exit();
}

$booking = $result->fetch_assoc();

// Check if booking can be cancelled (only pending bookings can be cancelled)
if ($booking['status'] !== 'pending') {
    $_SESSION['error'] = "Only pending bookings can be cancelled.";
    header("Location: my-bookings.php");
    exit();
}

// Process cancellation if confirmed
if (isset($_POST['confirm_cancel'])) {
    // Update booking status to cancelled
    $update_sql = "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE booking_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $booking_id);
    
    if ($stmt->execute()) {
        // Notify the vehicle owner (in a real app, you might send an email here)
        
        $_SESSION['success'] = "Booking has been successfully cancelled.";
        header("Location: my-bookings.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to cancel booking. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking - RentMyKeys</title>
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

        /* Button hover effects */
        .action-button {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .action-button::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
        }

        .action-button::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
            z-index: -1;
        }

        .action-button:hover::before {
            width: 100%;
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
    <div class="relative z-10 py-12 px-4 sm:px-6 lg:px-8 flex items-center justify-center min-h-[calc(100vh-4rem)]">
        <div class="max-w-md w-full">
            <div class="glass-card rounded-2xl overflow-hidden p-8">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center h-20 w-20 rounded-full bg-red-500/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-2">Cancel Booking</h2>
                    <p class="text-gray-400">Are you sure you want to cancel this booking?</p>
                </div>
                
                <!-- Booking Details -->
                <div class="glass-card rounded-xl mb-6 p-4 bg-gray-800/30">
                    <h3 class="font-semibold text-white mb-2"><?php echo $booking['title']; ?></h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="text-gray-400">
                            <span>Start Date:</span>
                            <span class="block text-white"><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                        </div>
                        <div class="text-gray-400">
                            <span>End Date:</span>
                            <span class="block text-white"><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></span>
                        </div>
                        <div class="text-gray-400 colspan-2">
                            <span>Total Price:</span>
                            <span class="block text-white font-semibold">₹<?php echo number_format($booking['total_price'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="text-gray-400 text-sm mb-6">
                    <p>Please note that cancelling your booking may affect your booking history.</p>
                </div>
                
                <form method="POST" action="">
                    <div class="flex flex-col space-y-4">
                        <button type="submit" name="confirm_cancel" class="bg-gradient-to-r from-red-500 to-red-700 hover:from-red-600 hover:to-red-800 text-white font-medium py-3 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Confirm Cancellation
                        </button>
                        <a href="my-bookings.php" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-3 px-4 rounded-lg transition duration-300 ease-in-out text-center action-button">
                            Go Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

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