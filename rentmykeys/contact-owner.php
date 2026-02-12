<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Check if user is logged in
requireLogin();

$message = '';
$messageType = '';
$owner = null;
$vehicle = null;

// Get owner ID from query parameter
if (isset($_GET['owner_id']) && !empty($_GET['owner_id'])) {
    $owner_id = (int)$_GET['owner_id'];
    
    // Get owner information
    $stmt = $conn->prepare("SELECT user_id, username, email, full_name FROM users WHERE user_id = ? AND user_type = 'renter'");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $owner = $result->fetch_assoc();
    } else {
        // Owner not found
        header("Location: search.php");
        exit();
    }
} elseif (isset($_GET['vehicle_id']) && !empty($_GET['vehicle_id'])) {
    $vehicle_id = (int)$_GET['vehicle_id'];
    
    // Get vehicle information with owner details
    $stmt = $conn->prepare("SELECT v.vehicle_id, v.title, u.user_id, u.username, u.email, u.full_name 
                           FROM vehicles v 
                           JOIN users u ON v.user_id = u.user_id 
                           WHERE v.vehicle_id = ? AND u.user_type = 'renter'");
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $vehicle = $result->fetch_assoc();
        $owner = [
            'user_id' => $vehicle['user_id'],
            'username' => $vehicle['username'],
            'email' => $vehicle['email'],
            'full_name' => $vehicle['full_name']
        ];
    } 
    }


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $subject = isset($_POST['subject']) ? sanitize($_POST['subject']) : '';
    $message_content = isset($_POST['message']) ? sanitize($_POST['message']) : '';
    
    // Basic validation
    if (empty($subject) || empty($message_content)) {
        $message = "All fields are required. Please fill out the form completely.";
        $messageType = "error";
    } else {
        // Get sender (current user) information
        $sender_name = $_SESSION['full_name'];
        $sender_email = $_SESSION['email'];
        
        // Email configuration
        $to = $owner['email'];
        $email_subject = "RentMyKeys Message: " . $subject;
        
        $email_body = "Hello " . $owner['full_name'] . ",\n\n";
        $email_body .= "You have received a new message from " . $sender_name . " via RentMyKeys.\n\n";
        
        if ($vehicle) {
            $email_body .= "Regarding Vehicle: " . $vehicle['title'] . " (ID: " . $vehicle['vehicle_id'] . ")\n\n";
        }
        
        $email_body .= "Message:\n" . $message_content . "\n\n";
        $email_body .= "To reply, you can contact " . $sender_name . " directly at: " . $sender_email . "\n\n";
        $email_body .= "Best regards,\nRentMyKeys Team";
        
        $headers = "From: no-reply@rentmykeys.com\r\n";
        $headers .= "Reply-To: " . $sender_email . "\r\n";
        
        // Send email
        if (mail($to, $email_subject, $email_body, $headers)) {
            $message = "Your message has been sent to " . $owner['full_name'] . ". They will contact you soon!";
            $messageType = "success";
        } else {
            $message = "Sorry, there was an error sending your message. Please try again later.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Owner - RentMyKeys</title>
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
                            <a href="<?php echo isRenter() ? 'dashboard-renter.php' : 'dashboard-customer.php'; ?>" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Dashboard</a>
                            <a href="profile.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">Profile</a>
                            <a href="my-bookings.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">My Bookings</a>
                            <?php if (isRenter()): ?>
                                <a href="my-vehicles.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800">My Vehicles</a>
                            <?php endif; ?>
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
        <div class="max-w-3xl mx-auto">
            <div class="glass-card rounded-2xl p-8 shadow-lg mb-8">
                <h1 class="text-2xl font-bold gradient-text mb-6">
                    Contact <?php echo htmlspecialchars($owner['full_name']); ?>
                </h1>
                
                <?php if (!empty($message)): ?>
                    <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900/40 border border-green-500/50 text-white' : 'bg-red-900/40 border border-red-500/50 text-white'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mb-6">
                    <?php if ($vehicle): ?>
                        <div class="p-4 bg-gray-800/50 rounded-lg mb-6">
                            <p class="text-gray-300 mb-2">You are contacting the owner regarding:</p>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($vehicle['title']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-6">
                            <label for="subject" class="block text-white text-sm font-medium mb-2">Subject</label>
                            <input 
                                type="text" 
                                id="subject" 
                                name="subject" 
                                class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                placeholder="Enter subject"
                                value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ($vehicle ? 'Inquiry about ' . htmlspecialchars($vehicle['title']) : ''); ?>"
                                required
                            >
                        </div>
                        
                        <div class="mb-6">
                            <label for="message" class="block text-white text-sm font-medium mb-2">Your Message</label>
                            <textarea 
                                id="message" 
                                name="message" 
                                rows="6" 
                                class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                                placeholder="Enter your message to the owner"
                                required
                            ><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <button 
                                type="submit" 
                                class="w-full bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Send Message
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="text-center text-gray-400 text-sm">
                    <p>Your message will be sent to <?php echo htmlspecialchars($owner['full_name']); ?>'s email address.</p>
                    <p class="mt-2">They will be able to reply directly to you at <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                </div>
            </div>
            
            <div class="text-center">
                <a href="<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'; ?>" class="inline-flex items-center text-primary hover:text-blue-400 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Go Back
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
        });
    </script>
</body>
</html>