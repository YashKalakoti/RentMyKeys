<?php
session_start();
require_once "includes/functions.php";
include 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - RentMyKeys</title>
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
        
        /* Timeline */
        .timeline-container {
            position: relative;
        }
        
        .timeline-container::after {
            content: '';
            position: absolute;
            width: 4px;
            background: linear-gradient(to bottom, #3B82F6, #8B5CF6);
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -2px;
        }
        
        .timeline-item {
            position: relative;
            width: 50%;
            padding: 20px 40px;
        }
        
        .timeline-item:nth-child(odd) {
            left: 0;
        }
        
        .timeline-item:nth-child(even) {
            left: 50%;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            right: -10px;
            top: 30px;
            background: linear-gradient(135deg, #3B82F6, #8B5CF6);
            border-radius: 50%;
            z-index: 1;
        }
        
        .timeline-item:nth-child(even)::after {
            left: -10px;
        }
        
        @media screen and (max-width: 768px) {
            .timeline-container::after {
                left: 30px;
            }
            
            .timeline-item {
                width: 100%;
                padding-left: 70px;
                padding-right: 20px;
            }
            
            .timeline-item:nth-child(odd),
            .timeline-item:nth-child(even) {
                left: 0;
            }
            
            .timeline-item::after,
            .timeline-item:nth-child(even)::after {
                left: 20px;
            }
        }
    </style>
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
                        <a href="about.php" class="text-white hover:text-primary px-3 py-2 rounded-md text-sm font-medium relative after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-full after:bg-primary after:transition-all after:duration-300">About</a>
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
                <a href="about.php" class="text-white block px-3 py-2 rounded-md text-base font-medium bg-primary/20">About</a>
                <a href="contact.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-primary/20 transition duration-300">Contact</a>
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
                <div class="relative h-80 overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-primary/30 to-accent/30 mix-blend-overlay"></div>
                    <img class="w-full h-full object-cover" src="assets/images/about-banner.png" alt="About RentMyKeys">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full px-8 md:px-16">
                            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4 drop-shadow-lg">About RentMyKeys</h1>
                            <p class="text-xl text-white max-w-3xl drop-shadow-lg">Connecting people with vehicles, creating opportunities and adventures.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Story Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-2xl p-8 md:p-12 shadow-lg">
                <div class="flex flex-col md:flex-row items-center gap-12">
                    <div class="md:w-1/2">
                        <h2 class="text-3xl font-bold gradient-text mb-6">Our Story</h2>
                        <p class="text-gray-300 mb-6">RentMyKeys was founded in 2024 with a simple mission: to make vehicle rental easier, more affordable, and more accessible for everyone. We saw a gap in the market where traditional rental companies were charging high fees, had complicated processes, and limited vehicle options.</p>
                        <p class="text-gray-300 mb-6">Our platform was built to connect vehicle owners who have idle vehicles with people who need transportation. This peer-to-peer model allows owners to earn extra income and provides renters with more options at better prices.</p>
                        <p class="text-gray-300">Today, we're proud to facilitate thousands of rentals across the country, helping people get where they need to go while creating economic opportunities for vehicle owners.</p>
                    </div>
                    <div class="md:w-1/2 relative">
                        <div class="absolute -top-5 -left-5 w-20 h-20 rounded-full bg-gradient-to-br from-primary/30 to-transparent"></div>
                        <div class="absolute -bottom-5 -right-5 w-20 h-20 rounded-full bg-gradient-to-br from-accent/30 to-transparent"></div>
                        <div class="relative rounded-xl overflow-hidden shadow-2xl border border-gray-700/50">
                            <img src="assets/images/pic.jpg" alt="Our Story" class="w-full h-auto transform hover:scale-105 transition-transform duration-500">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Vision & Mission Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="glass-card rounded-2xl p-8 md:p-10 shadow-lg transform hover:scale-[1.01] transition-all duration-300">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary/30 to-primary/10 rounded-full flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold gradient-text mb-4">Our Vision</h2>
                    <p class="text-gray-300 mb-4">We envision a world where accessing a vehicle is as easy as booking a hotel room – affordable, convenient, and personalized to your exact needs.</p>
                    <p class="text-gray-300">By leveraging technology and community, we're creating a more efficient use of transportation resources, reducing the need for excess vehicle production and ownership, and contributing to a more sustainable future.</p>
                </div>
                
                <div class="glass-card rounded-2xl p-8 md:p-10 shadow-lg transform hover:scale-[1.01] transition-all duration-300">
                    <div class="w-16 h-16 bg-gradient-to-br from-accent/30 to-accent/10 rounded-full flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold gradient-text mb-4">Our Mission</h2>
                    <p class="text-gray-300 mb-4">Our mission is to connect vehicle owners with people who need transportation, creating economic opportunities and making mobility more accessible for everyone.</p>
                    <p class="text-gray-300">We're committed to building a platform that is secure, easy to use, and beneficial for both renters and owners, with transparent pricing, comprehensive insurance, and exceptional customer support.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Meet the Founders Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-2xl p-8 md:p-12 shadow-lg">
                <h2 class="text-3xl font-bold gradient-text mb-12 text-center">Meet the Founders</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-16">
                    <!-- Founder 1 -->
                    <div class="flex flex-col items-center">
                        <div class="relative">
                            <div class="absolute inset-0 rounded-full bg-gradient-to-r from-primary to-accent blur-md opacity-70 -z-10 transform scale-110"></div>
                            <img src="assets/images/founder1.jpg" alt="Adarsh Dixit" class="w-40 h-40 object-cover rounded-full border-4 border-gray-800/50">
                        </div>
                        <h3 class="text-2xl font-bold text-white mt-6">Adarsh Dixit</h3>
                        <p class="text-lg text-primary mb-4">Co-Founder & CTO</p>
                        <p class="text-gray-300 text-center mb-6">With a background in transportation technology and a passion for sharing economy businesses, Adarsh leads our strategic vision and business development.</p>
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
                            <a href="https://www.linkedin.com/in/adarshdixit77/" class="text-gray-400 hover:text-primary transition-colors duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm-2 16h-2v-6h2v6zm-1-6.891c-.607 0-1.1-.496-1.1-1.109 0-.612.492-1.109 1.1-1.109s1.1.497 1.1 1.109c0 .613-.493 1.109-1.1 1.109zm8 6.891h-1.998v-2.861c0-1.881-2.002-1.722-2.002 0v2.861h-2v-6h2v1.093c.872-1.616 4-1.736 4 1.548v3.359z" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Founder 2 -->
                    <div class="flex flex-col items-center">
                        <div class="relative">
                            <div class="absolute inset-0 rounded-full bg-gradient-to-r from-accent to-primary blur-md opacity-70 -z-10 transform scale-110"></div>
                            <img src="assets/images/founder2.jpeg" alt="Yash Kalakoti" class="w-40 h-40 object-cover rounded-full border-4 border-gray-800/50">
                        </div>
                        <h3 class="text-2xl font-bold text-white mt-6">Yash Kalakoti</h3>
                        <p class="text-lg text-accent mb-4">Co-Founder & CEO</p>
                        <p class="text-gray-300 text-center mb-6">A technology innovator with expertise in software development and UI/UX design, Yash oversees our platform development and technical operations.</p>
                        <div class="flex space-x-4">
                            <a href="#" class="text-gray-400 hover:text-accent transition-colors duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z" />
                                </svg>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-accent transition-colors duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6.066 9.645c.183 4.04-2.83 8.544-8.164 8.544-1.622 0-3.131-.476-4.402-1.291 1.524.18 3.045-.244 4.252-1.189-1.256-.023-2.317-.854-2.684-1.995.451.086.895.061 1.298-.049-1.381-.278-2.335-1.522-2.304-2.853.388.215.83.344 1.301.359-1.279-.855-1.641-2.544-.889-3.835 1.416 1.738 3.533 2.881 5.92 3.001-.419-1.796.944-3.527 2.799-3.527.825 0 1.572.349 2.096.907.654-.128 1.27-.368 1.824-.697-.215.671-.67 1.233-1.263 1.589.581-.07 1.135-.224 1.649-.453-.384.578-.87 1.084-1.433 1.489z" />
                                </svg>
                            </a>
                            <a href="https://www.linkedin.com/in/yashkalakoti/" class="text-gray-400 hover:text-accent transition-colors duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm-2 16h-2v-6h2v6zm-1-6.891c-.607 0-1.1-.496-1.1-1.109 0-.612.492-1.109 1.1-1.109s1.1.497 1.1 1.109c0 .613-.493 1.109-1.1 1.109zm8 6.891h-1.998v-2.861c0-1.881-2.002-1.722-2.002 0v2.861h-2v-6h2v1.093c.872-1.616 4-1.736 4 1.548v3.359z" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Journey Timeline Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-2xl p-8 md:p-12 shadow-lg">
                <h2 class="text-3xl font-bold gradient-text mb-12 text-center">Our Journey</h2>
                
                <div class="timeline-container">
                    <div class="timeline-item">
                        <div class="glass-card rounded-xl p-6 shadow-md">
                            <h3 class="text-xl font-bold text-white mb-2">2024</h3>
                            <p class="text-gray-300">RentMyKeys was created by Adarsh Dixit and Yash Kalakoti with a vision to revolutionize vehicle rentals.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="glass-card rounded-xl p-6 shadow-md">
                            <h3 class="text-xl font-bold text-white mb-2">2024, May</h3>
                            <p class="text-gray-300">Worked on maintainability and Regulations of the Company</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="glass-card rounded-xl p-6 shadow-md">
                            <h3 class="text-xl font-bold text-white mb-2">2024, Dec</h3>
                            <p class="text-gray-300">Finalised all the features and permission required for rentmykeys</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="glass-card rounded-xl p-6 shadow-md">
                            <h3 class="text-xl font-bold text-white mb-2">2025, Feb</h3>
                            <p class="text-gray-300">Made the first prototype of our website</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="glass-card rounded-xl p-6 shadow-md">
                            <h3 class="text-xl font-bold text-white mb-2">2025, April</h3>
                            <p class="text-gray-300">Made the first working model of the website as a project</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Values Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <h2 class="text-3xl font-bold gradient-text mb-12 text-center">Our Values</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="glass-card rounded-xl p-6 shadow-lg transform hover:scale-[1.03] transition-all duration-300">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary/30 to-primary/10 rounded-full flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Community</h3>
                    <p class="text-gray-300">We believe in the power of connecting people and creating a trusted community of vehicle owners and renters.</p>
                </div>
                
                <div class="glass-card rounded-xl p-6 shadow-lg transform hover:scale-[1.03] transition-all duration-300">
                    <div class="w-16 h-16 bg-gradient-to-br from-accent/30 to-accent/10 rounded-full flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Trust & Safety</h3>
                    <p class="text-gray-300">We prioritize the safety of our users with comprehensive insurance coverage, secure payment processing, and user verification.</p>
                </div>
                
                <div class="glass-card rounded-xl p-6 shadow-lg transform hover:scale-[1.03] transition-all duration-300">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary/30 to-primary/10 rounded-full flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Fairness</h3>
                    <p class="text-gray-300">We ensure transparent pricing, fair policies, and equitable opportunities for all users of our platform.</p>
                </div>
                
                <div class="glass-card rounded-xl p-6 shadow-lg transform hover:scale-[1.03] transition-all duration-300">
                    <div class="w-16 h-16 bg-gradient-to-br from-accent/30 to-accent/10 rounded-full flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Innovation</h3>
                    <p class="text-gray-300">We continuously improve our platform with new features and technology to provide the best experience for our users.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact CTA Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="glass-card rounded-2xl p-8 md:p-12 shadow-lg bg-gradient-to-br from-primary/20 to-accent/20">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="md:w-2/3 mb-8 md:mb-0">
                        <h2 class="text-3xl font-bold text-white mb-4">Have Any Questions?</h2>
                        <p class="text-gray-300 text-lg">We'd love to hear from you! Feel free to reach out with any questions, feedback, or partnership opportunities.</p>
                    </div>
                    <div class="md:w-1/3 flex justify-center md:justify-end">
                        <a href="contact.php" class="bg-gradient-to-r from-primary to-accent text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 inline-flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Contact Us
                        </a>
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
                            <span class="text-gray-300">Lovely Professional University , Phagwara</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span class="text-gray-300">+91 9027973734</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span class="text-gray-300">yashkalakotibackup@gmail.com</span>
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