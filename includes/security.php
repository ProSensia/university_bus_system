<?php
require_once __DIR__ . '/config.php';

class Security {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Generate CSRF token
    public function generateCSRFToken($form_name = 'default') {
        if (empty($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$form_name] = [
            'token' => $token,
            'expires' => time() + CSRF_TOKEN_LIFE
        ];
        
        return $token;
    }

    // Validate CSRF token
    public function validateCSRFToken($token, $form_name = 'default') {
        if (!isset($_SESSION['csrf_tokens'][$form_name])) {
            $this->logSecurityEvent('CSRF_MISSING_TOKEN', 'No CSRF token found for form: ' . $form_name);
            return false;
        }
        
        $stored_token = $_SESSION['csrf_tokens'][$form_name];
        
        // Check expiration
        if (time() > $stored_token['expires']) {
            unset($_SESSION['csrf_tokens'][$form_name]);
            $this->logSecurityEvent('CSRF_EXPIRED_TOKEN', 'Expired CSRF token for form: ' . $form_name);
            return false;
        }
        
        // Validate token
        if (!hash_equals($stored_token['token'], $token)) {
            $this->logSecurityEvent('CSRF_INVALID_TOKEN', 'Invalid CSRF token for form: ' . $form_name);
            return false;
        }
        
        // Remove token after use (one-time use)
        unset($_SESSION['csrf_tokens'][$form_name]);
        return true;
    }

    // Check for brute force attacks
    public function checkBruteForce($identifier, $type = 'login') {
        $ip = $this->getClientIP();
        $time_ago = time() - LOCKOUT_TIME;
        
        $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM security_logs 
            WHERE action = :action 
            AND ip_address = :ip 
            AND timestamp > :time_ago
        ");
        $this->db->bind(':action', $type . '_attempt');
        $this->db->bind(':ip', $ip);
        $this->db->bind(':time_ago', date('Y-m-d H:i:s', $time_ago));
        
        $result = $this->db->single();
        
        return $result['attempts'] >= MAX_LOGIN_ATTEMPTS;
    }

    // Validate honeypot fields
    public function checkHoneyPot($post_data) {
        global $honey_pot_fields;
        
        foreach ($honey_pot_fields as $field) {
            if (!empty($post_data[$field])) {
                $this->logSecurityEvent('HONEYPOT_TRIGGERED', "Honeypot field '$field' filled");
                return false;
            }
        }
        return true;
    }

    // Validate email
    public function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Check for disposable emails
        $disposable_domains = ['tempmail.com', 'mailinator.com', 'guerrillamail.com'];
        $domain = explode('@', $email)[1];
        
        if (in_array($domain, $disposable_domains)) {
            $this->logSecurityEvent('DISPOSABLE_EMAIL', "Disposable email used: $email");
            return false;
        }
        
        return true;
    }

    // Validate password strength
    public function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }
        if (!preg_match("/[A-Z]/", $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        if (!preg_match("/[a-z]/", $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match("/[0-9]/", $password)) {
            $errors[] = "Password must contain at least one number";
        }
        if (!preg_match("/[^A-Za-z0-9]/", $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return empty($errors) ? true : $errors;
    }

    // Generate secure random string
    public function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    // Encrypt data
    public function encrypt($data, $key = null) {
        if ($key === null) {
            $key = openssl_random_pseudo_bytes(32);
        }
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        
        return base64_encode($iv . $tag . $ciphertext);
    }

    // Decrypt data
    public function decrypt($data, $key) {
        $data = base64_decode($data);
        
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        
        return openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }

    // Get client IP address
    public function getClientIP() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'];
    }

    // Get user agent info
    public function getUserAgentInfo() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $browser = 'Unknown';
        $platform = 'Unknown';
        
        // Browser detection
        if (preg_match('/MSIE/i', $user_agent) && !preg_match('/Opera/i', $user_agent)) {
            $browser = 'Internet Explorer';
        } elseif (preg_match('/Firefox/i', $user_agent)) {
            $browser = 'Mozilla Firefox';
        } elseif (preg_match('/Chrome/i', $user_agent)) {
            $browser = 'Google Chrome';
        } elseif (preg_match('/Safari/i', $user_agent)) {
            $browser = 'Apple Safari';
        } elseif (preg_match('/Opera/i', $user_agent)) {
            $browser = 'Opera';
        } elseif (preg_match('/Netscape/i', $user_agent)) {
            $browser = 'Netscape';
        }
        
        // Platform detection
        if (preg_match('/linux/i', $user_agent)) {
            $platform = 'Linux';
        } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
            $platform = 'Mac';
        } elseif (preg_match('/windows|win32/i', $user_agent)) {
            $platform = 'Windows';
        } elseif (preg_match('/android/i', $user_agent)) {
            $platform = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $user_agent)) {
            $platform = 'iOS';
        }
        
        return [
            'user_agent' => $user_agent,
            'browser' => $browser,
            'platform' => $platform
        ];
    }

    // Log security events
    public function logSecurityEvent($event_type, $details, $user_id = null) {
        $ip = $this->getClientIP();
        $user_agent = $this->getUserAgentInfo();
        
        $this->db->prepare("
            INSERT INTO security_logs (user_id, event_type, details, ip_address, user_agent) 
            VALUES (:user_id, :event_type, :details, :ip_address, :user_agent)
        ");
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':event_type', $event_type);
        $this->db->bind(':details', $details);
        $this->db->bind(':ip_address', $ip);
        $this->db->bind(':user_agent', json_encode($user_agent));
        $this->db->execute();
        
        // Also log to file
        $log = "[" . date('Y-m-d H:i:s') . "] [$event_type] IP: $ip - User: $user_id - $details\n";
        file_put_contents(BASE_PATH . '/logs/security.log', $log, FILE_APPEND);
    }

    // Sanitize filename for upload
    public function sanitizeFilename($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9\.\-\_]/', '_', $filename);
        $filename = time() . '_' . $filename;
        return $filename;
    }

    // Validate file upload
    public function validateFileUpload($file, $allowed_types = null) {
        if ($allowed_types === null) {
            $allowed_types = ALLOWED_IMAGE_TYPES;
        }
        
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'No file uploaded';
            return [false, $errors];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errors[] = $upload_errors[$file['error']] ?? 'Unknown upload error';
            return [false, $errors];
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds maximum allowed (5MB)';
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
        }
        
        // Check for malicious content in images
        if (in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
            $image_info = getimagesize($file['tmp_name']);
            if ($image_info === false) {
                $errors[] = 'File is not a valid image';
            }
        }
        
        return [empty($errors), $errors];
    }

    // Rate limiting
    public function rateLimit($action, $identifier, $limit, $time_window) {
        $key = "rate_limit_{$action}_{$identifier}";
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'first_attempt' => $now
            ];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if ($now - $data['first_attempt'] > $time_window) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'first_attempt' => $now
            ];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['attempts'] >= $limit) {
            return false;
        }
        
        // Increment attempts
        $_SESSION[$key]['attempts']++;
        return true;
    }
}

// Initialize security system
$security = new Security();
?>