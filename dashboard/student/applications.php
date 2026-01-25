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
    SELECT u.*, up.first_name, up.last_name, up.university_id,
           ua.bus_id, ua.seat_number, b.bus_number
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN student_bus_assignments ua ON u.id = ua.student_id AND ua.status = 'active'
    LEFT JOIN buses b ON ua.bus_id = b.id
    WHERE u.id = :user_id
");
$db->bind(':user_id', $user_id);
$student = $db->single();

if (!$student) {
    session_destroy();
    $functions->redirect('../../login.php');
}

// Handle new application submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    // Validate CSRF token
    if (!$security->validateCSRFToken($_POST['csrf_token'], 'application_submit')) {
        $errors[] = "Security token invalid. Please refresh the page and try again.";
    }
    
    if (empty($errors)) {
        $application_type = $security->sanitize($_POST['application_type']);
        $subject = $security->sanitize($_POST['subject']);
        $details = $security->sanitize($_POST['details']);
        $start_date = !empty($_POST['start_date']) ? $security->sanitize($_POST['start_date']) : null;
        $end_date = !empty($_POST['end_date']) ? $security->sanitize($_POST['end_date']) : null;
        $new_bus_id = !empty($_POST['new_bus_id']) ? intval($_POST['new_bus_id']) : null;
        
        // Validate required fields
        if (empty($subject)) {
            $errors[] = "Subject is required.";
        }
        
        if (empty($details)) {
            $errors[] = "Details are required.";
        }
        
        // Validate dates for leave applications
        if ($application_type === 'leave' && (empty($start_date) || empty($end_date))) {
            $errors[] = "Start date and end date are required for leave applications.";
        } elseif ($application_type === 'leave') {
            if (strtotime($start_date) > strtotime($end_date)) {
                $errors[] = "Start date cannot be after end date.";
            }
            
            $max_leave_days = 30;
            $leave_days = floor((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24));
            if ($leave_days > $max_leave_days) {
                $errors[] = "Maximum leave period is $max_leave_days days.";
            }
        }
        
        // Validate bus change
        if ($application_type === 'bus_change' && empty($new_bus_id)) {
            $errors[] = "Please select a new bus.";
        }
        
        // Handle file upload
        $supporting_docs = [];
        if (isset($_FILES['supporting_docs'])) {
            for ($i = 0; $i < count($_FILES['supporting_docs']['name']); $i++) {
                if ($_FILES['supporting_docs']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['supporting_docs']['name'][$i],
                        'type' => $_FILES['supporting_docs']['type'][$i],
                        'tmp_name' => $_FILES['supporting_docs']['tmp_name'][$i],
                        'error' => $_FILES['supporting_docs']['error'][$i],
                        'size' => $_FILES['supporting_docs']['size'][$i]
                    ];
                    
                    list($valid, $upload_errors) = $security->validateFileUpload($file, [
                        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                        'application/pdf', 'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ]);
                    
                    if ($valid) {
                        $filename = $security->sanitizeFilename($file['name']);
                        $upload_path = '../../uploads/applications/' . $filename;
                        
                        if (!is_dir('../../uploads/applications')) {
                            mkdir('../../uploads/applications', 0755, true);
                        }
                        
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            $supporting_docs[] = 'uploads/applications/' . $filename;
                        } else {
                            $errors[] = "Failed to upload file: " . $file['name'];
                        }
                    } else {
                        $errors = array_merge($errors, $upload_errors);
                    }
                }
            }
        }
        
        if (empty($errors)) {
            $db->beginTransaction();
            try {
                // Create application
                $db->prepare("
                    INSERT INTO applications (student_id, application_type, subject, details, 
                                            supporting_docs, start_date, end_date, new_bus_id,
                                            status, submitted_at)
                    VALUES (:student_id, :application_type, :subject, :details, 
                           :supporting_docs, :start_date, :end_date, :new_bus_id,
                           'pending', NOW())
                ");
                $db->bind(':student_id', $user_id);
                $db->bind(':application_type', $application_type);
                $db->bind(':subject', $subject);
                $db->bind(':details', $details);
                $db->bind(':supporting_docs', json_encode($supporting_docs));
                $db->bind(':start_date', $start_date);
                $db->bind(':end_date', $end_date);
                $db->bind(':new_bus_id', $new_bus_id);
                $db->execute();
                
                $application_id = $db->lastInsertId();
                
                // Create notification for admin
                $application_types = [
                    'leave' => 'Leave Application',
                    'bus_change' => 'Bus Change Request',
                    'profile_update' => 'Profile Update Request',
                    'fee_exemption' => 'Fee Exemption Request',
                    'other' => 'General Application'
                ];
                
                $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, priority)
                    VALUES (1, 'New Application Submitted', 
                           CONCAT(:student_name, ' submitted a ', :app_type, ': ', :subject), 
                           'info', 'normal')
                ");
                $db->bind(':student_name', $student['first_name'] . ' ' . $student['last_name']);
                $db->bind(':app_type', $application_types[$application_type]);
                $db->bind(':subject', $subject);
                $db->execute();
                
                // Log activity
                $functions->logActivity($user_id, 'APPLICATION_SUBMITTED', 
                    "Submitted $application_type application: $subject");
                
                $db->commit();
                
                $success = "Application submitted successfully! Application ID: APP" . str_pad($application_id, 6, '0', STR_PAD_LEFT);
                
            } catch (Exception $e) {
                $db->rollBack();
                
                // Delete uploaded files
                foreach ($supporting_docs as $doc) {
                    if (file_exists('../../' . $doc)) {
                        unlink('../../' . $doc);
                    }
                }
                
                $errors[] = "Failed to submit application: " . $e->getMessage();
            }
        }
    }
}

// Handle application withdrawal
if (isset($_GET['withdraw'])) {
    $app_id = intval($_GET['withdraw']);
    
    $db->prepare("
        UPDATE applications 
        SET status = 'cancelled', 
            admin_notes = CONCAT(IFNULL(admin_notes, ''), '\\nWithdrawn by student on ', NOW())
        WHERE id = :id AND student_id = :student_id AND status = 'pending'
    ");
    $db->bind(':id', $app_id);
    $db->bind(':student_id', $user_id);
    
    if ($db->execute() && $db->rowCount() > 0) {
        $functions->logActivity($user_id, 'APPLICATION_WITHDRAWN', "Withdrew application ID: $app_id");
        $_SESSION['flash_message'] = [
            'text' => 'Application withdrawn successfully.',
            'type' => 'success'
        ];
        $functions->redirect('applications.php');
    }
}

// Fetch applications
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';

$query = "
    SELECT a.*, 
           CASE 
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'rejected' THEN 'danger'
               WHEN a.status = 'pending' THEN 'warning'
               WHEN a.status = 'processing' THEN 'info'
               WHEN a.status = 'cancelled' THEN 'secondary'
               ELSE 'dark'
           END as status_color,
           b.bus_number as new_bus_number
    FROM applications a
    LEFT JOIN buses b ON a.new_bus_id = b.id
    WHERE a.student_id = :user_id
";

$params = [':user_id' => $user_id];

if ($status_filter !== 'all') {
    $query .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter !== 'all') {
    $query .= " AND a.application_type = :type";
    $params[':type'] = $type_filter;
}

$query .= " ORDER BY a.submitted_at DESC";

$db->prepare($query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$applications = $db->resultSet();

// Get application statistics
$db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
    FROM applications 
    WHERE student_id = :user_id
");
$db->bind(':user_id', $user_id);
$app_stats = $db->single();

// Fetch available buses for bus change
$db->prepare("
    SELECT b.*, c.name as campus_name, 
           COUNT(sba.id) as occupied_seats,
           (b.capacity - COUNT(sba.id)) as available_seats
    FROM buses b
    LEFT JOIN campuses c ON b.campus_id = c.id
    LEFT JOIN student_bus_assignments sba ON b.id = sba.bus_id AND sba.status = 'active'
    WHERE b.status = 'active'
    GROUP BY b.id
    HAVING available_seats > 0
    ORDER BY c.name, b.bus_number
");
$available_buses = $db->resultSet();

// Generate CSRF token
$csrf_token = $security->generateCSRFToken('application_submit');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Student Dashboard</title>
    
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
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
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
        
        .app-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-card.total {
            border-top: 4px solid var(--secondary-color);
        }
        
        .stat-card.pending {
            border-top: 4px solid var(--warning-color);
        }
        
        .stat-card.approved {
            border-top: 4px solid var(--success-color);
        }
        
        .stat-card.rejected {
            border-top: 4px solid var(--danger-color);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .app-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .app-card .card-header {
            background: transparent;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-cancelled {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .type-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            background: var(--light-color);
            color: var(--primary-color);
        }
        
        .table-applications {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-applications thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 15px;
        }
        
        .table-applications tbody tr {
            transition: background 0.3s ease;
        }
        
        .table-applications tbody tr:hover {
            background: #f8f9fa;
        }
        
        .docs-preview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .doc-thumb {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            object-fit: cover;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .doc-thumb:hover {
            transform: scale(1.1);
            border-color: var(--secondary-color);
        }
        
        .action-btns {
            display: flex;
            gap: 5px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--secondary-color);
            border: 2px solid white;
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
            
            .app-stats {
                grid-template-columns: repeat(2, 1fr);
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
            
            .app-stats {
                grid-template-columns: 1fr;
            }
            
            .action-btns {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar (Same as other pages) -->
    <div class="sidebar">
        <div class="sidebar-header text-center py-4">
            <h4><i class="fas fa-bus"></i></h4>
            <small>Student Portal</small>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link" href="index.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a class="nav-link" href="profile.php">
                <i class="fas fa-user"></i> <span>My Profile</span>
            </a>
            <a class="nav-link" href="fees.php">
                <i class="fas fa-money-check-alt"></i> <span>Fee Management</span>
            </a>
            <a class="nav-link" href="bus_card.php">
                <i class="fas fa-id-card"></i> <span>Bus Card</span>
            </a>
            <a class="nav-link active" href="applications.php">
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
                <h2 class="fw-bold" style="color: var(--primary-color);">Applications</h2>
                <p class="text-muted mb-0">Submit and track your applications</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newApplicationModal">
                    <i class="fas fa-plus-circle me-2"></i> New Application
                </button>
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

        <!-- Application Statistics -->
        <div class="app-stats">
            <div class="stat-card total">
                <div class="stat-number" style="color: var(--secondary-color);">
                    <?php echo $app_stats['total'] ?: 0; ?>
                </div>
                <div class="stat-label">Total Applications</div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-number text-warning">
                    <?php echo $app_stats['pending'] ?: 0; ?>
                </div>
                <div class="stat-label">Pending</div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-number text-success">
                    <?php echo $app_stats['approved'] ?: 0; ?>
                </div>
                <div class="stat-label">Approved</div>
            </div>
            
            <div class="stat-card rejected">
                <div class="stat-number text-danger">
                    <?php echo $app_stats['rejected'] ?: 0; ?>
                </div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="app-card">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Filter by Status:</label>
                    <select class="form-select" id="statusFilter" onchange="filterApplications()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Filter by Type:</label>
                    <select class="form-select" id="typeFilter" onchange="filterApplications()">
                        <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="leave" <?php echo $type_filter === 'leave' ? 'selected' : ''; ?>>Leave</option>
                        <option value="bus_change" <?php echo $type_filter === 'bus_change' ? 'selected' : ''; ?>>Bus Change</option>
                        <option value="profile_update" <?php echo $type_filter === 'profile_update' ? 'selected' : ''; ?>>Profile Update</option>
                        <option value="fee_exemption" <?php echo $type_filter === 'fee_exemption' ? 'selected' : ''; ?>>Fee Exemption</option>
                        <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Applications List -->
        <?php if (!empty($applications)): ?>
            <div class="table-responsive">
                <table class="table table-applications">
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Submitted On</th>
                            <th>Status</th>
                            <th>Documents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <strong>APP<?php echo str_pad($app['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                </td>
                                <td>
                                    <span class="type-badge">
                                        <?php 
                                        $types = [
                                            'leave' => 'Leave',
                                            'bus_change' => 'Bus Change',
                                            'profile_update' => 'Profile Update',
                                            'fee_exemption' => 'Fee Exemption',
                                            'other' => 'Other'
                                        ];
                                        echo $types[$app['application_type']] ?? $app['application_type'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['subject']); ?></strong>
                                    <div class="small text-muted">
                                        <?php echo strlen($app['details']) > 50 ? substr(htmlspecialchars($app['details']), 0, 50) . '...' : htmlspecialchars($app['details']); ?>
                                    </div>
                                </td>
                                <td><?php echo $functions->formatDate($app['submitted_at']); ?></td>
                                <td>
                                    <span class="status-badge badge-<?php echo $app['status']; ?>">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                    <?php if ($app['processed_at']): ?>
                                        <div class="small text-muted">
                                            <?php echo $functions->formatDate($app['processed_at']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $docs = json_decode($app['supporting_docs'] ?? '[]', true);
                                    if (!empty($docs)): 
                                    ?>
                                        <div class="docs-preview">
                                            <?php foreach ($docs as $doc): ?>
                                                <a href="../../<?php echo htmlspecialchars($doc); ?>" target="_blank">
                                                    <img src="../../<?php echo htmlspecialchars($doc); ?>" 
                                                         class="doc-thumb" 
                                                         alt="Document"
                                                         onerror="this.src='../../assets/images/document-icon.png'">
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No documents</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewApplicationModal"
                                                onclick="viewApplication(<?php echo $app['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <a href="?withdraw=<?php echo $app['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to withdraw this application?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($app['status'] === 'approved' && $app['application_type'] === 'bus_change'): ?>
                                            <button class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav aria-label="Applications pagination">
                <ul class="pagination justify-content-center mt-4">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </ul>
            </nav>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <h5>No Applications Found</h5>
                <p class="text-muted">
                    <?php if ($status_filter !== 'all' || $type_filter !== 'all'): ?>
                        No applications match your filters. <a href="applications.php">Clear filters</a>
                    <?php else: ?>
                        You haven't submitted any applications yet.
                    <?php endif; ?>
                </p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newApplicationModal">
                    <i class="fas fa-plus-circle me-2"></i> Submit Your First Application
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- New Application Modal -->
    <div class="modal fade" id="newApplicationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit New Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="application_type" class="form-label">Application Type *</label>
                                <select class="form-select" id="application_type" name="application_type" required onchange="toggleApplicationFields()">
                                    <option value="">Select Type</option>
                                    <option value="leave">Leave Application</option>
                                    <option value="bus_change">Bus Change Request</option>
                                    <option value="profile_update">Profile Update Request</option>
                                    <option value="fee_exemption">Fee Exemption Request</option>
                                    <option value="other">Other Request</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <!-- Leave Application Fields -->
                            <div id="leaveFields" style="display: none;">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">End Date *</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date">
                                </div>
                            </div>
                            
                            <!-- Bus Change Fields -->
                            <div id="busChangeFields" style="display: none;">
                                <div class="col-12 mb-3">
                                    <label for="new_bus_id" class="form-label">Select New Bus *</label>
                                    <select class="form-select" id="new_bus_id" name="new_bus_id">
                                        <option value="">Select Bus</option>
                                        <?php foreach ($available_buses as $bus): ?>
                                            <option value="<?php echo $bus['id']; ?>">
                                                Bus <?php echo htmlspecialchars($bus['bus_number']); ?> 
                                                (<?php echo htmlspecialchars($bus['campus_name']); ?>)
                                                - Seats: <?php echo $bus['available_seats']; ?> available
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="details" class="form-label">Details *</label>
                                <textarea class="form-control" id="details" name="details" rows="4" required 
                                          placeholder="Provide detailed explanation for your application..."></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="supporting_docs" class="form-label">Supporting Documents (Optional)</label>
                                <input type="file" class="form-control" id="supporting_docs" name="supporting_docs[]" multiple>
                                <div class="form-text">
                                    Upload supporting documents (max 5 files, 5MB each). Allowed: JPG, PNG, PDF, DOC, DOCX
                                </div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Important:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Applications are processed within 3-5 working days</li>
                                        <li>Ensure all information is accurate before submission</li>
                                        <li>You will be notified via email about status updates</li>
                                        <li>Withdraw option is available for pending applications</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_application" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i> Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Application Modal -->
    <div class="modal fade" id="viewApplicationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="applicationDetails">
                    <!-- Content loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printApplication()">
                        <i class="fas fa-print me-2"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Applications Script -->
    <script>
        function toggleApplicationFields() {
            const type = document.getElementById('application_type').value;
            
            // Hide all fields first
            document.getElementById('leaveFields').style.display = 'none';
            document.getElementById('busChangeFields').style.display = 'none';
            
            // Clear required fields
            document.getElementById('start_date').required = false;
            document.getElementById('end_date').required = false;
            document.getElementById('new_bus_id').required = false;
            
            // Show relevant fields
            if (type === 'leave') {
                document.getElementById('leaveFields').style.display = 'block';
                document.getElementById('start_date').required = true;
                document.getElementById('end_date').required = true;
            } else if (type === 'bus_change') {
                document.getElementById('busChangeFields').style.display = 'block';
                document.getElementById('new_bus_id').required = true;
            }
        }
        
        function filterApplications() {
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;
            window.location.href = `applications.php?status=${status}&type=${type}`;
        }
        
        function viewApplication(appId) {
            // Show loading
            document.getElementById('applicationDetails').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading application details...</p>
                </div>
            `;
            
            // Fetch application details
            $.ajax({
                url: 'get_application.php',
                method: 'POST',
                data: { 
                    application_id: appId,
                    csrf_token: '<?php echo $security->generateCSRFToken('view_application'); ?>'
                },
                success: function(response) {
                    try {
                        const app = JSON.parse(response);
                        renderApplicationDetails(app);
                    } catch (e) {
                        document.getElementById('applicationDetails').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Failed to load application details.
                            </div>
                        `;
                    }
                },
                error: function() {
                    document.getElementById('applicationDetails').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Failed to load application details. Please try again.
                        </div>
                    `;
                }
            });
        }
        
        function renderApplicationDetails(app) {
            const types = {
                'leave': 'Leave Application',
                'bus_change': 'Bus Change Request',
                'profile_update': 'Profile Update Request',
                'fee_exemption': 'Fee Exemption Request',
                'other': 'Other Request'
            };
            
            let html = `
                <div class="app-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">${app.subject}</h4>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="type-badge">${types[app.application_type] || app.application_type}</span>
                                    <span class="status-badge badge-${app.status}">${app.status.charAt(0).toUpperCase() + app.status.slice(1)}</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">ID: APP${String(app.id).padStart(6, '0')}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Submitted On:</strong> ${app.submitted_at}</p>
                            ${app.processed_at ? `<p><strong>Processed On:</strong> ${app.processed_at}</p>` : ''}
                        </div>
                        <div class="col-md-6">
                            ${app.start_date ? `<p><strong>Start Date:</strong> ${app.start_date}</p>` : ''}
                            ${app.end_date ? `<p><strong>End Date:</strong> ${app.end_date}</p>` : ''}
                            ${app.new_bus_number ? `<p><strong>New Bus:</strong> ${app.new_bus_number}</p>` : ''}
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Application Details:</h6>
                        <div class="p-3 bg-light rounded">${app.details.replace(/\n/g, '<br>')}</div>
                    </div>
            `;
            
            // Admin Notes
            if (app.admin_notes) {
                html += `
                    <div class="mb-4">
                        <h6>Admin Notes:</h6>
                        <div class="p-3 bg-info bg-opacity-10 rounded">${app.admin_notes.replace(/\n/g, '<br>')}</div>
                    </div>
                `;
            }
            
            // Supporting Documents
            const docs = JSON.parse(app.supporting_docs || '[]');
            if (docs.length > 0) {
                html += `
                    <div class="mb-4">
                        <h6>Supporting Documents:</h6>
                        <div class="docs-preview">
                `;
                docs.forEach(doc => {
                    html += `
                        <a href="../../${doc}" target="_blank" class="me-2 mb-2">
                            <img src="../../${doc}" class="doc-thumb" alt="Document" 
                                 onerror="this.src='../../assets/images/document-icon.png'">
                        </a>
                    `;
                });
                html += `
                        </div>
                    </div>
                `;
            }
            
            // Application Timeline
            html += `
                <div class="timeline">
                    <div class="timeline-item">
                        <strong>Application Submitted</strong>
                        <p class="mb-0 text-muted">${app.submitted_at}</p>
                    </div>
            `;
            
            if (app.processed_at) {
                html += `
                    <div class="timeline-item">
                        <strong>Application ${app.status.charAt(0).toUpperCase() + app.status.slice(1)}</strong>
                        <p class="mb-0 text-muted">${app.processed_at}</p>
                        ${app.admin_notes ? `<p class="mb-0">${app.admin_notes}</p>` : ''}
                    </div>
                `;
            }
            
            html += `
                </div>
            </div>
            `;
            
            document.getElementById('applicationDetails').innerHTML = html;
        }
        
        function printApplication() {
            const printContent = document.getElementById('applicationDetails').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Application Details</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .app-card { border: 1px solid #ddd; padding: 20px; margin: 20px 0; }
                        .status-badge { padding: 5px 15px; border-radius: 20px; font-size: 0.85rem; }
                        .badge-pending { background: #fff3cd; color: #856404; }
                        .badge-approved { background: #d4edda; color: #155724; }
                        .badge-rejected { background: #f8d7da; color: #721c24; }
                        .badge-processing { background: #d1ecf1; color: #0c5460; }
                        .type-badge { background: #f8f9fa; padding: 4px 12px; border-radius: 15px; }
                    </style>
                </head>
                <body>
                    <h2>Application Details</h2>
                    ${printContent}
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(function() {
                                window.close();
                            }, 500);
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
        
        // Initialize on page load
        $(document).ready(function() {
            // Set min dates for leave applications
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').min = today;
            document.getElementById('end_date').min = today;
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Show new application modal if there are errors
            <?php if (!empty($errors) && isset($_POST['submit_application'])): ?>
                $('#newApplicationModal').modal('show');
            <?php endif; ?>
            
            // Auto-hide alerts after 5 seconds
            $('.alert').delay(5000).fadeOut('slow');
            
            // File upload preview
            $('#supporting_docs').change(function() {
                const files = this.files;
                if (files.length > 5) {
                    alert('Maximum 5 files allowed. Only the first 5 will be uploaded.');
                    return;
                }
                
                let totalSize = 0;
                for (let i = 0; i < Math.min(files.length, 5); i++) {
                    totalSize += files[i].size;
                }
                
                if (totalSize > 5 * 1024 * 1024) {
                    alert('Total file size exceeds 5MB limit.');
                    this.value = '';
                }
            });
            
            // Form validation
            $('form').submit(function() {
                const type = $('#application_type').val();
                const subject = $('#subject').val().trim();
                const details = $('#details').val().trim();
                
                if (!type) {
                    alert('Please select application type.');
                    return false;
                }
                
                if (!subject) {
                    alert('Please enter subject.');
                    return false;
                }
                
                if (!details) {
                    alert('Please enter details.');
                    return false;
                }
                
                // Validate leave dates
                if (type === 'leave') {
                    const startDate = $('#start_date').val();
                    const endDate = $('#end_date').val();
                    
                    if (!startDate || !endDate) {
                        alert('Please select both start and end dates for leave.');
                        return false;
                    }
                    
                    if (new Date(startDate) > new Date(endDate)) {
                        alert('Start date cannot be after end date.');
                        return false;
                    }
                }
                
                // Validate bus change
                if (type === 'bus_change' && ! $('#new_bus_id').val()) {
                    alert('Please select a new bus.');
                    return false;
                }
                
                // Add loading state
                $(this).find('button[type="submit"]').html('<i class="fas fa-spinner fa-spin me-2"></i> Submitting...');
                $(this).find('button[type="submit"]').prop('disabled', true);
                
                return true;
            });
        });
    </script>
</body>
</html>
<?php
// Log applications page view
$functions->logActivity($user_id, 'APPLICATIONS_PAGE_VIEW', 'Applications page viewed');
?>