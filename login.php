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

// Initialize variables
$error = '';
$success = '';
$email = '';
$login_attempts = 0;

// Check for brute force
$ip = $security->getClientIP();
$identifier = $ip . '_login';
if ($security->checkBruteForce($identifier, 'login')) {
    $error = "Too many login attempts. Please try again in 15 minutes.";
    $security->logSecurityEvent('BRUTE_FORCE_BLOCKED', 'Login attempts exceeded for IP: ' . $ip);
}

// Handle test login (for development/testing only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
    // Get test user from database
    $db->prepare("
        SELECT id, email, role_id, username 
        FROM users 
        WHERE email = :email 
        LIMIT 1
    ");
    $db->bind(':email', 'student@test.edu');
    $test_user = $db->single();
    
    if ($test_user) {
        // Set session for test user
        $_SESSION['user_id'] = $test_user['id'];
        $_SESSION['email'] = $test_user['email'];
        $_SESSION['role_id'] = $test_user['role_id'];
        $_SESSION['username'] = $test_user['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION['ip_address'] = $ip;
        
        // Log test login
        $security->logSecurityEvent('TEST_LOGIN_USED', 'Test login accessed from IP: ' . $ip);
        
        // Redirect to student dashboard
        $functions->redirect('dashboard/student/', 'Welcome! You are logged in as a test student.', 'info');
    } else {
        $error = "Test account not found. Please create test@student.edu account first.";
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Validate CSRF token
    if (!$security->validateCSRFToken($_POST['csrf_token'], 'login')) {
        $error = "Security token invalid. Please refresh the page and try again.";
        $security->logSecurityEvent('CSRF_FAILED', 'Login form CSRF validation failed');
    }
    
    // Check honeypot
    if (!$security->checkHoneyPot($_POST)) {
        $error = "Invalid request detected.";
        $security->logSecurityEvent('HONEYPOT_TRIGGERED', 'Login form honeypot triggered');
    }
    
    // Rate limiting
    if (!$security->rateLimit('login', $ip, 5, 900)) {
        $error = "Too many login attempts. Please try again later.";
        $security->logSecurityEvent('RATE_LIMIT_EXCEEDED', 'Login rate limit exceeded for IP: ' . $ip);
    }
    
    if (empty($error)) {
        $email = $security->sanitize($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        // Validate email
        if (!$security->validateEmail($email)) {
            $error = "Please enter a valid email address.";
        }
        
        // Validate password
        if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        }
        
        if (empty($error)) {
            // Check user credentials
            $db->prepare("
                SELECT u.id, u.email, u.password, u.role_id, u.status, u.login_attempts, 
                       u.locked_until, p.first_name, p.last_name, p.university_id
                FROM users u 
                LEFT JOIN user_profiles p ON u.id = p.user_id 
                WHERE u.email = :email OR u.university_id = :email
                LIMIT 1
            ");
            $db->bind(':email', $email);
            $user = $db->single();
            
            if ($user) {
                // Check if account is locked
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error = "Account is locked. Please try again later.";
                    $security->logSecurityEvent('ACCOUNT_LOCKED', 'Login attempt to locked account: ' . $email);
                }
                
                // Check if account is active
                elseif ($user['status'] !== 'active') {
                    $error = "Your account is " . $user['status'] . ". Please contact administrator.";
                    $security->logSecurityEvent('INACTIVE_ACCOUNT_LOGIN', 'Login attempt to inactive account: ' . $email);
                }
                
                // Verify password
                elseif (password_verify($password, $user['password'])) {
                    // Reset login attempts on successful login
                    $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = :id");
                    $db->bind(':id', $user['id']);
                    $db->execute();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['first_name'] = $user['first_name'] ?? '';
                    $_SESSION['last_name'] = $user['last_name'] ?? '';
                    $_SESSION['university_id'] = $user['university_id'] ?? '';
                    $_SESSION['login_time'] = time();
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Set remember me cookie if requested
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        $db->prepare("
                            INSERT INTO remember_tokens (user_id, token, expires_at) 
                            VALUES (:user_id, :token, :expires_at)
                        ");
                        $db->bind(':user_id', $user['id']);
                        $db->bind(':token', hash('sha256', $token));
                        $db->bind(':expires_at', date('Y-m-d H:i:s', $expiry));
                        $db->execute();
                        
                        setcookie('remember_token', $token, $expiry, '/', '', true, true);
                    }
                    
                    // Log successful login
                    $security->logSecurityEvent('LOGIN_SUCCESS', 'User logged in successfully', $user['id']);
                    $functions->logActivity($user['id'], 'LOGIN', 'User logged in from IP: ' . $ip);
                    
                    // Redirect based on role
                    switch ($user['role_id']) {
                        case 1: // Super Admin
                        case 2: // Admin
                            $functions->redirect('dashboard/admin/');
                            break;
                        case 3: // Student
                            $functions->redirect('dashboard/student/');
                            break;
                        case 4: // Driver
                            $functions->redirect('dashboard/driver/');
                            break;
                        case 5: // Faculty
                            $functions->redirect('dashboard/faculty/');
                            break;
                        default:
                            $functions->redirect('dashboard/');
                    }
                } else {
                    // Increment failed login attempts
                    $login_attempts = $user['login_attempts'] + 1;
                    
                    $db->prepare("
                        UPDATE users 
                        SET login_attempts = :attempts, 
                            last_attempt = NOW(),
                            locked_until = CASE 
                                WHEN :attempts >= :max_attempts THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                ELSE locked_until 
                            END
                        WHERE id = :id
                    ");
                    $db->bind(':attempts', $login_attempts);
                    $db->bind(':max_attempts', MAX_LOGIN_ATTEMPTS);
                    $db->bind(':id', $user['id']);
                    $db->execute();
                    
                    $error = "Invalid email or password. Attempts remaining: " . (MAX_LOGIN_ATTEMPTS - $login_attempts);
                    $security->logSecurityEvent('LOGIN_FAILED', 'Invalid credentials for email: ' . $email);
                }
            } else {
                // User not found - generic error for security
                $error = "Invalid email or password.";
                $security->logSecurityEvent('LOGIN_FAILED_UNKNOWN_USER', 'Login attempt with non-existent email: ' . $email);
            }
        }
    }
}

// Generate CSRF token for login form
$csrf_token = $security->generateCSRFToken('login');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to University Bus Management System">
    <meta name="author" content="University Bus System">
    
    <title>Login - <?php echo APP_NAME; ?></title>
    
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 60px;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            margin-bottom: 0;
        }
        
        .login-logo {
            font-size: 3rem;
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .input-group-text {
            background: transparent;
            border: 2px solid #e0e0e0;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .btn-login {
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
            border: none;
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .login-footer a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .honeypot {
            position: absolute;
            left: -9999px;
        }
        
        .security-notice {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .security-notice i {
            color: var(--secondary-color);
            margin-right: 10px;
        }
        
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 20px;
            }
            
            body {
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
            <div class="navbar-nav ms-auto">
                <a href="index.php" class="nav-link"><i class="fas fa-home me-1"></i> Home</a>
                <a href="register.php" class="nav-link"><i class="fas fa-user-plus me-1"></i> Register</a>
            </div>
        </div>
    </nav>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-bus"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php echo $functions->displayFlashMessage(); ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="honeypot">
                    <input type="text" name="homepage" id="homepage" tabindex="-1">
                    <input type="email" name="email_confirmation" id="email_confirmation" tabindex="-1">
                </div>
                
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-4">
                    <label for="email" class="form-label fw-bold">Email or University ID</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required autocomplete="username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label fw-bold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               required autocomplete="current-password">
                        <button type="button" class="input-group-text" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label" for="remember">
                            Remember me for 30 days
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <button type="submit" name="login" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                    </button>
                </div>
                
                <div class="login-footer">
                    <a href="forgot-password.php" class="d-block mb-2">
                        <i class="fas fa-key me-1"></i> Forgot Password?
                    </a>
                    <p class="mb-0">
                        Don't have an account? 
                        <a href="register.php" class="fw-bold">Register here</a>
                    </p>
                </div>
            </form>

            <!-- TEST LOGIN (Development Only) -->
            <hr class="my-4">
            <div class="alert alert-info mb-3">
                <i class="fas fa-flask me-2"></i> <strong>Quick Test:</strong> Click below to test the student dashboard
            </div>
            <form method="POST" action="">
                <button type="submit" name="test_login" class="btn btn-outline-primary w-100">
                    <i class="fas fa-user-graduate me-2"></i> Test Student Login
                </button>
            </form>
            
            <div class="security-notice">
                <p class="mb-0">
                    <i class="fas fa-shield-alt"></i>
                    <small>Your login is secured with SSL encryption and protected against brute force attacks.</small>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Login Script -->
    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('#togglePassword').click(function() {
                const passwordField = $('#password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });
            
            // Form validation
            $('#loginForm').submit(function(e) {
                const email = $('#email').val().trim();
                const password = $('#password').val().trim();
                
                if (!email) {
                    e.preventDefault();
                    alert('Please enter your email or university ID.');
                    $('#email').focus();
                    return false;
                }
                
                if (!password) {
                    e.preventDefault();
                    alert('Please enter your password.');
                    $('#password').focus();
                    return false;
                }
                
                // Add loading state
                $('button[name="login"]').html('<i class="fas fa-spinner fa-spin me-2"></i> Signing in...');
                $('button[name="login"]').prop('disabled', true);
                
                return true;
            });
            
            // Auto-capitalize university IDs
            $('#email').on('input', function() {
                const val = $(this).val();
                if (val.match(/^[A-Z]/)) {
                    $(this).val(val.toUpperCase());
                }
            });
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Security: Disable right-click
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
            
            // Auto-focus on email field
            $('#email').focus();
        });
    </script>
    
    <!-- Security Script -->
    <script>
        // Track invalid password attempts
        let invalidAttempts = 0;
        const maxAttempts = 3;
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const passwordField = document.getElementById('password');
            
            form.addEventListener('submit', function(e) {
                // Additional client-side validation
                if (passwordField.value.length < 6) {
                    e.preventDefault();
                    invalidAttempts++;
                    
                    if (invalidAttempts >= maxAttempts) {
                        // Trigger additional security measures
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    }
                    
                    return false;
                }
                
                // Add timestamp to prevent replay attacks
                const timestamp = Date.now();
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'login_timestamp';
                hiddenInput.value = timestamp;
                form.appendChild(hiddenInput);
                
                return true;
            });
        });
    </script>
</body>
</html>
<?php
// Log page access
$security->logSecurityEvent('LOGIN_PAGE_ACCESS', 'Login page accessed from IP: ' . $ip);
?>