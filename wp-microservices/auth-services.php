<?php
/**
 * Authentication Service
 * 
 * Handles user authentication, login, and sessions
 */

// Include necessary WordPress core files for authentication
require_once __DIR__ . '/wp-includes/user.php';
require_once __DIR__ . '/wp-includes/pluggable.php';
require_once __DIR__ . '/wp-includes/class-phpass.php';

// Database connection
$db = new mysqli('db', 'wordpress_user', 'password', 'wordpress');

// Handle login requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/wp-login.php') !== false) {
    $username = $_POST['log'] ?? '';
    $password = $_POST['pwd'] ?? '';
    
    // Authenticate user
    $stmt = $db->prepare("SELECT ID, user_login, user_pass FROM wp_users WHERE user_login = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $wp_hasher = new PasswordHash(8, true);
        
        if ($wp_hasher->CheckPassword($password, $user['user_pass'])) {
            // Generate JWT token for microservices auth
            $token = generateJWT($user['ID'], $user['user_login']);
            
            // Return success response with token
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'token' => $token,
                'user_id' => $user['ID']
            ]);
        } else {
            // Authentication failed
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password'
            ]);
        }
    } else {
        // User not found
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password'
        ]);
    }
    
    $stmt->close();
    exit;
}

// Handle token validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/validate-token') !== false) {
    $token = getBearerToken();
    $isValid = validateJWT($token);
    
    header('Content-Type: application/json');
    echo json_encode(['valid' => $isValid]);
    exit;
}

/**
 * Generate JWT token
 */
function generateJWT($user_id, $username) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'sub' => $user_id,
        'name' => $username,
        'iat' => time(),
        'exp' => time() + 3600
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, 'your_secret_key', true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Validate JWT token
 */
function validateJWT($token) {
    // Token validation logic here
    list($header, $payload, $signature) = explode(".", $token);
    
    // Verify signature
    $valid = hash_hmac('sha256', $header . "." . $payload, 'your_secret_key', true);
    $valid_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($valid));
    
    return $signature === $valid_signature;
}

/**
 * Get bearer token from request headers
 */
function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}