<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$uid = $_SESSION['user_id'];

// Mark all read
if (isset($_GET['mark_read'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    header('Location: notifications.php');
    exit;
}

// Mark single read
if (isset($_GET['read_id'])) {
    $rid = intval($_GET['read_id']);
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$rid AND user_id=$uid");
    // Redirect to complaint
    $notif = $conn->query("SELECT complaint_id FROM notifications WHERE id=$rid")->fetch_assoc();
    if ($notif) {
        header('Location: complaints.php?action=view&id=' . $notif['complaint_id']);
        exit;
    }
}

$notifications = $conn->query("SELECT n.*, c.complaint_no, c.title as complaint_title
    FROM notifications n
    JOIN complaints c ON n.complaint_id = c.id
    WHERE n.user_id = $uid
    ORDER BY n.created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications – CFRS</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">🔔 <span>Notifications</span></div>
            <div class="topbar-actions">
                <?php if ($unread > 0): ?>
                <a href="notifications.php?mark_read=1" class="btn btn-secondary btn-sm">✓ Mark All Read</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-content">
            <div class="page-header">
                <div>
                    <h1>🔔 Notifications</h1>
                    <p><?= $unread ?> unread notification(s)</p>
                </div>
            </div>

            <div class="card">
                <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <div class="icon">🔕</div>
                    <h3>No notifications yet</h3>
                    <p>You'll be notified when complaints are updated.</p>
                </div>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;">
                <?php foreach ($notifications as $n): ?>
                <a href="notifications.php?read_id=<?= $n['id'] ?>"
                   style="display:flex;align-items:flex-start;gap:16px;padding:18px 24px;
                          border-bottom:1px solid #f0f0f0;transition:background .15s;
                          background:<?= !$n['is_read'] ? '#fffdf0' : 'white' ?>;">
                    <div style="width:40px;height:40px;border-radius:50%;
                         background:<?= !$n['is_read'] ? '#c0392b' : '#e0e0e0' ?>;
                         display:flex;align-items:center;justify-content:center;
                         font-size:18px;flex-shrink:0;color:white;">
                        <?= !$n['is_read'] ? '🔔' : '🔕' ?>
                    </div>
                    <div style="flex:1;">
                        <p style="font-size:14px;color:#333;font-weight:<?= !$n['is_read'] ? '600' : '400' ?>;">
                            <?= htmlspecialchars($n['message']) ?>
                        </p>
                        <div style="margin-top:4px;display:flex;gap:12px;align-items:center;">
                            <span class="complaint-no" style="font-size:11px;"><?= htmlspecialchars($n['complaint_no']) ?></span>
                            <span style="font-size:11px;color:#aaa;"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></span>
                            <?php if (!$n['is_read']): ?>
                            <span style="background:#c0392b;color:white;border-radius:4px;padding:1px 7px;font-size:10px;font-weight:700;">NEW</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="color:#ccc;font-size:18px;">›</div>
                </a>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
