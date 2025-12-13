<?php
date_default_timezone_set('Asia/Jakarta');
require_once '../config/database.php';
require_once '../config/helpers.php';
startSession();

if (!isLoggedIn()) {
    setFlashMessage('danger', 'Anda harus login terlebih dahulu');
    redirect($_SERVER['HTTP_REFERER'] ?? '../index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? ''; // topic or comment
    $id = (int)($_POST['id'] ?? 0);
    $user_id = getCurrentUserId();
    
    $db = Database::getInstance()->getConnection();
    
    // Check if already liked
    $stmt = $db->prepare("SELECT like_id FROM likes WHERE user_id = ? AND likeable_type = ? AND likeable_id = ?");
    $stmt->bind_param("isi", $user_id, $type, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Unlike
        $stmt = $db->prepare("DELETE FROM likes WHERE user_id = ? AND likeable_type = ? AND likeable_id = ?");
        $stmt->bind_param("isi", $user_id, $type, $id);
        $stmt->execute();
        
        // Update count
        if ($type === 'topic') {
            $db->query("UPDATE topics SET like_count = like_count - 1 WHERE topic_id = $id");
        } else {
            $db->query("UPDATE comments SET like_count = like_count - 1 WHERE comment_id = $id");
        }
        
        setFlashMessage('info', 'Like dihapus');
    } else {
        // Like
        $stmt = $db->prepare("INSERT INTO likes (user_id, likeable_type, likeable_id) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $user_id, $type, $id);
        $stmt->execute();
        
        // Update count
        if ($type === 'topic') {
            $db->query("UPDATE topics SET like_count = like_count + 1 WHERE topic_id = $id");
        } else {
            $db->query("UPDATE comments SET like_count = like_count + 1 WHERE comment_id = $id");
        }
        
        setFlashMessage('success', 'Like ditambahkan');
    }
}

redirect($_SERVER['HTTP_REFERER'] ?? '../index.php');
?>
