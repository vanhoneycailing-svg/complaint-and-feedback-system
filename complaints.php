<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$uid    = $_SESSION['user_id'];
$role   = $_SESSION['role'];
$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);
$msg    = '';
$err    = '';

// ── CREATE ───────────────────────────────────────────
if ($action === 'new' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = $_POST['category'] ?? 'others';

    if (!$title || !$description) {
        $err = 'Title and description are required.';
        $action = 'new';
    } else {
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $photo = uploadPhoto($_FILES['photo'], 'complaint');
            if (!$photo) $err = 'Invalid photo. Use JPG/PNG under 5MB.';
        }

        if (!$err) {
            $cno  = generateComplaintNo();
            $stmt = $conn->prepare("INSERT INTO complaints (complaint_no, user_id, title, description, category, photo)
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissss", $cno, $uid, $title, $description, $category, $photo);
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                // Notify admins
                $admins = $conn->query("SELECT id FROM users WHERE role='admin'")->fetch_all(MYSQLI_ASSOC);
                foreach ($admins as $admin) {
                    addNotification($conn, $admin['id'], $newId, "New complaint [{$cno}] submitted by {$_SESSION['full_name']}.");
                }
                $msg    = "Complaint submitted successfully! Reference: <strong>{$cno}</strong>";
                $action = 'list';
            } else {
                $err = 'Database error. Please try again.';
            }
            $stmt->close();
        } else {
            $action = 'new';
        }
    }
}

// ── UPDATE STATUS (Admin/Staff) ───────────────────────
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isResident()) {
        $cid     = intval($_POST['complaint_id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        $notes   = trim($_POST['resolution_notes'] ?? '');
        $valid   = ['pending','in_progress','resolved','rejected'];

        if (in_array($status, $valid) && $cid) {
            $resPhoto = null;
            if (!empty($_FILES['resolution_photo']['name'])) {
                $resPhoto = uploadPhoto($_FILES['resolution_photo'], 'resolution');
            }
            $resolvedAt = $status === 'resolved' ? date('Y-m-d H:i:s') : null;

            $stmt = $conn->prepare("UPDATE complaints SET status=?, resolution_notes=?, resolution_photo=?, resolved_at=? WHERE id=?");
            $stmt->bind_param("ssssi", $status, $notes, $resPhoto, $resolvedAt, $cid);
            $stmt->execute();
            $stmt->close();

            // Get complaint owner
            $owner = $conn->query("SELECT user_id, complaint_no FROM complaints WHERE id=$cid")->fetch_assoc();
            if ($owner) {
                addNotification($conn, $owner['user_id'], $cid, "Your complaint [{$owner['complaint_no']}] status changed to: " . strtoupper($status) . ".");
            }
            $msg    = 'Status updated successfully.';
            $action = 'view';
            $id     = $cid;
        }
    }
}

// ── ASSIGN (Admin) ────────────────────────────────────
if ($action === 'assign' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $cid    = intval($_POST['complaint_id'] ?? 0);
    $staffId = intval($_POST['staff_id'] ?? 0);
    if ($cid && $staffId) {
        $stmt = $conn->prepare("UPDATE complaints SET assigned_to=?, status='in_progress' WHERE id=?");
        $stmt->bind_param("ii", $staffId, $cid);
        $stmt->execute();
        $stmt->close();
        // Notify staff
        $cno = $conn->query("SELECT complaint_no FROM complaints WHERE id=$cid")->fetch_assoc()['complaint_no'] ?? '';
        addNotification($conn, $staffId, $cid, "Complaint [{$cno}] has been assigned to you.");
        $msg    = 'Complaint assigned to staff successfully.';
        $action = 'view';
        $id     = $cid;
    }
}

// ── RATE/FEEDBACK (Resident, Resolved) ───────────────
if ($action === 'rate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid      = intval($_POST['complaint_id'] ?? 0);
    $rating   = intval($_POST['rating'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');
    if ($cid && $rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("UPDATE complaints SET rating=?, feedback=? WHERE id=? AND user_id=?");
        $stmt->bind_param("isii", $rating, $feedback, $cid, $uid);
        $stmt->execute();
        $stmt->close();
        $msg    = 'Thank you for your feedback!';
        $action = 'view';
        $id     = $cid;
    }
}

// ── DELETE (Admin) ────────────────────────────────────
if ($action === 'delete' && isAdmin() && $id) {
    $complaint = $conn->query("SELECT photo, resolution_photo FROM complaints WHERE id=$id")->fetch_assoc();
    if ($complaint) {
        foreach (['photo', 'resolution_photo'] as $pf) {
            if ($complaint[$pf]) @unlink(__DIR__ . '/uploads/' . $complaint[$pf]);
        }
        $conn->query("DELETE FROM complaints WHERE id=$id");
        $msg    = 'Complaint deleted successfully.';
        $action = 'list';
        $id     = 0;
    }
}

// ── LOAD SINGLE ───────────────────────────────────────
$complaint = null;
if (in_array($action, ['view', 'edit']) && $id) {
    $q = "SELECT c.*, u.full_name as submitter_name,
                 s.full_name as staff_name
          FROM complaints c
          JOIN users u ON c.user_id = u.id
          LEFT JOIN users s ON c.assigned_to = s.id
          WHERE c.id = $id";
    if ($role === 'resident') $q .= " AND c.user_id = $uid";
    elseif ($role === 'staff') $q .= " AND (c.assigned_to = $uid OR c.user_id = $uid)";
    $complaint = $conn->query($q)->fetch_assoc();
    if (!$complaint) { $action = 'list'; $id = 0; }
}

// ── LIST ──────────────────────────────────────────────
$complaints = [];
if ($action === 'list') {
    $where = [];
    if ($role === 'resident') $where[] = "c.user_id = $uid";
    elseif ($role === 'staff') $where[] = "c.assigned_to = $uid";

    // Filters
    $filterStatus = $_GET['status'] ?? '';
    $filterCat    = $_GET['category'] ?? '';
    $search       = trim($_GET['search'] ?? '');
    if ($filterStatus) $where[] = "c.status = '" . $conn->real_escape_string($filterStatus) . "'";
    if ($filterCat)    $where[] = "c.category = '" . $conn->real_escape_string($filterCat) . "'";
    if ($search)       $where[] = "(c.title LIKE '%" . $conn->real_escape_string($search) . "%' OR c.complaint_no LIKE '%" . $conn->real_escape_string($search) . "%')";

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $complaints = $conn->query("SELECT c.*, u.full_name FROM complaints c
        JOIN users u ON c.user_id = u.id
        $whereSQL ORDER BY c.created_at DESC")->fetch_all(MYSQLI_ASSOC);
}

// Staff list for assign
$staffList = [];
if (isAdmin()) {
    $staffList = $conn->query("SELECT id, full_name, department FROM users WHERE role='staff' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints – CFRS</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">📄 <span>Complaints</span></div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-notif">🔔
                    <?php if ($unread > 0): ?><span class="notif-dot"><?= $unread ?></span><?php endif; ?>
                </a>
                <?php if ($action !== 'new' && (isResident() || isAdmin())): ?>
                <a href="complaints.php?action=new" class="btn btn-primary btn-sm">+ Submit Complaint</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-content">

        <?php if ($msg): ?>
        <div class="alert alert-success">✅ <?= $msg ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!-- ══ SUBMIT FORM ══════════════════════════════════════ -->
        <?php if ($action === 'new'): ?>
        <div class="page-header">
            <div>
                <h1>📝 Submit Complaint</h1>
                <p>Fill out the form below to submit your complaint.</p>
            </div>
            <a href="complaints.php" class="btn btn-secondary">← Back to List</a>
        </div>

        <div class="card">
            <div class="card-header"><h3>Complaint Details</h3></div>
            <div class="card-body">
                <form method="POST" action="complaints.php?action=new" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Complaint Title *</label>
                            <input type="text" name="title" placeholder="Brief description of the issue"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" required>
                                <option value="facilities" <?= ($_POST['category']??'') === 'facilities' ? 'selected':'' ?>>🏢 Facilities</option>
                                <option value="noise"      <?= ($_POST['category']??'') === 'noise'      ? 'selected':'' ?>>🔊 Noise</option>
                                <option value="safety"     <?= ($_POST['category']??'') === 'safety'     ? 'selected':'' ?>>⚠️ Safety</option>
                                <option value="cleanliness"<?= ($_POST['category']??'') === 'cleanliness'? 'selected':'' ?>>🧹 Cleanliness</option>
                                <option value="others"     <?= ($_POST['category']??'') === 'others'     ? 'selected':'' ?>>📋 Others</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Photo Evidence (optional)</label>
                            <div class="input-file-wrapper">
                                📷 <strong>Click or drag to upload</strong>
                                <p>JPG, PNG, GIF, WEBP · Max 5MB</p>
                                <input type="file" name="photo" accept="image/*" id="photoInput"
                                       onchange="previewPhoto(this,'photoPreview')">
                            </div>
                            <img id="photoPreview" src="" style="display:none;max-height:160px;border-radius:8px;margin-top:8px;">
                        </div>
                        <div class="form-group full">
                            <label>Detailed Description *</label>
                            <textarea name="description" rows="5"
                                      placeholder="Provide as much detail as possible about the issue..."
                                      required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div style="margin-top:24px;display:flex;gap:12px;">
                        <button type="submit" class="btn btn-primary">📤 Submit Complaint</button>
                        <a href="complaints.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ VIEW ════════════════════════════════════════════ -->
        <?php elseif ($action === 'view' && $complaint): ?>
        <div class="page-header">
            <div>
                <h1><?= htmlspecialchars($complaint['title']) ?></h1>
                <p><span class="complaint-no"><?= $complaint['complaint_no'] ?></span>
                &nbsp;·&nbsp; Filed <?= date('M j, Y g:i A', strtotime($complaint['created_at'])) ?></p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <?= statusBadge($complaint['status']) ?>
                <a href="complaints.php" class="btn btn-secondary btn-sm">← Back</a>
                <?php if (isAdmin()): ?>
                <a href="complaints.php?action=delete&id=<?= $complaint['id'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this complaint permanently?')">🗑 Delete</a>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;">
            <!-- Left -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                <div class="card">
                    <div class="card-header"><h3>📋 Complaint Details</h3></div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                            <div>
                                <div style="font-size:11px;font-weight:600;color:#999;text-transform:uppercase;margin-bottom:4px;">Category</div>
                                <span class="cat-badge"><?= categoryIcon($complaint['category']) ?> <?= ucfirst($complaint['category']) ?></span>
                            </div>
                            <div>
                                <div style="font-size:11px;font-weight:600;color:#999;text-transform:uppercase;margin-bottom:4px;">Status</div>
                                <?= statusBadge($complaint['status']) ?>
                            </div>
                            <div>
                                <div style="font-size:11px;font-weight:600;color:#999;text-transform:uppercase;margin-bottom:4px;">Submitted By</div>
                                <div style="font-size:14px;"><?= htmlspecialchars($complaint['submitter_name']) ?></div>
                            </div>
                            <?php if ($complaint['staff_name']): ?>
                            <div>
                                <div style="font-size:11px;font-weight:600;color:#999;text-transform:uppercase;margin-bottom:4px;">Assigned To</div>
                                <div style="font-size:14px;">👷 <?= htmlspecialchars($complaint['staff_name']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom:16px;">
                            <div style="font-size:11px;font-weight:600;color:#999;text-transform:uppercase;margin-bottom:6px;">Description</div>
                            <p style="font-size:14px;line-height:1.7;color:#444;"><?= nl2br(htmlspecialchars($complaint['description'])) ?></p>
                        </div>

                        <?php if ($complaint['photo']): ?>
                        <div>
                            <div style="font-size:11px;font-weight:600;color:#999;text-transform:uppercase;margin-bottom:6px;">Photo Evidence</div>
                            <img src="uploads/<?= htmlspecialchars($complaint['photo']) ?>"
                                 class="photo-large" style="max-height:300px;cursor:zoom-in;"
                                 onclick="openLightbox(this.src)">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($complaint['resolution_notes'] || $complaint['resolution_photo']): ?>
                <div class="card">
                    <div class="card-header"><h3>🔧 Resolution Details</h3></div>
                    <div class="card-body">
                        <?php if ($complaint['resolution_notes']): ?>
                        <p style="font-size:14px;line-height:1.7;margin-bottom:12px;"><?= nl2br(htmlspecialchars($complaint['resolution_notes'])) ?></p>
                        <?php endif; ?>
                        <?php if ($complaint['resolution_photo']): ?>
                        <img src="uploads/<?= htmlspecialchars($complaint['resolution_photo']) ?>"
                             class="photo-large" style="max-height:240px;cursor:zoom-in;"
                             onclick="openLightbox(this.src)">
                        <?php endif; ?>
                        <?php if ($complaint['resolved_at']): ?>
                        <p style="font-size:12px;color:#999;margin-top:8px;">
                            ✅ Resolved on <?= date('M j, Y g:i A', strtotime($complaint['resolved_at'])) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rating -->
                <?php if ($role === 'resident' && $complaint['status'] === 'resolved' && !$complaint['rating']): ?>
                <div class="card" style="border: 2px solid #f39c12;">
                    <div class="card-header"><h3>⭐ Rate This Resolution</h3></div>
                    <div class="card-body">
                        <form method="POST" action="complaints.php?action=rate">
                            <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                            <div class="form-group" style="margin-bottom:16px;">
                                <label>Your Rating</label>
                                <div class="star-rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>">
                                    <label for="star<?= $i ?>">★</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:16px;">
                                <label>Feedback (optional)</label>
                                <textarea name="feedback" rows="3" placeholder="Share your experience..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning">⭐ Submit Rating</button>
                        </form>
                    </div>
                </div>
                <?php elseif ($complaint['rating']): ?>
                <div class="card">
                    <div class="card-header"><h3>⭐ User Feedback</h3></div>
                    <div class="card-body">
                        <div style="font-size:24px;margin-bottom:8px;">
                            <?= str_repeat('★', $complaint['rating']) ?><?= str_repeat('☆', 5 - $complaint['rating']) ?>
                        </div>
                        <?php if ($complaint['feedback']): ?>
                        <p style="font-size:14px;color:#555;font-style:italic;">"<?= htmlspecialchars($complaint['feedback']) ?>"</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Panel -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <?php if (isAdmin() && !$complaint['assigned_to'] && $complaint['status'] === 'pending'): ?>
                <!-- Assign -->
                <div class="card">
                    <div class="card-header"><h3>👷 Assign to Staff</h3></div>
                    <div class="card-body">
                        <form method="POST" action="complaints.php?action=assign">
                            <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>Select Staff</label>
                                <select name="staff_id" required>
                                    <option value="">-- Choose --</option>
                                    <?php foreach ($staffList as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['department'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                                ✅ Assign
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Update Status -->
                <?php if (!isResident() && $complaint['status'] !== 'resolved' && $complaint['status'] !== 'rejected'): ?>
                <div class="card">
                    <div class="card-header"><h3>🔄 Update Status</h3></div>
                    <div class="card-body">
                        <form method="POST" action="complaints.php?action=update_status" enctype="multipart/form-data">
                            <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>New Status</label>
                                <select name="status" required>
                                    <option value="pending"     <?= $complaint['status']==='pending'     ?'selected':'' ?>>⏳ Pending</option>
                                    <option value="in_progress" <?= $complaint['status']==='in_progress' ?'selected':'' ?>>🔄 In Progress</option>
                                    <option value="resolved"    <?= $complaint['status']==='resolved'    ?'selected':'' ?>>✅ Resolved</option>
                                    <option value="rejected"    <?= $complaint['status']==='rejected'    ?'selected':'' ?>>❌ Rejected</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>Resolution Notes</label>
                                <textarea name="resolution_notes" rows="3"
                                          placeholder="Describe what was done..."><?= htmlspecialchars($complaint['resolution_notes'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom:16px;">
                                <label>Resolution Photo (optional)</label>
                                <div class="input-file-wrapper" style="padding:12px;">
                                    📷 Upload proof photo
                                    <input type="file" name="resolution_photo" accept="image/*"
                                           onchange="previewPhoto(this,'resPreview')">
                                </div>
                                <img id="resPreview" src="" style="display:none;max-height:120px;border-radius:8px;margin-top:6px;">
                            </div>
                            <button type="submit" class="btn btn-success" style="width:100%;justify-content:center;">
                                💾 Update Status
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Timeline/Info -->
                <div class="card">
                    <div class="card-header"><h3>📅 Timeline</h3></div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-dot">📝</div>
                                <div class="timeline-content">
                                    <h4>Complaint Submitted</h4>
                                    <p class="timeline-time"><?= date('M j, Y g:i A', strtotime($complaint['created_at'])) ?></p>
                                </div>
                            </div>
                            <?php if ($complaint['assigned_to']): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot" style="background:#2980b9;">👷</div>
                                <div class="timeline-content">
                                    <h4>Assigned to <?= htmlspecialchars($complaint['staff_name'] ?? 'Staff') ?></h4>
                                    <p>Status changed to In Progress</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($complaint['resolved_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot" style="background:#27ae60;">✅</div>
                                <div class="timeline-content">
                                    <h4>Resolved</h4>
                                    <p class="timeline-time"><?= date('M j, Y g:i A', strtotime($complaint['resolved_at'])) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($complaint['rating']): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot" style="background:#f39c12;">⭐</div>
                                <div class="timeline-content">
                                    <h4>Rated <?= $complaint['rating'] ?>/5 Stars</h4>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ LIST ════════════════════════════════════════════ -->
        <?php else: ?>
        <div class="page-header">
            <div>
                <h1>📄 All Complaints</h1>
                <p>Total: <?= count($complaints) ?> record(s)</p>
            </div>
            <?php if (isResident() || isAdmin()): ?>
            <a href="complaints.php?action=new" class="btn btn-primary">+ Submit Complaint</a>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <form method="GET" action="complaints.php">
        <div class="filter-bar">
            <span style="font-size:13px;font-weight:600;color:#999;">Filters:</span>
            <input type="text" name="search" placeholder="🔍 Search complaints..."
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="flex:1;min-width:200px;">
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending"     <?= ($_GET['status']??'')==='pending'     ?'selected':'' ?>>⏳ Pending</option>
                <option value="in_progress" <?= ($_GET['status']??'')==='in_progress' ?'selected':'' ?>>🔄 In Progress</option>
                <option value="resolved"    <?= ($_GET['status']??'')==='resolved'    ?'selected':'' ?>>✅ Resolved</option>
                <option value="rejected"    <?= ($_GET['status']??'')==='rejected'    ?'selected':'' ?>>❌ Rejected</option>
            </select>
            <select name="category">
                <option value="">All Categories</option>
                <option value="facilities"  <?= ($_GET['category']??'')==='facilities'  ?'selected':'' ?>>🏢 Facilities</option>
                <option value="noise"       <?= ($_GET['category']??'')==='noise'       ?'selected':'' ?>>🔊 Noise</option>
                <option value="safety"      <?= ($_GET['category']??'')==='safety'      ?'selected':'' ?>>⚠️ Safety</option>
                <option value="cleanliness" <?= ($_GET['category']??'')==='cleanliness' ?'selected':'' ?>>🧹 Cleanliness</option>
                <option value="others"      <?= ($_GET['category']??'')==='others'      ?'selected':'' ?>>📋 Others</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <a href="complaints.php" class="btn btn-secondary btn-sm">Clear</a>
        </div>
        </form>

        <div class="card">
            <div class="table-wrap">
            <?php if (empty($complaints)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h3>No complaints found</h3>
                <p>Try adjusting your filters or submit a new complaint.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Complaint No.</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <?php if (!isResident()): ?><th>Submitted By</th><?php endif; ?>
                        <th>Photo</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($complaints as $i => $c): ?>
                <tr>
                    <td style="color:#999;font-size:12px;"><?= $i+1 ?></td>
                    <td><span class="complaint-no"><?= htmlspecialchars($c['complaint_no']) ?></span></td>
                    <td style="max-width:200px;">
                        <?= htmlspecialchars(substr($c['title'], 0, 45)) . (strlen($c['title']) > 45 ? '…' : '') ?>
                    </td>
                    <td><span class="cat-badge"><?= categoryIcon($c['category']) ?> <?= ucfirst($c['category']) ?></span></td>
                    <td><?= statusBadge($c['status']) ?></td>
                    <?php if (!isResident()): ?>
                    <td><?= htmlspecialchars($c['full_name']) ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($c['photo']): ?>
                        <img src="uploads/<?= htmlspecialchars($c['photo']) ?>"
                             class="photo-thumb" onclick="openLightbox('uploads/<?= htmlspecialchars($c['photo']) ?>')">
                        <?php else: ?><span style="color:#ccc;font-size:12px;">—</span><?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:#999;white-space:nowrap;"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                    <td>
                        <div class="actions">
                            <a href="complaints.php?action=view&id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">👁 View</a>
                            <?php if (isAdmin()): ?>
                            <a href="complaints.php?action=delete&id=<?= $c['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete complaint <?= htmlspecialchars($c['complaint_no']) ?>?')">🗑</a>
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

        </div><!-- /page-content -->
    </div>
</div>

<!-- Lightbox -->
<div id="lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;align-items:center;justify-content:center;cursor:pointer;"
     onclick="this.style.display='none'">
    <img id="lightboxImg" src="" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 8px 48px rgba(0,0,0,0.5);">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').style.display = 'flex';
}
function previewPhoto(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
