<?php
include 'connection.php';
session_start();

// Fetch all months from database
$months_sql = "SELECT month_name FROM months ORDER BY id";
$months_result = $conn->query($months_sql);
$all_months = [];
while ($month = $months_result->fetch_assoc()) {
    $all_months[] = $month['month_name'];
}

$message = '';
$message_type = '';
$student_info = null;
$selected_seat = null;
$fee_status = [];
$pending_months = [];

// Handle university ID verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_university_id'])) {
    $university_id = trim($_POST['university_id']);

    if (!empty($university_id)) {
        // Get student information
        $sql = "SELECT s.*, 
                GROUP_CONCAT(CONCAT(m.month_name, ':', fp.status)) as fee_data
                FROM students s 
                LEFT JOIN fee_payments fp ON s.id = fp.student_id 
                LEFT JOIN months m ON fp.month_id = m.id 
                WHERE s.university_id = ? 
                GROUP BY s.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $university_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $student_info = $result->fetch_assoc();
            $_SESSION['verified_student'] = $student_info;

            // Parse fee data - FIXED: Check if fee_data exists and is string
            $fee_data = $student_info['fee_data'];
            $fee_payments = [];
            if ($fee_data && is_string($fee_data)) {
                $payments = explode(',', $fee_data);
                foreach ($payments as $payment) {
                    if (strpos($payment, ':') !== false) {
                        list($month, $status) = explode(':', $payment, 2);
                        $fee_payments[$month] = $status;
                    }
                }
            }

            // Find pending months for voucher submission
            foreach ($all_months as $month) {
                if (!isset($fee_payments[$month]) || $fee_payments[$month] === 'Pending') {
                    $pending_months[] = $month;
                }
            }

            $fee_status = $fee_payments;
            $message = "Student verified successfully!";
            $message_type = "success";

        } else {
            $message = "University ID not found!";
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Please enter a University ID";
        $message_type = "warning";
    }
}

// Handle fee voucher submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_voucher'])) {
    if (!isset($_SESSION['verified_student'])) {
        $message = "Please verify your University ID first!";
        $message_type = "warning";
    } else {
        $student_info = $_SESSION['verified_student'];
        $submitted_months = isset($_POST['months']) ? $_POST['months'] : [];

        if (empty($submitted_months)) {
            $message = "Please select at least one month!";
            $message_type = "warning";
        } elseif (!isset($_FILES['voucher_image']) || $_FILES['voucher_image']['error'] !== UPLOAD_ERR_OK) {
            $message = "Please upload a valid voucher image!";
            $message_type = "warning";
        } else {
            // Prevent duplicate submission for the same student & months
            $months_check = implode(',', $submitted_months);
            $check_sql = "SELECT * FROM fee_vouchers WHERE student_id = ? AND months_applied = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $student_info['id'], $months_check);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = "You have already submitted a voucher for these months!";
                $message_type = "warning";
                $check_stmt->close();
            } else {
                $check_stmt->close();

                $voucher_image = $_FILES['voucher_image'];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $file_type = mime_content_type($voucher_image['tmp_name']);

                if (!in_array($file_type, $allowed_types)) {
                    $message = "Only JPG, PNG, and GIF images are allowed!";
                    $message_type = "warning";
                } else {
                    // Prepare file upload
                    $file_extension = pathinfo($voucher_image['name'], PATHINFO_EXTENSION);
                    $filename = 'voucher_' . $student_info['university_id'] . '_' . time() . '.' . $file_extension;
                    $upload_path = 'uploads/' . $filename;
                    if (!is_dir('uploads'))
                        mkdir('uploads', 0777, true);

                    if (!move_uploaded_file($voucher_image['tmp_name'], $upload_path)) {
                        $message = "Error uploading image!";
                        $message_type = "danger";
                    } else {
                        // Start transaction
                        $conn->begin_transaction();
                        try {
                            // Device & IP info
                            $mac_address = getMacAddress();
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            $device_info = json_encode([
                                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                                'browser' => 'Unknown',
                                'platform' => 'Unknown'
                            ]);

                            // Insert voucher
                            $months_applied = implode(',', $submitted_months);
                            $sql = "INSERT INTO fee_vouchers 
                                    (student_id, months_applied, voucher_image, mac_address, ip_address, device_info) 
                                    VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("isssss", $student_info['id'], $months_applied, $filename, $mac_address, $ip_address, $device_info);
                            $stmt->execute();
                            $stmt->close();

                            // Update fee_payments as Pending Verification
                            foreach ($submitted_months as $month_name) {
                                $month_name = trim($month_name);
                                $month_sql = "SELECT id FROM months WHERE month_name = ?";
                                $month_stmt = $conn->prepare($month_sql);
                                $month_stmt->bind_param("s", $month_name);
                                $month_stmt->execute();
                                $month_result = $month_stmt->get_result();

                                if ($month_result->num_rows > 0) {
                                    $month_data = $month_result->fetch_assoc();
                                    $month_id = $month_data['id'];

                                    $fee_sql = "INSERT INTO fee_payments (student_id, month_id, status) 
                                                VALUES (?, ?, 'Pending Verification') 
                                                ON DUPLICATE KEY UPDATE status = 'Pending Verification'";
                                    $fee_stmt = $conn->prepare($fee_sql);
                                    $fee_stmt->bind_param("ii", $student_info['id'], $month_id);
                                    $fee_stmt->execute();
                                    $fee_stmt->close();
                                }
                                $month_stmt->close();
                            }

                            $conn->commit();

                            // Redirect to prevent resubmission
                            header("Location: " . $_SERVER['PHP_SELF'] . "?submitted=1");
                            exit();

                        } catch (Exception $e) {
                            $conn->rollback();
                            $message = "Error submitting voucher: " . $e->getMessage();
                            $message_type = "danger";

                            // Delete uploaded file on failure
                            if (file_exists($upload_path))
                                unlink($upload_path);
                        }
                    }
                }
            }
        }
    }
}


// Handle delay application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_delay_request'])) {
    if (!isset($_SESSION['verified_student'])) {
        $message = "Please verify your University ID first!";
        $message_type = "warning";
    } else {
        $student_info = $_SESSION['verified_student'];
        $delay_months = isset($_POST['delay_months']) ? $_POST['delay_months'] : [];
        $reason_for_delay = trim($_POST['reason_for_delay']);
        $delay_period = $_POST['delay_period'];
        $requested_days = $_POST['requested_days'] ?: 0;

        if (empty($delay_months)) {
            $message = "Please select at least one month!";
            $message_type = "warning";
        } elseif (empty($reason_for_delay)) {
            $message = "Please provide reason for delay!";
            $message_type = "warning";
        } else {
            // Check for existing pending application for same months
            $months_check = implode(',', $delay_months);
            $check_sql = "SELECT * FROM fee_delay_applications 
                         WHERE student_id = ? AND months_applied = ? AND status IN ('pending', 'under_review')";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $student_info['id'], $months_check);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = "You already have a pending delay request for these months!";
                $message_type = "warning";
                $check_stmt->close();
            } else {
                $check_stmt->close();

                // Device & IP info
                $mac_address = getMacAddress();
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $device_info = json_encode([
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'browser' => 'Unknown',
                    'platform' => 'Unknown'
                ]);

                // Insert delay application
                $months_applied = implode(',', $delay_months);
                $sql = "INSERT INTO fee_delay_applications 
                        (student_id, university_id, student_name, months_applied, reason_for_delay, 
                         delay_period, requested_days, ip_address, device_info) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "isssssiss",
                    $student_info['id'],
                    $student_info['university_id'],
                    $student_info['name'],
                    $months_applied,
                    $reason_for_delay,
                    $delay_period,
                    $requested_days,
                    $ip_address,
                    $device_info
                );

                if ($stmt->execute()) {
                    // Update fee status for these months to indicate delay request
                    foreach ($delay_months as $month_name) {
                        $month_name = trim($month_name);
                        $month_sql = "SELECT id FROM months WHERE month_name = ?";
                        $month_stmt = $conn->prepare($month_sql);
                        $month_stmt->bind_param("s", $month_name);
                        $month_stmt->execute();
                        $month_result = $month_stmt->get_result();

                        if ($month_result->num_rows > 0) {
                            $month_data = $month_result->fetch_assoc();
                            $month_id = $month_data['id'];

                            // Update fee status to show delay request
                            $fee_sql = "INSERT INTO fee_payments (student_id, month_id, status) 
                                        VALUES (?, ?, 'Delay Requested') 
                                        ON DUPLICATE KEY UPDATE status = 'Delay Requested'";
                            $fee_stmt = $conn->prepare($fee_sql);
                            $fee_stmt->bind_param("ii", $student_info['id'], $month_id);
                            $fee_stmt->execute();
                            $fee_stmt->close();
                        }
                        $month_stmt->close();
                    }

                    $message = "Delay request submitted successfully! It will be reviewed within 24 hours.";
                    $message_type = "success";
                } else {
                    $message = "Error submitting delay request: " . $conn->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
        }
    }
}

// Display success message after redirect
if (isset($_GET['submitted']) && $_GET['submitted'] == '1') {
    $message = "Fee voucher submitted successfully! It will be verified within 24 hours.";
    $message_type = "success";
}


// Handle seat booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_seat'])) {
    $seat_number = $_POST['seat_number'];
    $university_id = $_POST['booking_university_id'];
    $passenger_name = $_POST['passenger_name'];
    $gender = $_POST['gender'];

    // 1️⃣ Check if this user already has a booked seat
    $seat_check_sql = "SELECT * FROM seats WHERE university_id = ? AND is_booked = TRUE";
    $seat_check_stmt = $conn->prepare($seat_check_sql);
    $seat_check_stmt->bind_param("s", $university_id);
    $seat_check_stmt->execute();
    $seat_check_result = $seat_check_stmt->get_result();

    if ($seat_check_result->num_rows > 0) {
        $message = "You have already booked a seat. Only one seat allowed per person.";
        $message_type = "warning";
    } else {
        // 2️⃣ Check if seat is already booked
        $check_sql = "SELECT is_booked FROM seats WHERE seat_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $seat_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $seat_data = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($seat_data && !$seat_data['is_booked']) {
            // 3️⃣ Check user category & fee
            $user_sql = "SELECT s.*, 
                     GROUP_CONCAT(CONCAT(m.month_name, ':', fp.status)) as fee_data
                     FROM students s 
                     LEFT JOIN fee_payments fp ON s.id = fp.student_id 
                     LEFT JOIN months m ON fp.month_id = m.id 
                     WHERE s.university_id = ? 
                     GROUP BY s.id";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("s", $university_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();

            if ($user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                $category = strtolower($user_data['category']);
                $can_book = false;

                if ($category === 'faculty') {
                    $can_book = true;
                } else {
                    // Check fee payments for students
                    $fee_data = $user_data['fee_data'];
                    $fee_payments = [];
                    if ($fee_data && is_string($fee_data)) {
                        $payments = explode(',', $fee_data);
                        foreach ($payments as $payment) {
                            if (strpos($payment, ':') !== false) {
                                list($month, $status) = explode(':', $payment, 2);
                                $fee_payments[$month] = $status;
                            }
                        }
                    }

                    // Check if any current or future month has "Submitted" status
                    $current_month = date('F');
                    $current_year = date('Y');

                    // Get current month index from all_months
                    $current_month_index = array_search($current_month, $all_months);

                    if ($current_month_index !== false) {
                        // Check current and future months
                        for ($i = $current_month_index; $i < count($all_months); $i++) {
                            $month = $all_months[$i];
                            if (isset($fee_payments[$month]) && $fee_payments[$month] === 'Submitted') {
                                $can_book = true;
                                break;
                            }
                        }
                    }
                }

                if ($can_book) {
                    // Check if delay is approved for any current/future month
                    $delay_check = false;
                    if ($current_month_index !== false) {
                        for ($i = $current_month_index; $i < count($all_months); $i++) {
                            $month = $all_months[$i];
                            if (
                                isset($fee_payments[$month]) &&
                                ($fee_payments[$month] === 'Submitted' ||
                                    $fee_payments[$month] === 'Delay Approved')
                            ) {
                                $delay_check = true;
                                break;
                            }
                        }
                    }

                    if ($delay_check || $category === 'faculty') {
                        // 4️⃣ Book the seat
                        $update_sql = "UPDATE seats SET is_booked = TRUE, passenger_name = ?, 
                                      university_id = ?, gender = ?, booking_time = NOW() 
                                      WHERE seat_number = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("ssss", $passenger_name, $university_id, $gender, $seat_number);

                        if ($update_stmt->execute()) {
                            $message = "Seat $seat_number booked successfully for $passenger_name!";
                            $message_type = "success";
                        } else {
                            $message = "Error booking seat: " . $conn->error;
                            $message_type = "danger";
                        }
                        $update_stmt->close();
                    } else {
                        $message = "Cannot book seat. No active fee payment or approved delay found for current period.";
                        $message_type = "warning";
                    }
                }
            } else {
                $message = "User not found. Please verify University ID first.";
                $message_type = "danger";
            }
            $user_stmt->close();
        } else {
            $message = "Seat $seat_number is already booked!";
            $message_type = "warning";
        }
    }

    $seat_check_stmt->close();
}

// Fetch all seats
$sql = "SELECT * FROM seats ORDER BY 
        CAST(SUBSTRING(seat_number, 1, 1) AS UNSIGNED),
        seat_number";
$result = $conn->query($sql);
$seats = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $seats[] = $row;
    }
}

// Function to get MAC address
function getMacAddress()
{
    $mac = 'Unknown';

    // For Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @exec('ipconfig /all', $output);
        foreach ($output as $line) {
            if (preg_match('/Physical Address[^:]*: ([0-9A-F-]+)/i', $line, $matches)) {
                $mac = $matches[1];
                break;
            }
        }
    }
    // For Linux/Unix
    else {
        @exec('/sbin/ifconfig -a', $output);
        foreach ($output as $line) {
            if (preg_match('/ether (([0-9a-f]{2}[:]){5}([0-9a-f]{2}))/i', $line, $matches)) {
                $mac = $matches[1];
                break;
            }
        }
    }

    return $mac;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaster Bus Seat Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>

<body>
    <div class="container mt-4">
        <div class="bus-container">
            <h1 class="text-center mb-4"><i class="fas fa-bus"></i> Coaster Bus Seat Booking</h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- University ID Verification Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-id-card"></i> Verify University ID</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-8">
                            <label for="university_id" class="form-label">University ID</label>
                            <input type="text" class="form-control" id="university_id" name="university_id"
                                placeholder="Enter your University ID" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="verify_university_id" class="btn btn-primary w-100">
                                <i class="fas fa-check"></i> Verify ID
                            </button>
                        </div>
                    </form>

                    <?php if ($student_info): ?>
                        <div class="student-info mt-3">
                            <h6>Student Information:</h6>
                            <p><strong>Name:</strong> <?php echo $student_info['name']; ?></p>
                            <p><strong>University ID:</strong> <?php echo $student_info['university_id']; ?></p>
                            <p><strong>Semester:</strong> <?php echo $student_info['semester']; ?></p>
                            <p><strong>Category:</strong> <?php echo $student_info['category']; ?></p>

                            <!-- Check for delay applications -->
                            <?php
                            $delay_sql = "SELECT * FROM fee_delay_applications 
                     WHERE student_id = ? AND status != 'disapproved'
                     ORDER BY application_date DESC LIMIT 3";
                            $delay_stmt = $conn->prepare($delay_sql);
                            $delay_stmt->bind_param("i", $student_info['id']);
                            $delay_stmt->execute();
                            $delay_result = $delay_stmt->get_result();

                            if ($delay_result->num_rows > 0): ?>
                                <div class="mt-3">
                                    <h6>Delay Applications Status:</h6>
                                    <?php while ($delay = $delay_result->fetch_assoc()):
                                        $status_badge = '';
                                        if ($delay['status'] == 'approved')
                                            $status_badge = 'bg-success';
                                        elseif ($delay['status'] == 'pending')
                                            $status_badge = 'bg-warning';
                                        elseif ($delay['status'] == 'under_review')
                                            $status_badge = 'bg-info';
                                        elseif ($delay['status'] == 'forwarded_to_transport')
                                            $status_badge = 'bg-primary';
                                        elseif ($delay['status'] == 'disapproved')
                                            $status_badge = 'bg-danger';
                                        ?>
                                        <div class="card mb-2">
                                            <div class="card-body p-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>Months:</strong> <?php echo $delay['months_applied']; ?><br>
                                                        <small class="text-muted">Applied:
                                                            <?php echo $delay['application_date']; ?></small>
                                                    </div>
                                                    <span class="badge <?php echo $status_badge; ?>">
                                                        <?php echo str_replace('_', ' ', ucfirst($delay['status'])); ?>
                                                    </span>
                                                </div>
                                                <?php if ($delay['admin_notes']): ?>
                                                    <div class="mt-1">
                                                        <small><strong>Admin Notes:</strong>
                                                            <?php echo $delay['admin_notes']; ?></small>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($delay['status'] == 'forwarded_to_transport'): ?>
                                                    <div class="mt-1">
                                                        <small><strong>Forwarded:</strong>
                                                            <?php echo $delay['forwarded_date']; ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif;
                            $delay_stmt->close();
                            ?>

                            <h6 class="mt-3">Fee Status:</h6>
                            <div class="fee-status">
                                <?php
                                foreach ($all_months as $month) {
                                    $status = isset($fee_status[$month]) ? $fee_status[$month] : 'Pending';
                                    $badge_class = 'badge-pending';
                                    if ($status === 'Submitted')
                                        $badge_class = 'badge-submitted';
                                    elseif ($status === 'Pending Verification')
                                        $badge_class = 'badge-verification';
                                    elseif ($status === 'Delay Requested')
                                        $badge_class = 'badge-delay-requested';
                                    elseif ($status === 'Delay Approved')
                                        $badge_class = 'badge-delay-approved';
                                    elseif ($status === 'Delay Rejected')
                                        $badge_class = 'badge-delay-rejected';
                                    elseif ($status === 'Under Admin Review')
                                        $badge_class = 'badge-delay-under-review';
                                    elseif ($status === 'Under Transport Review')
                                        $badge_class = 'badge-delay-transport-review';

                                    echo "
                <div class='fee-month'>
                    <div>$month</div>
                    <span class='fee-badge $badge_class'>$status</span>
                </div>";
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fee Voucher Submission Section -->
            <?php if ($student_info && !empty($pending_months)): ?>
                <div class="card mb-4 voucher-section">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-receipt"></i> Submit Fee Voucher</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Select Months to Pay:</label>
                                    <div class="months-selection">
                                        <?php foreach ($pending_months as $month): ?>
                                            <div class="form-check month-checkbox">
                                                <input class="form-check-input" type="checkbox" name="months[]"
                                                    value="<?php echo $month; ?>" id="month_<?php echo $month; ?>">
                                                <label class="form-check-label w-100" for="month_<?php echo $month; ?>">
                                                    <strong><?php echo $month; ?></strong>
                                                    <span class="badge bg-warning float-end">Pending</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="voucher_image" class="form-label">Upload Fee Voucher Image</label>
                                    <input type="file" class="form-control" id="voucher_image" name="voucher_image"
                                        accept="image/jpeg,image/jpg,image/png,image/gif" required>
                                    <div class="form-text">
                                        Upload clear image of your fee payment receipt/voucher (JPG, PNG, GIF)
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Important Notes:</h6>
                                        <ul class="mb-0">
                                            <li>Your voucher will be verified within 24 hours</li>
                                            <li>Once approved, fee status will be updated to "Submitted"</li>
                                            <li>You can book seats only for months with "Submitted" status</li>
                                            <li>System tracks your device information for security</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <button type="submit" name="submit_voucher" class="btn btn-success">
                                        <i class="fas fa-upload"></i> Submit Voucher for Verification
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>



            <!-- Delay Application Section -->
            <?php if ($student_info && !empty($pending_months)): ?>
                <div class="card mb-4 delay-section">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0"><i class="fas fa-clock"></i> Request Fee Payment Delay</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Important Information:</h6>
                            <ul class="mb-0">
                                <li>Use this form only if you need extra time to submit fee payment</li>
                                <li>You must provide valid reason for delay</li>
                                <li>Delay request will be reviewed by admin within 24 hours</li>
                                <li>You cannot book seats until your delay request is approved</li>
                                <li>Max delay period: 30 days</li>
                            </ul>
                        </div>

                        <form method="POST" id="delayForm">
                            <input type="hidden" name="student_id" value="<?php echo $student_info['id']; ?>">
                            <input type="hidden" name="university_id" value="<?php echo $student_info['university_id']; ?>">
                            <input type="hidden" name="student_name" value="<?php echo $student_info['name']; ?>">

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Select Months for Delay Request:</label>
                                    <div class="months-selection">
                                        <?php foreach ($pending_months as $month): ?>
                                            <div class="form-check month-checkbox">
                                                <input class="form-check-input" type="checkbox" name="delay_months[]"
                                                    value="<?php echo $month; ?>" id="delay_month_<?php echo $month; ?>">
                                                <label class="form-check-label w-100" for="delay_month_<?php echo $month; ?>">
                                                    <strong><?php echo $month; ?></strong>
                                                    <span class="badge bg-warning float-end">Pending</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="delay_period" class="form-label">Delay Period</label>
                                    <select class="form-select" id="delay_period" name="delay_period" required>
                                        <option value="">Select period...</option>
                                        <option value="7 days">7 days</option>
                                        <option value="14 days">14 days</option>
                                        <option value="21 days">21 days</option>
                                        <option value="30 days">30 days</option>
                                        <option value="custom">Custom (specify below)</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="requested_days" class="form-label">Number of Days (if custom)</label>
                                    <input type="number" class="form-control" id="requested_days" name="requested_days"
                                        min="1" max="60" placeholder="Enter days" disabled>
                                </div>

                                <div class="col-12">
                                    <label for="reason_for_delay" class="form-label">Reason for Delay *</label>
                                    <textarea class="form-control" id="reason_for_delay" name="reason_for_delay" rows="4"
                                        placeholder="Please provide detailed reason for fee payment delay..."
                                        required></textarea>
                                    <div class="form-text">Be specific about your situation. False information may lead to
                                        rejection.</div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Application Process:</h6>
                                        <ul class="mb-0">
                                            <li>Submit this application</li>
                                            <li>Admin will review within 24 hours</li>
                                            <li>You will see status update in your profile</li>
                                            <li>If approved, you can book seats during delay period</li>
                                            <li>If disapproved, you must submit fee voucher immediately</li>
                                            <li>May be forwarded to Transport Office for further review</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                        <label class="form-check-label" for="agree_terms">
                                            I understand that this is a formal request and I will be responsible for any
                                            consequences if I fail to submit fee within the approved delay period.
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <button type="submit" name="submit_delay_request" class="btn btn-warning">
                                        <i class="fas fa-paper-plane"></i> Submit Delay Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bus-grid">
                <?php
                $current_row = '';
                $rows = [];

                // Group seats by row
                foreach ($seats as $seat) {
                    $row = substr($seat['seat_number'], 0, 1);
                    $rows[$row][] = $seat;
                }

                // Generate grid layout
                foreach ($rows as $row_num => $row_seats) {
                    echo '<div class="grid-row grid-row-' . $row_num . '">';
                    echo '<div class="row-label">' . $row_num . '</div>';

                    // Row 1: Special layout with driver
                    if ($row_num == '1') {
                        // Left seats (1A, 1B) - span 1.5 columns each
                        foreach (array_slice($row_seats, 0, 2) as $seat) {
                            $seat_class = 'available';
                            if ($seat['is_booked']) {
                                $seat_class = 'booked';
                                if ($seat['gender'] == 'male')
                                    $seat_class .= ' male';
                                else if ($seat['gender'] == 'female')
                                    $seat_class .= ' female';
                            }

                            echo '<div class="seat ' . $seat_class . '" data-seat="' . $seat['seat_number'] . '" 
                     data-booked="' . ($seat['is_booked'] ? 'true' : 'false') . '">';
                            echo $seat['seat_number'];
                            if ($seat['is_booked']) {
                                echo '<i class="fas fa-lock"></i>';
                                if ($seat['passenger_name']) {
                                    $first_name = explode(' ', $seat['passenger_name'])[0];
                                    echo '<div class="passenger-name">' . htmlspecialchars($first_name) . '</div>';

                                }
                            }
                            echo '</div>';
                        }

                        // Driver area
                        echo '<div class="driver-area">DRIVER</div>';
                    }
                    // Row 3: Door layout
                    else if ($row_num == '3') {
                        // Door area
                        echo '<div class="door-area">DOOR</div>';

                        // Walking area
                        echo '<div class="walking-area"></div>';

                        // Right side seats (3A, 3B)
                        foreach (array_slice($row_seats, 0, 2) as $seat) {
                            $seat_class = 'available';
                            if ($seat['is_booked']) {
                                $seat_class = 'booked';
                                if ($seat['gender'] == 'male')
                                    $seat_class .= ' male';
                                else if ($seat['gender'] == 'female')
                                    $seat_class .= ' female';
                            }

                            echo '<div class="seat ' . $seat_class . '" data-seat="' . $seat['seat_number'] . '" 
                     data-booked="' . ($seat['is_booked'] ? 'true' : 'false') . '">';
                            echo $seat['seat_number'];
                            if ($seat['is_booked']) {
                                echo '<i class="fas fa-lock"></i>';
                                if ($seat['passenger_name']) {
                                    $first_name = explode(' ', $seat['passenger_name'])[0];
                                    echo '<div class="passenger-name">' . htmlspecialchars($first_name) . '</div>';
                                }
                            }
                            echo '</div>';
                        }
                    }
                    // Row 8: Back row with 5 seats
                    else if ($row_num == '9') {
                        // All 5 seats in one row
                        foreach ($row_seats as $seat) {
                            $seat_class = 'available';
                            if ($seat['is_booked']) {
                                $seat_class = 'booked';
                                if ($seat['gender'] == 'male')
                                    $seat_class .= ' male';
                                else if ($seat['gender'] == 'female')
                                    $seat_class .= ' female';
                            }

                            echo '<div class="seat ' . $seat_class . '" data-seat="' . $seat['seat_number'] . '" 
                     data-booked="' . ($seat['is_booked'] ? 'true' : 'false') . '">';
                            echo $seat['seat_number'];
                            if ($seat['is_booked']) {
                                echo '<i class="fas fa-lock"></i>';
                                if ($seat['passenger_name']) {
                                    $first_name = explode(' ', $seat['passenger_name'])[0];
                                    echo '<div class="passenger-name">' . htmlspecialchars($first_name) . '</div>';
                                }
                            }
                            echo '</div>';
                        }
                    }
                    // Regular rows (2, 4, 5, 6, 7)
                    else {
                        // Left side seats (A, B)
                        foreach (array_slice($row_seats, 0, 2) as $seat) {
                            $seat_class = 'available';
                            if ($seat['is_booked']) {
                                $seat_class = 'booked';
                                if ($seat['gender'] == 'male')
                                    $seat_class .= ' male';
                                else if ($seat['gender'] == 'female')
                                    $seat_class .= ' female';
                            }

                            echo '<div class="seat ' . $seat_class . '" data-seat="' . $seat['seat_number'] . '" 
             data-booked="' . ($seat['is_booked'] ? 'true' : 'false') . '">';
                            echo $seat['seat_number'];
                            if ($seat['is_booked']) {
                                echo '<i class="fas fa-lock"></i>';
                                if ($seat['passenger_name']) {
                                    $first_name = explode(' ', $seat['passenger_name'])[0];
                                    echo '<div class="passenger-name">' . htmlspecialchars($first_name) . '</div>';
                                }
                            }
                            echo '</div>';
                        }

                        // Walking area (must be here in DOM order)
                        echo '<div class="walking-area"></div>';

                        // Right side seats (C, D)
                        foreach (array_slice($row_seats, 2, 2) as $seat) {
                            $seat_class = 'available';
                            if ($seat['is_booked']) {
                                $seat_class = 'booked';
                                if ($seat['gender'] == 'male')
                                    $seat_class .= ' male';
                                else if ($seat['gender'] == 'female')
                                    $seat_class .= ' female';
                            }

                            echo '<div class="seat ' . $seat_class . '" data-seat="' . $seat['seat_number'] . '" 
             data-booked="' . ($seat['is_booked'] ? 'true' : 'false') . '">';
                            echo $seat['seat_number'];
                            if ($seat['is_booked']) {
                                echo '<i class="fas fa-lock"></i>';
                                if ($seat['passenger_name']) {
                                    $first_name = explode(' ', $seat['passenger_name'])[0];
                                    echo '<div class="passenger-name">' . htmlspecialchars($first_name) . '</div>';
                                }
                            }
                            echo '</div>';
                        }
                    }


                    echo '</div>';
                }
                ?>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #c0c0c0;"></div>
                    <span>Available Seat</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #4d79ff;"></div>
                    <span>Male Booked</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ff66b2;"></div>
                    <span>Female Booked</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #6c757d;"></div>
                    <span>Booked</span>
                </div>
            </div>

            <!-- Booking Form (initially hidden) -->
            <div class="mt-4" id="booking-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-ticket-alt"></i> Book Selected Seat</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="booking-form">
                            <input type="hidden" name="seat_number" id="selected-seat">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="booking_university_id" class="form-label">University ID</label>
                                    <input type="text" class="form-control" id="booking_university_id"
                                        name="booking_university_id"
                                        value="<?php echo $student_info ? $student_info['university_id'] : ''; ?>"
                                        readonly required>
                                </div>
                                <div class="col-md-6">
                                    <label for="passenger_name" class="form-label">Passenger Name</label>
                                    <input type="text" class="form-control" id="passenger_name" name="passenger_name"
                                        value="<?php echo $student_info ? $student_info['name'] : ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" id="male"
                                                value="male" required>
                                            <label class="form-check-label" for="male">Male</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" id="female"
                                                value="female">
                                            <label class="form-check-label" for="female">Female</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="book_seat" class="btn btn-success">
                                        <i class="fas fa-check"></i> Confirm Booking
                                    </button>
                                    <button type="button" id="cancel-booking" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Admin Login Link -->
            <div class="text-center mt-4">
                <a href="admin.php" class="btn btn-outline-primary">
                    <i class="fas fa-cog"></i> Admin Panel
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const seats = document.querySelectorAll('.seat');
            const selectedSeatInput = document.getElementById('selected-seat');
            const bookingSection = document.getElementById('booking-section');
            const bookingUniversityId = document.getElementById('booking_university_id');
            const passengerName = document.getElementById('passenger_name');
            const cancelBtn = document.getElementById('cancel-booking');
            let selectedSeat = null;

            seats.forEach(seat => {
                seat.addEventListener('click', function () {
                    const isBooked = this.getAttribute('data-booked') === 'true';

                    if (!isBooked) {
                        // Remove selection from all seats
                        seats.forEach(s => s.classList.remove('selected'));

                        // Add selection to clicked seat
                        this.classList.add('selected');
                        selectedSeat = this.getAttribute('data-seat');
                        selectedSeatInput.value = selectedSeat;

                        // Show booking section
                        bookingSection.style.display = 'block';

                        // Scroll to booking section
                        bookingSection.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });

            cancelBtn.addEventListener('click', function () {
                // Remove selection
                seats.forEach(s => s.classList.remove('selected'));
                selectedSeat = null;
                selectedSeatInput.value = '';

                // Hide booking section
                bookingSection.style.display = 'none';
            });

            // Add selection styling to month checkboxes
            document.querySelectorAll('.month-checkbox').forEach(checkbox => {
                checkbox.addEventListener('click', function (e) {
                    if (e.target.type !== 'checkbox') {
                        const checkboxInput = this.querySelector('input[type="checkbox"]');
                        checkboxInput.checked = !checkboxInput.checked;
                    }
                    this.classList.toggle('selected', this.querySelector('input[type="checkbox"]').checked);
                });
            });

            // If student info is available, pre-fill the booking form
            <?php if ($student_info): ?>
                bookingUniversityId.value = '<?php echo $student_info['university_id']; ?>';
                passengerName.value = '<?php echo $student_info['name']; ?>';
            <?php endif; ?>
            // Delay form functionality
            const delayPeriod = document.getElementById('delay_period');
            const requestedDays = document.getElementById('requested_days');

            if (delayPeriod && requestedDays) {
                delayPeriod.addEventListener('change', function () {
                    if (this.value === 'custom') {
                        requestedDays.disabled = false;
                        requestedDays.required = true;
                    } else {
                        requestedDays.disabled = true;
                        requestedDays.required = false;
                        requestedDays.value = '';
                    }
                });
            }
        });

    </script>
</body>

</html>
<?php
$conn->close();
?>