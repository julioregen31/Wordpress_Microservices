<?php
/**
 * Content Service
 * 
 * Handles posts, pages, and other content
 */

// Database connection
$db = new mysqli('db', 'wordpress_user', 'password', 'wordpress');

// Get post by ID
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/\/wp-json\/wp\/v2\/posts\/(\d+)/', $_SERVER['REQUEST_URI'], $matches)) {
    $post_id = $matches[1];
    
    $stmt = $db->prepare("SELECT * FROM wp_posts WHERE ID = ? AND post_status = 'publish'");
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $post = $result->fetch_assoc();
        
        // Get post meta
        $stmt_meta = $db->prepare("SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ?");
        $stmt_meta->bind_param('i', $post_id);
        $stmt_meta->execute();
        $meta_result = $stmt_meta->get_result();
        
        $post_meta = [];
        while ($meta = $meta_result->fetch_assoc()) {
            $post_meta[$meta['meta_key']] = $meta['meta_value'];
        }
        
        $post['meta'] = $post_meta;
        
        header('Content-Type: application/json');
        echo json_encode($post);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
    }
    
    exit;
}

// Get posts
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/wp-json/wp/v2/posts') !== false) {
    $per_page = $_GET['per_page'] ?? 10;
    $page = $_GET['page'] ?? 1;
    $offset = ($page - 1) * $per_page;
    
    $stmt = $db->prepare("SELECT * FROM wp_posts WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($post = $result->fetch_assoc()) {
        $posts[] = $post;
    }
    
    header('Content-Type: application/json');
    echo json_encode($posts);
    exit;
}

// Create post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/wp-json/wp/v2/posts') !== false) {
    // Verify authentication
    $token = getBearerToken();
    if (!validateToken($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    $status = $data['status'] ?? 'draft';
    
    $stmt = $db->prepare("INSERT INTO wp_posts (post_title, post_content, post_status, post_type, post_date) VALUES (?, ?, ?, 'post', NOW())");
    $stmt->bind_param('sss', $title, $content, $status);
    
    if ($stmt->execute()) {
        $post_id = $stmt->insert_id;
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'post_id' => $post_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create post']);
    }
    
    exit;
}

// Handle default homepage request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/index.php')) {
    // Get latest posts for homepage
    $stmt = $db->prepare("SELECT * FROM wp_posts WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($post = $result->fetch_assoc()) {
        $posts[] = $post;
    }
    
    // Render homepage with posts
    include 'templates/homepage.php';
    exit;
}

/**
 * Validate token by calling auth service
 */
function validateToken($token) {
    $ch = curl_init('http://auth-service:8000/validate-token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result && isset($result['valid']) && $result['valid'] === true;
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