<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

$db = Database::getInstance()->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query
$where = ['t.is_approved = 1'];
$params = [];
$types = '';

if ($category_id > 0) {
    $where[] = 't.category_id = ?';
    $params[] = $category_id;
    $types .= 'i';
}

if (!empty($search)) {
    $where[] = '(t.title LIKE ? OR t.content LIKE ? OR t.tags LIKE ?)';
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

$whereClause = implode(' AND ', $where);

// Sort
$orderBy = 't.created_at DESC';
if ($sort === 'popular') {
    $orderBy = 't.view_count DESC, t.like_count DESC';
} elseif ($sort === 'unanswered') {
    $orderBy = 't.comment_count ASC, t.created_at DESC';
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM topics t WHERE $whereClause";
$stmt = $db->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

// Get topics
$sql = "SELECT t.*, u.username, u.full_name, c.category_name, c.color as category_color
        FROM topics t
        JOIN users u ON t.author_id = u.user_id
        LEFT JOIN categories c ON t.category_id = c.category_id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";

$stmt = $db->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = $db->query("SELECT * FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi UTBK – Diskusi</title>
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

    <!-- Hero Section -->
    <section class="hero-section" id="diskusi">
        <div class="container text-center">
            <h1><i class="bi bi-people-fill"></i> Diskusi</h1>
            <p>Temukan dan ikuti diskusi yang kamu minati</p>
        </div>
    </section>

    <!-- Search & Filter Section -->
    <section class="container mb-4" id="diskusi">
        <form method="GET" action="diskusi.php">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <input type="text" name="search" class="form-control search-box" 
                           placeholder="Cari topik diskusi..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <select name="category_id" class="form-select search-box" onchange="this.form.submit()">
                        <option value="0">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" 
                                    <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </section>

    <!-- Diskusi Terbaru Section -->
    <section class="container mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">
                <?php 
                if ($category_id > 0) {
                    $catName = array_filter($categories, fn($c) => $c['category_id'] == $category_id);
                    echo 'Diskusi: ' . htmlspecialchars(reset($catName)['category_name']);
                } else {
                    echo 'Diskusi Terbaru';
                }
                ?>
            </h2>
            <div class="btn-group" role="group">
                <a href="?sort=newest<?php echo $category_id ? "&category_id=$category_id" : ''; ?>" 
                   class="btn btn-outline-primary <?php echo $sort === 'newest' ? 'active' : ''; ?>">Terbaru</a>
                <a href="?sort=popular<?php echo $category_id ? "&category_id=$category_id" : ''; ?>" 
                   class="btn btn-outline-primary <?php echo $sort === 'popular' ? 'active' : ''; ?>">Populer</a>
                <a href="?sort=unanswered<?php echo $category_id ? "&category_id=$category_id" : ''; ?>" 
                   class="btn btn-outline-primary <?php echo $sort === 'unanswered' ? 'active' : ''; ?>">Belum Terjawab</a>
            </div>
        </div>

        <!-- Topic Cards -->
        <?php foreach ($topics as $topic): ?>
            <div class="topic-card" onclick="window.location.href='topic-detail.php?id=<?php echo $topic['topic_id']; ?>'">
                <div class="d-flex">
                    <div class="user-avatar me-3"><?php echo getUserInitials($topic['full_name'] ?: $topic['username']); ?></div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($topic['title']); ?></h5>
                                <span class="badge bg-<?php echo $topic['category_color']; ?> badge-custom">
                                    <?php echo htmlspecialchars($topic['category_name']); ?>
                                </span>
                            </div>
                            <small class="text-muted"><?php echo timeAgo($topic['created_at']); ?></small>
                        </div>
                        <p class="mt-2 mb-2"><?php echo htmlspecialchars(truncate($topic['content'], 150)); ?></p>
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

        <?php if (empty($topics)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                <h5 class="mt-3 text-muted">Tidak ada diskusi ditemukan</h5>
                <p class="text-muted">Coba ubah filter atau kata kunci pencarian Anda</p>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?><?php echo $category_id ? "&category_id=$category_id" : ''; ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?><?php echo $category_id ? "&category_id=$category_id" : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?><?php echo $category_id ? "&category_id=$category_id" : ''; ?>">Next</a>
                    </li>
                </ul>
            </nav>
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
