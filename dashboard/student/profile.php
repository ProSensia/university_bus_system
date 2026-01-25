<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/security.php';
require_once '../../includes/functions.php';

// Start secure session
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    $functions->redirect('../../login.php');
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance();
$security = new Security();
$functions = new Functions();

// Fetch student data
$db->prepare("
    SELECT u.*, up.*, 
           ua.bus_id, ua.seat_number,
           b.bus_number, b.type as bus_type,
           br.route_name,
           c.name as campus_name
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN student_bus_assignments ua ON u.id = ua.student_id AND ua.status = 'active'
    LEFT JOIN buses b ON ua.bus_id = b.id
    LEFT JOIN bus_routes br ON b.id = br.bus_id
    LEFT JOIN campuses c ON b.campus_id = c.id
    WHERE u.id = :user_id
");
$db->bind(':user_id', $user_id);
$student = $db->single();

if (!$student) {
    session_destroy();
    $functions->redirect('../../login.php');
}

// Handle profile update
$errors = [];
$success = '';
$profile_updated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!$security->validateCSRFToken($_POST['csrf_token'], 'profile_update')) {
        $errors[] = "Security token invalid. Please refresh the page and try again.";
    }
    
    if (empty($errors)) {
        $phone = $security->sanitize($_POST['phone']);
        $emergency_contact = $security->sanitize($_POST['emergency_contact']);
        $address = $security->sanitize($_POST['address']);
        $blood_group = $security->sanitize($_POST['blood_group']);
        
        // Validate phone numbers
        if (!empty($phone) && !$functions->validatePhone($phone)) {
            $errors[] = "Please enter a valid phone number.";
        }
        
        if (!empty($emergency_contact) && !$functions->validatePhone($emergency_contact)) {
            $errors[] = "Please enter a valid emergency contact number.";
        }
        
        if (empty($errors)) {
            // Check if any changes were made
            $changes = [];
            if ($phone != $student['phone']) $changes[] = "Phone";
            if ($emergency_contact != $student['emergency_contact']) $changes[] = "Emergency Contact";
            if ($address != $student['address']) $changes[] = "Address";
            if ($blood_group != $student['blood_group']) $changes[] = "Blood Group";
            
            if (!empty($changes)) {
                // For major changes, create an approval request
                if (in_array('Phone', $changes) || in_array('Emergency Contact', $changes)) {
                    $db->prepare("
                        INSERT INTO approval_requests (user_id, request_type, details, status, submitted_at)
                        VALUES (:user_id, 'profile_update', :details, 'pending', NOW())
                    ");
                    $db->bind(':user_id', $user_id);
                    $db->bind(':details', 'Request to update: ' . implode(', ', $changes));
                    $db->execute();
                    
                    $success = "Profile update request submitted for admin approval.";
                } else {
                    // Minor updates can be done directly
                    $db->beginTransaction();
                    try {
                        // Update user profile
                        $db->prepare("
                            UPDATE user_profiles 
                            SET phone = :phone, 
                                emergency_contact = :emergency_contact,
                                address = :address,
                                blood_group = :blood_group,
                                updated_at = NOW()
                            WHERE user_id = :user_id
                        ");
                        $db->bind(':phone', $phone);
                        $db->bind(':emergency_contact', $emergency_contact);
                        $db->bind(':address', $address);
                        $db->bind(':blood_group', $blood_group);
                        $db->bind(':user_id', $user_id);
                        $db->execute();
                        
                        // Log the update
                        $functions->logActivity($user_id, 'PROFILE_UPDATE', 'Updated profile information');
                        
                        $db->commit();
                        
                        $success = "Profile updated successfully!";
                        $profile_updated = true;
                        
                        // Refresh student data
                        $db->prepare("SELECT * FROM user_profiles WHERE user_id = :user_id");
                        $db->bind(':user_id', $user_id);
                        $student = array_merge($student, $db->single());
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        $errors[] = "Failed to update profile: " . $e->getMessage();
                    }
                }
            } else {
                $errors[] = "No changes detected.";
            }
        }
    }
}

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_photo'])) {
    if (!$security->validateCSRFToken($_POST['csrf_token'], 'photo_upload')) {
        $errors[] = "Security token invalid. Please refresh the page and try again.";
    }
    
    if (empty($errors) && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        list($valid, $upload_errors) = $security->validateFileUpload($_FILES['profile_photo']);
        
        if ($valid) {
            $filename = $security->sanitizeFilename($_FILES['profile_photo']['name']);
            $upload_path = '../../uploads/profiles/' . $filename;
            
            if (!is_dir('../../uploads/profiles')) {
                mkdir('../../uploads/profiles', 0755, true);
            }
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                // Delete old photo if exists
                if ($student['profile_photo'] && file_exists('../../' . $student['profile_photo'])) {
                    unlink('../../' . $student['profile_photo']);
                }
                
                // Update database
                $db->prepare("UPDATE user_profiles SET profile_photo = :photo WHERE user_id = :user_id");
                $db->bind(':photo', 'uploads/profiles/' . $filename);
                $db->bind(':user_id', $user_id);
                $db->execute();
                
                // Log the update
                $functions->logActivity($user_id, 'PROFILE_PHOTO_UPDATE', 'Updated profile photo');
                
                $success = "Profile photo updated successfully!";
                $student['profile_photo'] = 'uploads/profiles/' . $filename;
                
                // Update session
                $_SESSION['profile_photo'] = $student['profile_photo'];
            } else {
                $errors[] = "Failed to upload photo. Please try again.";
            }
        } else {
            $errors = array_merge($errors, $upload_errors);
        }
    } else {
        $errors[] = "Please select a valid photo.";
    }
}

// Generate CSRF tokens
$profile_token = $security->generateCSRFToken('profile_update');
$photo_token = $security->generateCSRFToken('photo_upload');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard</title>
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;">
    
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
            background-color: #f5f7fb;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, #1a2530 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            z-index: 1000;
            box-shadow: 3px 0 15px rgba(0,0,0,0.1);
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .profile-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .profile-photo-container {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }
        
        .profile-photo {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--secondary-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .photo-upload-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 4px solid var(--secondary-color);
        }
        
        .info-card h5 {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--primary-color);
            width: 200px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: #555;
            flex-grow: 1;
        }
        
        .badge-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .form-control:disabled {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar (Same as index.php) -->
    <div class="sidebar">
        <div class="sidebar-header text-center py-4">
            <h4><i class="fas fa-bus"></i></h4>
            <small>Student Portal</small>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link" href="index.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a class="nav-link active" href="profile.php">
                <i class="fas fa-user"></i> <span>My Profile</span>
            </a>
            <a class="nav-link" href="fees.php">
                <i class="fas fa-money-check-alt"></i> <span>Fee Management</span>
            </a>
            <a class="nav-link" href="bus_card.php">
                <i class="fas fa-id-card"></i> <span>Bus Card</span>
            </a>
            <a class="nav-link" href="applications.php">
                <i class="fas fa-file-alt"></i> <span>Applications</span>
            </a>
            <div class="mt-4 pt-3 border-top border-secondary">
                <a class="nav-link" href="../../logout.php">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold" style="color: var(--primary-color);">My Profile</h2>
                <p class="text-muted mb-0">Manage your personal information and settings</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
                <span class="badge bg-secondary">
                    <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($student['university_id']); ?>
                </span>
            </div>
        </div>

        <?php echo $functions->displayFlashMessage(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
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

        <div class="row">
            <!-- Left Column - Profile Photo and Basic Info -->
            <div class="col-lg-4">
                <div class="profile-header">
                    <div class="profile-photo-container">
                        <img src="../../<?php echo $student['profile_photo'] ?: 'assets/images/default-avatar.jpg'; ?>" 
                             class="profile-photo" alt="Profile Photo" id="profilePhotoPreview">
                        <div class="photo-upload-btn" data-bs-toggle="modal" data-bs-target="#photoUploadModal">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <h3 class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($student['university_id']); ?></p>
                        
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <span class="badge-status badge-<?php echo $student['status']; ?>">
                                <i class="fas fa-circle me-1"></i> <?php echo ucfirst($student['status']); ?>
                            </span>
                            <?php if ($student['bus_number']): ?>
                                <span class="badge bg-primary">
                                    <i class="fas fa-bus me-1"></i> Bus <?php echo htmlspecialchars($student['bus_number']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateProfileModal">
                                <i class="fas fa-edit me-2"></i> Edit Profile
                            </button>
                            <a href="bus_card.php" class="btn btn-outline-secondary">
                                <i class="fas fa-id-card me-2"></i> View Bus Card
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Account Status -->
                <div class="info-card">
                    <h5><i class="fas fa-shield-alt me-2"></i> Account Security</h5>
                    <div class="info-item">
                        <div class="info-label">Account Status</div>
                        <div class="info-value">
                            <span class="badge-status badge-<?php echo $student['status']; ?>">
                                <?php echo ucfirst($student['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div class="info-value"><?php echo $functions->formatDate($student['last_login']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?php echo $functions->formatDate($student['created_at'], 'F j, Y'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">IP Address</div>
                        <div class="info-value"><?php echo $security->getClientIP(); ?></div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Detailed Information -->
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="info-card">
                    <h5><i class="fas fa-user-circle me-2"></i> Personal Information</h5>
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?php echo ucfirst($student['gender'] ?: 'Not specified'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo $functions->formatDate($student['date_of_birth'], 'F j, Y'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Blood Group</div>
                        <div class="info-value"><?php echo $student['blood_group'] ?: 'Not specified'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['phone'] ?: 'Not provided'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Emergency Contact</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['emergency_contact'] ?: 'Not provided'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($student['address'] ?: 'Not provided')); ?></div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="info-card">
                    <h5><i class="fas fa-graduation-cap me-2"></i> Academic Information</h5>
                    <div class="info-item">
                        <div class="info-label">University ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['university_id']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Semester</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['semester'] ?: 'Not specified'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Program</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['program'] ?: 'Not specified'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Department</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['department'] ?: 'Not specified'); ?></div>
                    </div>
                </div>

                <!-- Bus Information -->
                <?php if ($student['bus_id']): ?>
                <div class="info-card">
                    <h5><i class="fas fa-bus me-2"></i> Bus Information</h5>
                    <div class="info-item">
                        <div class="info-label">Bus Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['bus_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Bus Type</div>
                        <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $student['bus_type'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Route</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['route_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Campus</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['campus_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Seat Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['seat_number']); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="info-card">
                    <h5><i class="fas fa-history me-2"></i> Recent Profile Activity</h5>
                    <?php
                    $db->prepare("
                        SELECT * FROM activity_logs 
                        WHERE user_id = :user_id 
                        AND action LIKE '%PROFILE%'
                        ORDER BY timestamp DESC 
                        LIMIT 5
                    ");
                    $db->bind(':user_id', $user_id);
                    $activities = $db->resultSet();
                    ?>
                    
                    <?php if (!empty($activities)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($activities as $activity): ?>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                            <p class="mb-1 small text-muted"><?php echo htmlspecialchars($activity['details']); ?></p>
                                        </div>
                                        <small class="text-muted"><?php echo $functions->timeAgo($activity['timestamp']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No recent profile activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Upload Modal -->
    <div class="modal fade" id="photoUploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $photo_token; ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="profile_photo" class="form-label">Select Photo</label>
                            <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*" required>
                            <div class="form-text">
                                Max file size: 5MB. Allowed formats: JPG, PNG, GIF.
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Your photo will be used for your bus card and identification.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_photo" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i> Upload Photo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="updateProfileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Profile Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $profile_token; ?>">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" 
                                       value="<?php echo htmlspecialchars($student['first_name']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" 
                                       value="<?php echo htmlspecialchars($student['last_name']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($student['email']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="university_id" class="form-label">University ID</label>
                                <input type="text" class="form-control" id="university_id" 
                                       value="<?php echo htmlspecialchars($student['university_id']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($student['phone']); ?>" 
                                       pattern="[0-9]{10,15}" required>
                                <div class="form-text">Format: 03001234567</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="emergency_contact" class="form-label">Emergency Contact *</label>
                                <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" 
                                       value="<?php echo htmlspecialchars($student['emergency_contact']); ?>" 
                                       pattern="[0-9]{10,15}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="blood_group" class="form-label">Blood Group</label>
                                <select class="form-select" id="blood_group" name="blood_group">
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo $student['blood_group'] == 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo $student['blood_group'] == 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo $student['blood_group'] == 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo $student['blood_group'] == 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="O+" <?php echo $student['blood_group'] == 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo $student['blood_group'] == 'O-' ? 'selected' : ''; ?>>O-</option>
                                    <option value="AB+" <?php echo $student['blood_group'] == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo $student['blood_group'] == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="address" class="form-label">Address *</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($student['address']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> Changes to phone number and emergency contact require admin approval.
                            Other changes will be updated immediately.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Profile Script -->
    <script>
        $(document).ready(function() {
            // Photo preview
            $('#profile_photo').change(function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#profilePhotoPreview').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Form validation
            $('form').submit(function() {
                const phone = $('#phone').val();
                const emergency = $('#emergency_contact').val();
                const address = $('#address').val();
                
                if (!phone || !emergency || !address) {
                    alert('Please fill all required fields marked with *');
                    return false;
                }
                
                // Add loading state
                $(this).find('button[type="submit"]').html('<i class="fas fa-spinner fa-spin me-2"></i> Processing...');
                $(this).find('button[type="submit"]').prop('disabled', true);
                
                return true;
            });

            // Auto-format phone numbers
            $('#phone, #emergency_contact').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9]/g, ''));
            });

            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            // Show modal if there are errors in form submission
            <?php if (!empty($errors) && isset($_POST['update_profile'])): ?>
                $('#updateProfileModal').modal('show');
            <?php endif; ?>

            <?php if (!empty($errors) && isset($_POST['update_photo'])): ?>
                $('#photoUploadModal').modal('show');
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php
// Log profile view
$functions->logActivity($user_id, 'PROFILE_VIEW', 'Profile page viewed');
?>