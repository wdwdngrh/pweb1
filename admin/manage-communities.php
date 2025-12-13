<?php
date_default_timezone_set('Asia/Jakarta');
require_once '../config/database.php';
require_once '../config/helpers.php';
startSession();

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Anda tidak memiliki akses ke halaman ini');
    redirect('../index.php');
}

$db = Database::getInstance()->getConnection();

// Handle add/edit community
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_community'])) {
        $name = sanitize($_POST['community_name']);
        $slug = generateSlug($name);
        $description = sanitize($_POST['description']);
        $full_description = sanitize($_POST['full_description']);
        $icon = sanitize($_POST['icon']);
        $icon_bg = sanitize($_POST['icon_bg']);
        $category_id = (int)$_POST['category_id'];
        $creator_id = getCurrentUserId();
        
        // Handle icon image upload
        $icon_image = null;
        if (isset($_FILES['icon_image']) && $_FILES['icon_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            $filename = $_FILES['icon_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'community_' . time() . '.' . $ext;
                $upload_path = '../uploads/communities/' . $new_filename;
                
                if (!is_dir('../uploads/communities')) {
                    mkdir('../uploads/communities', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['icon_image']['tmp_name'], $upload_path)) {
                    $icon_image = $new_filename;
                }
            }
        }
        
        $stmt = $db->prepare("INSERT INTO communities (community_name, slug, description, full_description, icon, icon_image, icon_bg, category_id, creator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssii", $name, $slug, $description, $full_description, $icon, $icon_image, $icon_bg, $category_id, $creator_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Komunitas berhasil ditambahkan');
        } else {
            setFlashMessage('danger', 'Gagal menambahkan komunitas');
        }
        redirect('manage-communities.php');
    }
    
    if (isset($_POST['edit_community'])) {
        $id = (int)$_POST['community_id'];
        $name = sanitize($_POST['community_name']);
        $slug = generateSlug($name);
        $description = sanitize($_POST['description']);
        $full_description = sanitize($_POST['full_description']);
        $icon = sanitize($_POST['icon']);
        $icon_bg = sanitize($_POST['icon_bg']);
        $category_id = (int)$_POST['category_id'];
        
        // Get current icon_image
        $stmt = $db->prepare("SELECT icon_image FROM communities WHERE community_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $icon_image = $current['icon_image'];
        
        // Handle new icon image upload
        if (isset($_FILES['icon_image']) && $_FILES['icon_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            $filename = $_FILES['icon_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'community_' . time() . '.' . $ext;
                $upload_path = '../uploads/communities/' . $new_filename;
                
                if (!is_dir('../uploads/communities')) {
                    mkdir('../uploads/communities', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['icon_image']['tmp_name'], $upload_path)) {
                    // Delete old image
                    if ($icon_image && file_exists('../uploads/communities/' . $icon_image)) {
                        unlink('../uploads/communities/' . $icon_image);
                    }
                    $icon_image = $new_filename;
                }
            }
        }
        
        $stmt = $db->prepare("UPDATE communities SET community_name = ?, slug = ?, description = ?, full_description = ?, icon = ?, icon_image = ?, icon_bg = ?, category_id = ? WHERE community_id = ?");
        $stmt->bind_param("sssssssii", $name, $slug, $description, $full_description, $icon, $icon_image, $icon_bg, $category_id, $id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Komunitas berhasil diupdate');
        } else {
            setFlashMessage('danger', 'Gagal mengupdate komunitas');
        }
        redirect('manage-communities.php');
    }
    
    if (isset($_POST['delete_community'])) {
        $id = (int)$_POST['community_id'];
        
        // Get icon_image to delete
        $stmt = $db->prepare("SELECT icon_image FROM communities WHERE community_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $community = $stmt->get_result()->fetch_assoc();
        
        $stmt = $db->prepare("DELETE FROM communities WHERE community_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Delete image file
            if ($community['icon_image'] && file_exists('../uploads/communities/' . $community['icon_image'])) {
                unlink('../uploads/communities/' . $community['icon_image']);
            }
            setFlashMessage('success', 'Komunitas berhasil dihapus');
        } else {
            setFlashMessage('danger', 'Gagal menghapus komunitas');
        }
        redirect('manage-communities.php');
    }
}

// Get all communities
$communities = $db->query("
    SELECT c.*, cat.category_name 
    FROM communities c
    LEFT JOIN categories cat ON c.category_id = cat.category_id
    ORDER BY c.community_name
")->fetch_all(MYSQLI_ASSOC);

// Get categories for dropdown
$categories = $db->query("SELECT * FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Komunitas - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill"></i> UTBK Forum - Admin
            </a>
            <div class="ms-auto">
                <a class="btn btn-light btn-sm" href="../index.php">
                    <i class="bi bi-house"></i> Ke Forum
                </a>
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

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Kelola Komunitas</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCommunityModal">
                <i class="bi bi-plus-circle"></i> Tambah Komunitas
            </button>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Icon</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Anggota</th>
                                <th>Diskusi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($communities as $com): ?>
                                <tr>
                                    <td><?php echo $com['community_id']; ?></td>
                                    <td>
                                        <?php if ($com['icon_image']): ?>
                                            <img src="../uploads/communities/<?php echo htmlspecialchars($com['icon_image']); ?>" 
                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; background: <?php echo $com['icon_bg']; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi <?php echo $com['icon']; ?> text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($com['community_name']); ?></td>
                                    <td><?php echo htmlspecialchars($com['category_name']); ?></td>
                                    <td><?php echo $com['member_count']; ?></td>
                                    <td><?php echo $com['discussion_count']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="editCommunity(<?php echo htmlspecialchars(json_encode($com)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus?')">
                                            <input type="hidden" name="community_id" value="<?php echo $com['community_id']; ?>">
                                            <button type="submit" name="delete_community" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Community Modal -->
    <div class="modal fade" id="addCommunityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Komunitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Komunitas</label>
                                <input type="text" name="community_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>">
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Singkat</label>
                            <textarea name="description" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Lengkap</label>
                            <textarea name="full_description" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Icon Bootstrap</label>
                                <input type="text" name="icon" class="form-control" placeholder="bi-calculator-fill" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warna Background Icon</label>
                                <input type="color" name="icon_bg" class="form-control" value="#007bff" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Icon Gambar (opsional)</label>
                            <input type="file" name="icon_image" class="form-control" accept="image/*">
                            <small class="text-muted">Jika diisi, akan menggantikan icon Bootstrap</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_community" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Community Modal -->
    <div class="modal fade" id="editCommunityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Komunitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="community_id" id="edit_community_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Komunitas</label>
                                <input type="text" name="community_name" id="edit_community_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="category_id" id="edit_category_id" class="form-select" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>">
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Singkat</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Lengkap</label>
                            <textarea name="full_description" id="edit_full_description" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Icon Bootstrap</label>
                                <input type="text" name="icon" id="edit_icon" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warna Background Icon</label>
                                <input type="color" name="icon_bg" id="edit_icon_bg" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Icon Gambar Baru (opsional)</label>
                            <input type="file" name="icon_image" class="form-control" accept="image/*">
                            <div id="current_community_icon_preview" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_community" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editCommunity(com) {
        document.getElementById('edit_community_id').value = com.community_id;
        document.getElementById('edit_community_name').value = com.community_name;
        document.getElementById('edit_category_id').value = com.category_id;
        document.getElementById('edit_description').value = com.description;
        document.getElementById('edit_full_description').value = com.full_description;
        document.getElementById('edit_icon').value = com.icon;
        document.getElementById('edit_icon_bg').value = com.icon_bg;
        
        const preview = document.getElementById('current_community_icon_preview');
        if (com.icon_image) {
            preview.innerHTML = '<small class="text-muted">Icon saat ini:</small><br><img src="../uploads/communities/' + com.icon_image + '" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;" class="mt-2">';
        } else {
            preview.innerHTML = '';
        }
        
        new bootstrap.Modal(document.getElementById('editCommunityModal')).show();
    }
    </script>
</body>
</html>
