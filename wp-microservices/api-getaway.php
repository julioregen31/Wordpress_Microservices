<?php
/**
 * API Gateway for WordPress Microservices
 * Routes requests to appropriate microservices
 */

// Define service endpoints
$services = [
    'auth' => 'http://auth-service:8000',
    'content' => 'http://content-service:8001',
    'media' => 'http://media-service:8002',
    'theme' => 'http://theme-service:8003',
    'user' => 'http://user-service:8004',
    'comment' => 'http://comment-service:8005',
    'plugin' => 'http://plugin-service:8006',
    'search' => 'http://search-service:8007',
];

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Route the request based on the path
if (strpos($path, '/wp-login') === 0 || strpos($path, '/wp-admin/user') === 0) {
    proxyRequest($services['auth'] . $request_uri);
} elseif (strpos($path, '/wp-content/uploads') === 0) {
    proxyRequest($services['media'] . $request_uri);
} elseif (strpos($path, '/wp-content/themes') === 0) {
    proxyRequest($services['theme'] . $request_uri);
} elseif (strpos($path, '/wp-content/plugins') === 0) {
    proxyRequest($services['plugin'] . $request_uri);
} elseif (strpos($path, '/wp-comments-post.php') === 0) {
    proxyRequest($services['comment'] . $request_uri);
} elseif (strpos($path, '/search') === 0) {
    proxyRequest($services['search'] . $request_uri);
} elseif (strpos($path, '/wp-json/wp/v2/users') === 0) {
    proxyRequest($services['user'] . $request_uri);
} else {
    // Default to content service
    proxyRequest($services['content'] . $request_uri);
}

/**
 * Proxy function to forward requests to the appropriate service
 */
function proxyRequest($serviceUrl) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $serviceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
    
    // Forward headers
    $headers = getallheaders();
    $header_array = [];
    foreach ($headers as $name => $value) {
        $header_array[] = "$name: $value";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);
    
    // Forward POST/PUT data
    if ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    }
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    http_response_code($httpCode);
    echo $response;
    
    curl_close($ch);
    exit;
}