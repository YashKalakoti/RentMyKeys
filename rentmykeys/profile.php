<?php
session_start();
require_once "includes/functions.php";
include 'db.php';

// Check if user is logged in
requireLogin();

$message = '';
$messageType = '';
$user = null;

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    // User not found
    header("Location: logout.php");
    exit();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone_number = isset($_POST['phone_number']) ? sanitize($_POST['phone_number']) : null;
        $address = isset($_POST['address']) ? sanitize($_POST['address']) : null;
        $city = isset($_POST['city']) ? sanitize($_POST['city']) : null;
        $state = isset($_POST['state']) ? sanitize($_POST['state']) : null;
        $zip_code = isset($_POST['zip_code']) ? sanitize($_POST['zip_code']) : null;
        
        // Validate input
        $errors = [];
        
        if (empty($full_name)) {
            $errors[] = "Full name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email is already used by another user
        if ($email !== $user['email']) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "Email is already registered by another user";
            }
        }
        
        // Validate phone number for renters
        if (isRenter() && empty($phone_number)) {
            $errors[] = "Phone number is required for renters";
        }
        
        // Handle profile picture upload
        $profile_picture = $user['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $new_profile_picture = uploadImage($_FILES['profile_picture'], "uploads/profiles/");
            
            if ($new_profile_picture) {
                // Delete old profile picture if it exists
                if ($profile_picture && file_exists($profile_picture) && $profile_picture != 'assets/images/default-avatar.png') {
                    unlink($profile_picture);
                }
                
                $profile_picture = $new_profile_picture;
            } else {
                $errors[] = "Failed to upload profile picture. Please ensure it's a valid image (JPG, PNG, GIF) and under 5MB.";
            }
        }
        
        if (empty($errors)) {
            // Update user information
            $stmt = $conn->prepare("UPDATE users SET 
                                    full_name = ?, 
                                    email = ?, 
                                    profile_picture = ?,
                                    phone_number = ?, 
                                    address = ?, 
                                    city = ?, 
                                    state = ?, 
                                    zip_code = ? 
                                    WHERE user_id = ?");
            
            $stmt->bind_param("ssssssssi", 
                             $full_name, 
                             $email, 
                             $profile_picture,
                             $phone_number, 
                             $address, 
                             $city, 
                             $state, 
                             $zip_code, 
                             $user_id);
            
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['profile_picture'] = $profile_picture;
                
                $message = "Profile updated successfully!";
                $messageType = "success";
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $message = "Error updating profile: " . $conn->error;
                $messageType = "error";
            }
        } else {
            $message = implode("<br>", $errors);
            $messageType = "error";
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        $errors = [];
        
        if (empty($current_password)) {
            $errors[] = "Current password is required";
        }
        
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        if (empty($errors)) {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Password changed successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error changing password: " . $conn->error;
                    $messageType = "error";
                }
            } else {
                $message = "Current password is incorrect";
                $messageType = "error";
            }
        } else {
            $message = implode("<br>", $errors);
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
    <title>Profile - RentMyKeys</title>
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
        
        /* Profile picture hover effect */
        .profile-picture-hover {
            position: relative;
        }
        
        .profile-picture-hover::after {
            content: 'Change Photo';
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(17, 24, 39, 0.7);
            color: white;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
        
        .profile-picture-hover:hover::after {
            opacity: 1;
        }
        
        /* Form input styles */
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
        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold gradient-text mb-8">Profile Settings</h1>
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900/40 border border-green-500/50 text-white' : 'bg-red-900/40 border border-red-500/50 text-white'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div x-data="{ activeTab: 'profile' }" class="mb-8">
                <!-- Profile tabs -->
                <div class="flex flex-wrap border-b border-gray-700/50 mb-6">
                    <button 
                        @click="activeTab = 'profile'" 
                        :class="{ 'border-primary text-primary': activeTab === 'profile', 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-500': activeTab !== 'profile' }"
                        class="px-4 py-2 font-medium border-b-2 transition-colors duration-300 -mb-px"
                    >
                        Personal Information
                    </button>
                    <button 
                        @click="activeTab = 'security'" 
                        :class="{ 'border-primary text-primary': activeTab === 'security', 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-500': activeTab !== 'security' }"
                        class="px-4 py-2 font-medium border-b-2 transition-colors duration-300 -mb-px"
                    >
                        Security
                    </button>
                </div>
                
                <!-- Profile Information Tab -->
                <div x-show="activeTab === 'profile'" class="glass-card rounded-xl p-6 md:p-8 shadow-lg">
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                        <div class="flex flex-col md:flex-row items-start gap-8">
                            <div class="md:w-1/3 flex flex-col items-center">
                                <div class="mb-4 relative">
                                    <div class="w-40 h-40 rounded-full overflow-hidden border-4 border-gray-800/80 profile-picture-hover">
                                        <img 
                                            src="<?php echo isset($user['profile_picture']) && $user['profile_picture'] ? $user['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                            alt="Profile Picture" 
                                            class="w-full h-full object-cover"
                                            id="profile-preview"
                                        >
                                    </div>
                                    <input 
                                        type="file" 
                                        id="profile_picture" 
                                        name="profile_picture" 
                                        class="hidden" 
                                        accept="image/*"
                                    >
                                </div>
                                <label for="profile_picture" class="text-primary hover:text-blue-400 transition cursor-pointer text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Upload Photo
                                </label>
                                <p class="text-gray-400 text-xs mt-2 text-center">
                                    Allowed formats: JPG, PNG, GIF<br>
                                    Max size: 5MB
                                </p>
                                
                                <div class="mt-6 p-4 bg-gray-800/50 rounded-lg w-full">
                                    <h3 class="font-semibold text-white mb-2">Account Type</h3>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                                        <span><?php echo ucfirst($user['user_type']); ?></span>
                                    </div>
                                    <div class="text-gray-400 text-sm mt-2">
                                        <?php if ($user['user_type'] === 'renter'): ?>
                                            You can list and rent out your vehicles
                                        <?php else: ?>
                                            You can book and rent vehicles from others
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:w-2/3 space-y-6 w-full">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="full_name" class="block text-white text-sm font-medium mb-2">Full Name*</label>
                                        <input 
                                            type="text" 
                                            id="full_name" 
                                            name="full_name" 
                                            class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                            placeholder="Enter your full name"
                                            value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                            required
                                        >
                                    </div>
                                    
                                    <div>
                                        <label for="email" class="block text-white text-sm font-medium mb-2">Email Address*</label>
                                        <input 
                                            type="email" 
                                            id="email" 
                                            name="email" 
                                            class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                            placeholder="Enter your email"
                                            value="<?php echo htmlspecialchars($user['email']); ?>"
                                            required
                                        >
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="phone_number" class="block text-white text-sm font-medium mb-2">
                                        Phone Number<?php echo isRenter() ? '*' : ''; ?>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="phone_number" 
                                        name="phone_number" 
                                        class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter your phone number"
                                        value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>"
                                        <?php echo isRenter() ? 'required' : ''; ?>
                                    >
                                </div>
                                
                                <div>
                                    <label for="address" class="block text-white text-sm font-medium mb-2">Address</label>
                                    <input 
                                        type="text" 
                                        id="address" 
                                        name="address" 
                                        class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter your address"
                                        value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                                    >
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label for="city" class="block text-white text-sm font-medium mb-2">City</label>
                                        <input 
                                            type="text" 
                                            id="city" 
                                            name="city" 
                                            class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                            placeholder="Enter your city"
                                            value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
                                        >
                                    </div>
                                    
                                    <div>
                                        <label for="state" class="block text-white text-sm font-medium mb-2">State</label>
                                        <input 
                                            type="text" 
                                            id="state" 
                                            name="state" 
                                            class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                            placeholder="Enter your state"
                                            value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>"
                                        >
                                    </div>
                                    
                                    <div>
                                        <label for="zip_code" class="block text-white text-sm font-medium mb-2">ZIP Code</label>
                                        <input 
                                            type="text" 
                                            id="zip_code" 
                                            name="zip_code" 
                                            class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                            placeholder="Enter your ZIP code"
                                            value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>"
                                        >
                                    </div>
                                </div>
                                
                                <div class="mt-8">
                                    <input type="hidden" name="update_profile" value="1">
                                    <button 
                                        type="submit" 
                                        class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out"
                                    >
                                        Save Changes
                                    </button>
                                </div>
                            </div>
                            </div>
                    </form>
                </div>
                
                <!-- Security Tab -->
                <div x-show="activeTab === 'security'" class="glass-card rounded-xl p-6 md:p-8 shadow-lg">
                    <form method="POST" action="" class="space-y-6">
                        <div class="max-w-xl mx-auto">
                            <h3 class="text-xl font-semibold text-white mb-6">Change Password</h3>
                            
                            <div class="mb-6">
                                <label for="current_password" class="block text-white text-sm font-medium mb-2">Current Password*</label>
                                <div class="relative">
                                    <input 
                                        type="password" 
                                        id="current_password" 
                                        name="current_password" 
                                        class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter your current password"
                                        required
                                    >
                                    <button 
                                        type="button" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white toggle-password"
                                        data-target="current_password"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="new_password" class="block text-white text-sm font-medium mb-2">New Password*</label>
                                <div class="relative">
                                    <input 
                                        type="password" 
                                        id="new_password" 
                                        name="new_password" 
                                        class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter your new password"
                                        required
                                    >
                                    <button 
                                        type="button" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white toggle-password"
                                        data-target="new_password"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                                <p class="text-gray-400 text-xs mt-1">Minimum 8 characters</p>
                            </div>
                            
                            <div class="mb-6">
                                <label for="confirm_password" class="block text-white text-sm font-medium mb-2">Confirm New Password*</label>
                                <div class="relative">
                                    <input 
                                        type="password" 
                                        id="confirm_password" 
                                        name="confirm_password" 
                                        class="form-input w-full px-4 py-3 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Confirm your new password"
                                        required
                                    >
                                    <button 
                                        type="button" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white toggle-password"
                                        data-target="confirm_password"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="p-4 bg-amber-900/30 border border-amber-600/30 rounded-lg mb-6">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-amber-200">
                                            Changing your password will log you out from all other devices. You'll need to log in again with your new password.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <input type="hidden" name="change_password" value="1">
                                <button 
                                    type="submit" 
                                    class="bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out"
                                >
                                    Change Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-8">
                <a href="<?php echo isRenter() ? 'dashboard-renter.php' : 'dashboard-customer.php'; ?>" class="inline-flex items-center text-primary hover:text-blue-400 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Dashboard
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
            
            // Profile picture preview
            const profilePicInput = document.getElementById('profile_picture');
            const profilePreview = document.getElementById('profile-preview');
            
            if (profilePicInput && profilePreview) {
                profilePicInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            profilePreview.src = e.target.result;
                        }
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
                
                // Trigger file input when clicking on the preview image
                const profilePictureContainer = document.querySelector('.profile-picture-hover');
                
                if (profilePictureContainer) {
                    profilePictureContainer.addEventListener('click', function() {
                        profilePicInput.click();
                    });
                }
            }
            
            // Toggle password visibility
            const toggleButtons = document.querySelectorAll('.toggle-password');
            
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const inputField = document.getElementById(targetId);
                    
                    if (inputField.type === 'password') {
                        inputField.type = 'text';
                        this.innerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        `;
                    } else {
                        inputField.type = 'password';
                        this.innerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        `;
                    }
                });
            });
        });
    </script>
</body>
</html>