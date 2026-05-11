<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireRole('admin');

$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);
$msg    = '';
$err    = '';

// ── CREATE ──────────────────────────────────────────
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $role  = $_POST['role'] ?? 'resident';
    $dept  = trim($_POST['department'] ?? '');

    if (!$name || !$email || !$pass) {
        $err = 'Name, email and password are required.';
        $action = 'create';
    } elseif (strlen($pass) < 6) {
        $err = 'Password must be at least 6 characters.';
        $action = 'create';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, department) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $hash, $role, $dept);
        if ($stmt->execute()) {
            $msg = "User '{$name}' created successfully.";
            $action = 'list';
        } else {
            $err = 'Email already exists or database error.';
            $action = 'create';
        }
        $stmt->close();
    }
}

// ── UPDATE ──────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid2  = intval($_POST['user_id'] ?? 0);
    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role  = $_POST['role'] ?? 'resident';
    $dept  = trim($_POST['department'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$name || !$email) {
        $err = 'Name and email are required.';
        $action = 'edit';
        $id = $uid2;
    } else {
        if ($pass && strlen($pass) >= 6) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, password=?, role=?, department=? WHERE id=?");
            $stmt->bind_param("sssssi", $name, $email, $hash, $role, $dept, $uid2);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role=?, department=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $email, $role, $dept, $uid2);
        }
        if ($stmt->execute()) {
            $msg = "User updated successfully.";
            $action = 'list';
        } else {
            $err = 'Email already taken or database error.';
            $action = 'edit';
            $id = $uid2;
        }
        $stmt->close();
    }
}

// ── DELETE ──────────────────────────────────────────
if ($action === 'delete' && $id) {
    if ($id === intval($_SESSION['user_id'])) {
        $err = 'You cannot delete your own account.';
        $action = 'list';
    } else {
        $conn->query("DELETE FROM users WHERE id = $id");
        $msg = 'User deleted successfully.';
        $action = 'list';
    }
}

// Load user for edit
$user = null;
if ($action === 'edit' && $id) {
    $user = $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();
    if (!$user) $action = 'list';
}

// List
$users = [];
if ($action === 'list') {
    $users = $conn->query("SELECT u.*, 
        (SELECT COUNT(*) FROM complaints WHERE user_id = u.id) as complaint_count
        FROM users u ORDER BY u.role, u.full_name")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users – CFRS</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">👥 <span>User Management</span></div>
            <div class="topbar-actions">
                <?php if ($action !== 'create'): ?>
                <a href="users.php?action=create" class="btn btn-primary btn-sm">+ Add User</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-content">
        <?php if ($msg): ?><div class="alert alert-success">✅ <?= $msg ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

        <?php if (in_array($action, ['create', 'edit'])): ?>
        <!-- ══ FORM ════════════════════════════════════════════ -->
        <div class="page-header">
            <div>
                <h1><?= $action === 'create' ? '➕ Add New User' : '✏️ Edit User' ?></h1>
            </div>
            <a href="users.php" class="btn btn-secondary">← Back</a>
        </div>
        <div class="card" style="max-width:640px;">
            <div class="card-body">
                <form method="POST" action="users.php?action=<?= $action === 'edit' ? 'update' : 'create' ?>">
                    <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" required
                                   value="<?= htmlspecialchars($action === 'edit' ? $user['full_name'] : ($_POST['full_name'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" required
                                   value="<?= htmlspecialchars($action === 'edit' ? $user['email'] : ($_POST['email'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label><?= $action === 'edit' ? 'New Password (leave blank to keep)' : 'Password *' ?></label>
                            <input type="password" name="password" <?= $action === 'create' ? 'required' : '' ?>
                                   placeholder="<?= $action === 'edit' ? 'Leave blank to keep current' : 'Min 6 characters' ?>">
                        </div>
                        <div class="form-group">
                            <label>Role *</label>
                            <select name="role" required>
                                <option value="admin"    <?= ($action==='edit'?$user['role']:($_POST['role']??''))==='admin'    ?'selected':'' ?>>🛡 Admin</option>
                                <option value="staff"    <?= ($action==='edit'?$user['role']:($_POST['role']??''))==='staff'    ?'selected':'' ?>>👷 Staff</option>
                                <option value="resident" <?= ($action==='edit'?$user['role']:($_POST['role']??''))==='resident' ?'selected':'' ?>>🏠 Resident/Student</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Department (for staff)</label>
                            <input type="text" name="department" placeholder="e.g. Maintenance, Cleaning..."
                                   value="<?= htmlspecialchars($action === 'edit' ? ($user['department'] ?? '') : ($_POST['department'] ?? '')) ?>">
                        </div>
                    </div>
                    <div style="margin-top:24px;display:flex;gap:12px;">
                        <button type="submit" class="btn btn-primary">
                            <?= $action === 'create' ? '➕ Create User' : '💾 Save Changes' ?>
                        </button>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- ══ LIST ════════════════════════════════════════════ -->
        <div class="page-header">
            <div>
                <h1>👥 System Users</h1>
                <p><?= count($users) ?> registered user(s)</p>
            </div>
            <a href="users.php?action=create" class="btn btn-primary">➕ Add User</a>
        </div>

        <div class="card">
            <div class="table-wrap">
            <?php if (empty($users)): ?>
            <div class="empty-state"><div class="icon">👤</div><h3>No users found</h3></div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Complaints</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td style="color:#999;font-size:12px;"><?= $i+1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="avatar" style="width:32px;height:32px;font-size:13px;"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
                            <?= htmlspecialchars($u['full_name']) ?>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                            <span style="font-size:11px;color:#27ae60;font-weight:600;">(you)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="font-size:13px;color:#666;"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php
                        $roleColors = ['admin'=>'#c0392b','staff'=>'#e67e22','resident'=>'#2980b9'];
                        $roleIcons  = ['admin'=>'🛡','staff'=>'👷','resident'=>'🏠'];
                        ?>
                        <span style="background:<?= $roleColors[$u['role']] ?? '#999' ?>1a;color:<?= $roleColors[$u['role']] ?? '#999' ?>;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;">
                            <?= $roleIcons[$u['role']] ?? '' ?> <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($u['department'] ?? '—') ?></td>
                    <td style="font-size:13px;"><?= $u['complaint_count'] ?></td>
                    <td style="font-size:12px;color:#999;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="actions">
                            <a href="users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="users.php?action=delete&id=<?= $u['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete user <?= htmlspecialchars($u['full_name']) ?>?')">🗑</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
