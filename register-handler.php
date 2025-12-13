<?php
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        setFlashMessage('danger', 'Semua field harus diisi');
        redirect('index.php');
    }
    
    if (strlen($username) < 3) {
        setFlashMessage('danger', 'Username minimal 3 karakter');
        redirect('index.php');
    }
    
    if (!isValidEmail($email)) {
        setFlashMessage('danger', 'Email tidak valid');
        redirect('index.php');
    }
    
    if (strlen($password) < 8) {
        setFlashMessage('danger', 'Password minimal 8 karakter');
        redirect('index.php');
    }
    
    if ($password !== $confirm_password) {
        setFlashMessage('danger', 'Password tidak cocok');
        redirect('index.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Check if username exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        setFlashMessage('danger', 'Username sudah digunakan');
        redirect('index.php');
    }
    
    // Check if email exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        setFlashMessage('danger', 'Email sudah terdaftar');
        redirect('index.php');
    }
    
    // Hash password and insert user
    $hashed_password = hashPassword($password);
    $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $username);
    
    if ($stmt->execute()) {
        setFlashMessage('success', 'Registrasi berhasil! Silakan login.');
        redirect('index.php');
    } else {
        setFlashMessage('danger', 'Terjadi kesalahan saat registrasi');
        redirect('index.php');
    }
} else {
    redirect('index.php');
}
?>