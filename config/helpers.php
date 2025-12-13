<?php
date_default_timezone_set('Asia/Jakarta');
// config/helpers.php - Helper functions

// Start session if not started
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Check if user is logged in
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Get current user ID
function getCurrentUserId() {
    startSession();
    return $_SESSION['user_id'] ?? null;
}

// Get current user data
function getCurrentUser() {
    startSession();
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'avatar' => $_SESSION['avatar'] ?? 'default-avatar.png'
    ];
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate CSRF token
function generateCsrfToken() {
    startSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken($token) {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Format time ago
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' detik lalu';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' hari lalu';
    } elseif ($diff < 2592000) {
        return floor($diff / 604800) . ' minggu lalu';
    } else {
        return date('d M Y', $time);
    }
}

// Get user initials for avatar
function getUserInitials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Set flash message
function setFlashMessage($type, $message) {
    startSession();
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

// Get and clear flash message
function getFlashMessage() {
    startSession();
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Display flash message HTML
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        return "
        <div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
            {$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
    return '';
}

// Pagination helper
function paginate($currentPage, $totalPages, $baseUrl) {
    $html = '<nav><ul class="pagination justify-content-center">';
    
    // Previous
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link">Previous</a></li>';
    }
    
    // Pages
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><a class="page-link">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link">Next</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

// Truncate text
function truncate($text, $length = 150, $suffix = '...') {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . $suffix;
    }
    return $text;
}

// Generate slug from string
function generateSlug($string) {
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($string));
    return trim($slug, '-');
}
?>
