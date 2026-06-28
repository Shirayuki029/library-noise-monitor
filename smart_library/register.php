<?php
require_once 'config.php';

if (isAuthenticated()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $conn = getDB();
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $password_hash);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! Please <a href="login.php">login</a>.';
            } else {
                $error = 'Registration failed: ' . $conn->error;
            }
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Library Noise Monitor</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 20px;
        }

        .register-wrapper {
            width: 100%;
            max-width: 450px;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand .logo {
            font-size: 64px;
            display: block;
            margin-bottom: 10px;
        }

        .brand h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #a5b4fc, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand .subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 5px;
        }

        .register-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 35px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
        }

        .register-card h2 {
            color: #f8fafc;
            font-size: 22px;
            text-align: center;
            margin-bottom: 8px;
        }

        .register-card .register-sub {
            color: #94a3b8;
            text-align: center;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-group .input-wrapper {
            position: relative;
        }

        .form-group .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #475569;
        }

        .form-group input {
            width: 100%;
            padding: 14px 14px 14px 48px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(0, 0, 0, 0.3);
            color: #f8fafc;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            background: rgba(0, 0, 0, 0.5);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .auth-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 5px;
        }

        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.3);
        }

        .auth-link {
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 14px;
        }

        .auth-link a {
            color: #a5b4fc;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-link a:hover {
            text-decoration: underline;
        }

        .error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        .success {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid rgba(34, 197, 94, 0.15);
        }

        .success a { color: #22c55e; }

        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0 15px 0;
            gap: 15px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.06);
        }

        .divider span {
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        @media (max-width: 500px) {
            .register-card { padding: 30px 20px; }
            .brand .logo { font-size: 56px; }
            .brand h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <div class="brand">
            <span class="logo">📚</span>
            <h1>Library Noise Monitor</h1>
            <p class="subtitle">🔊 Smart Noise Detection System</p>
        </div>

        <div class="register-card">
            <h2>📝 Create Account</h2>
            <p class="register-sub">Sign up to start monitoring noise levels</p>

            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>👤 Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" placeholder="Choose a username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>📧 Email</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📧</span>
                        <input type="email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>🔑 Password (min 8 characters)</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" placeholder="Create a password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>✅ Confirm Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✅</span>
                        <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                    </div>
                </div>

                <button type="submit" class="auth-btn">🚀 Create Account</button>

                <div class="divider"><span>Already a member?</span></div>

                <div class="auth-link">
                    Already have an account? <a href="login.php">Sign in here</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>