<?php
// Add output buffering at the VERY TOP to prevent header errors
ob_start();

include 'connection.php';
session_start();

// Simple authentication
$admin_username = "momin";
$admin_password = "mominkhan@123";

// Check if user is logging in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
    } else {
        $error = "Invalid credentials!";
    }
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    ob_end_clean(); // Clear buffer before showing login
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="card-title mb-0 text-center"><i class="fas fa-lock"></i> Admin Login</h4>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" name="login" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle actions
$action_message = '';
$action_type = '';

// Download Sample Excel File
if (isset($_GET['download_sample'])) {
    ob_end_clean(); // Clear buffer before headers
    
    // Get months dynamically
    $months_sql = "SELECT * FROM months ORDER BY id";
    $months_result = $conn->query($months_sql);
    $months = [];
    while ($month = $months_result->fetch_assoc()) {
        $months[] = $month;
    }

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="sample_students_template.xls"');
    header('Cache-Control: max-age=0');

    // Create sample data
    echo "Sno\tName\tUniversity ID\tSemester\tCategory\t";
    foreach ($months as $month) {
        echo $month['month_name'] . "\t";
    }
    echo "\n";

    // Sample student data
    $sample_students = [
        [1, "John Doe", "UNI001", "5th", "Student"],
        [2, "Jane Smith", "UNI002", "4th", "Student"],
        [3, "Dr. Robert Brown", "UNI003", "N/A", "Faculty"]
    ];

    foreach ($sample_students as $student) {
        echo $student[0] . "\t"; // Sno
        echo $student[1] . "\t"; // Name
        echo $student[2] . "\t"; // University ID
        echo $student[3] . "\t"; // Semester
        echo $student[4] . "\t"; // Category

        // Add sample fee status
        foreach ($months as $index => $month) {
            $status = ($index % 2 == 0) ? "Submitted" : "Pending";
            echo $status . "\t";
        }
        echo "\n";
    }

    exit;
}

// Export to Excel
if (isset($_GET['export_excel'])) {
    ob_end_clean(); // Clear buffer before headers
    
    // Get months dynamically
    $months_sql = "SELECT * FROM months ORDER BY id";
    $months_result = $conn->query($months_sql);
    $months = [];
    while ($month = $months_result->fetch_assoc()) {
        $months[] = $month;
    }

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="students_data_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    // Get students data
    $students_sql = "SELECT s.* FROM students s ORDER BY s.sno";
    $students_result = $conn->query($students_sql);

    echo "Sno\tName\tUniversity ID\tSemester\tCategory\t";
    foreach ($months as $month) {
        echo $month['month_name'] . "\t";
    }
    echo "\n";

    while ($student = $students_result->fetch_assoc()) {
        // Get fee status for each month
        $fee_sql = "SELECT m.month_name, fp.status 
                   FROM fee_payments fp 
                   JOIN months m ON fp.month_id = m.id 
                   WHERE fp.student_id = ? 
                   ORDER BY m.id";
        $fee_stmt = $conn->prepare($fee_sql);
        $fee_stmt->bind_param("i", $student['id']);
        $fee_stmt->execute();
        $fee_result = $fee_stmt->get_result();

        $fee_status = [];
        while ($fee = $fee_result->fetch_assoc()) {
            $fee_status[$fee['month_name']] = $fee['status'];
        }
        $fee_stmt->close();

        echo $student['sno'] . "\t";
        echo $student['name'] . "\t";
        echo $student['university_id'] . "\t";
        echo $student['semester'] . "\t";
        echo $student['category'] . "\t";

        foreach ($months as $month) {
            echo isset($fee_status[$month['month_name']]) ? $fee_status[$month['month_name']] : 'Pending';
            echo "\t";
        }
        echo "\n";
    }
    exit;
}

// Import from Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['excel_file']['tmp_name'];
        $file_name = $_FILES['excel_file']['name'];

        if (pathinfo($file_name, PATHINFO_EXTENSION) === 'xls' || pathinfo($file_name, PATHINFO_EXTENSION) === 'xlsx') {
            // Read the file
            $file_data = file_get_contents($file_tmp_path);
            $lines = explode("\n", $file_data);

            // Get header and months
            $headers = explode("\t", trim($lines[0]));
            $month_columns = array_slice($headers, 5);

            $success_count = 0;
            $error_count = 0;

            // Process each row
            for ($i = 1; $i < count($lines); $i++) {
                if (empty(trim($lines[$i])))
                    continue;

                $row_data = explode("\t", trim($lines[$i]));
                if (count($row_data) < 5)
                    continue;

                $sno = $row_data[0];
                $name = $row_data[1];
                $university_id = $row_data[2];
                $semester = $row_data[3];
                $category = $row_data[4];

                // Check if student already exists
                $check_sql = "SELECT id FROM students WHERE university_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $university_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    // Update existing student
                    $student = $check_result->fetch_assoc();
                    $student_id = $student['id'];
                    $update_sql = "UPDATE students SET sno = ?, name = ?, semester = ?, category = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("isssi", $sno, $name, $semester, $category, $student_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    // Insert new student
                    $insert_sql = "INSERT INTO students (sno, name, university_id, semester, category) VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("issss", $sno, $name, $university_id, $semester, $category);

                    if ($insert_stmt->execute()) {
                        $student_id = $insert_stmt->insert_id;
                    } else {
                        $error_count++;
                        continue;
                    }
                    $insert_stmt->close();
                }

                // Process fee payments
                foreach ($month_columns as $index => $month_name) {
                    $month_index = $index + 5;
                    $status = isset($row_data[$month_index]) ? $row_data[$month_index] : 'Pending';

                    // Get month ID
                    $month_sql = "SELECT id FROM months WHERE month_name = ?";
                    $month_stmt = $conn->prepare($month_sql);
                    $month_stmt->bind_param("s", $month_name);
                    $month_stmt->execute();
                    $month_result = $month_stmt->get_result();

                    if ($month_result->num_rows > 0) {
                        $month = $month_result->fetch_assoc();
                        $month_id = $month['id'];

                        // Insert or update fee payment
                        $fee_sql = "INSERT INTO fee_payments (student_id, month_id, status) 
                                   VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE status = ?";
                        $fee_stmt = $conn->prepare($fee_sql);
                        $fee_stmt->bind_param("iiss", $student_id, $month_id, $status, $status);
                        $fee_stmt->execute();
                        $fee_stmt->close();
                    }
                    $month_stmt->close();
                }
                $success_count++;
            }

            $action_message = "Excel file imported successfully! $success_count records processed. $error_count errors.";
            $action_type = "success";
        } else {
            $action_message = "Please upload a valid Excel file (.xls or .xlsx)";
            $action_type = "danger";
        }
    } else {
        $action_message = "Please select a file to upload";
        $action_type = "warning";
    }
}

// Handle voucher verification - SIMPLE FIX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_voucher'])) {
    $action = $_POST['verify_voucher']; // This will be 'approve' or 'reject'
    $voucher_id = isset($_POST['voucher_id']) ? $_POST['voucher_id'] : '';
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';

    if (!empty($voucher_id) && !empty($action)) {
        // Start transaction
        $conn->begin_transaction();

        try {
            $voucher_sql = "SELECT * FROM fee_vouchers WHERE id = ? FOR UPDATE";
            $voucher_stmt = $conn->prepare($voucher_sql);
            $voucher_stmt->bind_param("i", $voucher_id);
            $voucher_stmt->execute();
            $voucher_result = $voucher_stmt->get_result();
            $voucher = $voucher_result->fetch_assoc();

            if ($voucher) {
                if ($action === 'approve') {
                    $months_applied = $voucher['months_applied'];
                    $months_array = explode(',', $months_applied);

                    // Update fee payments for each month
                    foreach ($months_array as $month_name) {
                        $month_name = trim($month_name);

                        // Get month ID
                        $month_sql = "SELECT id FROM months WHERE month_name = ?";
                        $month_stmt = $conn->prepare($month_sql);
                        $month_stmt->bind_param("s", $month_name);
                        $month_stmt->execute();
                        $month_result = $month_stmt->get_result();

                        if ($month_result->num_rows > 0) {
                            $month_data = $month_result->fetch_assoc();
                            $month_id = $month_data['id'];

                            // Update fee payment
                            $fee_sql = "INSERT INTO fee_payments (student_id, month_id, status) 
                                       VALUES (?, ?, 'Submitted') 
                                       ON DUPLICATE KEY UPDATE status = 'Submitted'";
                            $fee_stmt = $conn->prepare($fee_sql);
                            $fee_stmt->bind_param("ii", $voucher['student_id'], $month_id);
                            $fee_stmt->execute();
                            $fee_stmt->close();
                        }
                        $month_stmt->close();
                    }

                    $status = 'approved';
                    $action_message = "Voucher approved successfully! Fee status updated.";
                } else {
                    $status = 'rejected';
                    $action_message = "Voucher rejected.";
                }

                // Update voucher status
                $update_sql = "UPDATE fee_vouchers SET status = ?, admin_notes = ?, processed_date = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssi", $status, $admin_notes, $voucher_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Log admin action
                $student_sql = "SELECT name FROM students WHERE id = ?";
                $student_stmt = $conn->prepare($student_sql);
                $student_stmt->bind_param("i", $voucher['student_id']);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result();

                if ($student_result->num_rows > 0) {
                    $student_data = $student_result->fetch_assoc();

                    $log_sql = "INSERT INTO admin_logs (admin_username, action, target_student, ip_address) VALUES (?, ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_action = "Voucher $status for " . $student_data['name'] . " (Months: " . $voucher['months_applied'] . ")";
                    $log_stmt->bind_param("ssss", $_SESSION['admin_username'], $log_action, $student_data['name'], $_SERVER['REMOTE_ADDR']);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                $student_stmt->close();

                $action_type = "success";
            } else {
                throw new Exception("Voucher not found!");
            }

            $voucher_stmt->close();
            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            $action_message = "Error: " . $e->getMessage();
            $action_type = "danger";
        }
    } else {
        $action_message = "Invalid voucher action request";
        $action_type = "danger";
    }
}

// Handle delay application processing - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_delay'])) {
    $delay_id = isset($_POST['delay_id']) ? $_POST['delay_id'] : '';
    $student_id = isset($_POST['student_id']) ? $_POST['student_id'] : '';
    $months_applied = isset($_POST['months_applied']) ? $_POST['months_applied'] : '';
    $delay_action = isset($_POST['delay_action']) ? $_POST['delay_action'] : '';
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';

    if (!empty($delay_id) && !empty($delay_action)) {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Get delay application
            $delay_sql = "SELECT * FROM fee_delay_applications WHERE id = ? FOR UPDATE";
            $delay_stmt = $conn->prepare($delay_sql);
            $delay_stmt->bind_param("i", $delay_id);
            $delay_stmt->execute();
            $delay_result = $delay_stmt->get_result();
            $delay_app = $delay_result->fetch_assoc();

            if ($delay_app) {
                $status = $delay_action;
                $update_data = [
                    'status' => $status,
                    'admin_notes' => $admin_notes,
                    'admin_username' => $_SESSION['admin_username'],
                    'processed_date' => date('Y-m-d H:i:s')
                ];

                if ($delay_action == 'forwarded_to_transport') {
                    $update_data['forwarded_date'] = date('Y-m-d H:i:s');
                }

                // Update fee payments status based on action
                $months_array = explode(',', $months_applied);
                $fee_status = 'Pending';

                if ($delay_action == 'approved') {
                    $fee_status = 'Delay Approved';
                } elseif ($delay_action == 'disapproved') {
                    $fee_status = 'Delay Rejected';
                } elseif ($delay_action == 'forwarded_to_transport') {
                    $fee_status = 'Under Transport Review';
                } elseif ($delay_action == 'under_review') {
                    $fee_status = 'Under Admin Review';
                }

                // Update fee payments for each month
                foreach ($months_array as $month_name) {
                    $month_name = trim($month_name);

                    // Get month ID
                    $month_sql = "SELECT id FROM months WHERE month_name = ?";
                    $month_stmt = $conn->prepare($month_sql);
                    $month_stmt->bind_param("s", $month_name);
                    $month_stmt->execute();
                    $month_result = $month_stmt->get_result();

                    if ($month_result->num_rows > 0) {
                        $month_data = $month_result->fetch_assoc();
                        $month_id = $month_data['id'];

                        // Update fee payment
                        $fee_sql = "UPDATE fee_payments SET status = ? 
                               WHERE student_id = ? AND month_id = ?";
                        $fee_stmt = $conn->prepare($fee_sql);
                        $fee_stmt->bind_param("sii", $fee_status, $student_id, $month_id);
                        $fee_stmt->execute();
                        $fee_stmt->close();
                    }
                    $month_stmt->close();
                }

                // Update delay application
                $update_sql = "UPDATE fee_delay_applications SET 
                          status = ?, admin_notes = ?, admin_username = ?, 
                          processed_date = ?";
                
                if ($delay_action == 'forwarded_to_transport') {
                    $update_sql .= ", forwarded_date = ? ";
                    $update_sql .= " WHERE id = ?";
                    
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param(
                        "sssssi",
                        $update_data['status'],
                        $update_data['admin_notes'],
                        $update_data['admin_username'],
                        $update_data['processed_date'],
                        $update_data['forwarded_date'],
                        $delay_id
                    );
                } else {
                    $update_sql .= " WHERE id = ?";
                    
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param(
                        "ssssi",
                        $update_data['status'],
                        $update_data['admin_notes'],
                        $update_data['admin_username'],
                        $update_data['processed_date'],
                        $delay_id
                    );
                }
                $update_stmt->execute();
                $update_stmt->close();

                // Log admin action
                $log_sql = "INSERT INTO admin_logs (admin_username, action, target_student, ip_address) 
                       VALUES (?, ?, ?, ?)";
                $log_stmt = $conn->prepare($log_sql);
                $log_action = "Delay application $status for " . $delay_app['student_name'] .
                    " (Months: " . $months_applied . ")";
                $log_stmt->bind_param(
                    "ssss",
                    $_SESSION['admin_username'],
                    $log_action,
                    $delay_app['student_name'],
                    $_SERVER['REMOTE_ADDR']
                );
                $log_stmt->execute();
                $log_stmt->close();

                $action_message = "Delay application processed successfully!";
                $action_type = "success";
            } else {
                throw new Exception("Delay application not found!");
            }

            $delay_stmt->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $action_message = "Error processing delay application: " . $e->getMessage();
            $action_type = "danger";
        }
    } else {
        $action_message = "Invalid delay application request";
        $action_type = "danger";
    }
}

// Add new student with fee management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $sno = $_POST['sno'];
    $name = $_POST['name'];
    $university_id = $_POST['university_id'];
    $semester = $_POST['semester'];
    $category = $_POST['category'];

    // Get selected months and their status
    $selected_months = isset($_POST['selected_months']) ? $_POST['selected_months'] : [];
    $month_status = isset($_POST['month_status']) ? $_POST['month_status'] : [];

    $sql = "INSERT INTO students (sno, name, university_id, semester, category) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $sno, $name, $university_id, $semester, $category);

    if ($stmt->execute()) {
        $student_id = $stmt->insert_id;

        // Get ALL months from database
        $all_months_sql = "SELECT id FROM months ORDER BY id";
        $all_months_result = $conn->query($all_months_sql);

        // Add fee payments for ALL months
        while ($month = $all_months_result->fetch_assoc()) {
            $month_id = $month['id'];

            // Check if this month was selected with a specific status
            $status = 'Pending'; // Default
            if (in_array($month_id, $selected_months) && isset($month_status[$month_id])) {
                $status = $month_status[$month_id];
            }

            $fee_sql = "INSERT INTO fee_payments (student_id, month_id, status) VALUES (?, ?, ?)";
            $fee_stmt = $conn->prepare($fee_sql);
            $fee_stmt->bind_param("iis", $student_id, $month_id, $status);
            $fee_stmt->execute();
            $fee_stmt->close();
        }

        $action_message = "Student added successfully with fee data for all months!";
        $action_type = "success";
    } else {
        $action_message = "Error adding student: " . $conn->error;
        $action_type = "danger";
    }
    $stmt->close();
}

// Update fee status - SINGLE MONTH
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fee'])) {
    $student_id = $_POST['student_id'];
    $month_id = $_POST['month_id'];
    $status = $_POST['status'];

    $sql = "INSERT INTO fee_payments (student_id, month_id, status) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $student_id, $month_id, $status, $status);

    if ($stmt->execute()) {
        $action_message = "Fee status updated successfully!";
        $action_type = "success";
    } else {
        $action_message = "Error updating fee status: " . $conn->error;
        $action_type = "danger";
    }
    $stmt->close();
}

// Bulk update fees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_fees'])) {
    $student_id = $_POST['bulk_student_id'];
    $selected_months = isset($_POST['bulk_selected_months']) ? $_POST['bulk_selected_months'] : [];
    $month_status = isset($_POST['bulk_month_status']) ? $_POST['bulk_month_status'] : [];

    $success = true;
    $conn->begin_transaction();

    try {
        // Update only selected months
        foreach ($selected_months as $month_id) {
            $status = isset($month_status[$month_id]) ? $month_status[$month_id] : 'Pending';

            $sql = "INSERT INTO fee_payments (student_id, month_id, status) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE status = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $student_id, $month_id, $status, $status);

            if (!$stmt->execute()) {
                throw new Exception("Error updating month ID: $month_id");
            }
            $stmt->close();
        }

        $conn->commit();
        $action_message = "Fee status updated successfully for selected months!";
        $action_type = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $action_message = "Error updating fee status: " . $e->getMessage();
        $action_type = "danger";
    }
}

// Add new month - FIXED: Check for duplicate before adding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_month'])) {
    $month_name = $_POST['month_name'];

    // Check if month already exists
    $check_sql = "SELECT id FROM months WHERE month_name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $month_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $action_message = "Month '$month_name' already exists!";
        $action_type = "warning";
        $check_stmt->close();
    } else {
        $check_stmt->close();

        // Start transaction
        $conn->begin_transaction();

        try {
            // 1. Insert the new month
            $sql = "INSERT INTO months (month_name) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $month_name);
            $stmt->execute();
            $new_month_id = $stmt->insert_id;
            $stmt->close();

            // 2. Get all active students
            $students_sql = "SELECT id FROM students WHERE is_active = TRUE";
            $students_result = $conn->query($students_sql);

            $success_count = 0;

            // 3. Prepare the fee payment insert statement
            $fee_sql = "INSERT INTO fee_payments (student_id, month_id, status) VALUES (?, ?, 'Pending')";
            $fee_stmt = $conn->prepare($fee_sql);

            // 4. Insert fee payment records for each student for the new month
            while ($student = $students_result->fetch_assoc()) {
                $fee_stmt->bind_param("ii", $student['id'], $new_month_id);
                if ($fee_stmt->execute()) {
                    $success_count++;
                }
            }

            $fee_stmt->close();

            // Commit the transaction
            $conn->commit();

            $action_message = "Month '$month_name' added successfully! Fee records created for $success_count students.";
            $action_type = "success";

        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $action_message = "Error adding month: " . $e->getMessage();
            $action_type = "danger";
        }
    }
}

// Delete student
if (isset($_GET['delete_student'])) {
    $student_id = $_GET['delete_student'];

    $conn->begin_transaction();

    try {
        // Delete from fee_payments
        $fee_sql = "DELETE FROM fee_payments WHERE student_id = ?";
        $fee_stmt = $conn->prepare($fee_sql);
        $fee_stmt->bind_param("i", $student_id);
        $fee_stmt->execute();
        $fee_stmt->close();

        // Delete from students
        $sql = "DELETE FROM students WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $action_message = "Student deleted successfully!";
        $action_type = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $action_message = "Error deleting student: " . $e->getMessage();
        $action_type = "danger";
    }
}

// Remove seat booking
if (isset($_GET['remove_seat'])) {
    $seat_number = $_GET['remove_seat'];

    $sql = "UPDATE seats SET is_booked = FALSE, passenger_name = NULL, university_id = NULL, gender = NULL, booking_time = NULL WHERE seat_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $seat_number);

    if ($stmt->execute()) {
        $action_message = "Seat $seat_number booking removed successfully!";
        $action_type = "success";
    } else {
        $action_message = "Error removing seat booking: " . $conn->error;
        $action_type = "danger";
    }
    $stmt->close();
}

// Replace seat booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['replace_seat'])) {
    $old_seat = $_POST['old_seat'];
    $new_seat = $_POST['new_seat'];
    $passenger_name = $_POST['passenger_name'];
    $university_id = $_POST['university_id'];
    $gender = $_POST['gender'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Free the old seat
        $free_sql = "UPDATE seats SET is_booked = FALSE, passenger_name = NULL, university_id = NULL, gender = NULL, booking_time = NULL WHERE seat_number = ?";
        $free_stmt = $conn->prepare($free_sql);
        $free_stmt->bind_param("s", $old_seat);
        $free_stmt->execute();
        $free_stmt->close();

        // Book the new seat
        $book_sql = "UPDATE seats SET is_booked = TRUE, passenger_name = ?, university_id = ?, gender = ?, booking_time = NOW() WHERE seat_number = ?";
        $book_stmt = $conn->prepare($book_sql);
        $book_stmt->bind_param("ssss", $passenger_name, $university_id, $gender, $new_seat);
        $book_stmt->execute();
        $book_stmt->close();

        $conn->commit();
        $action_message = "Seat successfully replaced from $old_seat to $new_seat for $passenger_name!";
        $action_type = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $action_message = "Error replacing seat: " . $e->getMessage();
        $action_type = "danger";
    }
}

// Fetch all months
$months_sql = "SELECT * FROM months ORDER BY id";
$months_result = $conn->query($months_sql);
$months = [];
while ($month = $months_result->fetch_assoc()) {
    $months[] = $month;
}

// Fetch all students with their fee status
$students_sql = "SELECT s.* FROM students s ORDER BY s.sno";
$students_result = $conn->query($students_sql);

// Fetch all booked seats
$seats_sql = "SELECT * FROM seats WHERE is_booked = TRUE ORDER BY seat_number";
$seats_result = $conn->query($seats_sql);

// Fetch all available seats for replacement
$available_seats_sql = "SELECT * FROM seats WHERE is_booked = FALSE ORDER BY seat_number";
$available_seats_result = $conn->query($available_seats_sql);

// Fetch pending vouchers count
$pending_count_sql = "SELECT COUNT(*) as count FROM fee_vouchers WHERE status = 'pending'";
$pending_count_result = $conn->query($pending_count_sql);
$pending_count = $pending_count_result->fetch_assoc()['count'];

// Fetch pending delay applications count
$delay_pending_count_sql = "SELECT COUNT(*) as count FROM fee_delay_applications WHERE status = 'pending'";
$delay_pending_result = $conn->query($delay_pending_count_sql);
$delay_pending_count = $delay_pending_result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Bus Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            background-color: #f8f9fa;
            border-bottom-color: #f8f9fa;
        }
        .fee-status-badge {
            cursor: pointer;
            min-width: 100px;
        }
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .bg-pink {
            background-color: #ff66b2 !important;
        }
        .seat-actions {
            min-width: 200px;
        }
        .fee-card {
            transition: all 0.3s ease;
        }
        .fee-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .bulk-fee-btn {
            min-width: 120px;
        }
        .month-checkbox {
            min-height: 80px;
        }
        .export-import-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .voucher-badge {
            font-size: 0.7em;
            margin-left: 5px;
        }
        .tab-pane {
            padding-top: 20px;
        }
        .modal-backdrop {
            z-index: 1040 !important;
        }
        .modal {
            z-index: 1050 !important;
        }
        .nav-link {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-cog"></i> Admin Panel - Bus Booking System</h1>
            <div>
                <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="?logout" class="btn btn-outline-danger btn-sm ms-2">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
                <a href="index.php" class="btn btn-outline-primary btn-sm ms-2">
                    <i class="fas fa-bus"></i> View Booking
                </a>
            </div>
        </div>

        <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($action_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Export/Import Section -->
        <div class="export-import-section">
            <div class="row">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title mb-0"><i class="fas fa-download"></i> Export Data</h6>
                        </div>
                        <div class="card-body">
                            <p>Export all student data with fee status to Excel format.</p>
                            <a href="?export_excel" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="card-title mb-0"><i class="fas fa-file-download"></i> Download Template</h6>
                        </div>
                        <div class="card-body">
                            <p>Download sample Excel template with proper format for importing.</p>
                            <a href="?download_sample" class="btn btn-warning">
                                <i class="fas fa-download"></i> Download Sample
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="card-title mb-0"><i class="fas fa-upload"></i> Import Data</h6>
                        </div>
                        <div class="card-body">
                            <p>Import student data from Excel file using the template format.</p>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="input-group">
                                    <input type="file" class="form-control" name="excel_file" accept=".xls,.xlsx" required>
                                    <button type="submit" name="import_excel" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Import
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" aria-controls="students" aria-selected="true">
                    <i class="fas fa-users"></i> Students & Fees
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vouchers-tab" data-bs-toggle="tab" data-bs-target="#vouchers" type="button" role="tab" aria-controls="vouchers" aria-selected="false">
                    <i class="fas fa-receipt"></i> Fee Vouchers
                    <?php if ($pending_count > 0): ?>
                        <span class="badge bg-danger voucher-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="seats-tab" data-bs-toggle="tab" data-bs-target="#seats" type="button" role="tab" aria-controls="seats" aria-selected="false">
                    <i class="fas fa-chair"></i> Booked Seats
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="false">
                    <i class="fas fa-plus"></i> Add Student
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="months-tab" data-bs-toggle="tab" data-bs-target="#months" type="button" role="tab" aria-controls="months" aria-selected="false">
                    <i class="fas fa-calendar"></i> Manage Months
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="delay-tab" data-bs-toggle="tab" data-bs-target="#delay" type="button" role="tab" aria-controls="delay" aria-selected="false">
                    <i class="fas fa-clock"></i> Delay Applications
                    <?php if ($delay_pending_count > 0): ?>
                        <span class="badge bg-danger voucher-badge"><?php echo $delay_pending_count; ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="adminTabsContent">
            <!-- Students & Fees Tab -->
            <div class="tab-pane fade show active" id="students" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-list"></i> Student List & Fee Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>SNo</th>
                                        <th>Name</th>
                                        <th>University ID</th>
                                        <th>Semester</th>
                                        <th>Category</th>
                                        <?php foreach ($months as $month): ?>
                                            <th><?php echo htmlspecialchars($month['month_name']); ?></th>
                                        <?php endforeach; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Reset students result pointer
                                    $students_result->data_seek(0);
                                    while ($student = $students_result->fetch_assoc()):
                                        // Get fee status for this student
                                        $fee_sql = "SELECT m.id, m.month_name, fp.status 
                                                   FROM months m 
                                                   LEFT JOIN fee_payments fp ON fp.month_id = m.id AND fp.student_id = ?
                                                   ORDER BY m.id";
                                        $fee_stmt = $conn->prepare($fee_sql);
                                        $fee_stmt->bind_param("i", $student['id']);
                                        $fee_stmt->execute();
                                        $fee_result = $fee_stmt->get_result();

                                        $fee_status = [];
                                        while ($fee = $fee_result->fetch_assoc()) {
                                            $fee_status[$fee['id']] = $fee['status'] ? $fee['status'] : 'Pending';
                                        }
                                        $fee_stmt->close();
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['sno']); ?></td>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['university_id']); ?></td>
                                            <td><?php echo htmlspecialchars($student['semester']); ?></td>
                                            <td><?php echo htmlspecialchars($student['category']); ?></td>

                                            <!-- Fee Status Columns -->
                                            <?php foreach ($months as $month):
                                                $status = isset($fee_status[$month['id']]) ? $fee_status[$month['id']] : 'Pending';
                                                $badge_class = 'bg-secondary';
                                                if ($status === 'Submitted') {
                                                    $badge_class = 'bg-success';
                                                } elseif ($status === 'Pending Verification') {
                                                    $badge_class = 'bg-warning text-dark';
                                                } elseif ($status === 'Pending') {
                                                    $badge_class = 'bg-danger';
                                                }
                                                ?>
                                                <td>
                                                    <span class="badge <?php echo $badge_class; ?> fee-status-badge"
                                                        data-bs-toggle="modal" data-bs-target="#feeModal"
                                                        data-student-id="<?php echo $student['id']; ?>"
                                                        data-student-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                        data-month-id="<?php echo $month['id']; ?>"
                                                        data-month-name="<?php echo htmlspecialchars($month['month_name']); ?>"
                                                        data-current-status="<?php echo htmlspecialchars($status); ?>">
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </span>
                                                </td>
                                            <?php endforeach; ?>

                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-info bulk-fee-btn"
                                                        data-bs-toggle="modal" data-bs-target="#bulkFeeModal"
                                                        data-student-id="<?php echo $student['id']; ?>"
                                                        data-student-name="<?php echo htmlspecialchars($student['name']); ?>">
                                                        <i class="fas fa-edit"></i> All Fees
                                                    </button>
                                                    <a href="?delete_student=<?php echo $student['id']; ?>"
                                                        class="btn btn-danger"
                                                        onclick="return confirm('Are you sure you want to delete this student? This will delete all fee records as well.')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delay Applications Tab -->
            <div class="tab-pane fade" id="delay" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-clock"></i> Fee Delay Applications Management</h5>
                    </div>
                    <div class="card-body">
                        <!-- Tab Navigation for Delay Applications -->
                        <ul class="nav nav-pills mb-4" id="delayTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="delay-pending-tab" data-bs-toggle="tab" data-bs-target="#delay-pending" type="button" role="tab">
                                    Pending <span class="badge bg-warning"><?php echo $delay_pending_count; ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="delay-under-review-tab" data-bs-toggle="tab" data-bs-target="#delay-under-review" type="button" role="tab">
                                    Under Review
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="delay-forwarded-tab" data-bs-toggle="tab" data-bs-target="#delay-forwarded" type="button" role="tab">
                                    Forwarded to Transport
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="delay-approved-tab" data-bs-toggle="tab" data-bs-target="#delay-approved" type="button" role="tab">
                                    Approved
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="delay-disapproved-tab" data-bs-toggle="tab" data-bs-target="#delay-disapproved" type="button" role="tab">
                                    Disapproved
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="delayTabContent">
                            <!-- Pending Applications -->
                            <div class="tab-pane fade show active" id="delay-pending">
                                <?php
                                $pending_delays_sql = "SELECT * FROM fee_delay_applications 
                                         WHERE status = 'pending' 
                                         ORDER BY application_date ASC";
                                $pending_delays_result = $conn->query($pending_delays_sql);
                                ?>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="table-warning">
                                            <tr>
                                                <th>Student</th>
                                                <th>University ID</th>
                                                <th>Months</th>
                                                <th>Reason</th>
                                                <th>Delay Period</th>
                                                <th>Application Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($app = $pending_delays_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($app['student_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['university_id']); ?></td>
                                                    <td>
                                                        <?php
                                                        $months = explode(',', $app['months_applied']);
                                                        foreach ($months as $m) {
                                                            echo "<span class='badge bg-primary me-1'>" . htmlspecialchars($m) . "</span>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><small><?php echo htmlspecialchars($app['reason_for_delay']); ?></small></td>
                                                    <td><?php echo htmlspecialchars($app['delay_period']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['application_date']); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-info"
                                                                data-bs-toggle="modal" data-bs-target="#delayActionModal"
                                                                data-app-id="<?php echo $app['id']; ?>"
                                                                data-student-id="<?php echo $app['student_id']; ?>"
                                                                data-student-name="<?php echo htmlspecialchars($app['student_name']); ?>"
                                                                data-months-applied="<?php echo htmlspecialchars($app['months_applied']); ?>">
                                                                <i class="fas fa-edit"></i> Process
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Other status tabs -->
                            <?php
                            $statuses = [
                                'under_review' => 'info',
                                'forwarded_to_transport' => 'primary',
                                'approved' => 'success',
                                'disapproved' => 'danger'
                            ];

                            foreach ($statuses as $status => $color):
                                $sql = "SELECT * FROM fee_delay_applications 
                           WHERE status = '$status' 
                           ORDER BY processed_date DESC";
                                $result = $conn->query($sql);
                                ?>
                                <div class="tab-pane fade" id="delay-<?php echo str_replace('_', '-', $status); ?>">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead class="table-<?php echo $color; ?>">
                                                <tr>
                                                    <th>Student</th>
                                                    <th>University ID</th>
                                                    <th>Months</th>
                                                    <th>Reason</th>
                                                    <th>Status</th>
                                                    <th>Admin Notes</th>
                                                    <th>Processed Date</th>
                                                    <th>Processed By</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($app = $result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($app['student_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($app['university_id']); ?></td>
                                                        <td><?php echo htmlspecialchars($app['months_applied']); ?></td>
                                                        <td><small><?php echo htmlspecialchars($app['reason_for_delay']); ?></small></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $color; ?>">
                                                                <?php echo str_replace('_', ' ', ucfirst($status)); ?>
                                                            </span>
                                                        </td>
                                                        <td><small><?php echo htmlspecialchars($app['admin_notes'] ?: 'N/A'); ?></small></td>
                                                        <td><?php echo htmlspecialchars($app['processed_date']); ?></td>
                                                        <td><?php echo htmlspecialchars($app['admin_username'] ?: 'N/A'); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Vouchers Tab -->
            <div class="tab-pane fade" id="vouchers" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-receipt"></i> Fee Voucher Verification</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Fetch pending vouchers
                        $pending_vouchers_sql = "SELECT fv.*, s.name, s.university_id 
                                               FROM fee_vouchers fv 
                                               JOIN students s ON fv.student_id = s.id 
                                               WHERE fv.status = 'pending' 
                                               ORDER BY fv.submission_date ASC";
                        $pending_vouchers_result = $conn->query($pending_vouchers_sql);
                        ?>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student</th>
                                        <th>University ID</th>
                                        <th>Months</th>
                                        <th>Submission Date</th>
                                        <th>Device Info</th>
                                        <th>Voucher Image</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($voucher = $pending_vouchers_result->fetch_assoc()):
                                        // FIXED: Safely decode JSON data
                                        $device_info = @json_decode($voucher['device_info'], true);
                                        if (empty($device_info) || !is_array($device_info)) {
                                            $device_info = array('browser' => 'Unknown', 'platform' => 'Unknown');
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($voucher['name']); ?></td>
                                            <td><?php echo htmlspecialchars($voucher['university_id']); ?></td>
                                            <td>
                                                <?php
                                                $voucher_months = explode(',', $voucher['months_applied']);
                                                foreach ($voucher_months as $m) {
                                                    echo "<span class='badge bg-primary me-1'>" . htmlspecialchars($m) . "</span>";
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($voucher['submission_date']); ?></td>
                                            <td>
                                                <small>
                                                    <strong>MAC:</strong> <?php echo htmlspecialchars($voucher['mac_address']); ?><br>
                                                    <strong>IP:</strong> <?php echo htmlspecialchars($voucher['ip_address']); ?><br>
                                                    <strong>Browser:</strong> <?php echo htmlspecialchars($device_info['browser'] ?? 'Unknown'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if (file_exists('uploads/' . $voucher['voucher_image'])): ?>
                                                    <a href="uploads/<?php echo htmlspecialchars($voucher['voucher_image']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">File not found</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST">
                                                    <input type="hidden" name="voucher_id" value="<?php echo $voucher['id']; ?>">
                                                    <textarea name="admin_notes" class="form-control form-control-sm mb-2"
                                                        placeholder="Add notes..." rows="2"></textarea>

                                                    <div class="btn-group btn-group-sm">
                                                        <button type="submit" name="verify_voucher" value="approve"
                                                            class="btn btn-success" onclick="return confirmVoucherAction(this)">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button type="submit" name="verify_voucher" value="reject"
                                                            class="btn btn-danger" onclick="return confirmVoucherAction(this)">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Voucher History -->
                        <h5 class="mt-4">Voucher History</h5>
                        <?php
                        $history_vouchers_sql = "SELECT fv.*, s.name, s.university_id 
                                               FROM fee_vouchers fv 
                                               JOIN students s ON fv.student_id = s.id 
                                               WHERE fv.status != 'pending' 
                                               ORDER BY fv.processed_date DESC 
                                               LIMIT 50";
                        $history_vouchers_result = $conn->query($history_vouchers_sql);
                        ?>

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>Student</th>
                                        <th>Months</th>
                                        <th>Status</th>
                                        <th>Processed Date</th>
                                        <th>Admin Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($voucher = $history_vouchers_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($voucher['name']); ?></td>
                                            <td><?php echo htmlspecialchars($voucher['months_applied']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $voucher['status'] == 'approved' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo ucfirst($voucher['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($voucher['processed_date']); ?></td>
                                            <td><small><?php echo htmlspecialchars($voucher['admin_notes'] ?: 'N/A'); ?></small></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booked Seats Tab -->
            <div class="tab-pane fade" id="seats" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-chair"></i> Currently Booked Seats - Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Seat Number</th>
                                        <th>Passenger Name</th>
                                        <th>University ID</th>
                                        <th>Gender</th>
                                        <th>Booking Time</th>
                                        <th class="seat-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Reset seats result pointer
                                    $seats_result->data_seek(0);
                                    while ($seat = $seats_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($seat['seat_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($seat['passenger_name']); ?></td>
                                            <td><?php echo htmlspecialchars($seat['university_id']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $seat['gender'] === 'male' ? 'bg-primary' : 'bg-pink'; ?>">
                                                    <?php echo ucfirst($seat['gender']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($seat['booking_time']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?remove_seat=<?php echo $seat['seat_number']; ?>"
                                                        class="btn btn-danger"
                                                        onclick="return confirm('Are you sure you want to remove this seat booking?')">
                                                        <i class="fas fa-times"></i> Remove
                                                    </a>
                                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal"
                                                        data-bs-target="#replaceModal"
                                                        data-seat-number="<?php echo $seat['seat_number']; ?>"
                                                        data-passenger-name="<?php echo htmlspecialchars($seat['passenger_name']); ?>"
                                                        data-university-id="<?php echo htmlspecialchars($seat['university_id']); ?>"
                                                        data-gender="<?php echo $seat['gender']; ?>">
                                                        <i class="fas fa-exchange-alt"></i> Replace
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Student Tab -->
            <div class="tab-pane fade" id="add" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-plus"></i> Add New Student</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label for="sno" class="form-label">Serial No</label>
                                    <input type="number" class="form-control" id="sno" name="sno" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="university_id" class="form-label">University ID</label>
                                    <input type="text" class="form-control" id="university_id" name="university_id" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="semester" class="form-label">Semester</label>
                                    <input type="text" class="form-control" id="semester" name="semester">
                                </div>
                                <div class="col-md-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="Student">Student</option>
                                        <option value="Faculty">Faculty</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <h6 class="mt-3">Fee Status for Months (Optional - Leave blank for default 'Pending')</h6>
                                    <div class="row">
                                        <?php foreach ($months as $month): ?>
                                            <div class="col-md-3 mb-2">
                                                <div class="card h-100">
                                                    <div class="card-body p-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input month-check" type="checkbox" 
                                                                   name="selected_months[]" value="<?php echo $month['id']; ?>"
                                                                   id="month_<?php echo $month['id']; ?>">
                                                            <label class="form-check-label w-100" for="month_<?php echo $month['id']; ?>">
                                                                <strong><?php echo htmlspecialchars($month['month_name']); ?></strong>
                                                            </label>
                                                        </div>
                                                        <select class="form-select form-select-sm mt-2" 
                                                                name="month_status[<?php echo $month['id']; ?>]"
                                                                id="status_<?php echo $month['id']; ?>" disabled>
                                                            <option value="Pending">Pending</option>
                                                            <option value="Submitted">Submitted</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllMonths(true)">
                                            Select All Months
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllMonths(false)">
                                            Deselect All
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="setAllSelectedMonths('Submitted')">
                                            Mark Selected as Submitted
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="setAllSelectedMonths('Pending')">
                                            Mark Selected as Pending
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="add_student" class="btn btn-success">
                                        <i class="fas fa-save"></i> Add Student
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Manage Months Tab -->
            <div class="tab-pane fade" id="months" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-calendar"></i> Manage Months</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="card-title mb-0">Add New Month</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="month_name"
                                                    placeholder="Enter month name (e.g., March 2026)" required>
                                                <button type="submit" name="add_month" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Add Month
                                                </button>
                                            </div>
                                            <small class="text-muted">Note: This will add fee records for all existing students with 'Pending' status.</small>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="card-title mb-0">Current Months</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach ($months as $index => $month): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                                        <span><?php echo htmlspecialchars($month['month_name']); ?></span>
                                                        <span class="badge bg-secondary">ID: <?php echo $month['id']; ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fee Status Modal -->
    <div class="modal fade" id="feeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Fee Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="modal_student_id">
                        <input type="hidden" name="month_id" id="modal_month_id">

                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="modal_student_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Month</label>
                            <input type="text" class="form-control" id="modal_month_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="Pending">Pending</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Pending Verification">Pending Verification</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_fee" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Fee Update Modal -->
    <div class="modal fade" id="bulkFeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Update All Fees</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="bulk_student_id" id="bulk_student_id">

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="bulk_student_name" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Select Months to Update:</label>
                            <div class="row g-3">
                                <?php foreach ($months as $month): ?>
                                    <div class="col-md-6">
                                        <div class="card h-100 fee-card">
                                            <div class="card-header text-center py-2 bg-light">
                                                <div class="form-check">
                                                    <input class="form-check-input bulk-month-check" type="checkbox" 
                                                           name="bulk_selected_months[]" value="<?php echo $month['id']; ?>"
                                                           id="bulk_month_<?php echo $month['id']; ?>">
                                                    <label class="form-check-label" for="bulk_month_<?php echo $month['id']; ?>">
                                                        <strong><?php echo htmlspecialchars($month['month_name']); ?></strong>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="card-body text-center p-2">
                                                <select class="form-select"
                                                    name="bulk_month_status[<?php echo $month['id']; ?>]" 
                                                    id="bulk_status_<?php echo $month['id']; ?>">
                                                    <option value="Pending">Pending</option>
                                                    <option value="Submitted">Submitted</option>
                                                    <option value="Pending Verification">Pending Verification</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                onclick="selectAllBulkMonths(true)">
                                Select All Months
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="selectAllBulkMonths(false)">
                                Deselect All
                            </button>
                            <button type="button" class="btn btn-success btn-sm" onclick="setBulkAllFees('Submitted')">
                                Mark Selected as Submitted
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="setBulkAllFees('Pending')">
                                Mark Selected as Pending
                            </button>
                            <button type="button" class="btn btn-info btn-sm"
                                onclick="setBulkAllFees('Pending Verification')">
                                Mark Selected as Pending Verification
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="bulk_update_fees" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Selected Fees
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Replace Seat Modal -->
    <div class="modal fade" id="replaceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Replace Seat Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="old_seat" id="replace_old_seat">
                    <input type="hidden" name="passenger_name" id="replace_passenger_name">
                    <input type="hidden" name="university_id" id="replace_university_id">
                    <input type="hidden" name="gender" id="replace_gender">

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Seat</label>
                            <input type="text" class="form-control" id="replace_current_seat" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Passenger</label>
                            <input type="text" class="form-control" id="replace_current_passenger" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="new_seat" class="form-label">New Seat</label>
                            <select class="form-select" id="new_seat" name="new_seat" required>
                                <option value="">Select new seat...</option>
                                <?php
                                // Reset pointer for available seats
                                $available_seats_result->data_seek(0);
                                while ($available_seat = $available_seats_result->fetch_assoc()): ?>
                                    <option value="<?php echo $available_seat['seat_number']; ?>">
                                        <?php echo $available_seat['seat_number']; ?> (Available)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This will move the passenger from the current seat to the
                            new selected seat.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="replace_seat" class="btn btn-warning">
                            <i class="fas fa-exchange-alt"></i> Replace Seat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delay Action Modal -->
    <div class="modal fade" id="delayActionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Delay Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="delay_id" id="modal_delay_app_id">
                    <input type="hidden" name="student_id" id="modal_delay_student_id">
                    <input type="hidden" name="months_applied" id="modal_delay_months_applied">

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="modal_delay_student_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Months Applied</label>
                            <input type="text" class="form-control" id="modal_delay_months_list" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Action</label>
                            <select class="form-select" name="delay_action" id="delay_action_select" required>
                                <option value="">Select action...</option>
                                <option value="under_review">Mark as Under Review</option>
                                <option value="forwarded_to_transport">Forward to Transport Office</option>
                                <option value="approved">Approve Delay</option>
                                <option value="disapproved">Disapprove Delay</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="modal_delay_admin_notes" class="form-label">Admin Notes</label>
                            <textarea class="form-control" id="modal_delay_admin_notes" name="admin_notes" rows="3"
                                placeholder="Add notes or reason for action..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="process_delay" class="btn btn-primary">Process Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Fee modal functionality
            const feeModal = document.getElementById('feeModal');
            if (feeModal) {
                feeModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    document.getElementById('modal_student_id').value = button.getAttribute('data-student-id');
                    document.getElementById('modal_student_name').value = button.getAttribute('data-student-name');
                    document.getElementById('modal_month_id').value = button.getAttribute('data-month-id');
                    document.getElementById('modal_month_name').value = button.getAttribute('data-month-name');

                    // Set current status in select
                    const currentStatus = button.getAttribute('data-current-status');
                    const statusSelect = document.querySelector('#feeModal select[name="status"]');
                    if (statusSelect) {
                        statusSelect.value = currentStatus;
                    }
                });
            }

            // Bulk fee modal functionality
            const bulkFeeModal = document.getElementById('bulkFeeModal');
            if (bulkFeeModal) {
                bulkFeeModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    const studentId = button.getAttribute('data-student-id');
                    document.getElementById('bulk_student_id').value = studentId;
                    document.getElementById('bulk_student_name').value = button.getAttribute('data-student-name');
                });
            }

            // Replace seat modal functionality
            const replaceModal = document.getElementById('replaceModal');
            if (replaceModal) {
                replaceModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    const seatNumber = button.getAttribute('data-seat-number');
                    const passengerName = button.getAttribute('data-passenger-name');
                    const universityId = button.getAttribute('data-university-id');
                    const gender = button.getAttribute('data-gender');

                    document.getElementById('replace_old_seat').value = seatNumber;
                    document.getElementById('replace_passenger_name').value = passengerName;
                    document.getElementById('replace_university_id').value = universityId;
                    document.getElementById('replace_gender').value = gender;
                    document.getElementById('replace_current_seat').value = seatNumber;
                    document.getElementById('replace_current_passenger').value = passengerName;
                });
            }

            // Delay Action Modal
            const delayActionModal = document.getElementById('delayActionModal');
            if (delayActionModal) {
                delayActionModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    document.getElementById('modal_delay_app_id').value = button.getAttribute('data-app-id');
                    document.getElementById('modal_delay_student_id').value = button.getAttribute('data-student-id');
                    document.getElementById('modal_delay_student_name').value = button.getAttribute('data-student-name');
                    document.getElementById('modal_delay_months_applied').value = button.getAttribute('data-months-applied');
                    document.getElementById('modal_delay_months_list').value = button.getAttribute('data-months-applied');
                });
            }

            // Month management functions
            function toggleMonthStatus(checkbox, statusId) {
                const statusSelect = document.getElementById(statusId);
                if (statusSelect) {
                    statusSelect.disabled = !checkbox.checked;
                }
            }

            window.selectAllMonths = function(selectAll) {
                const checkboxes = document.querySelectorAll('input[name="selected_months[]"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll;
                    toggleMonthStatus(checkbox, 'status_' + checkbox.value);
                });
            };

            window.setAllSelectedMonths = function(status) {
                const checkboxes = document.querySelectorAll('input[name="selected_months[]"]:checked');
                checkboxes.forEach(checkbox => {
                    const statusSelect = document.getElementById('status_' + checkbox.value);
                    if (statusSelect && !statusSelect.disabled) {
                        statusSelect.value = status;
                    }
                });
            };

            window.selectAllBulkMonths = function(selectAll) {
                const checkboxes = document.querySelectorAll('.bulk-month-check');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll;
                });
            };

            window.setBulkAllFees = function(status) {
                const checkboxes = document.querySelectorAll('.bulk-month-check:checked');
                checkboxes.forEach(checkbox => {
                    const monthId = checkbox.value;
                    const select = document.getElementById('bulk_status_' + monthId);
                    if (select) {
                        select.value = status;
                    }
                });
            };

            // Enable/disable month status when checkbox is clicked (Add Student tab)
            document.querySelectorAll('.month-check').forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const statusId = 'status_' + this.value;
                    const statusSelect = document.getElementById(statusId);
                    if (statusSelect) {
                        statusSelect.disabled = !this.checked;
                    }
                });
            });

            // Confirm voucher action
            window.confirmVoucherAction = function(button) {
                const action = button.value;
                const confirmMsg = action === 'approve'
                    ? 'Are you sure you want to APPROVE this voucher? This will update fee status to "Submitted".'
                    : 'Are you sure you want to REJECT this voucher?';

                return confirm(confirmMsg);
            };

            // Logout confirmation
            const logoutLink = document.querySelector('a[href="?logout"]');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    if (!confirm('Are you sure you want to logout?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>

</html>

<?php
// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$conn->close();
?>