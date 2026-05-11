<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

// Stats
$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role === 'admin') {
    $r = $conn->query("SELECT
        COUNT(*) as total,
        SUM(status='pending') as pending,
        SUM(status='in_progress') as in_progress,
        SUM(status='resolved') as resolved,
        SUM(status='rejected') as rejected
        FROM complaints")->fetch_assoc();
} elseif ($role === 'staff') {
    $stmt = $conn->prepare("SELECT
        COUNT(*) as total,
        SUM(status='pending') as pending,
        SUM(status='in_progress') as in_progress,
        SUM(status='resolved') as resolved,
        SUM(status='rejected') as rejected
        FROM complaints WHERE assigned_to = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
} else {
    $stmt = $conn->prepare("SELECT
        COUNT(*) as total,
        SUM(status='pending') as pending,
        SUM(status='in_progress') as in_progress,
        SUM(status='resolved') as resolved,
        SUM(status='rejected') as rejected
        FROM complaints WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
}

// Recent complaints
if ($role === 'admin') {
    $recent = $conn->query("SELECT c.*, u.full_name FROM complaints c
        JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
} elseif ($role === 'staff') {
    $stmt = $conn->prepare("SELECT c.*, u.full_name FROM complaints c
        JOIN users u ON c.user_id = u.id WHERE c.assigned_to = ? ORDER BY c.created_at DESC LIMIT 8");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT c.*, u.full_name FROM complaints c
        JOIN users u ON c.user_id = u.id WHERE c.user_id = ? ORDER BY c.created_at DESC LIMIT 8");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – CFRS</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div>
                <div class="topbar-title">📊 <span>Dashboard</span></div>
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-notif">🔔
                    <?php if ($unread > 0): ?><span class="notif-dot"><?= $unread ?></span><?php endif; ?>
                </a>
                <?php if (isResident()): ?>
                <a href="complaints.php?action=new" class="btn btn-primary btn-sm">+ Submit Complaint</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-content">
            <div class="page-header">
                <div>
                    <h1>Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?>! 👋</h1>
                    <p><?= date('l, F j, Y') ?> &nbsp;·&nbsp; <?= ucfirst($role) ?> Dashboard</p>
                </div>
                <?php if (isResident()): ?>
                <a href="complaints.php?action=new" class="btn btn-primary">📝 Submit Complaint</a>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info">
                        <h3><?= $r['total'] ?? 0 ?></h3>
                        <p>Total Complaints</p>
                    </div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info">
                        <h3><?= $r['pending'] ?? 0 ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card progress">
                    <div class="stat-icon">🔄</div>
                    <div class="stat-info">
                        <h3><?= $r['in_progress'] ?? 0 ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>
                <div class="stat-card resolved">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <h3><?= $r['resolved'] ?? 0 ?></h3>
                        <p>Resolved</p>
                    </div>
                </div>
            </div>

            <!-- Recent Complaints -->
            <div class="card">
                <div class="card-header">
                    <h3>📄 Recent Complaints</h3>
                    <a href="complaints.php" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="table-wrap">
                    <?php if (empty($recent)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>No complaints yet</h3>
                        <p><?= isResident() ? 'Submit your first complaint using the button above.' : 'No complaints assigned to you yet.' ?></p>
                    </div>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Complaint #</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <?php if (!isResident()): ?><th>From</th><?php endif; ?>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $c): ?>
                            <tr>
                                <td><span class="complaint-no"><?= htmlspecialchars($c['complaint_no']) ?></span></td>
                                <td><?= htmlspecialchars(substr($c['title'], 0, 40)) . (strlen($c['title']) > 40 ? '…' : '') ?></td>
                                <td><span class="cat-badge"><?= categoryIcon($c['category']) ?> <?= ucfirst($c['category']) ?></span></td>
                                <td><?= statusBadge($c['status']) ?></td>
                                <?php if (!isResident()): ?>
                                <td><?= htmlspecialchars($c['full_name']) ?></td>
                                <?php endif; ?>
                                <td style="font-size:12px;color:#999;"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                                <td>
                                    <a href="complaints.php?action=view&id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
