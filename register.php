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
$errors = [];
$success = '';
$form_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'university_id' => '',
    'phone' => '',
    'gender' => '',
    'date_of_birth' => '',
    'address' => '',
    'emergency_contact' => '',
    'blood_group' => ''
];

// Check for registration limits
$ip = $security->getClientIP();
$identifier = $ip . '_register';
if ($security->checkBruteForce($identifier, 'register')) {
    $errors[] = "Too many registration attempts. Please try again in 15 minutes.";
    $security->logSecurityEvent('REGISTRATION_LIMIT', 'Registration attempts exceeded for IP: ' . $ip);
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Validate CSRF token
    if (!$security->validateCSRFToken($_POST['csrf_token'], 'register')) {
        $errors[] = "Security token invalid. Please refresh the page and try again.";
        $security->logSecurityEvent('CSRF_FAILED', 'Registration form CSRF validation failed');
    }
    
    // Check honeypot
    if (!$security->checkHoneyPot($_POST)) {
        $errors[] = "Invalid request detected.";
        $security->logSecurityEvent('HONEYPOT_TRIGGERED', 'Registration form honeypot triggered');
    }
    
    // Rate limiting
    if (!$security->rateLimit('register', $ip, 3, 3600)) {
        $errors[] = "Too many registration attempts. Please try again later.";
        $security->logSecurityEvent('RATE_LIMIT_EXCEEDED', 'Registration rate limit exceeded for IP: ' . $ip);
    }
    
    if (empty($errors)) {
        // Collect and sanitize form data
        $form_data['first_name'] = $security->sanitize($_POST['first_name']);
        $form_data['last_name'] = $security->sanitize($_POST['last_name']);
        $form_data['email'] = $security->sanitize($_POST['email']);
        $form_data['university_id'] = strtoupper($security->sanitize($_POST['university_id']));
        $form_data['phone'] = $security->sanitize($_POST['phone']);
        $form_data['gender'] = $security->sanitize($_POST['gender']);
        $form_data['date_of_birth'] = $security->sanitize($_POST['date_of_birth']);
        $form_data['address'] = $security->sanitize($_POST['address']);
        $form_data['emergency_contact'] = $security->sanitize($_POST['emergency_contact']);
        $form_data['blood_group'] = $security->sanitize($_POST['blood_group']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $terms = isset($_POST['terms']);
        
        // Validate required fields
        $required_fields = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'university_id' => 'University ID',
            'phone' => 'Phone Number',
            'gender' => 'Gender',
            'date_of_birth' => 'Date of Birth',
            'password' => 'Password'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($$field) && $field !== 'password') {
                $errors[] = "$label is required.";
            }
        }
        
        // Validate email
        if (!empty($form_data['email']) && !$security->validateEmail($form_data['email'])) {
            $errors[] = "Please enter a valid email address.";
        }
        
        // Check if email exists
        if (!empty($form_data['email']) && $functions->emailExists($form_data['email'])) {
            $errors[] = "Email already registered. Please use a different email.";
        }
        
        // Validate university ID format (example: B22F1181AI056)
        if (!empty($form_data['university_id'])) {
            if (!preg_match('/^[A-Z][0-9]{2}[A-Z][0-9]{4}[A-Z]{2}[0-9]{3}$/', $form_data['university_id'])) {
                $errors[] = "University ID must be in format: B22F1181AI056";
            } elseif ($functions->universityIdExists($form_data['university_id'])) {
                $errors[] = "University ID already registered.";
            }
        }
        
        // Validate phone number
        if (!empty($form_data['phone'])) {
            $phone = $functions->validatePhone($form_data['phone']);
            if (!$phone) {
                $errors[] = "Please enter a valid phone number.";
            } else {
                $form_data['phone'] = $phone;
            }
        }
        
        // Validate emergency contact
        if (!empty($form_data['emergency_contact'])) {
            $emergency_phone = $functions->validatePhone($form_data['emergency_contact']);
            if (!$emergency_phone) {
                $errors[] = "Please enter a valid emergency contact number.";
            } else {
                $form_data['emergency_contact'] = $emergency_phone;
            }
        }
        
        // Validate date of birth (must be at least 16 years old)
        if (!empty($form_data['date_of_birth'])) {
            $dob = strtotime($form_data['date_of_birth']);
            $min_age = strtotime('-16 years');
            if ($dob > $min_age) {
                $errors[] = "You must be at least 16 years old to register.";
            }
        }
        
        // Validate password
        if (!empty($password)) {
            $password_validation = $security->validatePassword($password);
            if ($password_validation !== true) {
                $errors = array_merge($errors, $password_validation);
            }
            
            // Check password confirmation
            if ($password !== $confirm_password) {
                $errors[] = "Passwords do not match.";
            }
        }
        
        // Validate terms acceptance
        if (!$terms) {
            $errors[] = "You must accept the terms and conditions.";
        }
        
        // Validate photo upload
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            list($valid, $upload_errors) = $security->validateFileUpload($_FILES['photo']);
            if (!$valid) {
                $errors = array_merge($errors, $upload_errors);
            }
        } else {
            $errors[] = "Profile photo is required.";
        }
        
        // If no errors, proceed with registration
        if (empty($errors)) {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Generate username
                $username = $functions->generateUsername($form_data['first_name'] . ' ' . $form_data['last_name']);
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Generate verification token
                $verification_token = bin2hex(random_bytes(32));
                
                // Insert user
                $db->prepare("
                    INSERT INTO users (email, username, password, university_id, role_id, status, 
                                      verification_token, created_at, ip_address)
                    VALUES (:email, :username, :password, :university_id, 3, 'pending', 
                           :verification_token, NOW(), :ip_address)
                ");
                $db->bind(':email', $form_data['email']);
                $db->bind(':username', $username);
                $db->bind(':password', $hashed_password);
                $db->bind(':university_id', $form_data['university_id']);
                $db->bind(':verification_token', $verification_token);
                $db->bind(':ip_address', $ip);
                $db->execute();
                
                $user_id = $db->lastInsertId();
                
                // Upload profile photo
                if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $photo_filename = $security->sanitizeFilename($_FILES['photo']['name']);
                    $photo_path = 'uploads/profiles/' . $photo_filename;
                    
                    if (!is_dir(BASE_PATH . '/uploads/profiles')) {
                        mkdir(BASE_PATH . '/uploads/profiles', 0755, true);
                    }
                    
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], BASE_PATH . '/' . $photo_path)) {
                        throw new Exception("Failed to upload profile photo.");
                    }
                }
                
                // Insert user profile
                $db->prepare("
                    INSERT INTO user_profiles (user_id, first_name, last_name, phone, gender, 
                                              date_of_birth, address, emergency_contact, 
                                              blood_group, profile_photo, created_at)
                    VALUES (:user_id, :first_name, :last_name, :phone, :gender, :date_of_birth, 
                           :address, :emergency_contact, :blood_group, :profile_photo, NOW())
                ");
                $db->bind(':user_id', $user_id);
                $db->bind(':first_name', $form_data['first_name']);
                $db->bind(':last_name', $form_data['last_name']);
                $db->bind(':phone', $form_data['phone']);
                $db->bind(':gender', $form_data['gender']);
                $db->bind(':date_of_birth', $form_data['date_of_birth']);
                $db->bind(':address', $form_data['address']);
                $db->bind(':emergency_contact', $form_data['emergency_contact']);
                $db->bind(':blood_group', $form_data['blood_group']);
                $db->bind(':profile_photo', $photo_path);
                $db->execute();
                
                // Create registration approval request
                $db->prepare("
                    INSERT INTO approval_requests (user_id, request_type, status, submitted_at)
                    VALUES (:user_id, 'registration', 'pending', NOW())
                ");
                $db->bind(':user_id', $user_id);
                $db->execute();
                
                // Commit transaction
                $db->commit();
                
                // Log successful registration
                $security->logSecurityEvent('REGISTRATION_SUCCESS', 'User registered successfully', $user_id);
                
                // Send welcome email (in production)
                /*
                $subject = "Welcome to " . APP_NAME;
                $message = "Dear {$form_data['first_name']},<br><br>
                    Thank you for registering with the University Bus Management System.<br>
                    Your account is pending admin approval. You will receive an email once approved.<br><br>
                    Regards,<br>" . APP_NAME . " Team";
                
                $functions->sendEmail($form_data['email'], $subject, $message);
                */
                
                $success = "Registration successful! Your account is pending admin approval. You will receive an email once approved.";
                
                // Clear form data
                $form_data = array_fill_keys(array_keys($form_data), '');
                
            } catch (Exception $e) {
                $db->rollBack();
                
                // Delete uploaded file if exists
                if ($photo_path && file_exists(BASE_PATH . '/' . $photo_path)) {
                    unlink(BASE_PATH . '/' . $photo_path);
                }
                
                $errors[] = "Registration failed. Please try again. Error: " . $e->getMessage();
                $security->logSecurityEvent('REGISTRATION_FAILED', 'Database error: ' . $e->getMessage());
            }
        } else {
            // Log validation errors
            $security->logSecurityEvent('REGISTRATION_VALIDATION_FAILED', 'Validation errors: ' . implode(', ', $errors));
        }
    }
}

// Generate CSRF token for registration form
$csrf_token = $security->generateCSRFToken('register');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for University Bus Management System">
    <meta name="author" content="University Bus System">
    
    <title>Register - <?php echo APP_NAME; ?></title>
    
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
            padding-top: 60px;
        }
        
        .register-container {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        .register-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }
        
        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #666;
            margin-bottom: 0;
        }
        
        .register-logo {
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
        
        .btn-register {
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
            border: none;
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--secondary-color);
            display: none;
            margin: 0 auto 20px;
        }
        
        .photo-upload {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .photo-upload-label {
            cursor: pointer;
            display: inline-block;
            padding: 10px 20px;
            background: var(--light-color);
            border-radius: 10px;
            border: 2px dashed #ccc;
            transition: all 0.3s ease;
        }
        
        .photo-upload-label:hover {
            background: #e9ecef;
            border-color: var(--secondary-color);
        }
        
        .password-strength {
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background 0.3s ease;
        }
        
        .strength-weak { background: #e74c3c; }
        .strength-medium { background: #f39c12; }
        .strength-strong { background: #27ae60; }
        
        .honeypot {
            position: absolute;
            left: -9999px;
        }
        
        .requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .requirements ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .requirements li {
            margin-bottom: 5px;
        }
        
        .requirements li.valid {
            color: #27ae60;
        }
        
        .requirements li.invalid {
            color: #e74c3c;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: #e0e0e0;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 auto 10px;
        }
        
        .step.active .step-number {
            background: var(--secondary-color);
        }
        
        .step.completed .step-number {
            background: #27ae60;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .form-step {
            display: none;
        }
        
        .form-step.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .register-card {
                padding: 30px 20px;
            }
            
            .step-indicator {
                flex-wrap: wrap;
            }
            
            .step {
                flex: 0 0 33.33%;
                margin-bottom: 20px;
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
                <a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
            </div>
        </div>
    </nav>

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="register-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Create Your Account</h2>
                <p>Register to access the bus management system</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="step-indicator">
                <div class="step active" id="step1-indicator">
                    <div class="step-number">1</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step" id="step2-indicator">
                    <div class="step-number">2</div>
                    <div class="step-label">Academic Info</div>
                </div>
                <div class="step" id="step3-indicator">
                    <div class="step-number">3</div>
                    <div class="step-label">Security</div>
                </div>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
                <div class="honeypot">
                    <input type="text" name="website" id="website" tabindex="-1">
                    <input type="text" name="phone_confirmation" id="phone_confirmation" tabindex="-1">
                </div>
                
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <!-- Step 1: Personal Information -->
                <div class="form-step active" id="step1">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label fw-bold">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($form_data['first_name']); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label fw-bold">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($form_data['last_name']); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label fw-bold">Gender *</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $form_data['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $form_data['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $form_data['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="date_of_birth" class="form-label fw-bold">Date of Birth *</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('-16 years')); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label fw-bold">Phone Number *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                                       pattern="[0-9]{10,15}" required>
                            </div>
                            <small class="text-muted">Format: 03001234567</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="emergency_contact" class="form-label fw-bold">Emergency Contact *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" 
                                       value="<?php echo htmlspecialchars($form_data['emergency_contact']); ?>" 
                                       pattern="[0-9]{10,15}" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="blood_group" class="form-label fw-bold">Blood Group</label>
                            <select class="form-select" id="blood_group" name="blood_group">
                                <option value="">Select Blood Group</option>
                                <option value="A+" <?php echo $form_data['blood_group'] == 'A+' ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo $form_data['blood_group'] == 'A-' ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo $form_data['blood_group'] == 'B+' ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo $form_data['blood_group'] == 'B-' ? 'selected' : ''; ?>>B-</option>
                                <option value="O+" <?php echo $form_data['blood_group'] == 'O+' ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo $form_data['blood_group'] == 'O-' ? 'selected' : ''; ?>>O-</option>
                                <option value="AB+" <?php echo $form_data['blood_group'] == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo $form_data['blood_group'] == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="address" class="form-label fw-bold">Address *</label>
                            <textarea class="form-control" id="address" name="address" rows="2" required><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="button" class="btn btn-register" onclick="nextStep(2)">
                                Next <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Academic Information -->
                <div class="form-step" id="step2">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-bold">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                       required>
                            </div>
                            <small class="text-muted">Use your university email if available</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="university_id" class="form-label fw-bold">University ID *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="university_id" name="university_id" 
                                       value="<?php echo htmlspecialchars($form_data['university_id']); ?>" 
                                       pattern="[A-Z][0-9]{2}[A-Z][0-9]{4}[A-Z]{2}[0-9]{3}" 
                                       title="Format: B22F1181AI056" required>
                            </div>
                            <small class="text-muted">Format: B22F1181AI056</small>
                        </div>
                        
                        <div class="col-12 mb-4">
                            <div class="photo-upload">
                                <img id="photoPreview" class="photo-preview" alt="Profile Photo Preview">
                                <label for="photo" class="photo-upload-label">
                                    <i class="fas fa-camera me-2"></i> Upload Profile Photo *
                                </label>
                                <input type="file" class="d-none" id="photo" name="photo" accept="image/*" required>
                                <div class="mt-2">
                                    <small class="text-muted">File must be JPEG, PNG, or GIF. Max 5MB. Square photos work best.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-6">
                            <button type="button" class="btn btn-secondary w-100" onclick="prevStep(1)">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-register" onclick="nextStep(3)">
                                Next <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Security -->
                <div class="form-step" id="step3">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label fw-bold">Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="input-group-text" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter" id="passwordStrength"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label fw-bold">Confirm Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="input-group-text" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-2"></div>
                        </div>
                        
                        <div class="col-12 mb-4">
                            <div class="requirements">
                                <p class="fw-bold mb-2">Password Requirements:</p>
                                <ul>
                                    <li id="req-length" class="invalid">At least 8 characters</li>
                                    <li id="req-uppercase" class="invalid">One uppercase letter</li>
                                    <li id="req-lowercase" class="invalid">One lowercase letter</li>
                                    <li id="req-number" class="invalid">One number</li>
                                    <li id="req-special" class="invalid">One special character</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> 
                                    and <a href="privacy.php" target="_blank">Privacy Policy</a> *
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter">
                                <label class="form-check-label" for="newsletter">
                                    Subscribe to newsletter for updates and announcements
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-6">
                            <button type="button" class="btn btn-secondary w-100" onclick="prevStep(2)">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="submit" name="register" class="btn btn-register">
                                <i class="fas fa-user-plus me-2"></i> Register
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <p class="mb-0">
                    Already have an account? 
                    <a href="login.php" class="fw-bold">Login here</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Registration Script -->
    <script>
        let currentStep = 1;
        const totalSteps = 3;
        
        function nextStep(step) {
            if (validateStep(currentStep)) {
                document.getElementById(`step${currentStep}`).classList.remove('active');
                document.getElementById(`step${currentStep}-indicator`).classList.remove('active');
                
                document.getElementById(`step${step}`).classList.add('active');
                document.getElementById(`step${step}-indicator`).classList.add('active');
                
                if (currentStep < step) {
                    document.getElementById(`step${currentStep}-indicator`).classList.add('completed');
                }
                
                currentStep = step;
                
                // Scroll to top of form
                document.querySelector('.register-card').scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        function prevStep(step) {
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}-indicator`).classList.remove('active');
            
            document.getElementById(`step${step}`).classList.add('active');
            document.getElementById(`step${step}-indicator`).classList.add('active');
            
            currentStep = step;
            
            // Scroll to top of form
            document.querySelector('.register-card').scrollIntoView({ behavior: 'smooth' });
        }
        
        function validateStep(step) {
            let isValid = true;
            
            switch(step) {
                case 1:
                    const requiredFields = ['first_name', 'last_name', 'gender', 'date_of_birth', 'phone', 'emergency_contact', 'address'];
                    requiredFields.forEach(field => {
                        const element = document.getElementById(field);
                        if (!element.value.trim()) {
                            element.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            element.classList.remove('is-invalid');
                        }
                    });
                    
                    // Validate phone numbers
                    const phone = document.getElementById('phone').value;
                    const emergencyContact = document.getElementById('emergency_contact').value;
                    
                    if (!/^[0-9]{10,15}$/.test(phone)) {
                        document.getElementById('phone').classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    if (!/^[0-9]{10,15}$/.test(emergencyContact)) {
                        document.getElementById('emergency_contact').classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    // Validate date of birth (must be at least 16)
                    const dob = new Date(document.getElementById('date_of_birth').value);
                    const minAgeDate = new Date();
                    minAgeDate.setFullYear(minAgeDate.getFullYear() - 16);
                    
                    if (dob > minAgeDate) {
                        document.getElementById('date_of_birth').classList.add('is-invalid');
                        isValid = false;
                        alert('You must be at least 16 years old to register.');
                    }
                    break;
                    
                case 2:
                    const email = document.getElementById('email').value;
                    const universityId = document.getElementById('university_id').value;
                    const photo = document.getElementById('photo').files.length;
                    
                    // Validate email
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        document.getElementById('email').classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    // Validate university ID format
                    const idRegex = /^[A-Z][0-9]{2}[A-Z][0-9]{4}[A-Z]{2}[0-9]{3}$/;
                    if (!idRegex.test(universityId)) {
                        document.getElementById('university_id').classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    // Validate photo
                    if (photo === 0) {
                        alert('Please upload a profile photo.');
                        isValid = false;
                    }
                    break;
            }
            
            if (!isValid) {
                alert('Please fill all required fields correctly before proceeding.');
            }
            
            return isValid;
        }
        
        $(document).ready(function() {
            // Photo preview
            $('#photo').change(function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#photoPreview').attr('src', e.target.result).show();
                    }
                    reader.readAsDataURL(file);
                    
                    // Validate file size
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size must be less than 5MB');
                        $(this).val('');
                        $('#photoPreview').hide();
                    }
                }
            });
            
            // Toggle password visibility
            $('#togglePassword').click(function() {
                const passwordField = $('#password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });
            
            $('#toggleConfirmPassword').click(function() {
                const confirmField = $('#confirm_password');
                const type = confirmField.attr('type') === 'password' ? 'text' : 'password';
                confirmField.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });
            
            // Password strength checker
            $('#password').on('input', function() {
                const password = $(this).val();
                let strength = 0;
                
                // Check length
                if (password.length >= 8) {
                    strength++;
                    $('#req-length').removeClass('invalid').addClass('valid');
                } else {
                    $('#req-length').removeClass('valid').addClass('invalid');
                }
                
                // Check uppercase
                if (/[A-Z]/.test(password)) {
                    strength++;
                    $('#req-uppercase').removeClass('invalid').addClass('valid');
                } else {
                    $('#req-uppercase').removeClass('valid').addClass('invalid');
                }
                
                // Check lowercase
                if (/[a-z]/.test(password)) {
                    strength++;
                    $('#req-lowercase').removeClass('invalid').addClass('valid');
                } else {
                    $('#req-lowercase').removeClass('valid').addClass('invalid');
                }
                
                // Check number
                if (/[0-9]/.test(password)) {
                    strength++;
                    $('#req-number').removeClass('invalid').addClass('valid');
                } else {
                    $('#req-number').removeClass('valid').addClass('invalid');
                }
                
                // Check special character
                if (/[^A-Za-z0-9]/.test(password)) {
                    strength++;
                    $('#req-special').removeClass('invalid').addClass('valid');
                } else {
                    $('#req-special').removeClass('valid').addClass('invalid');
                }
                
                // Update strength meter
                const strengthMeter = $('#passwordStrength');
                let width = (strength / 5) * 100;
                let className = 'strength-weak';
                
                if (strength >= 3 && strength <= 4) {
                    className = 'strength-medium';
                } else if (strength === 5) {
                    className = 'strength-strong';
                }
                
                strengthMeter.css('width', width + '%').removeClass().addClass('strength-meter ' + className);
            });
            
            // Password confirmation checker
            $('#confirm_password').on('input', function() {
                const password = $('#password').val();
                const confirmPassword = $(this).val();
                const matchIndicator = $('#passwordMatch');
                
                if (confirmPassword === '') {
                    matchIndicator.html('');
                } else if (password === confirmPassword) {
                    matchIndicator.html('<span class="text-success"><i class="fas fa-check-circle me-1"></i> Passwords match</span>');
                } else {
                    matchIndicator.html('<span class="text-danger"><i class="fas fa-times-circle me-1"></i> Passwords do not match</span>');
                }
            });
            
            // Auto-format university ID
            $('#university_id').on('input', function() {
                let value = $(this).val().toUpperCase().replace(/[^A-Z0-9]/g, '');
                $(this).val(value);
            });
            
            // Form submission validation
            $('#registerForm').submit(function(e) {
                // Validate all steps before submission
                for (let i = 1; i <= totalSteps; i++) {
                    if (!validateStep(i)) {
                        e.preventDefault();
                        alert('Please fill all required fields correctly before submitting.');
                        nextStep(i);
                        return false;
                    }
                }
                
                // Check terms acceptance
                if (!$('#terms').is(':checked')) {
                    e.preventDefault();
                    alert('You must accept the terms and conditions.');
                    return false;
                }
                
                // Add loading state
                $('button[name="register"]').html('<i class="fas fa-spinner fa-spin me-2"></i> Registering...');
                $('button[name="register"]').prop('disabled', true);
                
                return true;
            });
            
            // Auto-capitalize first letters of names
            $('#first_name, #last_name').on('input', function() {
                let value = $(this).val();
                if (value.length > 0) {
                    $(this).val(value.charAt(0).toUpperCase() + value.slice(1));
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
            
            // Auto-focus on first field
            $('#first_name').focus();
        });
    </script>
    
    <!-- Security Script -->
    <script>
        // Track form interaction
        let formInteraction = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    formInteraction = true;
                });
            });
            
            // Warn before leaving if form has been interacted with
            window.addEventListener('beforeunload', function(e) {
                if (formInteraction) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
            
            // Add timestamp to prevent replay attacks
            form.addEventListener('submit', function() {
                const timestamp = Date.now();
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'registration_timestamp';
                hiddenInput.value = timestamp;
                form.appendChild(hiddenInput);
            });
        });
    </script>
</body>
</html>
<?php
// Log page access
$security->logSecurityEvent('REGISTER_PAGE_ACCESS', 'Registration page accessed from IP: ' . $ip);
?>