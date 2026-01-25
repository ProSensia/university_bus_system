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

// Get student data
$user_id = $_SESSION['user_id'];
$db = Database::getInstance();
$security = new Security();
$functions = new Functions();

// Fetch student details
$db->prepare("
    SELECT u.*, up.*, ua.bus_id, ua.seat_number, ua.status as assignment_status,
           b.bus_number, b.type as bus_type, b.capacity, 
           br.route_name, br.start_point, br.end_point,
           c.name as campus_name,
           d.first_name as driver_first_name, d.last_name as driver_last_name,
           d.license_number
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN student_bus_assignments ua ON u.id = ua.student_id AND ua.status = 'active'
    LEFT JOIN buses b ON ua.bus_id = b.id
    LEFT JOIN bus_routes br ON b.id = br.bus_id
    LEFT JOIN campuses c ON b.campus_id = c.id
    LEFT JOIN drivers dr ON b.driver_id = dr.user_id
    LEFT JOIN user_profiles d ON dr.user_id = d.user_id
    WHERE u.id = :user_id
");
$db->bind(':user_id', $user_id);
$student = $db->single();

if (!$student) {
    session_destroy();
    $functions->redirect('../../login.php');
}

// Check if account is active
if ($student['status'] !== 'active') {
    $_SESSION['flash_message'] = [
        'text' => 'Your account is ' . $student['status'] . '. Please contact administrator.',
        'type' => 'warning'
    ];
}

// Fetch fee status
$db->prepare("
    SELECT 
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
        MAX(due_date) as last_due_date
    FROM fees 
    WHERE student_id = :user_id 
    AND YEAR(due_date) = YEAR(CURDATE())
");
$db->bind(':user_id', $user_id);
$fee_stats = $db->single();

// Fetch recent notifications
$db->prepare("
    SELECT * FROM notifications 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC 
    LIMIT 5
");
$db->bind(':user_id', $user_id);
$notifications = $db->resultSet();

// Fetch upcoming bus schedule
$today = date('Y-m-d');
$db->prepare("
    SELECT * FROM bus_schedules 
    WHERE bus_id = :bus_id 
    AND schedule_date >= :today
    ORDER BY schedule_date ASC 
    LIMIT 3
");
$db->bind(':bus_id', $student['bus_id']);
$db->bind(':today', $today);
$upcoming_schedules = $db->resultSet();

// Fetch attendance summary for current month
$current_month = date('Y-m');
$db->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
    FROM attendance 
    WHERE student_id = :user_id 
    AND DATE_FORMAT(date, '%Y-%m') = :current_month
");
$db->bind(':user_id', $user_id);
$db->bind(':current_month', $current_month);
$attendance = $db->single();

// Log dashboard access
$security->logSecurityEvent('DASHBOARD_ACCESS', 'Student dashboard accessed', $user_id);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon.ico">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: #333;
        }
        
        /* Sidebar Styles */
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
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
            color: white;
        }
        
        .sidebar-header small {
            color: rgba(255,255,255,0.7);
            font-size: 0.8rem;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .nav-link i {
            width: 25px;
            font-size: 1.1rem;
            margin-right: 10px;
        }
        
        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .nav-link.active {
            color: white;
            background: var(--secondary-color);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .topbar {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .topbar-left h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin: 0;
            font-weight: 600;
        }
        
        .topbar-left p {
            color: #666;
            margin: 5px 0 0;
            font-size: 0.9rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--secondary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            border-left: 4px solid var(--secondary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card.fees {
            border-left-color: var(--success-color);
        }
        
        .stat-card.attendance {
            border-left-color: var(--info-color);
        }
        
        .stat-card.applications {
            border-left-color: var(--warning-color);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        
        .stat-card.fees .stat-icon {
            color: var(--success-color);
        }
        
        .stat-card.attendance .stat-icon {
            color: var(--info-color);
        }
        
        .stat-card.applications .stat-icon {
            color: var(--warning-color);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .quick-actions h5 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 20px;
            background: var(--light-color);
            border: 2px dashed #ddd;
            border-radius: 10px;
            transition: all 0.3s ease;
            color: var(--primary-color);
            text-decoration: none;
            height: 100%;
        }
        
        .action-btn:hover {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
            transform: translateY(-3px);
        }
        
        .action-btn i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        /* Notifications */
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #f0f8ff;
            border-left: 3px solid var(--secondary-color);
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #888;
        }
        
        /* Bus Schedule */
        .schedule-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .schedule-date {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .schedule-time {
            color: var(--success-color);
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h4, 
            .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
            }
            
            .nav-link i {
                margin-right: 0;
                font-size: 1.3rem;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .bus-info-card {
            background: linear-gradient(135deg, var(--secondary-color), #2980b9);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .bus-info-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .bus-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .bus-route {
            font-size: 1.1rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-bus me-2"></i> <?php echo APP_NAME; ?></h4>
            <small>Student Dashboard</small>
        </div>
        
        <div class="sidebar-menu">
            <nav class="nav flex-column">
                <a class="nav-link active" href="index.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a class="nav-link" href="fees.php">
                    <i class="fas fa-money-check-alt"></i>
                    <span>Fee Management</span>
                </a>
                <a class="nav-link" href="bus_card.php">
                    <i class="fas fa-id-card"></i>
                    <span>Bus Card</span>
                </a>
                <a class="nav-link" href="applications.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Applications</span>
                </a>
                <a class="nav-link" href="attendance.php">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a class="nav-link" href="schedule.php">
                    <i class="fas fa-clock"></i>
                    <span>Bus Schedule</span>
                </a>
                <div class="mt-4 pt-3 border-top border-secondary">
                    <a class="nav-link" href="../../logout.php" onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-left">
                <h1>Welcome back, <?php echo htmlspecialchars($student['first_name']); ?>!</h1>
                <p><?php echo date('l, F j, Y'); ?> • Last login: <?php echo $functions->formatDate($student['last_login']); ?></p>
            </div>
            
            <div class="user-profile">
                <div class="position-relative">
                    <a href="#" class="text-dark" data-bs-toggle="dropdown">
                        <i class="fas fa-bell fa-lg"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span class="notification-badge"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width: 300px;">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0">Notifications</h6>
                        </div>
                        <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($notifications as $notification): ?>
                                <a href="#" class="list-group-item list-group-item-action notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        </div>
                                        <small class="notification-time"><?php echo $functions->timeAgo($notification['created_at']); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($notifications)): ?>
                                <div class="p-3 text-center text-muted">
                                    No notifications
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 border-top">
                            <a href="notifications.php" class="btn btn-sm btn-outline-primary w-100">View All</a>
                        </div>
                    </div>
                </div>
                
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="../../<?php echo $student['profile_photo'] ?: 'assets/images/default-avatar.jpg'; ?>" 
                             class="profile-img" alt="Profile">
                        <div class="ms-2">
                            <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                            <div class="small text-muted"><?php echo htmlspecialchars($student['university_id']); ?></div>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../../logout.php" onclick="return confirm('Are you sure you want to logout?')">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php echo $functions->displayFlashMessage(); ?>

        <!-- Status Badge -->
        <div class="mb-4">
            <span class="status-badge status-<?php echo $student['status']; ?>">
                <i class="fas fa-circle me-1"></i> Account: <?php echo ucfirst($student['status']); ?>
            </span>
            <?php if ($student['assignment_status']): ?>
                <span class="status-badge status-active ms-2">
                    <i class="fas fa-bus me-1"></i> Bus Assigned
                </span>
            <?php else: ?>
                <span class="status-badge status-pending ms-2">
                    <i class="fas fa-clock me-1"></i> Bus Assignment Pending
                </span>
            <?php endif; ?>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bus"></i>
                </div>
                <div class="stat-number">
                    <?php echo $student['bus_number'] ?: 'N/A'; ?>
                </div>
                <div class="stat-label">Bus Number</div>
                <small class="text-muted"><?php echo $student['bus_type'] ?: 'Not assigned'; ?></small>
            </div>
            
            <div class="stat-card fees">
                <div class="stat-icon">
                    <i class="fas fa-money-check-alt"></i>
                </div>
                <div class="stat-number text-success">
                    <?php echo $fee_stats['paid_count'] ?: '0'; ?>
                </div>
                <div class="stat-label">Paid Fees</div>
                <small class="text-muted">
                    <?php echo $fee_stats['pending_count'] ?: '0'; ?> pending • 
                    <?php echo $fee_stats['overdue_count'] ?: '0'; ?> overdue
                </small>
            </div>
            
            <div class="stat-card attendance">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number text-info">
                    <?php echo $attendance['present_days'] ?: '0'; ?>/<?php echo $attendance['total_days'] ?: '0'; ?>
                </div>
                <div class="stat-label">Attendance (This Month)</div>
                <small class="text-muted">
                    <?php echo round(($attendance['present_days'] / max($attendance['total_days'], 1)) * 100, 0); ?>% attendance rate
                </small>
            </div>
            
            <div class="stat-card applications">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-number text-warning">
                    <?php
                    $db->prepare("SELECT COUNT(*) as count FROM applications WHERE student_id = :user_id AND status = 'pending'");
                    $db->bind(':user_id', $user_id);
                    $pending_apps = $db->single();
                    echo $pending_apps['count'] ?: '0';
                    ?>
                </div>
                <div class="stat-label">Pending Applications</div>
                <small class="text-muted">
                    <?php
                    $db->prepare("SELECT COUNT(*) as count FROM applications WHERE student_id = :user_id");
                    $db->bind(':user_id', $user_id);
                    $total_apps = $db->single();
                    echo $total_apps['count'] ?: '0'; ?> total applications
                </small>
            </div>
        </div>

        <div class="row">
            <!-- Bus Information -->
            <div class="col-lg-4">
                <?php if ($student['bus_id']): ?>
                    <div class="bus-info-card">
                        <div class="bus-info-icon">
                            <i class="fas fa-bus"></i>
                        </div>
                        <div class="bus-number">Bus <?php echo htmlspecialchars($student['bus_number']); ?></div>
                        <div class="bus-route">
                            <i class="fas fa-route me-2"></i>
                            <?php echo htmlspecialchars($student['route_name']); ?>
                        </div>
                        <div class="mt-3">
                            <p class="mb-1">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                From: <?php echo htmlspecialchars($student['start_point']); ?>
                            </p>
                            <p class="mb-1">
                                <i class="fas fa-map-marker me-2"></i>
                                To: <?php echo htmlspecialchars($student['end_point']); ?>
                            </p>
                            <p class="mb-1">
                                <i class="fas fa-chair me-2"></i>
                                Seat: <?php echo htmlspecialchars($student['seat_number']); ?>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-user-tie me-2"></i>
                                Driver: <?php echo htmlspecialchars($student['driver_first_name'] . ' ' . $student['driver_last_name']); ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>No Bus Assigned</strong>
                        <p class="mb-0 mt-2">You haven't been assigned to a bus yet. Please contact the administration.</p>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h5>Quick Actions</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="fees.php?action=pay" class="action-btn">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Pay Fees</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="applications.php?action=new" class="action-btn">
                                <i class="fas fa-plus-circle"></i>
                                <span>New Application</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="bus_card.php" class="action-btn">
                                <i class="fas fa-download"></i>
                                <span>Download Card</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="attendance.php" class="action-btn">
                                <i class="fas fa-calendar-alt"></i>
                                <span>View Attendance</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-lg-8">
                <!-- Fee Status Chart -->
                <div class="chart-container">
                    <h5 class="mb-3">Fee Payment Status</h5>
                    <canvas id="feeChart" height="100"></canvas>
                </div>

                <div class="row">
                    <!-- Upcoming Schedule -->
                    <div class="col-md-6">
                        <div class="quick-actions">
                            <h5>Upcoming Schedule</h5>
                            <?php if (!empty($upcoming_schedules)): ?>
                                <?php foreach ($upcoming_schedules as $schedule): ?>
                                    <div class="schedule-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="schedule-date">
                                                    <?php echo $functions->formatDate($schedule['schedule_date'], 'M j'); ?>
                                                </div>
                                                <div class="schedule-time">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('h:i A', strtotime($schedule['departure_time'])); ?>
                                                </div>
                                            </div>
                                            <span class="badge bg-primary"><?php echo $schedule['trip_type']; ?></span>
                                        </div>
                                        <div class="mt-2 small">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?php echo htmlspecialchars($schedule['notes']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-times fa-2x mb-3"></i>
                                    <p>No upcoming schedule found</p>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="schedule.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-calendar-alt me-2"></i> View Full Schedule
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-md-6">
                        <div class="quick-actions">
                            <h5>Recent Activity</h5>
                            <?php
                            $db->prepare("
                                SELECT * FROM activity_logs 
                                WHERE user_id = :user_id 
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
                                            <div class="d-flex align-items-start">
                                                <div class="flex-shrink-0">
                                                    <span class="badge bg-light text-dark">
                                                        <i class="fas fa-<?php 
                                                            switch ($activity['action']) {
                                                                case 'LOGIN': echo 'sign-in-alt'; break;
                                                                case 'FEE_PAYMENT': echo 'money-bill-wave'; break;
                                                                case 'APPLICATION_SUBMITTED': echo 'file-upload'; break;
                                                                case 'PROFILE_UPDATE': echo 'user-edit'; break;
                                                                default: echo 'circle';
                                                            }
                                                        ?>"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                                    <p class="mb-1 small text-muted"><?php echo htmlspecialchars($activity['details']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo $functions->timeAgo($activity['timestamp']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-history fa-2x mb-3"></i>
                                    <p>No recent activity</p>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="activity.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-history me-2"></i> View All Activity
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-5 pt-4 border-top">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i> Secure Student Dashboard • 
                        <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-muted">
                        Logged in as: <?php echo htmlspecialchars($student['university_id']); ?> • 
                        IP: <?php echo $security->getClientIP(); ?>
                    </p>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Dashboard Scripts -->
    <script>
        $(document).ready(function() {
            // Fee Status Chart
            const feeCtx = document.getElementById('feeChart').getContext('2d');
            const feeChart = new Chart(feeCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Paid', 'Pending', 'Overdue'],
                    datasets: [{
                        data: [
                            <?php echo $fee_stats['paid_count'] ?: 0; ?>,
                            <?php echo $fee_stats['pending_count'] ?: 0; ?>,
                            <?php echo $fee_stats['overdue_count'] ?: 0; ?>
                        ],
                        backgroundColor: [
                            '#27ae60',
                            '#f39c12',
                            '#e74c3c'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.raw;
                                    return label;
                                }
                            }
                        }
                    }
                }
            });

            // Mark notifications as read on click
            $('.notification-item').click(function(e) {
                e.preventDefault();
                $(this).removeClass('unread');
            });

            // Auto-refresh every 60 seconds
            setInterval(function() {
                $.ajax({
                    url: 'check_updates.php',
                    method: 'GET',
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.new_notifications > 0) {
                            // Update notification badge
                            const badge = $('.notification-badge');
                            let currentCount = parseInt(badge.text()) || 0;
                            badge.text(currentCount + data.new_notifications);
                            
                            // Show alert
                            if (data.new_notifications > 0 && !document.hidden) {
                                showNotification('New notification received');
                            }
                        }
                    }
                });
            }, 60000);

            // Show notification
            function showNotification(message) {
                if ("Notification" in window && Notification.permission === "granted") {
                    new Notification(message);
                }
            }

            // Request notification permission
            if ("Notification" in window && Notification.permission === "default") {
                Notification.requestPermission();
            }

            // Security: Warn before leaving if there are unsaved changes
            let unsavedChanges = false;
            $(document).on('change', 'input, select, textarea', function() {
                unsavedChanges = true;
            });

            window.addEventListener('beforeunload', function(e) {
                if (unsavedChanges) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                }
            });

            // Auto-hide alerts after 5 seconds
            $('.alert').delay(5000).fadeOut('slow');

            // Mobile sidebar toggle
            $('.sidebar-toggle').click(function() {
                $('.sidebar').toggleClass('active');
                $('.main-content').toggleClass('shifted');
            });
        });
    </script>
</body>
</html>
<?php
// Log dashboard view
$functions->logActivity($user_id, 'DASHBOARD_VIEW', 'Student dashboard viewed');
?>