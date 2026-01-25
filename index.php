<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

// Start secure session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $functions->redirect('dashboard/');
}

// Handle honeypot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    if (!$security->checkHoneyPot($_POST)) {
        $functions->redirect('index.php', 'Invalid request detected.', 'danger');
    }
    
    // Process subscription (placeholder)
    // In production, add to newsletter database
    $functions->redirect('index.php', 'Thank you for subscribing to our newsletter!', 'success');
}

// Generate CSRF token for subscription form
$csrf_token = $security->generateCSRFToken('subscription');

// Log page visit
$security->logSecurityEvent('PAGE_VISIT', 'Landing page accessed');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="University Bus Management System - Efficient transportation management for educational institutions">
    <meta name="keywords" content="bus management, university, transportation, student, admin">
    <meta name="author" content="University Bus System">
    <meta name="robots" content="index, follow">
    
    <title><?php echo APP_NAME; ?> - Efficient Campus Transportation</title>
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .hero-section {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.8)), url('assets/images/bus-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            border-radius: 0 0 30px 30px;
            margin-bottom: 50px;
        }
        
        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 3.5rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
            border: none;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            font-size: 3rem;
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-outline-custom {
            border: 2px solid white;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-custom:hover {
            background: white;
            color: var(--primary-color);
        }
        
        .stats-section {
            background: var(--light-color);
            padding: 60px 0;
            border-radius: 30px;
            margin: 50px 0;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1.1rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .footer {
            background: var(--primary-color);
            color: white;
            padding: 60px 0 20px;
            margin-top: 50px;
        }
        
        .social-icons a {
            color: white;
            font-size: 1.5rem;
            margin: 0 10px;
            transition: color 0.3s ease;
        }
        
        .social-icons a:hover {
            color: var(--secondary-color);
        }
        
        .copyright {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 20px;
            margin-top: 40px;
        }
        
        .login-box {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        
        .honeypot {
            position: absolute;
            left: -9999px;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-section {
                padding: 60px 0;
            }
            
            .feature-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: rgba(44, 62, 80, 0.95);">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="fas fa-bus me-2" style="color: #3498db;"></i>
                <span class="fw-bold"><?php echo APP_NAME; ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php"><i class="fas fa-home me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features"><i class="fas fa-star me-1"></i> Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact"><i class="fas fa-phone me-1"></i> Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-custom ms-2" href="register.php">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">Smart Campus Transportation Management</h1>
                    <p class="hero-subtitle">
                        Efficiently manage university bus services, student transportation, 
                        fee collection, and seat bookings with our comprehensive system.
                    </p>
                    <div class="mt-4">
                        <a href="register.php" class="btn btn-primary-custom me-3">
                            <i class="fas fa-user-plus me-2"></i> Get Started
                        </a>
                        <a href="#features" class="btn btn-outline-custom">
                            <i class="fas fa-play-circle me-2"></i> Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="assets/images/bus-illustration.svg" alt="Bus Illustration" class="img-fluid" style="max-height: 400px;">
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section" id="stats">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-number" id="busCount">0</div>
                    <div class="stat-label">Buses Managed</div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-number" id="studentCount">0</div>
                    <div class="stat-label">Students Served</div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-number" id="routeCount">0</div>
                    <div class="stat-label">Active Routes</div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-number" id="successRate">99%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <h2 class="fw-bold mb-3" style="color: var(--primary-color);">Powerful Features</h2>
                    <p class="lead">Everything you need for efficient bus management</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="text-center">
                            <i class="fas fa-qrcode feature-icon"></i>
                            <h4 class="fw-bold mb-3">QR Code System</h4>
                            <p>Secure student identification and attendance tracking with unique QR codes for each student.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="text-center">
                            <i class="fas fa-bus feature-icon"></i>
                            <h4 class="fw-bold mb-3">Multi-Bus Management</h4>
                            <p>Manage multiple buses with different routes, schedules, and seating capacities.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="text-center">
                            <i class="fas fa-money-check-alt feature-icon"></i>
                            <h4 class="fw-bold mb-3">Fee Management</h4>
                            <p>Automated fee collection, tracking, and reporting for monthly/bi-monthly payments.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="text-center">
                            <i class="fas fa-shield-alt feature-icon"></i>
                            <h4 class="fw-bold mb-3">High Security</h4>
                            <p>Advanced security features including encryption, fraud detection, and activity logging.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="text-center">
                            <i class="fas fa-mobile-alt feature-icon"></i>
                            <h4 class="fw-bold mb-3">Digital ID Cards</h4>
                            <p>Generate and manage digital ID cards with photos and personal information.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="text-center">
                            <i class="fas fa-chart-bar feature-icon"></i>
                            <h4 class="fw-bold mb-3">Real-time Reports</h4>
                            <p>Generate detailed reports on attendance, fees, and bus utilization.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Subscription Section -->
    <section id="contact" class="py-5" style="background: var(--light-color);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="login-box">
                        <h3 class="text-center mb-4" style="color: var(--primary-color);">Stay Updated</h3>
                        <p class="text-center mb-4">Subscribe to our newsletter for updates and announcements.</p>
                        
                        <?php echo $functions->displayFlashMessage(); ?>
                        
                        <form method="POST" action="">
                            <div class="honeypot">
                                <input type="text" name="website" id="website" tabindex="-1">
                                <input type="email" name="email_confirmation" id="email_confirmation" tabindex="-1">
                            </div>
                            
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    I agree to receive updates and announcements
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="subscribe" class="btn btn-primary-custom">
                                    <i class="fas fa-paper-plane me-2"></i> Subscribe Now
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h4 class="mb-3">
                        <i class="fas fa-bus me-2"></i> <?php echo APP_NAME; ?>
                    </h4>
                    <p>Efficient campus transportation management system for universities and educational institutions.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-light text-decoration-none mb-2 d-block"><i class="fas fa-chevron-right me-2"></i> Home</a></li>
                        <li><a href="login.php" class="text-light text-decoration-none mb-2 d-block"><i class="fas fa-chevron-right me-2"></i> Login</a></li>
                        <li><a href="register.php" class="text-light text-decoration-none mb-2 d-block"><i class="fas fa-chevron-right me-2"></i> Register</a></li>
                        <li><a href="#features" class="text-light text-decoration-none mb-2 d-block"><i class="fas fa-chevron-right me-2"></i> Features</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> University Road, Abbottabad</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> +92 123 4567890</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> info@busmanagement.edu</li>
                        <li class="mb-2"><i class="fas fa-clock me-2"></i> Mon-Fri: 9:00 AM - 5:00 PM</li>
                    </ul>
                </div>
            </div>
            
            <div class="row copyright">
                <div class="col-12 text-center">
                    <p class="mb-0">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved. 
                        <span class="text-muted">Version <?php echo APP_VERSION; ?></span>
                    </p>
                    <small class="text-muted">
                        <i class="fas fa-lock me-1"></i> Secure SSL Connection | 
                        <i class="fas fa-shield-alt ms-2 me-1"></i> Data Protected
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Counter Animation -->
    <script>
        $(document).ready(function() {
            // Animate statistics
            function animateCounter(elementId, start, end, duration) {
                let obj = document.getElementById(elementId);
                let startTime = null;
                
                function step(timestamp) {
                    if (!startTime) startTime = timestamp;
                    const progress = Math.min((timestamp - startTime) / duration, 1);
                    const value = Math.floor(progress * (end - start) + start);
                    obj.innerHTML = value;
                    
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                }
                window.requestAnimationFrame(step);
            }
            
            // Start counters when in viewport
            function startCounters() {
                animateCounter('busCount', 0, 25, 2000);
                animateCounter('studentCount', 0, 1500, 2000);
                animateCounter('routeCount', 0, 12, 2000);
            }
            
            // Start counters after page load
            setTimeout(startCounters, 1000);
            
            // Security: Disable right-click and F12
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
            
            document.addEventListener('keydown', function(e) {
                // Disable F12
                if(e.keyCode === 123) {
                    e.preventDefault();
                }
                // Disable Ctrl+Shift+I
                if(e.ctrlKey && e.shiftKey && e.keyCode === 73) {
                    e.preventDefault();
                }
                // Disable Ctrl+U
                if(e.ctrlKey && e.keyCode === 85) {
                    e.preventDefault();
                }
            });
            
            // Auto-hide navbar on scroll
            let prevScrollpos = window.pageYOffset;
            window.onscroll = function() {
                let currentScrollPos = window.pageYOffset;
                if (prevScrollpos > currentScrollPos) {
                    document.querySelector(".navbar").style.top = "0";
                } else {
                    document.querySelector(".navbar").style.top = "-80px";
                }
                prevScrollpos = currentScrollPos;
            }
        });
    </script>
    
    <!-- Security Script -->
    <script>
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Track page visibility for security
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('Page is hidden - potential tab switching detected');
            }
        });
    </script>
</body>
</html>
<?php
// Log successful page load
$security->logSecurityEvent('PAGE_LOAD_SUCCESS', 'Landing page loaded successfully');
?>