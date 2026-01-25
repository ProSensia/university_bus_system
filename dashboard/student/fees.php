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
           ua.bus_id, b.bus_number, fs.amount, fs.fee_type
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN student_bus_assignments ua ON u.id = ua.student_id AND ua.status = 'active'
    LEFT JOIN buses b ON ua.bus_id = b.id
    LEFT JOIN fee_structures fs ON b.id = fs.bus_id AND fs.status = 'active'
    WHERE u.id = :user_id
");
$db->bind(':user_id', $user_id);
$student = $db->single();

if (!$student) {
    session_destroy();
    $functions->redirect('../../login.php');
}

// Handle fee payment submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_voucher'])) {
        // Validate CSRF token
        if (!$security->validateCSRFToken($_POST['csrf_token'], 'fee_payment')) {
            $errors[] = "Security token invalid. Please refresh the page and try again.";
        }
        
        if (empty($errors)) {
            $month = $security->sanitize($_POST['month']);
            $year = $security->sanitize($_POST['year']);
            $payment_method = $security->sanitize($_POST['payment_method']);
            $transaction_id = $security->sanitize($_POST['transaction_id']);
            $amount = floatval($_POST['amount']);
            
            // Validate amount
            if ($amount <= 0) {
                $errors[] = "Please enter a valid amount.";
            }
            
            // Validate voucher image
            if (!isset($_FILES['voucher_image']) || $_FILES['voucher_image']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Please upload a voucher image.";
            } else {
                list($valid, $upload_errors) = $security->validateFileUpload($_FILES['voucher_image']);
                if (!$valid) {
                    $errors = array_merge($errors, $upload_errors);
                }
            }
            
            if (empty($errors)) {
                $db->beginTransaction();
                try {
                    // Upload voucher image
                    $filename = $security->sanitizeFilename($_FILES['voucher_image']['name']);
                    $upload_path = '../../uploads/vouchers/' . $filename;
                    
                    if (!is_dir('../../uploads/vouchers')) {
                        mkdir('../../uploads/vouchers', 0755, true);
                    }
                    
                    if (!move_uploaded_file($_FILES['voucher_image']['tmp_name'], $upload_path)) {
                        throw new Exception("Failed to upload voucher image.");
                    }
                    
                    // Create fee payment record
                    $db->prepare("
                        INSERT INTO fees (student_id, bus_id, month, year, amount, 
                                         payment_method, transaction_id, voucher_image, 
                                         status, submitted_at)
                        VALUES (:student_id, :bus_id, :month, :year, :amount, 
                               :payment_method, :transaction_id, :voucher_image,
                               'pending', NOW())
                    ");
                    $db->bind(':student_id', $user_id);
                    $db->bind(':bus_id', $student['bus_id']);
                    $db->bind(':month', $month);
                    $db->bind(':year', $year);
                    $db->bind(':amount', $amount);
                    $db->bind(':payment_method', $payment_method);
                    $db->bind(':transaction_id', $transaction_id);
                    $db->bind(':voucher_image', 'uploads/vouchers/' . $filename);
                    $db->execute();
                    
                    $fee_id = $db->lastInsertId();
                    
                    // Create notification for admin
                    $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, priority)
                        VALUES (1, 'New Fee Voucher Submitted', 
                               CONCAT('Student ', :student_name, ' submitted fee voucher for ', :month, ' ', :year), 
                               'info', 'high')
                    ");
                    $db->bind(':student_name', $student['first_name'] . ' ' . $student['last_name']);
                    $db->bind(':month', $month);
                    $db->bind(':year', $year);
                    $db->execute();
                    
                    // Log activity
                    $functions->logActivity($user_id, 'FEE_VOUCHER_SUBMITTED', 
                        "Submitted fee voucher for $month $year - Amount: $amount");
                    
                    $db->commit();
                    
                    $success = "Fee voucher submitted successfully! It will be verified by admin within 24 hours.";
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    
                    // Delete uploaded file if exists
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                    
                    $errors[] = "Failed to submit voucher: " . $e->getMessage();
                }
            }
        }
    }
}

// Get current fee structure
$current_fee = $student['amount'] ?: 0;
$fee_type = $student['fee_type'] ?: 'monthly';

// Fetch fee history
$db->prepare("
    SELECT f.*, 
           CASE 
               WHEN f.status = 'paid' THEN 'success'
               WHEN f.status = 'pending' THEN 'warning'
               WHEN f.status = 'overdue' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM fees f
    WHERE f.student_id = :user_id
    ORDER BY f.year DESC, FIELD(f.month, 'January', 'February', 'March', 'April', 
           'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December')
");
$db->bind(':user_id', $user_id);
$fee_history = $db->resultSet();

// Calculate statistics
$total_paid = 0;
$total_pending = 0;
$total_overdue = 0;

foreach ($fee_history as $fee) {
    if ($fee['status'] === 'paid') {
        $total_paid += $fee['amount'];
    } elseif ($fee['status'] === 'pending') {
        $total_pending += $fee['amount'];
    } elseif ($fee['status'] === 'overdue') {
        $total_overdue += $fee['amount'];
    }
}

// Get pending fees
$db->prepare("
    SELECT * FROM fees 
    WHERE student_id = :user_id 
    AND status IN ('pending', 'overdue')
    ORDER BY due_date ASC
");
$db->bind(':user_id', $user_id);
$pending_fees = $db->resultSet();

// Generate CSRF token
$csrf_token = $security->generateCSRFToken('fee_payment');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management - Student Dashboard</title>
    
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
        
        .fee-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
        }
        
        .stat-box.paid {
            border-top: 4px solid var(--success-color);
        }
        
        .stat-box.pending {
            border-top: 4px solid var(--warning-color);
        }
        
        .stat-box.overdue {
            border-top: 4px solid var(--danger-color);
        }
        
        .stat-box.total {
            border-top: 4px solid var(--secondary-color);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-box.paid .stat-icon {
            color: var(--success-color);
        }
        
        .stat-box.pending .stat-icon {
            color: var(--warning-color);
        }
        
        .stat-box.overdue .stat-icon {
            color: var(--danger-color);
        }
        
        .stat-box.total .stat-icon {
            color: var(--secondary-color);
        }
        
        .stat-amount {
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
        
        .fee-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-fees {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-fees thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 15px;
        }
        
        .table-fees tbody tr {
            transition: background 0.3s ease;
        }
        
        .table-fees tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .voucher-preview {
            max-width: 200px;
            border-radius: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .voucher-preview:hover {
            transform: scale(1.05);
        }
        
        .modal-voucher {
            max-width: 100%;
            border-radius: 8px;
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
            
            .fee-stats {
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
            
            .fee-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar (Same as profile.php) -->
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
            <a class="nav-link active" href="fees.php">
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
                <h2 class="fw-bold" style="color: var(--primary-color);">Fee Management</h2>
                <p class="text-muted mb-0">Manage your bus fee payments and track payment history</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">
                    <i class="fas fa-plus-circle me-2"></i> Submit Payment
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

        <!-- Fee Statistics -->
        <div class="fee-stats">
            <div class="stat-box paid">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-amount text-success">
                    Rs. <?php echo number_format($total_paid, 2); ?>
                </div>
                <div class="stat-label">Total Paid</div>
            </div>
            
            <div class="stat-box pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-amount text-warning">
                    Rs. <?php echo number_format($total_pending, 2); ?>
                </div>
                <div class="stat-label">Pending Payment</div>
            </div>
            
            <div class="stat-box overdue">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-amount text-danger">
                    Rs. <?php echo number_format($total_overdue, 2); ?>
                </div>
                <div class="stat-label">Overdue Amount</div>
            </div>
            
            <div class="stat-box total">
                <div class="stat-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-amount" style="color: var(--secondary-color);">
                    Rs. <?php echo number_format($current_fee, 2); ?>
                </div>
                <div class="stat-label">Current Fee (<?php echo ucfirst($fee_type); ?>)</div>
            </div>
        </div>

        <div class="row">
            <!-- Pending Fees -->
            <div class="col-lg-6">
                <div class="fee-card">
                    <h5 class="mb-4">
                        <i class="fas fa-clock text-warning me-2"></i> Pending & Overdue Fees
                    </h5>
                    
                    <?php if (!empty($pending_fees)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_fees as $fee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fee['month'] . ' ' . $fee['year']); ?></td>
                                            <td class="fw-bold">Rs. <?php echo number_format($fee['amount'], 2); ?></td>
                                            <td>
                                                <?php echo $functions->formatDate($fee['due_date'], 'M j, Y'); ?>
                                                <?php if (strtotime($fee['due_date']) < time()): ?>
                                                    <span class="badge bg-danger ms-1">Overdue</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge badge-<?php echo $fee['status']; ?>">
                                                    <?php echo ucfirst($fee['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Please pay overdue fees immediately to avoid service suspension.
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>No Pending Fees</h5>
                            <p class="text-muted">All your fees are up to date.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fee Structure -->
            <div class="col-lg-6">
                <div class="fee-card">
                    <h5 class="mb-4">
                        <i class="fas fa-receipt text-primary me-2"></i> Fee Structure
                    </h5>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Bus Fee (<?php echo ucfirst($fee_type); ?>)</h6>
                                <p class="text-muted mb-0">Bus <?php echo htmlspecialchars($student['bus_number'] ?: 'N/A'); ?></p>
                            </div>
                            <div class="text-end">
                                <h4 class="text-primary mb-0">Rs. <?php echo number_format($current_fee, 2); ?></h4>
                                <small class="text-muted">per <?php echo $fee_type; ?></small>
                            </div>
                        </div>
                        
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: <?php echo min(($total_paid / ($current_fee * 12)) * 100, 100); ?>%"></div>
                        </div>
                        <small class="text-muted">Annual payment progress</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i> Payment Instructions:</h6>
                        <ol class="mb-0">
                            <li>Fee is due on the 5th of each month</li>
                            <li>Late payments incur a 5% penalty after 7 days</li>
                            <li>Submit clear image of payment receipt/voucher</li>
                            <li>Keep transaction ID for reference</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fee History -->
        <div class="fee-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">
                    <i class="fas fa-history text-secondary me-2"></i> Payment History
                </h5>
                <div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="printFeeHistory()">
                        <i class="fas fa-print me-2"></i> Print History
                    </button>
                </div>
            </div>
            
            <?php if (!empty($fee_history)): ?>
                <div class="table-responsive">
                    <table class="table table-fees">
                        <thead>
                            <tr>
                                <th>Month/Year</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Transaction ID</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                                <th>Voucher</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fee_history as $fee): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($fee['month']); ?></strong>
                                        <div class="small text-muted"><?php echo $fee['year']; ?></div>
                                    </td>
                                    <td class="fw-bold">Rs. <?php echo number_format($fee['amount'], 2); ?></td>
                                    <td><?php echo ucfirst($fee['payment_method']); ?></td>
                                    <td>
                                        <code><?php echo htmlspecialchars($fee['transaction_id']); ?></code>
                                    </td>
                                    <td><?php echo $functions->formatDate($fee['submitted_at']); ?></td>
                                    <td>
                                        <span class="status-badge badge-<?php echo $fee['status']; ?>">
                                            <?php echo ucfirst($fee['status']); ?>
                                        </span>
                                        <?php if ($fee['verified_at']): ?>
                                            <div class="small text-muted">
                                                Verified: <?php echo $functions->formatDate($fee['verified_at']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fee['voucher_image']): ?>
                                            <img src="../../<?php echo htmlspecialchars($fee['voucher_image']); ?>" 
                                                 class="voucher-preview" 
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#voucherModal"
                                                 data-voucher-src="../../<?php echo htmlspecialchars($fee['voucher_image']); ?>"
                                                 alt="Voucher">
                                        <?php else: ?>
                                            <span class="text-muted">No voucher</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fee['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="resubmitVoucher(<?php echo $fee['id']; ?>)">
                                                <i class="fas fa-redo me-1"></i> Resubmit
                                            </button>
                                        <?php elseif ($fee['status'] === 'paid'): ?>
                                            <a href="receipt.php?id=<?php echo $fee['id']; ?>" 
                                               class="btn btn-sm btn-outline-success" target="_blank">
                                                <i class="fas fa-receipt me-1"></i> Receipt
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Fee history pagination">
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
                    <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                    <h5>No Payment History</h5>
                    <p class="text-muted">You haven't submitted any fee payments yet.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">
                        <i class="fas fa-plus-circle me-2"></i> Submit Your First Payment
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Fee Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="month" class="form-label">Month *</label>
                                <select class="form-select" id="month" name="month" required>
                                    <option value="">Select Month</option>
                                    <?php
                                    $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                              'July', 'August', 'September', 'October', 'November', 'December'];
                                    foreach ($months as $month) {
                                        echo "<option value=\"$month\">$month</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="year" class="form-label">Year *</label>
                                <select class="form-select" id="year" name="year" required>
                                    <option value="">Select Year</option>
                                    <?php
                                    $current_year = date('Y');
                                    for ($i = $current_year - 1; $i <= $current_year + 1; $i++) {
                                        echo "<option value=\"$i\">$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">Amount (PKR) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           value="<?php echo $current_fee; ?>" min="1" step="0.01" required>
                                </div>
                                <div class="form-text">Standard fee: Rs. <?php echo number_format($current_fee, 2); ?></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="payment_method" class="form-label">Payment Method *</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Select Method</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="easypaisa">Easypaisa</option>
                                    <option value="jazzcash">JazzCash</option>
                                    <option value="cash">Cash</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="transaction_id" class="form-label">Transaction ID *</label>
                                <input type="text" class="form-control" id="transaction_id" name="transaction_id" required>
                                <div class="form-text">Enter the transaction/reference number from your payment</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="voucher_image" class="form-label">Payment Voucher *</label>
                                <input type="file" class="form-control" id="voucher_image" name="voucher_image" 
                                       accept="image/*" required>
                                <div class="form-text">Upload clear image of payment receipt/voucher</div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Important:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Ensure voucher image is clear and readable</li>
                                        <li>Transaction ID must match the payment receipt</li>
                                        <li>Payment verification takes 24-48 hours</li>
                                        <li>Keep original receipt until payment is verified</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_voucher" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i> Submit Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Voucher Preview Modal -->
    <div class="modal fade" id="voucherModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalVoucherImage" class="modal-voucher" alt="Voucher Image">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="downloadVoucher" class="btn btn-primary" download>
                        <i class="fas fa-download me-2"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Fee Management Script -->
    <script>
        $(document).ready(function() {
            // Voucher preview modal
            $('.voucher-preview').click(function() {
                const voucherSrc = $(this).data('voucher-src');
                $('#modalVoucherImage').attr('src', voucherSrc);
                $('#downloadVoucher').attr('href', voucherSrc);
            });

            // Auto-set current month and year
            const now = new Date();
            const month = now.toLocaleString('default', { month: 'long' });
            const year = now.getFullYear();
            
            $('#month').val(month);
            $('#year').val(year);

            // Validate amount
            $('#amount').change(function() {
                const standardFee = <?php echo $current_fee; ?>;
                const enteredFee = parseFloat($(this).val());
                
                if (enteredFee < standardFee) {
                    alert(`Warning: Standard fee is Rs. ${standardFee.toFixed(2)}. Underpayment may be rejected.`);
                }
            });

            // Print fee history
            window.printFeeHistory = function() {
                const printContent = $('.fee-card').html();
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                    <head>
                        <title>Fee History - <?php echo htmlspecialchars($student['first_name']); ?></title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                            th { background: #f5f5f5; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .footer { margin-top: 30px; text-align: right; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>Fee Payment History</h2>
                            <p>Student: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                            <p>University ID: <?php echo htmlspecialchars($student['university_id']); ?></p>
                            <p>Generated: ${new Date().toLocaleString()}</p>
                        </div>
                        ${printContent}
                        <div class="footer">
                            <p>Generated by <?php echo APP_NAME; ?></p>
                        </div>
                    </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            };

            // Resubmit voucher function
            window.resubmitVoucher = function(feeId) {
                if (confirm('Are you sure you want to resubmit this voucher?')) {
                    // In production, this would make an AJAX call
                    alert('Resubmission feature would refresh the voucher submission.');
                }
            };

            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            // Show payment modal if there are errors
            <?php if (!empty($errors) && isset($_POST['submit_voucher'])): ?>
                $('#paymentModal').modal('show');
            <?php endif; ?>

            // Auto-hide alerts after 5 seconds
            $('.alert').delay(5000).fadeOut('slow');
        });
    </script>
</body>
</html>
<?php
// Log fee page view
$functions->logActivity($user_id, 'FEE_PAGE_VIEW', 'Fee management page viewed');
?>