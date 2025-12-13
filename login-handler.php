<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        setFlashMessage('danger', 'Email dan password harus diisi');
        redirect('index.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Get user from database
    $stmt = $db->prepare("SELECT user_id, username, email, password, full_name, role, avatar, is_active FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        setFlashMessage('danger', 'Email atau password salah');
        redirect('index.php');
    }
    
    $user = $result->fetch_assoc();
    
    if (!$user['is_active']) {
        setFlashMessage('danger', 'Akun Anda telah dinonaktifkan');
        redirect('index.php');
    }
    
    if (verifyPassword($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'];
        
        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->bind_param("i", $user['user_id']);
        $stmt->execute();
        
        setFlashMessage('success', 'Login berhasil! Selamat datang ' . $user['username']);
        redirect('index.php');
    } else {
        setFlashMessage('danger', 'Email atau password salah');
        redirect('index.php');
    }
} else {
    redirect('index.php');
}
?>
