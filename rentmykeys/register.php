<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Default user type
$user_type = isset($_GET['type']) && $_GET['type'] == 'renter' ? 'renter' : 'customer';

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = isset($_POST['user_type']) ? sanitize($_POST['user_type']) : 'customer';
    $phone_number = isset($_POST['phone_number']) ? sanitize($_POST['phone_number']) : '';
    
    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Username is already taken";
        }
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email is already registered";
        }
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if ($user_type == 'renter' && empty($phone_number)) {
        $errors[] = "Phone number is required for renters";
    }
    
    // Upload profile picture if provided
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $profile_picture = uploadImage($_FILES['profile_picture'], "uploads/profiles/");
        if (!$profile_picture) {
            $errors[] = "Failed to upload profile picture. Please ensure it's a valid image (JPG, PNG, GIF) and under 5MB.";
        }
    }
    
    // Upload ID proof for renters if provided
    $id_proof = null;
    if ($user_type == 'renter' && isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] == 0) {
        $id_proof = uploadImage($_FILES['id_proof'], "uploads/id_proofs/");
        if (!$id_proof) {
            $errors[] = "Failed to upload ID proof. Please ensure it's a valid image (JPG, PNG, GIF) and under 5MB.";
        }
    }
    
    // Register user if no errors
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into database
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, profile_picture, phone_number, user_type, id_proof) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $email, $hashed_password, $full_name, $profile_picture, $phone_number, $user_type, $id_proof);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['profile_picture'] = $profile_picture;
            
            // Redirect based on user type
            $redirect = ($user_type == 'renter') ? "dashboard-renter.php" : "dashboard-customer.php";
            header("Location: $redirect");
            exit();
        } else {
            $errors[] = "Registration failed: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - RentMyKeys</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
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
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(23, 37, 84, 0.95) 100%);
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

        .spinning-logo {
            animation: spin-logo 4s infinite linear;
        }

        @keyframes spin-logo {
            0% { transform: perspective(120px) rotateY(0deg); }
            100% { transform: perspective(120px) rotateY(360deg); }
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

        /* Floating elements */
        .floating {
            animation: float 6s ease-in-out infinite;
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

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(17, 24, 39, 0.8);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #3B82F6, #8B5CF6);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #2563EB, #7C3AED);
        }

        /* Input glow effects */
        .input-glow:focus {
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
        }

        /* Radio button custom styles */
        .radio-card-glow {
            transition: all 0.3s ease;
        }
        
        .radio-card-glow:hover {
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        .radio-card-active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(139, 92, 246, 0.1) 100%);
            border: 1px solid rgba(99, 102, 241, 0.4);
        }
        /* Black tinted loader background */
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

/* Rotating key animation */
.rotating-key {
    animation: rotate-key 2s infinite linear;
}

@keyframes rotate-key {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Pulsing text animation */
.pulsing-text {
    animation: pulse-text 1.5s infinite ease-in-out;
}

@keyframes pulse-text {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
    </style>
</head>

<body class="bg-black text-white min-h-screen overflow-x-hidden">
    <!-- Page Loader -->
    <body class="bg-black text-white min-h-screen overflow-x-hidden">
    <!-- Page Loader -->
    <div id="page-loader" class="page-loader">
        <div class="loader-content">
            <div class="mx-auto w-24 h-24 mb-4 flex justify-center items-center">
                <img src="assets/images/lol.png" alt="RentMyKeys Logo" class="rotating-key w-full h-auto">
            </div>
            <div class="mt-4 text-center">
                <p class="text-lg font-medium pulsing-text">Loading...</p>
            </div>
        </div>
    </div>
</body>

    <!-- Background elements -->
    <div class="fixed inset-0 z-0 overflow-hidden opacity-10">
        <img src="assets/images/background.jpg" alt="Background" class="w-[120%] h-[120%] object-cover filter blur-sm background-animation absolute -top-[10%] -left-[10%]">
    </div>
    
    <!-- Animated glow effects -->
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>
    <div class="glow glow-3"></div>

    <!-- Navigation -->
    <div class="relative z-20 w-full p-6">
    <div class="max-w-7xl mx-auto">
        <a href="index.php" class="flex items-center">
            <img class="h-12 w-auto mr-2" src="assets/images/lol.png" alt="RentMyKeys">
        </a>
    </div>
</div>

    <!-- Main content -->
    <div class="relative z-10 min-h-screen pb-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto relative">
            <!-- Animated background elements -->
            <div class="absolute top-1/4 left-1/3 w-64 h-64 bg-gradient-to-br from-primary/20 to-transparent rounded-full filter blur-xl -z-10"></div>
            <div class="absolute bottom-1/4 right-1/3 w-64 h-64 bg-gradient-to-br from-accent/20 to-transparent rounded-full filter blur-xl -z-10"></div>
            
            <!-- Card container -->
            <div class="glass-card rounded-3xl overflow-hidden shadow-xl transition-all duration-300 hover:shadow-primary/20">
                <div class="flex flex-col md:flex-row">
                    <!-- Left side: Registration Form -->
                    <div class="md:w-2/3 p-8 md:p-12 relative">
                        <h2 class="text-3xl font-bold gradient-text mb-2">Create an Account</h2>
                        <p class="text-gray-300 mb-8">Join our community to rent or list vehicles</p>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="bg-red-900/40 border border-red-500/50 text-white px-4 py-3 rounded-lg mb-6 backdrop-blur-sm">
                                <ul class="list-disc list-inside">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="register.php" enctype="multipart/form-data">
                            <div class="mb-8">
                                <label class="block text-white text-sm font-medium mb-4">I want to:</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label class="flex items-center p-4 radio-card-glow <?php echo $user_type == 'customer' ? 'radio-card-active' : 'border-gray-700/50 bg-gray-900/30'; ?> rounded-lg cursor-pointer transition">
                                        <input type="radio" name="user_type" value="customer" class="h-4 w-4 text-primary focus:ring-primary" <?php echo $user_type == 'customer' ? 'checked' : ''; ?> onchange="toggleUserType(this)">
                                        <div class="ml-3">
                                            <span class="block text-white font-medium">Rent Vehicles</span>
                                            <span class="block text-gray-300 text-sm">I want to book vehicles from others</span>
                                        </div>
                                    </label>
                                    <label class="flex items-center p-4 radio-card-glow <?php echo $user_type == 'renter' ? 'radio-card-active' : 'border-gray-700/50 bg-gray-900/30'; ?> rounded-lg cursor-pointer transition">
                                        <input type="radio" name="user_type" value="renter" class="h-4 w-4 text-primary focus:ring-primary" <?php echo $user_type == 'renter' ? 'checked' : ''; ?> onchange="toggleUserType(this)">
                                        <div class="ml-3">
                                            <span class="block text-white font-medium">List My Vehicles</span>
                                            <span class="block text-gray-300 text-sm">I want to earn by renting out my vehicles</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="full_name" class="block text-white text-sm font-medium mb-2">Full Name</label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="full_name" 
                                            name="full_name" 
                                            class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary input-glow transition-all duration-300"
                                            placeholder="Enter your full name"
                                            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                        >
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="username" class="block text-white text-sm font-medium mb-2">Username</label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="username" 
                                            name="username" 
                                            class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary input-glow transition-all duration-300"
                                            placeholder="Choose a username"
                                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                        >
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-8">
                                <div class="neon-border rounded-lg">
                                    <button 
                                        type="submit" 
                                        class="w-full gradient-button text-white font-bold py-3 px-4 rounded-lg transition duration-300 ease-in-out flex items-center justify-center"
                                    >
                                        Create Account
                                    </button>
                                </div>
                            </div>
                            
                            <p class="text-center text-gray-300 mt-6">
                                Already have an account? 
                                <a href="login.php" class="text-primary hover:text-accent transition-colors duration-300 font-medium">
                                    Sign in
                                </a>
                            </p>
                            
                            <div class="mt-6">
                                <label for="email" class="block text-white text-sm font-medium mb-2">Email Address</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <input 
                                        type="email" 
                                        id="email" 
                                        name="email" 
                                        class="w-full pl-10 pr-3 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary input-glow transition-all duration-300"
                                        placeholder="Enter your email address"
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    >
                                </div>
                            </div>
                            
                            <div id="renter_fields" class="mt-6 <?php echo $user_type == 'renter' ? '' : 'hidden'; ?>">
                                <label for="phone_number" class="block text-white text-sm font-medium mb-2">Phone Number</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </div>
                                    <input 
                                        type="text" 
                                        id="phone_number" 
                                        name="phone_number" 
                                        class="w-full pl-10 pr-3 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary input-glow transition-all duration-300"
                                        placeholder="Enter your phone number"
                                        value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>"
                                    >
                                </div>
                                
                                <div class="mt-6">
                                    <label for="id_proof" class="block text-white text-sm font-medium mb-2">ID Proof (Driver's License, Aadhaar, etc.)</label>
                                    <div class="relative">
                                        <input 
                                            type="file" 
                                            id="id_proof" 
                                            name="id_proof" 
                                            class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary input-glow transition-all duration-300"
                                            accept="image/*"
                                        >
                                    </div>
                                    <p class="mt-1 text-sm text-gray-300">Upload a clear image of your ID proof. This helps verify your identity.</p>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <label for="profile_picture" class="block text-white text-sm font-medium mb-2">Profile Picture (Optional)</label>
                                <div class="relative">
                                    <input 
                                        type="file" 
                                        id="profile_picture" 
                                        name="profile_picture" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary input-glow transition-all duration-300"
                                        accept="image/*"
                                    >
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label for="password" class="block text-white text-sm font-medium mb-2">Password</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                        <input 
                                            type="password" 
                                            id="password" 
                                            name="password" 
                                            class="w-full pl-10 pr-3 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary input-glow transition-all duration-300"
                                            placeholder="••••••••"
                                        >
                                    </div>
                                    <p class="mt-1 text-sm text-gray-300">Minimum 8 characters</p>
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="block text-white text-sm font-medium mb-2">Confirm Password</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                        <input 
                                            type="password" 
                                            id="confirm_password" 
                                            name="confirm_password" 
                                            class="w-full pl-10 pr-3 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary input-glow transition-all duration-300"
                                            placeholder="••••••••"
                                        >
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Right side: Info -->
                    <div class="md:w-1/3 bg-gradient-to-br from-primary/30 to-accent/20 p-8 md:p-12 flex flex-col justify-center backdrop-blur-lg relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-primary/20 rounded-full filter blur-3xl -z-10"></div>
                        <div class="absolute bottom-0 left-0 w-64 h-64 bg-accent/20 rounded-full filter blur-3xl -z-10"></div>
                        
                        <div class="relative z-10 text-center md:text-left">
                            <h3 class="text-2xl font-bold gradient-text mb-6">Benefits of Joining</h3>
                            
                            <ul class="space-y-4">
                                <li class="flex items-start group">
                                    <div class="h-6 w-6 rounded-full bg-primary/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-white group-hover:text-primary transition-colors duration-300">Access to thousands of vehicles across the country</span>
                                </li>
                                <li class="flex items-start group">
                                    <div class="h-6 w-6 rounded-full bg-accent/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-accent/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-white group-hover:text-accent transition-colors duration-300">Earn money by renting out your idle vehicles</span>
                                </li>
                                <li class="flex items-start group">
                                    <div class="h-6 w-6 rounded-full bg-primary/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-white group-hover:text-primary transition-colors duration-300">Secure payments and identity verification</span>
                                </li>
                                <li class="flex items-start group">
                                    <div class="h-6 w-6 rounded-full bg-accent/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-accent/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-white group-hover:text-accent transition-colors duration-300">Customer support available 24/7</span>
                                </li>
                                <li class="flex items-start group">
                                    <div class="h-6 w-6 rounded-full bg-primary/20 flex items-center justify-center mr-3 flex-shrink-0 transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-white group-hover:text-primary transition-colors duration-300">Easy booking and management through user-friendly interface</span>
                                </li>
                            </ul>
                            
                            <div class="mt-8 relative perspective-container">
                                <div class="absolute inset-0 circle-animation opacity-30"></div>
                                <img src="assets/images/car.png" alt="Vehicle" class="w-full max-w-xs mx-auto md:mx-0 floating relative z-10">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Page transition script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loader = document.getElementById('page-loader');
            const progress = document.getElementById('loader-progress');
            
            // Show loader
            loader.classList.add('active');
            
            // Animate progress bar
            let width = 0;
            const progressInterval = setInterval(function() {
                if (width >= 100) {
                    clearInterval(progressInterval);
                    setTimeout(function() {
                        loader.classList.remove('active');
                    }, 300);
                } else {
                    width += 5;
                    progress.style.width = width + '%';
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
        });
        
        // 3D Tilt effect on hover
        const cards = document.querySelectorAll('.radio-card-glow');
        cards.forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const angleX = (y - centerY) / 20;
                const angleY = (centerX - x) / 20;
                
                card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
            });
        });
        
        // Toggle user type fields
        function toggleUserType(radio) {
            const renterFields = document.getElementById('renter_fields');
            
            if (radio.value === 'renter') {
                renterFields.classList.remove('hidden');
                gsap.fromTo(renterFields, {opacity: 0, y: 20}, {opacity: 1, y: 0, duration: 0.5});
            } else {
                gsap.to(renterFields, {opacity: 0, y: 20, duration: 0.3, onComplete: () => {
                    renterFields.classList.add('hidden');
                }});
            }
            
            // Update the styling of the selected option
            const options = document.querySelectorAll('input[name="user_type"]');
            options.forEach(option => {
                const parent = option.closest('label');
                if (option.checked) {
                    parent.classList.add('radio-card-active');
                    parent.classList.remove('border-gray-700/50', 'bg-gray-900/30');
                } else {
                    parent.classList.remove('radio-card-active');
                    parent.classList.add('border-gray-700/50', 'bg-gray-900/30');
                }
            });
        }
        
        // Fix the order of form fields
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements
            const form = document.querySelector('form');
            const emailDiv = document.querySelector('[for="email"]').parentNode;
            const renterFields = document.getElementById('renter_fields');
            const profilePictureDiv = document.querySelector('[for="profile_picture"]').parentNode;
            const passwordContainer = document.querySelector('[for="password"]').closest('.grid');
            const submitButtonDiv = document.querySelector('button[type="submit"]').closest('.mt-8');
            const signInLink = document.querySelector('p.text-center.text-gray-300');
            
            // Fix form field order
            const emailIndex = Array.from(form.children).indexOf(emailDiv);
            const submitButtonIndex = Array.from(form.children).indexOf(submitButtonDiv);
            const signInLinkIndex = Array.from(form.children).indexOf(signInLink);
            
            // Only reorder if the current order is wrong
            if (emailIndex > submitButtonIndex) {
                form.insertBefore(emailDiv, submitButtonDiv);
                form.insertBefore(renterFields, submitButtonDiv);
                form.insertBefore(profilePictureDiv, submitButtonDiv);
                form.insertBefore(passwordContainer, submitButtonDiv);
                
                // Add some space between the fields
                emailDiv.classList.add('mt-6');
                passwordContainer.classList.add('mt-6');
            }
        });
    </script>
</body>
</html>