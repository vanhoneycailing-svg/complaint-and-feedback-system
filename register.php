<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$full_name || !$email || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'resident')");
            $stmt->bind_param("sss", $full_name, $email, $hash);

            if ($stmt->execute()) {
                $success = 'Account created! You can now sign in.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account – Complaint & Feedback System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .divider { height: 1px; background: var(--border); margin: 8px 0 20px; }

        .btn-register {
            width: 100%;
            justify-content: center;
            padding: 13px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 10px;
        }

        .have-account {
            margin-top: 18px;
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
        }

        .have-account a {
            color: var(--blue);
            font-weight: 600;
        }

        .have-account a:hover { text-decoration: underline; }

        .password-hint {
            font-size: 11px;
            color: var(--text-dim);
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="login-page">

    <!-- ── Left: Branding ── -->
    <div class="login-left">
        <div class="login-brand">
            <div class="login-brand-badge">
                <span>📋</span> CFRS Portal
            </div>
            <h1>Join the<br><em>community</em><br>portal.</h1>
            <p>Create your resident account to submit complaints, track their progress, and get notified when issues are resolved.</p>

            <div class="login-features">
                <div class="login-feature">
                    <div class="login-feature-dot"></div>
                    <span>Free to register — residents only</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-dot"></div>
                    <span>Choose your own username and password</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-dot"></div>
                    <span>Track all your submitted complaints</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-dot"></div>
                    <span>Get notified on every status update</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Right: Registration Form ── -->
    <div class="login-right">
        <div class="login-box">

            <div class="login-box-header">
                <div class="login-logo">🏠</div>
                <h2>Create an account</h2>
                <p>Register as a resident to submit complaints</p>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success) ?>
                <a href="login.php" style="margin-left:8px;font-weight:700;color:var(--resolved);text-decoration:underline;">Sign In →</a>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="" autocomplete="on">

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="e.g. Maria Santos"
                            required
                            autocomplete="name"
                            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✉️</span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            required
                            autocomplete="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            required
                            autocomplete="new-password"
                            minlength="6"
                        >
                    </div>
                    <p class="password-hint">Minimum 6 characters. Choose something only you know.</p>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔑</span>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Repeat your password"
                            required
                            autocomplete="new-password"
                        >
                    </div>
                </div>

                <div class="divider"></div>

                <button type="submit" class="btn btn-primary btn-register">
                    Create My Account &rarr;
                </button>
            </form>
            <?php endif; ?>

            <div class="have-account">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>

        </div>
    </div>

</div>

<script>
// Client-side password match check
document.querySelector('form')?.addEventListener('submit', function(e) {
    const p1 = document.getElementById('password').value;
    const p2 = document.getElementById('confirm_password').value;
    if (p1 !== p2) {
        e.preventDefault();
        document.getElementById('confirm_password').style.borderColor = '#b91c1c';
        document.getElementById('confirm_password').style.boxShadow = '0 0 0 3px rgba(185,28,28,0.15)';
        document.getElementById('confirm_password').focus();
        document.getElementById('confirm_password').placeholder = 'Passwords do not match!';
    }
});
</script>

</body>
</html>
