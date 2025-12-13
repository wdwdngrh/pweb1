<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

$db = Database::getInstance()->getConnection();

// Get all communities with stats
$communities = $db->query("
    SELECT c.*, cat.category_name, cat.color as category_color,
           COUNT(DISTINCT cm.user_id) as member_count,
           COUNT(DISTINCT t.topic_id) as discussion_count
    FROM communities c
    LEFT JOIN categories cat ON c.category_id = cat.category_id
    LEFT JOIN community_members cm ON c.community_id = cm.community_id
    LEFT JOIN topics t ON c.community_id = t.community_id
    GROUP BY c.community_id
    ORDER BY member_count DESC
")->fetch_all(MYSQLI_ASSOC);

// Get stats
$totalCommunities = count($communities);
$totalMembers = array_sum(array_column($communities, 'member_count'));
$totalEvents = $db->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi UTBK – Komunitas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section" id="komunitas">
        <div class="container text-center">
            <h1><i class="bi bi-people-fill"></i> Komunitas Belajar</h1>
            <p>Bergabung dengan komunitas belajar sesuai minat dan kebutuhanmu</p>
        </div>
    </section>

    <!-- Stats Section -->
    <div class="container mb-5">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $totalCommunities; ?></div>
                    <div class="text-muted">Komunitas Aktif</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($totalMembers); ?></div>
                    <div class="text-muted">Total Anggota</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $totalEvents; ?></div>
                    <div class="text-muted">Event Tersedia</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Komunitas Section -->
    <section class="container mb-5">
        <h2 class="mb-4 fw-bold">Jelajahi Komunitas</h2>
        <div class="row" id="communitiesContainer">
            <?php foreach ($communities as $community): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card community-card" onclick="window.location.href='komunitas-detail.php?id=<?php echo $community['community_id']; ?>'">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="community-avatar me-3 bg-<?php echo $community['category_color']; ?> bg-opacity-10 text-<?php echo $community['category_color']; ?>">
                                    <i class="bi <?php echo $community['icon']; ?>"></i>
                                </div>
                                <div>
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($community['community_name']); ?></h5>
                                    <small class="text-muted"><?php echo $community['member_count']; ?> anggota</small>
                                </div>
                            </div>
                            <p class="card-text"><?php echo htmlspecialchars($community['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="badge bg-<?php echo $community['category_color']; ?>">
                                    <?php echo htmlspecialchars($community['category_name']); ?>
                                </span>
                                <small class="text-muted">
                                    <i class="bi bi-chat-dots"></i> <?php echo $community['discussion_count']; ?> diskusi
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Modals -->
    <?php include 'includes/modals.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/auth-dynamic.js"></script>
</body>
</html>
