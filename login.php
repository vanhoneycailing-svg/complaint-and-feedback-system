<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email address or password. Please try again.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In – Complaint & Feedback System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-form-footer {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 12px;
            color: var(--text-dim);
        }

        .login-form-footer a {
            color: var(--accent2);
            font-weight: 600;
        }

        .login-form-footer a:hover { text-decoration: underline; }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            opacity: 0.45;
            pointer-events: none;
        }

        .input-wrapper input {
            padding-left: 42px;
        }

        .btn-signin {
            width: 100%;
            justify-content: center;
            padding: 13px;
            font-size: 14px;
            border-radius: 10px;
            letter-spacing: 0.02em;
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: 8px 0 20px;
        }
    </style>
</head>
<body>
<div class="login-page">

    <!-- Left panel – branding -->
    <div class="login-left">
        <div class="login-brand">
            <div class="login-brand-badge">
                <span>📋</span> CFRS Portal
            </div>
            <h1>Resolve issues<br><em>faster,</em><br>together.</h1>
            <p>A centralized platform for residents and staff to submit, track, and resolve complaints efficiently and transparently.</p>

            <div class="login-features">
                <div class="login-feature">
                    <div class="login-feature-dot"></div>
                    <span>Submit complaints with photo evidence</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-dot"></div>
                    <span>Real-time status tracking and notifications</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-dot"></div>
                    <span>Assigned staff handle cases with accountability</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-dot"></div>
                    <span>Rate resolutions and provide feedback</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right panel – login form -->
    <div class="login-right">
        <div class="login-box">
            <div class="login-box-header">
                <h2>Welcome back</h2>
                <p>Sign in to your account to continue</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✉️</span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="you@example.com"
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
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                    </div>
                </div>

                <div class="divider"></div>

                <button type="submit" class="btn btn-primary btn-signin">
                    Sign In →
                </button>
            </form>

            <div class="login-form-footer">
                Complaint &amp; Feedback Reporting System &nbsp;·&nbsp; <?= date('Y') ?>
            </div>
        </div>
    </div>

</div>
</body>
</html>
