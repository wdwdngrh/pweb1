<?php
date_default_timezone_set('Asia/Jakarta');
// api/topics.php - Topics CRUD handler

require_once '../config/database.php';
require_once '../config/helpers.php';

header('Content-Type: application/json');
startSession();

$response = ['success' => false, 'message' => ''];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();

switch ($action) {
    case 'create':
        if ($method === 'POST') {
            if (!isLoggedIn()) {
                $response['message'] = 'Anda harus login terlebih dahulu';
                break;
            }
            
            $title = sanitize($_POST['title'] ?? '');
            $content = sanitize($_POST['content'] ?? '');
            $category_id = intval($_POST['category_id'] ?? 0);
            $community_id = intval($_POST['community_id'] ?? 0) ?: null;
            $tags = sanitize($_POST['tags'] ?? '');
            $author_id = getCurrentUserId();
            
            if (empty($title) || empty($content)) {
                $response['message'] = 'Judul dan isi topik harus diisi';
                break;
            }
            
            if ($category_id === 0) {
                $response['message'] = 'Kategori harus dipilih';
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO topics (title, content, author_id, category_id, community_id, tags) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiss", $title, $content, $author_id, $category_id, $community_id, $tags);
            
            if ($stmt->execute()) {
                $topic_id = $db->insert_id;
                
                // Update category topic count
                $db->query("UPDATE categories SET topic_count = topic_count + 1 WHERE category_id = $category_id");
                
                // Update community discussion count if applicable
                if ($community_id) {
                    $db->query("UPDATE communities SET discussion_count = discussion_count + 1 WHERE community_id = $community_id");
                }
                
                $response['success'] = true;
                $response['message'] = 'Topik berhasil dibuat';
                $response['topic_id'] = $topic_id;
            } else {
                $response['message'] = 'Gagal membuat topik';
            }
        }
        break;
        
    case 'list':
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;
        $category_id = intval($_GET['category_id'] ?? 0);
        $community_id = intval($_GET['community_id'] ?? 0);
        $sort = $_GET['sort'] ?? 'newest'; // newest, popular, unanswered
        $search = sanitize($_GET['search'] ?? '');
        
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        if ($category_id > 0) {
            $where[] = 't.category_id = ?';
            $params[] = $category_id;
            $types .= 'i';
        }
        
        if ($community_id > 0) {
            $where[] = 't.community_id = ?';
            $params[] = $community_id;
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
        
        // Get topics
        $sql = "SELECT t.*, u.username, u.full_name, c.category_name, c.color as category_color,
                com.community_name,
                (SELECT COUNT(*) FROM likes WHERE likeable_type = 'topic' AND likeable_id = t.topic_id) as actual_like_count
                FROM topics t
                JOIN users u ON t.author_id = u.user_id
                LEFT JOIN categories c ON t.category_id = c.category_id
                LEFT JOIN communities com ON t.community_id = com.community_id
                WHERE $whereClause
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";
        
        $stmt = $db->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $topics = [];
        while ($row = $result->fetch_assoc()) {
            $row['time_ago'] = timeAgo($row['created_at']);
            $row['initials'] = getUserInitials($row['full_name'] ?: $row['username']);
            $topics[] = $row;
        }
        
        $response['success'] = true;
        $response['topics'] = $topics;
        $response['pagination'] = [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_items' => $total,
            'per_page' => $limit
        ];
        break;
        
    case 'get':
        $topic_id = intval($_GET['id'] ?? 0);
        
        if ($topic_id === 0) {
            $response['message'] = 'ID topik tidak valid';
            break;
        }
        
        // Increment view count
        $db->query("UPDATE topics SET view_count = view_count + 1 WHERE topic_id = $topic_id");
        
        // Get topic with comments
        $sql = "SELECT t.*, u.username, u.full_name, u.avatar, c.category_name, c.color as category_color,
                com.community_name
                FROM topics t
                JOIN users u ON t.author_id = u.user_id
                LEFT JOIN categories c ON t.category_id = c.category_id
                LEFT JOIN communities com ON t.community_id = com.community_id
                WHERE t.topic_id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = 'Topik tidak ditemukan';
            break;
        }
        
        $topic = $result->fetch_assoc();
        $topic['time_ago'] = timeAgo($topic['created_at']);
        $topic['initials'] = getUserInitials($topic['full_name'] ?: $topic['username']);
        
        // Check if current user has liked
        if (isLoggedIn()) {
            $user_id = getCurrentUserId();
            $stmt = $db->prepare("SELECT like_id FROM likes WHERE user_id = ? AND likeable_type = 'topic' AND likeable_id = ?");
            $stmt->bind_param("ii", $user_id, $topic_id);
            $stmt->execute();
            $topic['is_liked'] = $stmt->get_result()->num_rows > 0;
        }
        
        // Get comments
        $commentsSql = "SELECT c.*, u.username, u.full_name, u.avatar
                        FROM comments c
                        JOIN users u ON c.user_id = u.user_id
                        WHERE c.topic_id = ? AND c.parent_comment_id IS NULL
                        ORDER BY c.created_at ASC";
        
        $stmt = $db->prepare($commentsSql);
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $commentsResult = $stmt->get_result();
        
        $comments = [];
        while ($row = $commentsResult->fetch_assoc()) {
            $row['time_ago'] = timeAgo($row['created_at']);
            $row['initials'] = getUserInitials($row['full_name'] ?: $row['username']);
            $comments[] = $row;
        }
        
        $response['success'] = true;
        $response['topic'] = $topic;
        $response['comments'] = $comments;
        break;
        
    case 'update':
        if ($method === 'POST') {
            if (!isLoggedIn()) {
                $response['message'] = 'Anda harus login terlebih dahulu';
                break;
            }
            
            $topic_id = intval($_POST['topic_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $content = sanitize($_POST['content'] ?? '');
            $tags = sanitize($_POST['tags'] ?? '');
            $user_id = getCurrentUserId();
            
            // Check if user is owner or admin
            $stmt = $db->prepare("SELECT author_id FROM topics WHERE topic_id = ?");
            $stmt->bind_param("i", $topic_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Topik tidak ditemukan';
                break;
            }
            
            $topic = $result->fetch_assoc();
            if ($topic['author_id'] != $user_id && !isAdmin()) {
                $response['message'] = 'Anda tidak memiliki izin untuk mengedit topik ini';
                break;
            }
            
            $stmt = $db->prepare("UPDATE topics SET title = ?, content = ?, tags = ? WHERE topic_id = ?");
            $stmt->bind_param("sssi", $title, $content, $tags, $topic_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Topik berhasil diupdate';
            } else {
                $response['message'] = 'Gagal mengupdate topik';
            }
        }
        break;
        
    case 'delete':
        if ($method === 'POST') {
            if (!isLoggedIn()) {
                $response['message'] = 'Anda harus login terlebih dahulu';
                break;
            }
            
            $topic_id = intval($_POST['topic_id'] ?? 0);
            $user_id = getCurrentUserId();
            
            // Check if user is owner or admin
            $stmt = $db->prepare("SELECT author_id, category_id, community_id FROM topics WHERE topic_id = ?");
            $stmt->bind_param("i", $topic_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Topik tidak ditemukan';
                break;
            }
            
            $topic = $result->fetch_assoc();
            if ($topic['author_id'] != $user_id && !isAdmin()) {
                $response['message'] = 'Anda tidak memiliki izin untuk menghapus topik ini';
                break;
            }
            
            // Delete topic (will cascade delete comments and likes)
            $stmt = $db->prepare("DELETE FROM topics WHERE topic_id = ?");
            $stmt->bind_param("i", $topic_id);
            
            if ($stmt->execute()) {
                // Update category count
                $db->query("UPDATE categories SET topic_count = topic_count - 1 WHERE category_id = " . $topic['category_id']);
                
                // Update community count if applicable
                if ($topic['community_id']) {
                    $db->query("UPDATE communities SET discussion_count = discussion_count - 1 WHERE community_id = " . $topic['community_id']);
                }
                
                $response['success'] = true;
                $response['message'] = 'Topik berhasil dihapus';
            } else {
                $response['message'] = 'Gagal menghapus topik';
            }
        }
        break;
        
    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);
?>
