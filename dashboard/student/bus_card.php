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

// Fetch student data with bus information
$db->prepare("
    SELECT u.*, up.*, 
           ua.bus_id, ua.seat_number, ua.assignment_type,
           b.bus_number, b.type as bus_type, b.registration_number,
           br.route_name, br.start_point, br.end_point,
           c.name as campus_name,
           d.first_name as driver_first_name, d.last_name as driver_last_name,
           d.phone as driver_phone
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

// Generate QR code data
$qr_data = [
    'student_id' => $student['id'],
    'university_id' => $student['university_id'],
    'name' => $student['first_name'] . ' ' . $student['last_name'],
    'bus_number' => $student['bus_number'],
    'seat_number' => $student['seat_number'],
    'valid_from' => date('Y-m-d'),
    'valid_to' => date('Y-m-d', strtotime('+1 year')),
    'timestamp' => time(),
    'hash' => hash_hmac('sha256', $student['id'] . time(), 'QR_SECRET_KEY')
];

$qr_encoded = base64_encode(json_encode($qr_data));
$qr_image_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_encoded);

// Check if bus card exists
$db->prepare("SELECT * FROM bus_cards WHERE student_id = :user_id AND status = 'active'");
$db->bind(':user_id', $user_id);
$existing_card = $db->single();

// Generate new bus card if doesn't exist or expired
if (!$existing_card || strtotime($existing_card['valid_to']) < time()) {
    $db->beginTransaction();
    try {
        // Mark old card as expired
        if ($existing_card) {
            $db->prepare("UPDATE bus_cards SET status = 'expired' WHERE id = :id");
            $db->bind(':id', $existing_card['id']);
            $db->execute();
        }
        
        // Create new bus card
        $card_number = 'BUS-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . date('Y');
        
        $db->prepare("
            INSERT INTO bus_cards (student_id, card_number, qr_data, qr_image, 
                                  valid_from, valid_to, status, created_at)
            VALUES (:student_id, :card_number, :qr_data, :qr_image,
                   :valid_from, :valid_to, 'active', NOW())
        ");
        $db->bind(':student_id', $user_id);
        $db->bind(':card_number', $card_number);
        $db->bind(':qr_data', json_encode($qr_data));
        $db->bind(':qr_image', $qr_image_url);
        $db->bind(':valid_from', date('Y-m-d'));
        $db->bind(':valid_to', date('Y-m-d', strtotime('+1 year')));
        $db->execute();
        
        $db->commit();
        
        // Log card generation
        $functions->logActivity($user_id, 'BUS_CARD_GENERATED', 'Generated new bus card');
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Failed to generate bus card: " . $e->getMessage();
    }
}

// Fetch active bus card
$db->prepare("SELECT * FROM bus_cards WHERE student_id = :user_id AND status = 'active'");
$db->bind(':user_id', $user_id);
$bus_card = $db->single();

// Handle card download request
if (isset($_GET['download'])) {
    // Log download
    $functions->logActivity($user_id, 'BUS_CARD_DOWNLOAD', 'Downloaded bus card');
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="bus_card_' . $student['university_id'] . '.pdf"');
    
    // Generate PDF content (simplified - in production use a PDF library)
    $pdf_content = "Bus Card - " . APP_NAME . "\n\n";
    $pdf_content .= "Name: " . $student['first_name'] . ' ' . $student['last_name'] . "\n";
    $pdf_content .= "University ID: " . $student['university_id'] . "\n";
    $pdf_content .= "Bus Number: " . $student['bus_number'] . "\n";
    $pdf_content .= "Seat Number: " . $student['seat_number'] . "\n";
    $pdf_content .= "Route: " . $student['route_name'] . "\n";
    $pdf_content .= "Valid From: " . date('F j, Y', strtotime($bus_card['valid_from'])) . "\n";
    $pdf_content .= "Valid To: " . date('F j, Y', strtotime($bus_card['valid_to'])) . "\n";
    $pdf_content .= "\nQR Code URL: " . $qr_image_url . "\n";
    
    echo $pdf_content;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Card - Student Dashboard</title>
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- HTML2Canvas for card capture -->
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --university-blue: #003366;
            --university-gold: #FFD700;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
        }
        
        .bus-card-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            padding: 20px;
        }
        
        /* Bus Card Design */
        .bus-card {
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            overflow: hidden;
            position: relative;
            border: 5px solid var(--university-blue);
        }
        
        .card-header {
            background: var(--university-blue);
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--university-gold);
        }
        
        .university-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--university-blue);
            font-size: 2rem;
            border: 3px solid var(--university-gold);
        }
        
        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .card-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .student-photo {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            object-fit: cover;
            border: 3px solid var(--secondary-color);
            margin: 0 auto 20px;
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .info-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            border-left: 3px solid var(--secondary-color);
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .qr-section {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .qr-code {
            width: 150px;
            height: 150px;
            margin: 0 auto 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .qr-code img {
            width: 100%;
            height: 100%;
        }
        
        .validity {
            background: #fff3cd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            margin-top: 15px;
            border: 1px dashed #f39c12;
        }
        
        .card-footer {
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            border-top: 1px solid #eee;
            font-size: 0.8rem;
            color: #666;
        }
        
        /* Controls */
        .card-controls {
            background: white;
            border-radius: 15px;
            padding: 25px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .btn-download {
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .instructions {
            background: #f0f8ff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .card-back {
            background: linear-gradient(145deg, #f8f9fa, #e9ecef);
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            padding: 30px;
            margin-top: 30px;
            border: 5px solid var(--university-blue);
        }
        
        .terms-list {
            font-size: 0.85rem;
            color: #555;
        }
        
        .terms-list li {
            margin-bottom: 8px;
        }
        
        .emergency-info {
            background: #f8d7da;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #f5c6cb;
        }
        
        @media print {
            .card-controls, .btn-print, .btn-download {
                display: none !important;
            }
            
            body {
                background: white !important;
            }
            
            .bus-card, .card-back {
                box-shadow: none !important;
                border: 2px solid #000 !important;
                page-break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            .bus-card-container {
                padding: 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="bus-card-container">
            <!-- Header -->
            <div class="text-center mb-4">
                <h1 class="text-white fw-bold">Digital Bus Card</h1>
                <p class="text-white-50">Your official bus identification card</p>
                <a href="index.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Front of Bus Card -->
            <div class="bus-card" id="busCardFront">
                <div class="card-header">
                    <div class="university-logo">
                        <i class="fas fa-university"></i>
                    </div>
                    <h2 class="card-title"><?php echo APP_NAME; ?></h2>
                    <div class="card-subtitle">Official Bus Identification Card</div>
                </div>
                
                <div class="card-body">
                    <img src="../../<?php echo $student['profile_photo'] ?: 'assets/images/default-avatar.jpg'; ?>" 
                         class="student-photo" alt="Student Photo">
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">University ID</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['university_id']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Bus Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['bus_number'] ?: 'N/A'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Seat Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['seat_number'] ?: 'N/A'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Route</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['route_name'] ?: 'N/A'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Campus</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['campus_name'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    
                    <div class="qr-section">
                        <div class="qr-code">
                            <img src="<?php echo $qr_image_url; ?>" alt="QR Code">
                        </div>
                        <p class="mb-0 small text-muted">Scan QR code for verification</p>
                    </div>
                    
                    <div class="validity">
                        <div class="info-label">Validity</div>
                        <div class="info-value">
                            <?php if ($bus_card): ?>
                                <?php echo date('F j, Y', strtotime($bus_card['valid_from'])); ?> - 
                                <?php echo date('F j, Y', strtotime($bus_card['valid_to'])); ?>
                            <?php else: ?>
                                Not generated
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <div class="row">
                        <div class="col-6 text-start">
                            <small>Card No: <?php echo $bus_card ? $bus_card['card_number'] : 'N/A'; ?></small>
                        </div>
                        <div class="col-6 text-end">
                            <small>Issued: <?php echo date('M Y'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card Controls -->
            <div class="card-controls">
                <h5 class="mb-4 text-center">Card Options</h5>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-download" onclick="downloadCard()">
                        <i class="fas fa-download me-2"></i> Download Card (PDF)
                    </button>
                    
                    <button class="btn btn-outline-primary" onclick="printCard()">
                        <i class="fas fa-print me-2"></i> Print Card
                    </button>
                    
                    <button class="btn btn-outline-secondary" onclick="saveAsImage()">
                        <i class="fas fa-image me-2"></i> Save as Image
                    </button>
                    
                    <button class="btn btn-outline-info" onclick="showQRScanner()">
                        <i class="fas fa-qrcode me-2"></i> Test QR Scanner
                    </button>
                </div>
                
                <div class="instructions">
                    <h6><i class="fas fa-info-circle me-2"></i> Instructions:</h6>
                    <ul class="mb-0 small">
                        <li>Carry this card whenever using the bus</li>
                        <li>Show QR code to driver for verification</li>
                        <li>Keep digital copy on your phone</li>
                        <li>Report lost cards immediately</li>
                    </ul>
                </div>
            </div>
            
            <!-- Back of Bus Card (Terms & Conditions) -->
            <div class="card-back" id="busCardBack">
                <h5 class="text-center mb-4">Terms & Conditions</h5>
                
                <div class="terms-list">
                    <ol>
                        <li>This card is non-transferable and for personal use only.</li>
                        <li>Must be presented upon request by bus driver or staff.</li>
                        <li>Valid only for assigned bus and route.</li>
                        <li>Report lost/stolen cards immediately to administration.</li>
                        <li>Card must be renewed annually or upon bus change.</li>
                        <li>Misuse may result in suspension of bus privileges.</li>
                        <li>QR code contains encrypted student information.</li>
                        <li>Card remains property of <?php echo APP_NAME; ?>.</li>
                    </ol>
                </div>
                
                <div class="emergency-info">
                    <h6><i class="fas fa-phone-alt me-2"></i> Emergency Contact</h6>
                    <p class="mb-2">Bus Driver: <?php echo htmlspecialchars($student['driver_first_name'] . ' ' . $student['driver_last_name']); ?></p>
                    <p class="mb-0">Contact: <?php echo htmlspecialchars($student['driver_phone'] ?: 'N/A'); ?></p>
                </div>
                
                <div class="text-center mt-4">
                    <p class="small text-muted mb-0">
                        For assistance, contact: admin@university.edu<br>
                        <?php echo APP_NAME; ?> • <?php echo date('Y'); ?>
                    </p>
                </div>
            </div>
            
            <!-- Card Status -->
            <div class="alert alert-info mt-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-shield-alt fa-2x me-3"></i>
                    <div>
                        <h6 class="mb-1">Card Security Features</h6>
                        <p class="mb-0 small">Unique QR code • Encrypted data • Tamper-proof design • Digital verification</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Scanner Test Modal -->
    <div class="modal fade" id="qrScannerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">QR Code Scanner Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <div id="qrReader" style="width: 300px; height: 300px; margin: 0 auto;"></div>
                    </div>
                    <div id="qrResult" class="alert alert-info" style="display: none;"></div>
                    <p class="text-muted small">Scan your bus card QR code to test verification</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    
    <!-- Bus Card Script -->
    <script>
        function downloadCard() {
            // Log download attempt
            console.log('Downloading bus card...');
            
            // Show loading
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating PDF...';
            btn.disabled = true;
            
            // Redirect to download endpoint
            window.location.href = 'bus_card.php?download=1&t=' + Date.now();
            
            // Restore button after 3 seconds
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        }
        
        function printCard() {
            // Log print attempt
            console.log('Printing bus card...');
            
            // Store original body content
            const originalContent = document.body.innerHTML;
            
            // Get card content
            const cardContent = document.getElementById('busCardFront').outerHTML + 
                              document.getElementById('busCardBack').outerHTML;
            
            // Create print window
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Bus Card - Print</title>
                    <style>
                        @media print {
                            @page { margin: 0; }
                            body { margin: 1.6cm; }
                        }
                        .bus-card { margin-bottom: 30px; }
                    </style>
                </head>
                <body>
                    ${cardContent}
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
        
        function saveAsImage() {
            // Log image save attempt
            console.log('Saving bus card as image...');
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Capturing...';
            btn.disabled = true;
            
            // Capture front of card
            html2canvas(document.getElementById('busCardFront')).then(canvas => {
                const link = document.createElement('a');
                link.download = 'bus_card_front_' + Date.now() + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                
                // Capture back of card after delay
                setTimeout(() => {
                    html2canvas(document.getElementById('busCardBack')).then(canvas2 => {
                        const link2 = document.createElement('a');
                        link2.download = 'bus_card_back_' + Date.now() + '.png';
                        link2.href = canvas2.toDataURL('image/png');
                        link2.click();
                        
                        // Restore button
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        
                        alert('Bus card saved as images! Check your downloads folder.');
                    });
                }, 1000);
            });
        }
        
        function showQRScanner() {
            const scannerModal = new bootstrap.Modal(document.getElementById('qrScannerModal'));
            scannerModal.show();
            
            // Initialize QR scanner
            setTimeout(() => {
                const html5QrCode = new Html5Qrcode("qrReader");
                
                html5QrCode.start(
                    { facingMode: "environment" },
                    {
                        fps: 10,
                        qrbox: 250
                    },
                    (decodedText) => {
                        // Successfully scanned
                        html5QrCode.stop();
                        
                        // Parse QR data
                        try {
                            const qrData = JSON.parse(atob(decodedText));
                            document.getElementById('qrResult').innerHTML = `
                                <h6><i class="fas fa-check-circle text-success me-2"></i> Valid QR Code</h6>
                                <p class="mb-0 small">
                                    Name: ${qrData.name}<br>
                                    Bus: ${qrData.bus_number}<br>
                                    Seat: ${qrData.seat_number}<br>
                                    Valid: ${new Date(qrData.valid_from).toLocaleDateString()} - ${new Date(qrData.valid_to).toLocaleDateString()}
                                </p>
                            `;
                            document.getElementById('qrResult').style.display = 'block';
                        } catch (e) {
                            document.getElementById('qrResult').innerHTML = `
                                <h6><i class="fas fa-times-circle text-danger me-2"></i> Invalid QR Code</h6>
                                <p class="mb-0 small">Could not decode QR data</p>
                            `;
                            document.getElementById('qrResult').style.display = 'block';
                        }
                    },
                    (errorMessage) => {
                        // Scan error - ignore
                    }
                ).catch(err => {
                    console.error('QR Scanner error:', err);
                });
                
                // Clean up scanner when modal closes
                document.getElementById('qrScannerModal').addEventListener('hidden.bs.modal', function() {
                    html5QrCode.stop();
                });
            }, 500);
        }
        
        // Auto-refresh QR code if expired
        function checkCardValidity() {
            const validTo = '<?php echo $bus_card ? $bus_card["valid_to"] : ""; ?>';
            if (validTo) {
                const expiryDate = new Date(validTo);
                const today = new Date();
                const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                
                if (daysUntilExpiry <= 7) {
                    showExpiryWarning(daysUntilExpiry);
                }
                
                if (daysUntilExpiry <= 0) {
                    showExpiredWarning();
                }
            }
        }
        
        function showExpiryWarning(days) {
            const warning = document.createElement('div');
            warning.className = 'alert alert-warning alert-dismissible fade show';
            warning.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Bus Card Expiring Soon!</strong> Your card expires in ${days} days.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.bus-card-container').prepend(warning);
        }
        
        function showExpiredWarning() {
            const warning = document.createElement('div');
            warning.className = 'alert alert-danger alert-dismissible fade show';
            warning.innerHTML = `
                <i class="fas fa-ban me-2"></i>
                <strong>Bus Card Expired!</strong> Please generate a new card to continue bus service.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.bus-card-container').prepend(warning);
        }
        
        // Initialize on page load
        $(document).ready(function() {
            // Check card validity
            checkCardValidity();
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Security: Disable right-click on card
            document.getElementById('busCardFront').addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
            
            document.getElementById('busCardBack').addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
        });
    </script>
</body>
</html>
<?php
// Log bus card view
$functions->logActivity($user_id, 'BUS_CARD_VIEW', 'Bus card page viewed');
?>