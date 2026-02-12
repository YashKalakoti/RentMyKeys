<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session and include required files
session_start();
require_once "includes/functions.php";
include 'db.php';

// Include the email sending file with a fallback
if (file_exists('send-email.php')) {
    include 'send-email.php';
} else {
    // Define a basic sendEmail function if the file doesn't exist
    function sendEmail($to, $subject, $body, $from_email, $from_name) {
        return array('success' => false, 'message' => 'send-email.php file not found');
    }
}

$message = '';
$messageType = '';
$debugInfo = ''; // For storing debugging information

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? sanitize($_POST['subject']) : '';
    $message_content = isset($_POST['message']) ? sanitize($_POST['message']) : '';
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message_content)) {
        $message = "All fields are required. Please fill out the form completely.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {
        try {
            // Email configuration
            $to = "yashkalakotibackup@gmail.com";
            $email_subject = "RentMyKeys Contact Form: " . $subject;
            
            // Create email body
            $email_body = "<strong>You have received a new message from your website contact form.</strong><br><br>";
            $email_body .= "<strong>Name:</strong> " . $name . "<br>";
            $email_body .= "<strong>Email:</strong> " . $email . "<br>";
            $email_body .= "<strong>Subject:</strong> " . $subject . "<br>";
            $email_body .= "<strong>Message:</strong> <br>" . nl2br($message_content) . "<br>";
            
            // Send email using our function
            $result = sendEmail($to, $email_subject, $email_body, $email, $name);
            
            // Store debug information
            $debugInfo .= "Email function result: " . print_r($result, true);
            
            if ($result['success']) {
                $message = "Your message has been sent. We'll get back to you soon!";
                $messageType = "success";
                
                // Also log messages in database (optional)
                if (isset($conn)) {
                    try {
                        // Check if the table exists
                        $checkTable = $conn->query("SHOW TABLES LIKE 'contact_messages'");
                        
                        // Create table if it doesn't exist
                        if ($checkTable->num_rows == 0) {
                            $createTable = "CREATE TABLE `contact_messages` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `name` varchar(255) NOT NULL,
                                `email` varchar(255) NOT NULL,
                                `subject` varchar(255) NOT NULL,
                                `message` text NOT NULL,
                                `created_at` datetime NOT NULL,
                                PRIMARY KEY (`id`)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                            
                            $conn->query($createTable);
                        }
                        
                        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->bind_param("ssss", $name, $email, $subject, $message_content);
                        $stmt->execute();
                    } catch (Exception $e) {
                        $debugInfo .= "Database error: " . $e->getMessage();
                    }
                }
                
                // Clear form fields
                $name = $email = $subject = $message_content = '';
            } else {
                // Try fallback method if PHPMailer fails
                $fallbackResult = sendEmailFallback($to, $email_subject, $email_body, $email, $name);
                
                if ($fallbackResult['success']) {
                    $message = "Your message has been sent using our backup system. We'll get back to you soon!";
                    $messageType = "success";
                } else {
                    $message = "Sorry, there was an error sending your message. Please try again later or contact us directly at yashkalakotibackup@gmail.com";
                    $messageType = "error";
                    $debugInfo .= "\nFallback result: " . print_r($fallbackResult, true);
                }
            }
        } catch (Exception $e) {
            $message = "An unexpected error occurred. Please try again later.";
            $messageType = "error";
            $debugInfo .= "\nException: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - RentMyKeys</title>
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
        
        .glow-3 {
            top: 60%;
            right: 10%;
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

        /* Glass card effects */
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
        
        /* Enhanced inputs */
        .input-glow:focus {
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
        }
        
        /* Contact form styles */
        .form-input {
            background: rgba(17, 24, 39, 0.5);
            border: 1px solid rgba(75, 85, 99, 0.3);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
            background: rgba(17, 24, 39, 0.7);
        }
        
        /* Info card hover effect */
        .info-card {
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }
        
        /* Icon pulse animation */
        .icon-pulse {
            animation: icon-pulse 2s infinite;
        }
        
        @keyframes icon-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
    <!-- Debug panel - Only visible for development -->
    <?php if (!empty($debugInfo) && (isset($_GET['debug']) || isset($_COOKIE['debug']))): ?>
    <style>
        #debug-panel {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 80%;
            max-width: 800px;
            max-height: 400px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid #3B82F6;
            border-radius: 8px;
            padding: 15px;
            z-index: 9999;
            color: #f0f0f0;
            font-family: monospace;
            font-size: 12px;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
        #debug-panel pre {
            white-space: pre-wrap;
            margin: 0;
        }
        #debug-panel .title {
            color: #3B82F6;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
        }
        #debug-panel .close {
            position: absolute;
            top: 10px;
            right: 15px;
            cursor: pointer;
            color: #f0f0f0;
        }
    </style>
    <div id="debug-panel">
        <div class="close" onclick="document.getElementById('debug-panel').style.display='none'">✕</div>
        <div class="title">Debug Information</div>
        <pre><?php echo htmlspecialchars($debugInfo); ?></pre>
        <pre>
PHP Version: <?php echo PHP_VERSION; ?>
Loaded Extensions: <?php echo implode(', ', get_loaded_extensions()); ?>
Server Software: <?php echo $_SERVER['SERVER_SOFTWARE']; ?>
        </pre>
    </div>
    <?php 
    // Set a debug cookie to keep debug panel visible on reload
    if (isset($_GET['debug']) && !isset($_COOKIE['debug'])) {
        setcookie('debug', '1', time() + 3600, '/');
    }
    ?>
    <?php endif; ?>
</head>

<body class="bg-black text-white min-h-screen overflow-x-hidden">
    <!-- Page Loader -->
    <div id="page-loader" class="page-loader">
        <div class="loader-content">
            <div class="mx-auto w-24 h-24 mb-4 flex justify-center items-center">
                <img src="assets/images/lol.png" alt="RentMyKeys Logo" class="w-full h-auto animate-pulse">
            </div>
            <div class="mt-4 text-center">
                <p class="text-lg font-medium">Loading...</p>
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

    <!-- Navbar -->
    <nav class="glass-card sticky top-0 z-50 border-b border-gray-800/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0 flex items-center">
                        <img class="h-12 w-auto mr-2" src="assets/images/lol.png" alt="RentMyKeys">
                    </a>
                    <div class="hidden md:ml-8 md:flex md:items-center md:space-x-6">
                        <a href="index.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Home</a>
                        <a href="search.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">Search</a>
                        <a href="about.php" class="text-gray-300 hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-primary after:transition-all after:duration-300">About</a>
                        <a href="contact.php" class="text-white hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-full after:bg-primary after:transition-all after:duration-300">Contact</a>
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
                        <a href="register.php" class="ml-3 bg-gradient-to-r from-primary to-accent text-white px-4 py-2 rounded-md text-sm font-medium transition-all duration-300 hover:shadow-lg hover:shadow-primary/30 hover:-translate-y-1">Register</a>
                    <?php endif; ?>
                    
                    <!-- Mobile menu button -->
                    <div class="flex items-center md:hidden ml-4">
                        <button type="button" class="bg-gray-800/60 rounded-md p-2 flex items-center justify-center text-gray-400 hover:text-white focus:outline-none" aria-controls="mobile-menu" aria-expanded="false" id="mobile-menu-button">
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
                <a href="index.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Home</a>
                <a href="search.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Search</a>
                <a href="about.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">About</a>
                <a href="contact.php" class="text-white block px-3 py-2 rounded-md text-base font-medium bg-primary/20">Contact</a>
                <?php if (!isLoggedIn()): ?>
                    <a href="login.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Sign In</a>
                    <a href="register.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-3xl overflow-hidden shadow-xl">
                <div class="relative h-60 md:h-80 overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-primary/30 to-accent/30 mix-blend-overlay"></div>
                    <img class="w-full h-full object-cover" src="assets/images/contact-banner.jpg" alt="Contact Us">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full px-8 md:px-16">
                            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4 drop-shadow-lg">Contact Us</h1>
                            <p class="text-xl text-white max-w-3xl drop-shadow-lg">We'd love to hear from you. Get in touch with our team.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Information Section -->
    <section class="py-12 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Contact Card 1 -->
                <div class="glass-card rounded-2xl p-8 shadow-lg info-card">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary/30 to-primary/10 rounded-full flex items-center justify-center mb-6 mx-auto icon-pulse">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 text-center">Call Us</h3>
                    <p class="text-gray-300 text-center mb-4">Have a quick question? Give us a call:</p>
                    <p class="text-primary text-lg font-semibold text-center">+91 9876543210</p>
                    <p class="text-gray-400 text-sm text-center mt-2">Available 9 AM - 6 PM, Monday to Friday</p>
                </div>
                
                <!-- Contact Card 2 -->
                <div class="glass-card rounded-2xl p-8 shadow-lg info-card">
                    <div class="w-16 h-16 bg-gradient-to-br from-accent/30 to-accent/10 rounded-full flex items-center justify-center mb-6 mx-auto icon-pulse">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 text-center">Email Us</h3>
                    <p class="text-gray-300 text-center mb-4">We're always here to help:</p>
                    <p class="text-accent text-lg font-semibold text-center">info@rentmykeys.com</p>
                    <p class="text-gray-400 text-sm text-center mt-2">We typically respond within 24 hours</p>
                </div>
                
                <!-- Contact Card 3 -->
                <div class="glass-card rounded-2xl p-8 shadow-lg info-card">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary/30 to-primary/10 rounded-full flex items-center justify-center mb-6 mx-auto icon-pulse">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 text-center">Visit Us</h3>
                    <p class="text-gray-300 text-center mb-4">Come meet our team in person:</p>
                    <p class="text-primary text-lg font-semibold text-center">123 Main Street</p>
                    <p class="text-gray-400 text-sm text-center mt-2">City, State, Country</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-2xl overflow-hidden shadow-xl">
                <div class="flex flex-col md:flex-row">
                    <div class="md:w-1/2 p-8 md:p-12">
                        <h2 class="text-3xl font-bold gradient-text mb-6">Get In Touch</h2>
                        <p class="text-gray-300 mb-8">Have a question about our services, need help with a booking, or want to suggest a feature? Fill out the form, and we'll get back to you as soon as possible.</p>
                        
                        <?php if (!empty($message)): ?>
                            <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900/40 border border-green-500/50 text-white' : 'bg-red-900/40 border border-red-500/50 text-white'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="contact.php">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="name" class="block text-white text-sm font-medium mb-2">Your Name</label>
                                    <input type="text" id="name" name="name" class="form-input input-glow w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your name" required>
                                </div>
                                <div>
                                    <label for="email" class="block text-white text-sm font-medium mb-2">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-input input-glow w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your email" required>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="subject" class="block text-white text-sm font-medium mb-2">Subject</label>
                                <input type="text" id="subject" name="subject" class="form-input input-glow w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter subject" required>
                            </div>
                            
                            <div class="mb-6">
                                <label for="message" class="block text-white text-sm font-medium mb-2">Message</label>
                                <textarea id="message" name="message" rows="5" class="form-input input-glow w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary resize-none" placeholder="Enter your message" required></textarea>
                            </div>
                            
                            <div class="mb-6">
                                <button type="submit" class="w-full bg-gradient-to-r from-primary to-accent text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 hover:shadow-lg hover:shadow-primary/30 hover:-translate-y-1 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    Send Message
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="md:w-1/2 relative"></div>
                    <div class="md:w-1/2 relative">
                        <div class="w-full h-full absolute inset-0 z-0">
                            <div class="absolute inset-0 bg-gradient-to-br from-primary/20 to-accent/20"></div>
                            <iframe class="w-full h-full" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d14008.17439308419!2d77.21736097397228!3d28.64280337146898!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x390cfd5b347eb62d%3A0x52c2b7494e204dce!2sNew%20Delhi%2C%20Delhi!5e0!3m2!1sen!2sin!4v1620647597665!5m2!1sen!2sin" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-2xl p-8 md:p-12 shadow-lg">
                <h2 class="text-3xl font-bold gradient-text mb-12 text-center">Frequently Asked Questions</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div x-data="{ open: false }">
                        <div @click="open = !open" class="flex justify-between items-center cursor-pointer p-4 rounded-lg bg-gray-800/50 hover:bg-gray-700/50 transition-all duration-300">
                            <h3 class="text-lg font-semibold text-white">How do I rent a vehicle?</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <div x-show="open" x-collapse class="p-4 text-gray-300">
                            Renting a vehicle is easy! Simply search for available vehicles in your area, select your preferred dates, and book through our secure platform. You'll receive confirmation and details for pick-up directly from the vehicle owner.
                        </div>
                    </div>
                    
                    <div x-data="{ open: false }">
                        <div @click="open = !open" class="flex justify-between items-center cursor-pointer p-4 rounded-lg bg-gray-800/50 hover:bg-gray-700/50 transition-all duration-300">
                            <h3 class="text-lg font-semibold text-white">How do I list my vehicle?</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <div x-show="open" x-collapse class="p-4 text-gray-300">
                            To list your vehicle, sign up as a Renter, verify your identity, and add your vehicle details including photos, pricing, and availability. Once approved, your listing will be visible to potential renters in your area.
                        </div>
                    </div>
                    
                    <div x-data="{ open: false }">
                        <div @click="open = !open" class="flex justify-between items-center cursor-pointer p-4 rounded-lg bg-gray-800/50 hover:bg-gray-700/50 transition-all duration-300">
                            <h3 class="text-lg font-semibold text-white">Is my vehicle insured during rentals?</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <div x-show="open" x-collapse class="p-4 text-gray-300">
                            Yes, all rentals are covered by our comprehensive insurance policy. This coverage protects against accidents, damage, and theft during the rental period, giving both owners and renters peace of mind.
                        </div>
                    </div>
                    
                    <div x-data="{ open: false }">
                        <div @click="open = !open" class="flex justify-between items-center cursor-pointer p-4 rounded-lg bg-gray-800/50 hover:bg-gray-700/50 transition-all duration-300">
                            <h3 class="text-lg font-semibold text-white">What if I need to cancel a booking?</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <div x-show="open" x-collapse class="p-4 text-gray-300">
                            Our flexible cancellation policy allows for full refunds if cancelled 48 hours or more before the rental start time. Cancellations less than 48 hours in advance may receive partial refunds, depending on the vehicle owner's policy.
                        </div>
                    </div>
                    
                    <div x-data="{ open: false }">
                        <div @click="open = !open" class="flex justify-between items-center cursor-pointer p-4 rounded-lg bg-gray-800/50 hover:bg-gray-700/50 transition-all duration-300">
                            <h3 class="text-lg font-semibold text-white">How are payments processed?</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <div x-show="open" x-collapse class="p-4 text-gray-300">
                            We handle all payments securely through our platform. Renters are charged when the booking is confirmed, and vehicle owners receive payment 24 hours after the rental begins, ensuring a smooth and secure transaction for both parties.
                        </div>
                    </div>
                    
                    <div x-data="{ open: false }">
                        <div @click="open = !open" class="flex justify-between items-center cursor-pointer p-4 rounded-lg bg-gray-800/50 hover:bg-gray-700/50 transition-all duration-300">
                            <h3 class="text-lg font-semibold text-white">What is your customer support availability?</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <div x-show="open" x-collapse class="p-4 text-gray-300">
                            Our customer support team is available from 9 AM to 6 PM, Monday to Friday. For urgent issues outside these hours, we offer emergency support through our 24/7 helpline for active rentals.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Social Media Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-2xl p-8 md:p-12 shadow-lg">
                <h2 class="text-3xl font-bold gradient-text mb-8 text-center">Connect With Us</h2>
                <p class="text-gray-300 text-center mb-10 max-w-3xl mx-auto">Follow us on social media for the latest updates, promotions, and community stories. Join our growing network of vehicle renters and owners.</p>
                
                <div class="flex flex-wrap justify-center gap-8">
                    <a href="#" class="group">
                        <div class="w-16 h-16 bg-gradient-to-br from-[#3b5998]/30 to-[#3b5998]/10 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#3b5998]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z" />
                            </svg>
                        </div>
                        <p class="text-center text-gray-300 group-hover:text-[#3b5998] transition-colors duration-300">Facebook</p>
                    </a>
                    
                    <a href="#" class="group">
                        <div class="w-16 h-16 bg-gradient-to-br from-[#1da1f2]/30 to-[#1da1f2]/10 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#1da1f2]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z" />
                            </svg>
                        </div>
                        <p class="text-center text-gray-300 group-hover:text-[#1da1f2] transition-colors duration-300">Twitter</p>
                    </a>
                    
                    <a href="#" class="group">
                        <div class="w-16 h-16 bg-gradient-to-br from-[#e1306c]/30 to-[#e1306c]/10 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#e1306c]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                            </svg>
                        </div>
                        <p class="text-center text-gray-300 group-hover:text-[#e1306c] transition-colors duration-300">Instagram</p>
                    </a>
                    
                    <a href="#" class="group">
                        <div class="w-16 h-16 bg-gradient-to-br from-[#0077b5]/30 to-[#0077b5]/10 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#0077b5]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm-2 16h-2v-6h2v6zm-1-6.891c-.607 0-1.1-.496-1.1-1.109 0-.612.492-1.109 1.1-1.109s1.1.497 1.1 1.109c0 .613-.493 1.109-1.1 1.109zm8 6.891h-1.998v-2.861c0-1.881-2.002-1.722-2.002 0v2.861h-2v-6h2v1.093c.872-1.616 4-1.736 4 1.548v3.359z" />
                            </svg>
                        </div>
                        <p class="text-center text-gray-300 group-hover:text-[#0077b5] transition-colors duration-300">LinkedIn</p>
                    </a>
                    
                    <a href="#" class="group">
                        <div class="w-16 h-16 bg-gradient-to-br from-[#ff0000]/30 to-[#ff0000]/10 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#ff0000]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm4.441 16.892c-2.102.144-6.784.144-8.883 0-2.276-.156-2.541-1.27-2.558-4.892.017-3.629.285-4.736 2.558-4.892 2.099-.144 6.782-.144 8.883 0 2.277.156 2.541 1.27 2.559 4.892-.018 3.629-.285 4.736-2.559 4.892zm-6.441-7.234l4.917 2.338-4.917 2.346v-4.684z" />
                            </svg>
                        </div>
                        <p class="text-center text-gray-300 group-hover:text-[#ff0000] transition-colors duration-300">YouTube</p>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-2xl p-8 md:p-12 shadow-lg bg-gradient-to-br from-primary/20 to-accent/20">
                <div class="flex flex-col md:flex-row items-center">
                    <div class="md:w-3/5 mb-8 md:mb-0 md:pr-12">
                        <h2 class="text-3xl font-bold text-white mb-4">Stay Updated</h2>
                        <p class="text-gray-300">Subscribe to our newsletter to receive the latest updates, special offers, and tips for vehicle rental and ownership.</p>
                    </div>
                    <div class="md:w-2/5">
                        <form action="#" method="POST" class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                            <input type="email" placeholder="Enter your email" class="form-input input-glow px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary flex-1">
                            <button type="submit" class="bg-gradient-to-r from-primary to-accent text-white font-bold py-3 px-6 rounded-lg transition-all duration-300 hover:shadow-lg hover:shadow-primary/30 hover:-translate-y-1">
                                Subscribe
                            </button>
                        </form>
                        <p class="text-gray-400 text-sm mt-4">We respect your privacy. Unsubscribe at any time.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 px-4 sm:px-6 lg:px-8 border-t border-gray-800/30 mt-12">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <a href="index.php" class="flex items-center mb-4 group">
                        <div class="relative h-12 w-12 mr-2 overflow-hidden rounded-full">
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
                        <li><a href="index.php" class="text-gray-300 hover:text-primary transition-colors duration-300">Home</a></li>
                        <li><a href="search.php" class="text-gray-300 hover:text-primary transition-colors duration-300">Search Vehicles</a></li>
                        <li><a href="about.php" class="text-gray-300 hover:text-primary transition-colors duration-300">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-300 hover:text-primary transition-colors duration-300">Contact Us</a></li>
                        <li><a href="register.php?type=renter" class="text-gray-300 hover:text-primary transition-colors duration-300">Become a Renter</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold gradient-text mb-4">Vehicle Types</h3>
                    <ul class="space-y-2">
                        <li><a href="search.php?vehicle_type=car" class="text-gray-300 hover:text-primary transition-colors duration-300">Cars</a></li>
                        <li><a href="search.php?vehicle_type=motorcycle" class="text-gray-300 hover:text-primary transition-colors duration-300">Motorcycles</a></li>
                        <li><a href="search.php?vehicle_type=scooter" class="text-gray-300 hover:text-primary transition-colors duration-300">Scooters</a></li>
                        <li><a href="search.php?vehicle_type=bicycle" class="text-gray-300 hover:text-primary transition-colors duration-300">Bicycles</a></li>
                        <li><a href="search.php?vehicle_type=other" class="text-gray-300 hover:text-primary transition-colors duration-300">Other Vehicles</a></li>
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
                            <span class="text-gray-300">123 Main Street, City, Country</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span class="text-gray-300">+91 9876543210</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span class="text-gray-300">info@rentmykeys.com</span>
                        </li>
                    </ul>
                </div>
            </div>
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