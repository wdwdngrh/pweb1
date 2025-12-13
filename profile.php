<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

if (!isLoggedIn()) {
    setFlashMessage('danger', 'Anda harus login terlebih dahulu');
    redirect('index.php');
}

$db = Database::getInstance()->getConnection();
$user_id = getCurrentUserId();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = sanitize($_POST['username']);
    $full_name = sanitize($_POST['full_name']);
    $bio = sanitize($_POST['bio']);
    $school = sanitize($_POST['school']);
    
    // Check if username is taken by another user
    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $stmt->bind_param("si", $username, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        setFlashMessage('danger', 'Username sudah digunakan');
    } else {
        // Handle avatar upload
        $avatar = $_SESSION['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['avatar']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                if ($_FILES['avatar']['size'] <= 2 * 1024 * 1024) { // 2MB max
                    $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = 'uploads/avatars/' . $new_filename;
                    
                    if (!is_dir('uploads/avatars')) {
                        mkdir('uploads/avatars', 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                        // Delete old avatar if not default
                        if ($avatar != 'default-avatar.png' && file_exists('uploads/avatars/' . $avatar)) {
                            unlink('uploads/avatars/' . $avatar);
                        }
                        $avatar = $new_filename;
                    }
                }
            }
        }
        
        // Update profile
        $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, bio = ?, school = ?, avatar = ? WHERE user_id = ?");
        $stmt->bind_param("sssssi", $username, $full_name, $bio, $school, $avatar, $user_id);
        
        if ($stmt->execute()) {
            // Update session
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['avatar'] = $avatar;
            
            setFlashMessage('success', 'Profil berhasil diupdate');
            redirect('profile.php');
        } else {
            setFlashMessage('danger', 'Gagal mengupdate profil');
        }
    }
}

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user stats
$topic_count = $db->query("SELECT COUNT(*) as count FROM topics WHERE author_id = $user_id")->fetch_assoc()['count'];
$comment_count = $db->query("SELECT COUNT(*) as count FROM comments WHERE user_id = $user_id")->fetch_assoc()['count'];
$like_count = $db->query("SELECT COUNT(*) as count FROM likes WHERE user_id = $user_id")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Forum UTBK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Flash Messages -->
    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
        <div class="container mt-3">
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Profile Section -->
    <section class="container mt-4 mb-5">
        <div class="row">
            <!-- Profile Info -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <?php if ($user['avatar'] != 'default-avatar.png' && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                            <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" 
                                 class="rounded-circle mb-3" 
                                 style="width: 150px; height: 150px; object-fit: cover;"
                                 alt="Avatar">
                        <?php else: ?>
                            <div class="user-avatar mx-auto mb-3" style="width: 150px; height: 150px; font-size: 4rem;">
                                <?php echo getUserInitials($user['full_name'] ?: $user['username']); ?>
                            </div>
                        <?php endif; ?>
                        <h4><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h4>
                        <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                        <?php if (!empty($user['bio'])): ?>
                            <p class="mt-3"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($user['school'])): ?>
                            <p class="text-muted"><i class="bi bi-building"></i> <?php echo htmlspecialchars($user['school']); ?></p>
                        <?php endif; ?>
                        <button class="btn btn-primary rounded-pill mt-3" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="bi bi-pencil"></i> Edit Profil
                        </button>
                    </div>
                </div>

                <!-- Stats -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Statistik</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-chat-dots"></i> Topik</span>
                            <strong><?php echo $topic_count; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-chat-left-text"></i> Komentar</span>
                            <strong><?php echo $comment_count; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="bi bi-hand-thumbs-up"></i> Like Diberikan</span>
                            <strong><?php echo $like_count; ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-md-8">
                <h4 class="mb-4">Aktivitas Terbaru</h4>
                
                <?php
                // Get recent topics
                $stmt = $db->prepare("
                    SELECT t.*, c.category_name, c.color as category_color
                    FROM topics t
                    LEFT JOIN categories c ON t.category_id = c.category_id
                    WHERE t.author_id = ?
                    ORDER BY t.created_at DESC
                    LIMIT 5
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $recent_topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                if (empty($recent_topics)):
                ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                        <h5 class="mt-3 text-muted">Belum ada aktivitas</h5>
                        <button class="btn btn-primary mt-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#createTopicModal">
                            Buat Topik Pertama
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_topics as $topic): ?>
                        <div class="topic-card" onclick="window.location.href='topic-detail.php?id=<?php echo $topic['topic_id']; ?>'">
                            <h5 class="mb-2"><?php echo htmlspecialchars($topic['title']); ?></h5>
                            <span class="badge bg-<?php echo $topic['category_color']; ?> badge-custom">
                                <?php echo htmlspecialchars($topic['category_name']); ?>
                            </span>
                            <p class="mt-2 mb-2"><?php echo htmlspecialchars(truncate($topic['content'], 150)); ?></p>
                            <div class="topic-meta d-flex gap-3">
                                <span><i class="bi bi-calendar"></i> <?php echo timeAgo($topic['created_at']); ?></span>
                                <span><i class="bi bi-chat-left-text"></i> <?php echo $topic['comment_count']; ?> Komentar</span>
                                <span><i class="bi bi-eye"></i> <?php echo $topic['view_count']; ?> Views</span>
                                <span><i class="bi bi-hand-thumbs-up"></i> <?php echo $topic['like_count']; ?> Likes</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-4">
                        <a href="my-topics.php" class="btn btn-outline-primary rounded-pill">Lihat Semua Topik</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Edit Profil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <?php if ($user['avatar'] != 'default-avatar.png' && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                                <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" 
                                     class="rounded-circle mb-2" 
                                     style="width: 100px; height: 100px; object-fit: cover;"
                                     alt="Avatar">
                            <?php else: ?>
                                <div class="user-avatar mx-auto mb-2" style="width: 100px; height: 100px; font-size: 2rem;">
                                    <?php echo getUserInitials($user['full_name'] ?: $user['username']); ?>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="avatar" id="avatar" class="d-none" accept="image/*">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('avatar').click()">Ganti Foto</button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-control" rows="3" placeholder="Ceritakan tentang diri Anda..."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sekolah</label>
                            <input type="text" name="school" class="form-control" placeholder="Nama sekolah" value="<?php echo htmlspecialchars($user['school']); ?>">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="update_profile" class="btn btn-primary rounded-pill px-4">Simpan</button>
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php include 'includes/modals.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/auth-dynamic.js"></script>
</body>
</html>
