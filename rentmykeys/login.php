
<?php
session_start();
require_once "includes/functions.php";
include 'db.php';




// Redirect if already logged in








$errors = [];




if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize($_POST['email']);
    $password = $_POST['password'];




    // Validate input
    if (empty($username)) {
        $errors[] = "Email is required";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    }




    // Attempt login
    if (empty($errors)) {
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();




        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                
                // Redirect based on user type
                $redirect = ($user['user_type'] == 'renter') ? "dashboard-renter.php" : "dashboard-customer.php";
                header("Location: $redirect");
                exit();
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RentMyKeys</title>
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
        /* Enhanced Glass-like styling for login page */
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
        
        .glow-3 {
            top: 40%;
            right: 10%;
            background-color: rgba(139, 92, 246, 0.15);
            animation: pulse 12s infinite alternate;
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
        
        .glass-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.6) 0%, rgba(59, 130, 246, 0.15) 100%);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .gradient-text {
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }




        .gradient-border {
            position: relative;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .gradient-border::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #3B82F6, #8B5CF6, #3B82F6);
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




<body class="bg-black text-white min-h-screen">




    <div id="page-loader" class="page-loader">
        <div class="loader-content">
            <div class="mx-auto w-24 h-24 mb-4 flex justify-center items-center">
                <img src="assets/images/lol.png" alt="RentMyKeys Logo" class="spinning-logo w-full h-auto">
            </div>
            <p>Loading...</p>
        </div>
    </div>




    <div class="fixed inset-0 z-0 overflow-hidden opacity-20">
        <img src="assets/images/background.png" alt="Background" class="w-[120%] h-[120%] object-cover filter blur-sm background-animation absolute -top-[10%] -left-[10%]">
    </div>
    
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>
    <div class="glow glow-3"></div>




    <!-- Navigation -->
    <div class="relative z-20 w-full p-6">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="flex items-center group">
                <img class="h-16 w-auto ml-10 transition transform group-hover:scale-110" src="assets/images/lol.png" alt="RentMyKeys">
            </a>
            
            <a href="index.php" class="nav-button px-6 py-2 rounded-full text-white font-medium flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-14 0l2 2m0 0l7 7 7-7m-14 0l2-2" />
                </svg>
                Home
            </a>
        </div>
    </div>




    <!-- Main content -->
    <div class="relative z-10 min-h-[calc(100vh-120px)] flex items-center justify-center p-4">
        <div class="w-full max-w-6xl">
            <!-- Card container -->
            <div class="glass-card rounded-3xl overflow-hidden">
                <div class="flex flex-col md:flex-row">
                    <!-- Left side: Vehicle Image -->
                    <div class="md:w-1/2 p-8 flex items-center justify-center">
                        <img src="assets/images/car.png" alt="Luxury Car" class="max-w-full max-h-100 md:max-h-100 animate-float">
                    </div>
                    
                    <!-- Right side: Login Form -->
                    <div class="md:w-1/2 p-8 md:p-12 flex items-center justify-center">
                        <div class="w-full max-w-md">
                            <div class="mb-8">
                                <h2 class="text-3xl font-bold gradient-text mb-2">Sign In</h2>
                                <p class="text-gray-300 mb-8">Welcome back! Please enter your details</p>
                            </div>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="bg-red-900/40 border border-red-500/50 text-white px-4 py-3 rounded-lg mb-6">
                                    <ul class="list-disc list-inside">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="login.php">
                                <div class="mb-6">
                                    <label for="email" class="block text-white text-sm font-medium mb-2">Email or Username</label>
                                    <input 
                                        type="text" 
                                        id="email" 
                                        name="email" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Enter your email or username"
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    >
                                </div>
                                
                                <div class="mb-6">
                                    <label for="password" class="block text-white text-sm font-medium mb-2">Password</label>
                                    <input 
                                        type="password" 
                                        id="password" 
                                        name="password" 
                                        class="w-full px-4 py-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="••••••••"
                                    >
                                </div>
                                
                                <div class="flex justify-between items-center mb-6">
                                    <div class="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            id="remember_me" 
                                            name="remember_me" 
                                            class="h-4 w-4 text-primary focus:ring-primary border-gray-700 rounded bg-gray-900/60"
                                        >
                                        <label for="remember_me" class="ml-2 block text-sm text-gray-300">
                                            Remember me
                                        </label>
                                    </div>
                                    <a href="forgot-password.php" class="text-sm text-primary hover:text-blue-400 transition">
                                        Forgot password?
                                    </a>
                                </div>
                                
                                <div class="gradient-border mb-6">
                                    <button 
                                        type="submit" 
                                        class="w-full bg-gradient-to-r from-primary to-blue-500 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 ease-in-out"
                                    >
                                        Sign In
                                    </button>
                                </div>
                                <div class="relative flex items-center justify-center my-6">
    <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-700/60"></div>
    </div>
    <div class="relative flex justify-center text-sm">
        <span class="px-4 text-gray-400">OR</span>
    </div>
</div>
                                
                                <button 
                                    type="button" 
                                    class="w-full flex items-center justify-center bg-transparent hover:bg-gray-800/40 text-white font-medium py-3 px-4 border border-gray-700/60 rounded-lg transition duration-300 ease-in-out mb-6"
                                >
                                    <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" width="24" height="24" xmlns="http://www.w3.org/2000/svg">
                                        <g transform="matrix(1, 0, 0, 1, 27.009001, -39.238998)">
                                            <path fill="#4285F4" d="M -3.264 51.509 C -3.264 50.719 -3.334 49.969 -3.454 49.239 L -14.754 49.239 L -14.754 53.749 L -8.284 53.749 C -8.574 55.229 -9.424 56.479 -10.684 57.329 L -10.684 60.329 L -6.824 60.329 C -4.564 58.239 -3.264 55.159 -3.264 51.509 Z" />
                                            <path fill="#34A853" d="M -14.754 63.239 C -11.514 63.239 -8.804 62.159 -6.824 60.329 L -10.684 57.329 C -11.764 58.049 -13.134 58.489 -14.754 58.489 C -17.884 58.489 -20.534 56.379 -21.484 53.529 L -25.464 53.529 L -25.464 56.619 C -23.494 60.539 -19.444 63.239 -14.754 63.239 Z" />
                                            <path fill="#FBBC05" d="M -21.484 53.529 C -21.734 52.809 -21.864 52.039 -21.864 51.239 C -21.864 50.439 -21.724 49.669 -21.484 48.949 L -21.484 45.859 L -25.464 45.859 C -26.284 47.479 -26.754 49.299 -26.754 51.239 C -26.754 53.179 -26.284 54.999 -25.464 56.619 L -21.484 53.529 Z" />
                                            <path fill="#EA4335" d="M -14.754 43.989 C -12.984 43.989 -11.404 44.599 -10.154 45.789 L -6.734 42.369 C -8.804 40.429 -11.514 39.239 -14.754 39.239 C -19.444 39.239 -23.494 41.939 -25.464 45.859 L -21.484 48.949 C -20.534 46.099 -17.884 43.989 -14.754 43.989 Z" />
                                        </g>
                                    </svg>
                                    Sign in with Google
                                </button>
                            </form>
                            
                            <p class="text-center text-gray-300 mt-4">
                                Don't have an account? 
                                <a href="register.php" class="text-primary hover:text-blue-400 font-medium transition">
                                    Sign up
                                </a>
                            </p>
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
        });
    </script>
</body>
</html>