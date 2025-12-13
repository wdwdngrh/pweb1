<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

$db = Database::getInstance()->getConnection();

// Get community ID
$community_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($community_id === 0) {
    redirect('komunitas.php');
}

// Get community details
$stmt = $db->prepare("
    SELECT c.*, cat.category_name, cat.color as category_color,
           u.username as creator_name
    FROM communities c
    LEFT JOIN categories cat ON c.category_id = cat.category_id
    LEFT JOIN users u ON c.creator_id = u.user_id
    WHERE c.community_id = ?
");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$community = $stmt->get_result()->fetch_assoc();

if (!$community) {
    setFlashMessage('danger', 'Komunitas tidak ditemukan');
    redirect('komunitas.php');
}

// Get community discussions
$stmt = $db->prepare("
    SELECT t.*, u.username, u.full_name
    FROM topics t
    JOIN users u ON t.author_id = u.user_id
    WHERE t.community_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$discussions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get community members
$stmt = $db->prepare("
    SELECT u.username, u.full_name, u.avatar, cm.role, cm.joined_at
    FROM community_members cm
    JOIN users u ON cm.user_id = u.user_id
    WHERE cm.community_id = ?
    ORDER BY 
        CASE cm.role 
            WHEN 'founder' THEN 1 
            WHEN 'moderator' THEN 2 
            ELSE 3 
        END,
        cm.joined_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if user is member
$is_member = false;
if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    $stmt = $db->prepare("SELECT member_id FROM community_members WHERE community_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $community_id, $user_id);
    $stmt->execute();
    $is_member = $stmt->get_result()->num_rows > 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($community['community_name']); ?> - Forum UTBK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Community Header -->
    <section class="hero-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-3">
                        <div class="community-avatar me-4" style="width: 100px; height: 100px; font-size: 3rem; background: rgba(255,255,255,0.2); color: white;">
                            <?php if ($community['icon_image']): ?>
                                <img src="uploads/communities/<?php echo htmlspecialchars($community['icon_image']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <i class="bi <?php echo $community['icon']; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h1 class="mb-2"><?php echo htmlspecialchars($community['community_name']); ?></h1>
                            <p class="mb-2"><i class="bi bi-people-fill"></i> <?php echo $community['member_count']; ?> anggota</p>
                            <span class="badge bg-light text-primary px-3 py-2">
                                <?php echo htmlspecialchars($community['category_name']); ?>
                            </span>
                        </div>
                    </div>
                    <p class="mb-0"><?php echo htmlspecialchars($community['description']); ?></p>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if (isLoggedIn()): ?>
                        <button class="btn btn-light btn-lg px-5 rounded-pill" onclick="toggleJoin(<?php echo $community_id; ?>)">
                            <i class="bi <?php echo $is_member ? 'bi-check-circle-fill' : 'bi-person-plus-fill'; ?>"></i> 
                            <?php echo $is_member ? 'Sudah Bergabung' : 'Bergabung'; ?>
                        </button>
                    <?php else: ?>
                        <button class="btn btn-light btn-lg px-5 rounded-pill" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="bi bi-person-plus-fill"></i> Bergabung
                        </button>
                    <?php endif; ?>
                    <p class="text-white mt-2 mb-0">
                        <small><i class="bi bi-chat-dots"></i> <?php echo $community['discussion_count']; ?> diskusi aktif</small>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Community Stats -->
    <div class="container mb-4 mt-4">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $community['member_count']; ?></div>
                    <div class="text-muted">Anggota</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $community['discussion_count']; ?></div>
                    <div class="text-muted">Diskusi</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $community['post_count']; ?></div>
                    <div class="text-muted">Postingan</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $community['event_count']; ?></div>
                    <div class="text-muted">Event</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="container mb-4">
        <ul class="nav nav-tabs" id="communityTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="discussions-tab" data-bs-toggle="tab" data-bs-target="#discussions" type="button">
                    <i class="bi bi-chat-dots"></i> Diskusi
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button">
                    <i class="bi bi-people"></i> Anggota
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="about-tab" data-bs-toggle="tab" data-bs-target="#about" type="button">
                    <i class="bi bi-info-circle"></i> Tentang
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Content -->
    <div class="container mb-5">
        <div class="tab-content" id="communityTabsContent">
            <!-- Discussions Tab -->
            <div class="tab-pane fade show active" id="discussions" role="tabpanel">
                <?php if (empty($discussions)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-chat-dots" style="font-size: 4rem; color: #ccc;"></i>
                        <h5 class="mt-3 text-muted">Belum ada diskusi</h5>
                        <?php if (isLoggedIn()): ?>
                            <button class="btn btn-primary mt-3 rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createTopicModal">
                                Mulai Diskusi Pertama
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($discussions as $topic): ?>
                        <div class="topic-card" onclick="window.location.href='topic-detail.php?id=<?php echo $topic['topic_id']; ?>'">
                            <div class="d-flex">
                                <div class="user-avatar me-3">
                                    <?php echo getUserInitials($topic['full_name'] ?: $topic['username']); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($topic['title']); ?></h5>
                                        <small class="text-muted"><?php echo timeAgo($topic['created_at']); ?></small>
                                    </div>
                                    <p class="mt-2 mb-3"><?php echo htmlspecialchars(truncate($topic['content'], 150)); ?></p>
                                    <div class="topic-meta d-flex gap-3">
                                        <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($topic['username']); ?></span>
                                        <span><i class="bi bi-chat-left-text"></i> <?php echo $topic['comment_count']; ?> Komentar</span>
                                        <span><i class="bi bi-eye"></i> <?php echo $topic['view_count']; ?> Views</span>
                                        <span><i class="bi bi-hand-thumbs-up"></i> <?php echo $topic['like_count']; ?> Likes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Members Tab -->
            <div class="tab-pane fade" id="members" role="tabpanel">
                <div class="row">
                    <?php if (empty($members)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-people" style="font-size: 4rem; color: #ccc;"></i>
                            <h5 class="mt-3 text-muted">Belum ada anggota</h5>
                        </div>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3">
                                                <?php echo getUserInitials($member['full_name'] ?: $member['username']); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?></h6>
                                                <small class="text-muted text-capitalize"><?php echo $member['role']; ?></small>
                                                <br>
                                                <small class="text-muted">Bergabung <?php echo timeAgo($member['joined_at']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- About Tab -->
            <div class="tab-pane fade" id="about" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Tentang Komunitas</h5>
                        <p class="card-text mb-4"><?php echo nl2br(htmlspecialchars($community['full_description'])); ?></p>
                        
                        <h6 class="mb-3">Informasi</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-calendar3 me-2"></i> Dibuat <?php echo date('d M Y', strtotime($community['created_at'])); ?></li>
                            <li class="mb-2"><i class="bi bi-person-badge me-2"></i> Pembuat: <?php echo htmlspecialchars($community['creator_name']); ?></li>
                            <li class="mb-2"><i class="bi bi-grid me-2"></i> Kategori: <?php echo htmlspecialchars($community['category_name']); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Modals -->
    <?php include 'includes/modals.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/auth-dynamic.js"></script>
    
    <script>
    function toggleJoin(communityId) {
        // Implement join/leave functionality
        alert('Fitur bergabung/keluar komunitas akan segera hadir!');
    }
    </script>
</body>
</html>
