<?php
/**
 * Theme Service
 * 
 * Handles WordPress themes
 */

// Define themes directory
define('THEMES_DIR', __DIR__ . '/themes');

// Get theme assets
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/wp-content/themes/') !== false) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file_path = THEMES_DIR . str_replace('/wp-content/themes/', '/', $path);
    
    if (file_exists($file_path)) {
        // Determine content type
        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        $content_type = 'text/plain';
        
        switch ($extension) {
            case 'css':
                $content_type = 'text/css';
                break;
            case 'js':
                $content_type = 'application/javascript';
                break;
            case 'png':
                $content_type = 'image/png';
                break;
            case 'jpg':
            case 'jpeg':
                $content_type = 'image/jpeg';
                break;
            case 'svg':
                $content_type = 'image/svg+xml';
                break;
            case 'woff':
                $content_type = 'font/woff';
                break;
            case 'woff2':
                $content_type = 'font/woff2';
                break;
            case 'ttf':
                $content_type = 'font/ttf';
                break;
        }
        
        header('Content-Type: ' . $content_type);
        readfile($file_path);
    } else {
        http_response_code(404);
        echo 'File not found';
    }
    
    exit;
}

// Get theme metadata
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/wp-json/wp/v2/themes') !== false) {
    $themes = [];
    
    // Get all theme directories
    $theme_dirs = array_filter(glob(THEMES_DIR . '/*'), 'is_dir');
    
    foreach ($theme_dirs as $theme_dir) {
        $style_path = $theme_dir . '/style.css';
        
        if (file_exists($style_path)) {
            $theme_data = get_theme_data($style_path);
            $theme_name = basename($theme_dir);
            
            $themes[] = [
                'name' => $theme_name,
                'title' => $theme_data['theme_name'] ?? $theme_name,
                'description' => $theme_data['description'] ?? '',
                'version' => $theme_data['version'] ?? '',
                'author' => $theme_data['author'] ?? '',
                'screenshot' => "/wp-content/themes/$theme_name/screenshot.png"
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($themes);
    exit;
}

// Render page with theme
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['render_template'])) {
    $template = $_GET['render_template'];
    $theme = $_GET['theme'] ?? 'twentytwentyone';
    $content = $_GET['content'] ?? '';
    
    $template_path = THEMES_DIR . '/' . $theme . '/' . $template . '.php';
    
    if (file_exists($template_path)) {
        // Include and render the template
        ob_start();
        include $template_path;
        $rendered_content = ob_get_clean();
        
        echo $rendered_content;
    } else {
        http_response_code(404);
        echo 'Template not found';
    }
    
    exit;
}

/**
 * Parse theme metadata from style.css
 */
function get_theme_data($style_path) {
    $theme_data = [];
    $content = file_get_contents($style_path);
    
    preg_match('/Theme Name:\s*(.+)/i', $content, $matches);
    if (isset($matches[1])) {
        $theme_data['theme_name'] = trim($matches[1]);
    }
    
    preg_match('/Description:\s*(.+)/i', $content, $matches);
    if (isset($matches[1])) {
        $theme_data['description'] = trim($matches[1]);
    }
    
    preg_match('/Version:\s*(.+)/i', $content, $matches);
    if (isset($matches[1])) {
        $theme_data['version'] = trim($matches[1]);
    }
    
    preg_match('/Author:\s*(.+)/i', $content, $matches);
    if (isset($matches[1])) {
        $theme_data['author'] = trim($matches[1]);
    }
    
    return $theme_data;
}