<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

class Functions {
    private $db;
    private $security;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->security = new Security();
    }

    // Send email
    public function sendEmail($to, $subject, $message, $headers = null) {
        if ($headers === null) {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
            $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
        }
        
        try {
            // Use PHPMailer or SwiftMailer in production
            return mail($to, $subject, $message, $headers);
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    // Generate QR code (placeholder - will implement with library)
    public function generateQRCode($data, $size = QR_CODE_SIZE) {
        $qr_data = [
            'student_id' => $data['id'],
            'university_id' => $data['university_id'],
            'name' => $data['name'],
            'timestamp' => time(),
            'hash' => hash_hmac('sha256', $data['id'] . time(), 'QR_SECRET_KEY')
        ];
        
        $encoded_data = base64_encode(json_encode($qr_data));
        
        // In production, use a QR library like phpqrcode
        // For now, return placeholder
        return [
            'data' => $encoded_data,
            'image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($encoded_data)
        ];
    }

    // Format date
    public function formatDate($date, $format = 'F j, Y, g:i a') {
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '';
    }

    // Get time ago
    public function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return $this->formatDate($datetime);
        }
    }

    // Validate phone number
    public function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            return false;
        }
        
        return $phone;
    }

    // Check if university ID exists
    public function universityIdExists($university_id) {
        $this->db->prepare("SELECT id FROM users WHERE university_id = :university_id LIMIT 1");
        $this->db->bind(':university_id', $university_id);
        $result = $this->db->single();
        return $result !== false;
    }

    // Check if email exists
    public function emailExists($email) {
        $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $this->db->bind(':email', $email);
        $result = $this->db->single();
        return $result !== false;
    }

    // Generate username from name
    public function generateUsername($name) {
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $username = substr($username, 0, 15);
        
        // Check if username exists
        $counter = 1;
        $original_username = $username;
        
        while ($this->usernameExists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        return $username;
    }

    private function usernameExists($username) {
        $this->db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        $this->db->bind(':username', $username);
        $result = $this->db->single();
        return $result !== false;
    }

    // Get user role name
    public function getRoleName($role_id) {
        $roles = [
            1 => 'Super Admin',
            2 => 'Admin',
            3 => 'Student',
            4 => 'Driver',
            5 => 'Faculty'
        ];
        
        return $roles[$role_id] ?? 'Unknown';
    }

    // Get status badge
    public function getStatusBadge($status) {
        $badges = [
            'active' => '<span class="badge bg-success">Active</span>',
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'suspended' => '<span class="badge bg-danger">Suspended</span>',
            'inactive' => '<span class="badge bg-secondary">Inactive</span>',
            'approved' => '<span class="badge bg-success">Approved</span>',
            'rejected' => '<span class="badge bg-danger">Rejected</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
    }

    // Redirect with message
    public function redirect($url, $message = null, $type = 'success') {
        if ($message) {
            $_SESSION['flash_message'] = [
                'text' => $message,
                'type' => $type
            ];
        }
        header("Location: $url");
        exit();
    }

    // Display flash message
    public function displayFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message']['text'];
            $type = $_SESSION['flash_message']['type'];
            
            $alert = '
            <div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            
            unset($_SESSION['flash_message']);
            return $alert;
        }
        return '';
    }

    // Generate pagination
    public function paginate($total_items, $items_per_page, $current_page, $url) {
        $total_pages = ceil($total_items / $items_per_page);
        
        if ($total_pages <= 1) {
            return '';
        }
        
        $pagination = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
        
        // Previous button
        if ($current_page > 1) {
            $pagination .= '<li class="page-item">
                <a class="page-link" href="' . $url . '?page=' . ($current_page - 1) . '">Previous</a>
            </li>';
        }
        
        // Page numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            $active = $i == $current_page ? ' active' : '';
            $pagination .= '<li class="page-item' . $active . '">
                <a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a>
            </li>';
        }
        
        // Next button
        if ($current_page < $total_pages) {
            $pagination .= '<li class="page-item">
                <a class="page-link" href="' . $url . '?page=' . ($current_page + 1) . '">Next</a>
            </li>';
        }
        
        $pagination .= '</ul></nav>';
        return $pagination;
    }

    // Log activity
    public function logActivity($user_id, $action, $details = null) {
        $ip = $this->security->getClientIP();
        $user_agent = json_encode($this->security->getUserAgentInfo());
        
        $this->db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (:user_id, :action, :details, :ip_address, :user_agent)
        ");
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':action', $action);
        $this->db->bind(':details', $details);
        $this->db->bind(':ip_address', $ip);
        $this->db->bind(':user_agent', $user_agent);
        $this->db->execute();
    }

    // Get current academic year
    public function getAcademicYear() {
        $month = date('n');
        $year = date('Y');
        
        if ($month >= 9) { // September to December
            return $year . '-' . ($year + 1);
        } else { // January to August
            return ($year - 1) . '-' . $year;
        }
    }
}

// Initialize functions
$functions = new Functions();
?>