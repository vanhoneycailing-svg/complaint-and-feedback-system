<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireRole('admin');

// Overall stats
$stats = $conn->query("SELECT
    COUNT(*) as total,
    SUM(status='pending') as pending,
    SUM(status='in_progress') as in_progress,
    SUM(status='resolved') as resolved,
    SUM(status='rejected') as rejected,
    AVG(rating) as avg_rating,
    SUM(rating IS NOT NULL) as rated_count
    FROM complaints")->fetch_assoc();

// By category
$byCat = $conn->query("SELECT category, COUNT(*) as cnt, 
    SUM(status='resolved') as resolved,
    AVG(rating) as avg_rating
    FROM complaints GROUP BY category ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);

// By month (last 6 months)
$byMonth = $conn->query("SELECT DATE_FORMAT(created_at,'%b %Y') as month,
    COUNT(*) as total, SUM(status='resolved') as resolved
    FROM complaints
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY created_at ASC")->fetch_all(MYSQLI_ASSOC);

// Staff performance
$staffPerf = $conn->query("SELECT u.full_name, u.department,
    COUNT(c.id) as assigned,
    SUM(c.status='resolved') as resolved,
    AVG(c.rating) as avg_rating
    FROM users u
    LEFT JOIN complaints c ON u.id = c.assigned_to
    WHERE u.role = 'staff'
    GROUP BY u.id ORDER BY resolved DESC")->fetch_all(MYSQLI_ASSOC);

// Top categories
$maxCat = max(array_column($byCat, 'cnt') ?: [1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports – CFRS</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .report-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; }
        .bar-chart { display:flex; flex-direction:column; gap:12px; }
        .bar-row { display:flex; align-items:center; gap:12px; }
        .bar-label { width:110px; font-size:13px; font-weight:500; flex-shrink:0; }
        .bar-track { flex:1; background:#f0f0f0; border-radius:6px; height:20px; overflow:hidden; }
        .bar-fill { height:100%; background:var(--primary); border-radius:6px; transition:width .5s; }
        .bar-val { width:40px; text-align:right; font-size:13px; font-weight:700; color:#444; }
        .perf-table td, .perf-table th { padding:10px 14px; }
        .star-sm { color:#f39c12; font-size:13px; }
        @media(max-width:800px){ .report-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">📊 <span>Reports & Analytics</span></div>
        </div>

        <div class="page-content">
            <div class="page-header">
                <div><h1>📊 System Reports</h1><p>Generated on <?= date('F j, Y') ?></p></div>
            </div>

            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom:24px;">
                <div class="stat-card total">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info"><h3><?= $stats['total'] ?></h3><p>Total Complaints</p></div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info"><h3><?= $stats['pending'] ?></h3><p>Pending</p></div>
                </div>
                <div class="stat-card progress">
                    <div class="stat-icon">🔄</div>
                    <div class="stat-info"><h3><?= $stats['in_progress'] ?></h3><p>In Progress</p></div>
                </div>
                <div class="stat-card resolved">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info"><h3><?= $stats['resolved'] ?></h3><p>Resolved</p></div>
                </div>
                <div class="stat-card" style="border-left-color:#9b59b6;">
                    <div class="stat-icon" style="background:#f3e5f5;">⭐</div>
                    <div class="stat-info">
                        <h3><?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '—' ?></h3>
                        <p>Avg. Rating (<?= $stats['rated_count'] ?> rated)</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color:#1abc9c;">
                    <div class="stat-icon" style="background:#e0f2f1;">📈</div>
                    <div class="stat-info">
                        <h3><?= $stats['total'] > 0 ? round(($stats['resolved'] / $stats['total']) * 100) : 0 ?>%</h3>
                        <p>Resolution Rate</p>
                    </div>
                </div>
            </div>

            <div class="report-grid">
                <!-- By Category -->
                <div class="card">
                    <div class="card-header"><h3>📂 Complaints by Category</h3></div>
                    <div class="card-body">
                        <?php if (empty($byCat)): ?>
                        <p style="color:#999;text-align:center;">No data yet</p>
                        <?php else: ?>
                        <div class="bar-chart">
                        <?php foreach ($byCat as $b): ?>
                        <div class="bar-row">
                            <div class="bar-label"><?= categoryIcon($b['category']) ?> <?= ucfirst($b['category']) ?></div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:<?= round(($b['cnt']/$maxCat)*100) ?>%"></div>
                            </div>
                            <div class="bar-val"><?= $b['cnt'] ?></div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Breakdown -->
                <div class="card">
                    <div class="card-header"><h3>🥧 Status Breakdown</h3></div>
                    <div class="card-body">
                        <?php
                        $statuses = [
                            ['label'=>'Pending',     'val'=>$stats['pending'],     'color'=>'#f39c12','icon'=>'⏳'],
                            ['label'=>'In Progress', 'val'=>$stats['in_progress'], 'color'=>'#2980b9','icon'=>'🔄'],
                            ['label'=>'Resolved',    'val'=>$stats['resolved'],    'color'=>'#27ae60','icon'=>'✅'],
                            ['label'=>'Rejected',    'val'=>$stats['rejected'],    'color'=>'#c0392b','icon'=>'❌'],
                        ];
                        $total = max($stats['total'], 1);
                        ?>
                        <div style="display:flex;flex-direction:column;gap:14px;">
                        <?php foreach ($statuses as $s): ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                                <span style="font-size:13px;font-weight:600;"><?= $s['icon'] ?> <?= $s['label'] ?></span>
                                <span style="font-size:13px;font-weight:700;color:<?= $s['color'] ?>"><?= $s['val'] ?> (<?= round($s['val']/$total*100) ?>%)</span>
                            </div>
                            <div style="background:#f0f0f0;border-radius:6px;height:10px;">
                                <div style="width:<?= round($s['val']/$total*100) ?>%;background:<?= $s['color'] ?>;height:100%;border-radius:6px;transition:width .5s;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trend -->
                <div class="card">
                    <div class="card-header"><h3>📅 Monthly Trend (6 months)</h3></div>
                    <div class="card-body">
                        <?php if (empty($byMonth)): ?>
                        <p style="color:#999;text-align:center;">No data yet</p>
                        <?php else:
                        $maxM = max(array_column($byMonth,'total') ?: [1]);
                        ?>
                        <div class="bar-chart">
                        <?php foreach ($byMonth as $m): ?>
                        <div class="bar-row">
                            <div class="bar-label" style="font-size:12px;"><?= $m['month'] ?></div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:<?= round(($m['total']/$maxM)*100) ?>%;background:#3498db;"></div>
                            </div>
                            <div class="bar-val"><?= $m['total'] ?></div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Staff Performance -->
                <div class="card">
                    <div class="card-header"><h3>👷 Staff Performance</h3></div>
                    <div class="card-body" style="padding:0;">
                        <?php if (empty($staffPerf)): ?>
                        <div class="empty-state"><div class="icon">👷</div><h3>No staff yet</h3></div>
                        <?php else: ?>
                        <div class="table-wrap">
                        <table class="perf-table">
                            <thead><tr>
                                <th>Staff</th>
                                <th>Assigned</th>
                                <th>Resolved</th>
                                <th>Rating</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($staffPerf as $sp): ?>
                            <tr>
                                <td>
                                    <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($sp['full_name']) ?></div>
                                    <div style="font-size:11px;color:#999;"><?= htmlspecialchars($sp['department'] ?? '—') ?></div>
                                </td>
                                <td><?= $sp['assigned'] ?></td>
                                <td>
                                    <span style="color:#27ae60;font-weight:700;"><?= $sp['resolved'] ?></span>
                                    <?php if ($sp['assigned'] > 0): ?>
                                    <span style="font-size:11px;color:#999;">(<?= round($sp['resolved']/$sp['assigned']*100) ?>%)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sp['avg_rating']): ?>
                                    <span class="star-sm">★</span> <?= number_format($sp['avg_rating'],1) ?>
                                    <?php else: ?><span style="color:#ccc;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
