<?php
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: {$url}");
    exit;
}

function app_url($path = '') {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $parts = array_values(array_filter(explode('/', $scriptDir)));
    if ($parts && in_array(end($parts), ['actions', 'pages', 'includes'], true)) {
        array_pop($parts);
    }
    $base = '/' . implode('/', $parts);
    $base = rtrim($base, '/');
    return ($base ?: '') . '/' . ltrim($path, '/');
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect(app_url('login.php'));
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        die('CSRF token tidak valid. Refresh halaman lalu coba lagi.');
    }
}

function role_label($role) {
    return $role === 'SUPERADMIN' ? 'SUPERADMIN' : $role;
}

function role_rank($role) {
    return [
        'USER' => 1,
        'MODERATOR' => 2,
        'ADMIN' => 3,
        'SUPERADMIN' => 4,
    ][$role] ?? 0;
}

function can_manage_users($role) {
    return in_array($role, ['SUPERADMIN', 'ADMIN', 'MODERATOR'], true);
}

function can_create_project($role) {
    return in_array($role, ['SUPERADMIN', 'ADMIN', 'MODERATOR'], true);
}

function can_create_task($role) {
    return in_array($role, ['SUPERADMIN', 'ADMIN', 'MODERATOR'], true);
}

function can_review_task($pdo, $task, $project, $user) {
    if (!$user) return false;
    if ($user['role'] === 'SUPERADMIN') return true;
    if ($user['role'] === 'USER') return false;
    if ((int)$project['owner_id'] === (int)$user['id']) return true;
    if ((int)$task['created_by'] === (int)$user['id']) return true;
    return false;
}

function can_see_project($pdo, $projectId, $user) {
    if (!$user) return false;
    if ($user['role'] === 'SUPERADMIN') return true;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM project_members WHERE project_id = ? AND user_id = ?");
    $stmt->execute([$projectId, $user['id']]);
    return (int)$stmt->fetchColumn() > 0;
}

function can_see_task($pdo, $taskId, $user) {
    if (!$user) return false;
    if ($user['role'] === 'SUPERADMIN') return true;
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tasks t
        LEFT JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
        LEFT JOIN task_assignees ta ON ta.task_id = t.id AND ta.user_id = ?
        WHERE t.id = ? AND (pm.user_id IS NOT NULL OR ta.user_id IS NOT NULL)
    ");
    $stmt->execute([$user['id'], $user['id'], $taskId]);
    return (int)$stmt->fetchColumn() > 0;
}

function can_submit_task($pdo, $taskId, $user) {
    if (!$user) return false;
    if ($user['role'] === 'SUPERADMIN') return true;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE task_id = ? AND user_id = ?");
    $stmt->execute([$taskId, $user['id']]);
    return (int)$stmt->fetchColumn() > 0;
}

function get_project_member_ids($pdo, $projectId) {
    $stmt = $pdo->prepare("SELECT user_id FROM project_members WHERE project_id = ?");
    $stmt->execute([$projectId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
}


function get_assignable_project_users($pdo, $currentUser) {
    if (!$currentUser) return [];
    if ($currentUser['role'] === 'SUPERADMIN') {
        $stmt = $pdo->prepare("SELECT id, name, email, role, unit FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY FIELD(role,'ADMIN','MODERATOR','USER','SUPERADMIN'), name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    if ($currentUser['role'] === 'ADMIN') {
        $stmt = $pdo->prepare("SELECT id, name, email, role, unit FROM users WHERE status = 'active' AND deleted_at IS NULL AND role IN ('ADMIN','MODERATOR','USER') ORDER BY FIELD(role,'ADMIN','MODERATOR','USER'), name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    if ($currentUser['role'] === 'MODERATOR') {
        $stmt = $pdo->prepare("SELECT id, name, email, role, unit FROM users WHERE status = 'active' AND deleted_at IS NULL AND role IN ('MODERATOR','USER') ORDER BY FIELD(role,'MODERATOR','USER'), name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    return [];
}

function can_assign_project_member($currentUser, $targetUser) {
    if (!$currentUser || !$targetUser) return false;
    if ((int)$currentUser['id'] === (int)$targetUser['id']) return true;
    if ($currentUser['role'] === 'SUPERADMIN') return true;
    if ($currentUser['role'] === 'ADMIN') return in_array($targetUser['role'], ['ADMIN', 'MODERATOR', 'USER'], true);
    if ($currentUser['role'] === 'MODERATOR') return in_array($targetUser['role'], ['MODERATOR', 'USER'], true);
    return false;
}


function get_project_members($pdo, $projectId) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, u.unit, pm.role_in_project, pm.created_at
        FROM project_members pm
        JOIN users u ON u.id = pm.user_id
        WHERE pm.project_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
        ORDER BY FIELD(pm.role_in_project,'owner','manager','member'), FIELD(u.role,'SUPERADMIN','ADMIN','MODERATOR','USER'), u.name ASC
    ");
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function get_task_assignable_users($pdo, $currentUser, $projectId) {
    $members = get_project_members($pdo, $projectId);
    return array_values(array_filter($members, function($member) use ($currentUser) {
        return can_assign_project_member($currentUser, $member);
    }));
}


function can_manage_project_members($pdo, $projectId, $user) {
    if (!$user) return false;
    if ($user['role'] === 'SUPERADMIN') return true;
    $stmt = $pdo->prepare("SELECT role_in_project FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$projectId, $user['id']]);
    $roleInProject = $stmt->fetchColumn();
    return in_array($roleInProject, ['owner', 'manager'], true);
}

function get_project_by_id($pdo, $projectId) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.name AS owner_name
        FROM projects p
        JOIN users u ON u.id = p.owner_id
        WHERE p.id = ? AND p.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$projectId]);
    return $stmt->fetch();
}

function get_project_active_task_count_for_user($pdo, $projectId, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM task_assignees ta
        JOIN tasks t ON t.id = ta.task_id
        WHERE t.project_id = ?
          AND ta.user_id = ?
          AND t.deleted_at IS NULL
          AND t.status IN ('open','submitted','rejected')
    ");
    $stmt->execute([$projectId, $userId]);
    return (int)$stmt->fetchColumn();
}

function get_project_progress_percent($pdo, $projectId) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count
        FROM tasks
        WHERE project_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();
    $total = (int)($row['total_count'] ?? 0);
    $approved = (int)($row['approved_count'] ?? 0);
    return $total > 0 ? (int)round(($approved / $total) * 100) : 0;
}

function get_task_status_label($status) {
    $labels = [
        'open' => 'Belum Dikerjakan',
        'submitted' => 'Menunggu Review',
        'approved' => 'Verified Checked',
        'rejected' => 'Perlu Revisi',
    ];
    return $labels[$status] ?? $status;
}



function get_creatable_roles($role) {
    if ($role === 'SUPERADMIN') return ['ADMIN', 'MODERATOR', 'USER'];
    if ($role === 'ADMIN') return ['MODERATOR', 'USER'];
    if ($role === 'MODERATOR') return ['USER'];
    return [];
}

function can_manage_target_user($currentUser, $targetUser) {
    if (!$currentUser || !$targetUser) return false;
    if ((int)$currentUser['id'] === (int)$targetUser['id']) return false;
    if ($currentUser['role'] === 'SUPERADMIN') return true;
    return role_rank($targetUser['role']) < role_rank($currentUser['role']);
}

function can_create_user_role($currentRole, $targetRole) {
    return in_array($targetRole, get_creatable_roles($currentRole), true);
}

function user_has_project_ownership($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE owner_id = ? AND deleted_at IS NULL AND status <> 'archived'");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}

function user_has_active_tasks($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM task_assignees ta
        JOIN tasks t ON t.id = ta.task_id
        WHERE ta.user_id = ? AND t.deleted_at IS NULL AND t.status IN ('open','submitted','rejected')
    ");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}

function user_has_subordinates($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_by = ? AND deleted_at IS NULL AND status = 'active'");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}

function last_active_superadmin($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'SUPERADMIN' AND status = 'active' AND deleted_at IS NULL AND id <> ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() === 0;
}

function user_can_be_deactivated($pdo, $targetUser) {
    if (!$targetUser) return [false, 'User tidak ditemukan.'];
    if ($targetUser['role'] === 'SUPERADMIN' && last_active_superadmin($pdo, (int)$targetUser['id'])) {
        return [false, 'SUPERADMIN terakhir tidak boleh dinonaktifkan.'];
    }
    if (user_has_project_ownership($pdo, (int)$targetUser['id'])) {
        return [false, 'User masih menjadi owner project aktif. Pindahkan ownership project terlebih dahulu.'];
    }
    if (user_has_active_tasks($pdo, (int)$targetUser['id'])) {
        return [false, 'User masih memiliki task aktif. Selesaikan atau pindahkan task terlebih dahulu.'];
    }
    if (user_has_subordinates($pdo, (int)$targetUser['id'])) {
        return [false, 'User masih memiliki bawahan aktif. Pindahkan bawahan terlebih dahulu.'];
    }
    return [true, ''];
}

function user_can_change_role($pdo, $targetUser, $newRole) {
    if (!$targetUser) return [false, 'User tidak ditemukan.'];
    if ($targetUser['role'] === $newRole) return [true, ''];
    if ($targetUser['role'] === 'SUPERADMIN' && $newRole !== 'SUPERADMIN' && last_active_superadmin($pdo, (int)$targetUser['id'])) {
        return [false, 'SUPERADMIN terakhir tidak boleh diturunkan role-nya.'];
    }
    if (role_rank($newRole) < role_rank($targetUser['role'])) {
        if (user_has_project_ownership($pdo, (int)$targetUser['id'])) {
            return [false, 'Role tidak bisa diturunkan karena user masih menjadi owner project aktif.'];
        }
        if (user_has_subordinates($pdo, (int)$targetUser['id'])) {
            return [false, 'Role tidak bisa diturunkan karena user masih memiliki bawahan aktif.'];
        }
    }
    return [true, ''];
}


function log_activity($pdo, $userId, $action, $detail, $projectId = null, $taskId = null) {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, detail, project_id, task_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $action, $detail, $projectId, $taskId]);
}

function notify_user($pdo, $userId, $title, $message, $projectId = null, $taskId = null) {
    if (!$userId) return;
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, project_id, task_id, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$userId, $title, $message, $projectId, $taskId]);
}

function status_badge($status) {
    $map = [
        'open' => 'bg-slate-100 text-slate-700',
        'submitted' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'completed' => 'bg-green-100 text-green-800',
        'active' => 'bg-blue-100 text-blue-800',
        'review' => 'bg-amber-100 text-amber-800',
        'draft' => 'bg-slate-100 text-slate-700',
        'archived' => 'bg-slate-200 text-slate-700',
    ];
    $class = $map[$status] ?? 'bg-slate-100 text-slate-700';
    return '<span class="inline-flex rounded-full px-2 py-1 text-xs font-bold ' . $class . '">' . e(strtoupper($status)) . '</span>';
}

function safe_upload_file($file, $uploadDir) {
    if (empty($file['name']) || empty($file['tmp_name'])) {
        return null;
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        die('Upload file gagal atau file tidak valid.');
    }

    $maxSize = 10 * 1024 * 1024; // 10 MB
    if ((int)$file['size'] > $maxSize) {
        die('Ukuran file maksimal 10 MB.');
    }

    $allowed = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];

    $originalName = (string)$file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        die('Format file tidak diizinkan.');
    }

    if (preg_match('/\.(php|phtml|phar|cgi|pl|asp|aspx|jsp|sh|bat|cmd)(\.|$)/i', $originalName)) {
        die('File executable/script tidak diizinkan.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
    if (!in_array($mime, $allowed[$ext], true)) {
        // Beberapa file Office di Windows bisa terbaca sebagai octet-stream/zip.
        // Tetap blok jika ekstensi gambar tetapi MIME bukan gambar.
        if (in_array($ext, ['png','jpg','jpeg','webp','gif'], true)) {
            die('MIME type gambar tidak valid.');
        }
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        die('Upload file gagal.');
    }

    chmod($target, 0644);
    return 'uploads/' . $safeName;
}

function safe_download_filename($filename) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$filename);
}


function can_edit_project($pdo, $projectId, $user) {
    if (!$user) return false;
    if ($user['role'] === 'SUPERADMIN') return true;
    $stmt = $pdo->prepare("SELECT role_in_project FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$projectId, $user['id']]);
    $role = $stmt->fetchColumn();
    return in_array($role, ['owner', 'manager'], true);
}

function get_project_status_from_tasks($pdo, $projectId, $currentStatus = 'active') {
    if ($currentStatus === 'archived' || $currentStatus === 'draft') {
        return $currentStatus;
    }
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) AS submitted_count,
            SUM(CASE WHEN deadline_at < NOW() AND status <> 'approved' THEN 1 ELSE 0 END) AS overdue_count
        FROM tasks
        WHERE project_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();
    $total = (int)($row['total_count'] ?? 0);
    $approved = (int)($row['approved_count'] ?? 0);
    $submitted = (int)($row['submitted_count'] ?? 0);
    $overdue = (int)($row['overdue_count'] ?? 0);
    if ($total > 0 && $approved === $total) return 'completed';
    if ($submitted > 0) return 'review';
    if ($overdue > 0) return 'active';
    return 'active';
}

function sync_project_status($pdo, $projectId) {
    $project = get_project_by_id($pdo, $projectId);
    if (!$project) return;
    $newStatus = get_project_status_from_tasks($pdo, $projectId, $project['status']);
    if ($newStatus !== $project['status']) {
        $stmt = $pdo->prepare("UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $projectId]);
    }
}

function can_edit_task($pdo, $task, $user) {
    if (!$user || !$task) return false;
    if ($user['role'] === 'SUPERADMIN') return true;
    if ((int)$task['created_by'] === (int)$user['id']) return true;
    return can_manage_project_members($pdo, (int)$task['project_id'], $user);
}

function task_has_locked_review($pdo, $taskId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM task_submissions WHERE task_id = ? AND status IN ('submitted','approved')");
    $stmt->execute([$taskId]);
    return (int)$stmt->fetchColumn() > 0;
}



function get_unread_notification_count($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function get_activity_target_url($row) {
    if (!empty($row['task_id'])) {
        return 'actions/task-detail.php?id=' . (int)$row['task_id'];
    }
    if (!empty($row['project_id'])) {
        return 'actions/project-detail.php?id=' . (int)$row['project_id'];
    }
    return 'index.php?page=activity';
}

function get_notification_type($title, $message = '') {
    $text = strtolower($title . ' ' . $message);
    if (str_contains($text, 'deadline')) return 'deadline';
    if (str_contains($text, 'komentar')) return 'comment';
    if (str_contains($text, 'bukti') || str_contains($text, 'submit')) return 'submit';
    if (str_contains($text, 'ditolak') || str_contains($text, 'reject')) return 'reject';
    if (str_contains($text, 'verified') || str_contains($text, 'approved')) return 'approved';
    if (str_contains($text, 'project')) return 'project';
    if (str_contains($text, 'task')) return 'task';
    return 'general';
}

function notification_type_badge($type) {
    $map = [
        'deadline' => 'bg-red-100 text-red-700',
        'comment' => 'bg-blue-100 text-blue-700',
        'submit' => 'bg-amber-100 text-amber-800',
        'reject' => 'bg-red-100 text-red-700',
        'approved' => 'bg-green-100 text-green-700',
        'project' => 'bg-indigo-100 text-indigo-700',
        'task' => 'bg-orange-100 text-orange-700',
        'general' => 'bg-slate-100 text-slate-700',
    ];
    $class = $map[$type] ?? $map['general'];
    return '<span class="inline-flex rounded-full px-2 py-1 text-xs font-black ' . $class . '">' . e(strtoupper($type)) . '</span>';
}
