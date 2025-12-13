<?php
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

if (!isLoggedIn()) {
    setFlashMessage('danger', 'Anda harus login terlebih dahulu');
    redirect('index.php');
}

$db = Database::getInstance()->getConnection();
$user_id = getCurrentUserId();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM topics WHERE author_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

// Get topics
$stmt = $db->prepare("
    SELECT t.*, c.category_name, c.color as category_color
    FROM topics t
    LEFT JOIN categories c ON t.category_id = c.category_id
    WHERE t.author_id = ?
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topik Saya - Forum UTBK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Header -->
    <section class="hero-section">
        <div class="container text-center">
            <h1><i class="bi bi-chat-dots-fill"></i> Topik Saya</h1>
            <p>Kelola semua topik diskusi yang Anda buat</p>
        </div>
    </section>

    <!-- Topics List -->
    <section class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Total: <?php echo $total; ?> topik</h4>
            <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#createTopicModal">
                <i class="bi bi-plus-circle"></i> Buat Topik Baru
            </button>
        </div>

        <?php if (empty($topics)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                <h5 class="mt-3 text-muted">Belum ada topik</h5>
                <button class="btn btn-primary mt-3 rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createTopicModal">
                    <i class="bi bi-plus-circle"></i> Buat Topik Pertama
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($topics as $topic): ?>
                <div class="topic-card" onclick="window.location.href='topic-detail.php?id=<?php echo $topic['topic_id']; ?>'">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($topic['title']); ?></h5>
                            <span class="badge bg-<?php echo $topic['category_color']; ?> badge-custom">
                                <?php echo htmlspecialchars($topic['category_name']); ?>
                            </span>
                        </div>
                        <div class="dropdown" onclick="event.stopPropagation();">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="edit-topic.php?id=<?php echo $topic['topic_id']; ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </a></li>
                                <li><a class="dropdown-item text-danger" href="delete-topic.php?id=<?php echo $topic['topic_id']; ?>" 
                                       onclick="return confirm('Yakin ingin menghapus topik ini?')">
                                    <i class="bi bi-trash"></i> Hapus
                                </a></li>
                            </ul>
                        </div>
                    </div>
                    <p class="mb-2"><?php echo htmlspecialchars(truncate($topic['content'], 150)); ?></p>
                    <div class="topic-meta d-flex gap-3">
                        <span><i class="bi bi-calendar"></i> <?php echo timeAgo($topic['created_at']); ?></span>
                        <span><i class="bi bi-chat-left-text"></i> <?php echo $topic['comment_count']; ?> Komentar</span>
                        <span><i class="bi bi-eye"></i> <?php echo $topic['view_count']; ?> Views</span>
                        <span><i class="bi bi-hand-thumbs-up"></i> <?php echo $topic['like_count']; ?> Likes</span>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Modals -->
    <?php include 'includes/modals.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/auth-dynamic.js"></script>
    <script src="js/forum-dynamic.js"></script>
</body>
</html>