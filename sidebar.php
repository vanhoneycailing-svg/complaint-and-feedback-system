<?php
// Get unread notification count
$uid_notif = $_SESSION['user_id'];
$unread = $conn->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid_notif AND is_read=0")->fetch_row()[0];

// Current page
$current_page = basename($_SERVER['PHP_SELF']);

function navLink($href, $icon, $label, $current, $badge = 0) {
    $active    = ($current === $href) ? 'active' : '';
    $badgeHtml = $badge > 0 ? "<span class='nav-badge'>$badge</span>" : '';
    return "<a href='$href' class='sidebar-link $active'><span class='nav-icon'>$icon</span> $label $badgeHtml</a>";
}
?>
<aside class="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">📋</div>
        <h2>CFRS</h2>
        <span>Complaint & Feedback System</span>
    </div>

    <!-- Main Navigation -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">Main</div>
        <nav class="sidebar-nav">
            <?= navLink('dashboard.php', '◈', 'Dashboard', $current_page) ?>
            <?= navLink('complaints.php', '📄', 'Complaints', $current_page) ?>
            <?= navLink('notifications.php', '🔔', 'Notifications', $current_page, $unread) ?>
        </nav>
    </div>

    <!-- Admin-only Navigation -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Administration</div>
        <nav class="sidebar-nav">
            <?= navLink('users.php', '👥', 'Users', $current_page) ?>
            <?= navLink('reports.php', '📊', 'Reports', $current_page) ?>
        </nav>
    </div>
    <?php endif; ?>

    <!-- User / Logout -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div class="sidebar-user-info">
                <div class="name"><?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?></div>
                <div class="role"><?= ucfirst($_SESSION['role']) ?></div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-link" style="margin-top:6px;color:rgba(255,255,255,0.5);">
            <span class="nav-icon">⏻</span> Sign Out
        </a>
    </div>

</aside>
