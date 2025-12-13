<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
startSession();

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Anda tidak memiliki akses ke halaman ini');
    redirect('../index.php');
}

$db = Database::getInstance()->getConnection();

// Get statistics
$stats = [];
$stats['users'] = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['topics'] = $db->query("SELECT COUNT(*) as count FROM topics")->fetch_assoc()['count'];
$stats['comments'] = $db->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$stats['communities'] = $db->query("SELECT COUNT(*) as count FROM communities")->fetch_assoc()['count'];
$stats['categories'] = $db->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];

// Get recent users
$recent_users = $db->query("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent topics
$recent_topics = $db->query("
    SELECT t.*, u.username, c.category_name 
    FROM topics t
    JOIN users u ON t.author_id = u.user_id
    LEFT JOIN categories c ON t.category_id = c.category_id
    ORDER BY t.created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get categories
$categories = $db->query("SELECT * FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

// Get communities
$communities = $db->query("SELECT * FROM communities ORDER BY community_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Forum UTBK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-mortarboard-fill"></i> UTBK Forum - Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link btn btn-light text-primary ms-2 px-3 rounded-pill" href="../index.php">
                            <i class="bi bi-house"></i> Ke Forum
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-light text-primary ms-2 px-3 rounded-pill" href="../logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

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

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 mb-4">
                <div class="list-group">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a href="manage-users.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-people"></i> Kelola User
                    </a>
                    <a href="manage-categories.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-grid"></i> Kelola Kategori
                    </a>
                    <a href="manage-communities.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-people-fill"></i> Kelola Komunitas
                    </a>
                    <a href="manage-topics.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-chat-dots"></i> Kelola Topik
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <h2 class="mb-4">Dashboard Admin</h2>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total User</h6>
                                        <h2 class="mb-0"><?php echo $stats['users']; ?></h2>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-people fs-2 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Topik</h6>
                                        <h2 class="mb-0"><?php echo $stats['topics']; ?></h2>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-chat-dots fs-2 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Komentar</h6>
                                        <h2 class="mb-0"><?php echo $stats['comments']; ?></h2>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-chat-left-text fs-2 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Komunitas</h6>
                                        <h2 class="mb-0"><?php echo $stats['communities']; ?></h2>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-people-fill fs-2 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Kategori</h6>
                                        <h2 class="mb-0"><?php echo $stats['categories']; ?></h2>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-grid fs-2 text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">User Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Tanggal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_users as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Topik Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Judul</th>
                                                <th>Author</th>
                                                <th>Kategori</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_topics as $topic): ?>
                                                <tr onclick="window.location.href='../topic-detail.php?id=<?php echo $topic['topic_id']; ?>'" style="cursor: pointer;">
                                                    <td><?php echo htmlspecialchars(truncate($topic['title'], 40)); ?></td>
                                                    <td><?php echo htmlspecialchars($topic['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($topic['category_name']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Aksi Cepat</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="manage-categories.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Tambah Kategori
                                    </a>
                                    <a href="manage-communities.php" class="btn btn-success">
                                        <i class="bi bi-plus-circle"></i> Tambah Komunitas
                                    </a>
                                    <a href="manage-users.php" class="btn btn-info">
                                        <i class="bi bi-people"></i> Kelola User
                                    </a>
                                    <a href="manage-topics.php" class="btn btn-warning">
                                        <i class="bi bi-chat-dots"></i> Moderasi Topik
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>