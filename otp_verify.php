<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'mail_config.php';  // Enable email sending

if (isAuthenticated()) {
    $_SESSION['login_success'] = true;
    $_SESSION['login_username'] = $_SESSION['username'];
    header("Location: dashboard.php");
    exit();
}

if (!isset($_SESSION['otp']) || !isset($_SESSION['temp_user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Handle resend OTP request
if (isset($_GET['resend'])) {
    // Generate new OTP
    $new_otp = rand(100000, 999999);
    $_SESSION['otp'] = $new_otp;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
    
    // Resend email
    $user_email = $_SESSION['temp_email'] ?? '';
    $username = $_SESSION['temp_username'] ?? '';
    
    if (sendOTPEmail($user_email, $new_otp, $username)) {
        $success = "✅ New OTP sent to your email!";
    } else {
        $error = "❌ Failed to send OTP. Please try again.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_otp = trim($_POST['otp'] ?? '');
    $stored_otp = $_SESSION['otp'] ?? '';
    $expiry = $_SESSION['otp_expiry'] ?? 0;
    
    if (empty($user_otp)) {
        $error = 'Please enter the OTP code';
    } elseif (time() > $expiry) {
        $error = 'OTP has expired. Please request a new one.';
        // Clear expired OTP
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
    } elseif ($user_otp === $stored_otp) {
        // Login successful
        $_SESSION['user_id'] = $_SESSION['temp_user_id'];
        $_SESSION['username'] = $_SESSION['temp_username'];
        $_SESSION['role'] = $_SESSION['temp_role'];
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_username']);
        unset($_SESSION['temp_role']);
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        unset($_SESSION['otp_sent']);
        
        $_SESSION['login_success'] = true;
        $_SESSION['login_username'] = $_SESSION['username'];
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "❌ Invalid OTP. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #0f172a;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            background: #1e293b;
            padding: 40px;
            border-radius: 20px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .header-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        h2 {
            color: #e2e8f0;
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 25px;
        }
        
        .info-box {
            background: #0f172a;
            padding: 16px;
            border-radius: 12px;
            margin: 20px 0;
            border: 1px solid #334155;
        }
        
        .info-box p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }
        
        .info-box .email-highlight {
            color: #a5b4fc;
            font-weight: 500;
        }
        
        .success-box {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            color: #22c55e;
            padding: 12px;
            border-radius: 10px;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 12px;
            border-radius: 10px;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .form-group {
            margin: 20px 0;
        }
        
        label {
            display: block;
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 8px;
            text-align: left;
            font-weight: 500;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: 2px solid #334155;
            background: #0f172a;
            color: white;
            font-size: 28px;
            text-align: center;
            letter-spacing: 12px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
            font-family: 'Courier New', monospace;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        input[type="text"]::placeholder {
            letter-spacing: 2px;
            font-size: 16px;
            color: #475569;
        }
        
        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 10px;
        }
        
        button[type="submit"]:hover {
            background: #4f46e5;
        }
        
        button[type="submit"]:active {
            transform: scale(0.98);
        }
        
        .links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #334155;
        }
        
        .links a {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .links a:hover {
            color: #a5b4fc;
        }
        
        .resend-btn {
            background: none;
            border: none;
            color: #6366f1;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: underline;
            transition: color 0.3s ease;
        }
        
        .resend-btn:hover {
            color: #4f46e5;
        }
        
        .timer {
            color: #64748b;
            font-size: 13px;
            margin-top: 15px;
        }
        
        .timer span {
            color: #a5b4fc;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 24px;
            }
            
            h2 {
                font-size: 20px;
            }
            
            input[type="text"] {
                font-size: 24px;
                letter-spacing: 8px;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-icon">📧</div>
        <h2>OTP Verification</h2>
        <p class="subtitle">Enter the 6-digit code sent to your email</p>
        
        <?php if ($success): ?>
            <div class="success-box"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-box"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>
                📨 We've sent a 6-digit verification code to<br>
                <span class="email-highlight"><?php echo htmlspecialchars($_SESSION['temp_email'] ?? 'your email'); ?></span>
            </p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="otp">Enter OTP Code</label>
                <input 
                    type="text" 
                    id="otp" 
                    name="otp" 
                    placeholder="000000" 
                    maxlength="6" 
                    pattern="[0-9]{6}"
                    required 
                    autofocus
                    autocomplete="one-time-code"
                >
            </div>
            <button type="submit">Verify OTP</button>
        </form>
        
        <div class="links">
            <a href="login.php">← Back to Login</a>
            <a href="?resend=1" class="resend-btn">Resend OTP</a>
        </div>
        
        <div class="timer">
            ⏱️ Code expires in <span id="timer">5:00</span>
        </div>
    </div>
    
    <script>
        // Timer countdown
        let timeLeft = 300; // 5 minutes in seconds
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('timer').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                document.getElementById('timer').textContent = 'Expired!';
                document.getElementById('timer').style.color = '#ef4444';
            } else {
                timeLeft--;
            }
        }
        
        // Update every second
        updateTimer();
        setInterval(updateTimer, 1000);
        
        // Auto-submit when 6 digits are entered
        document.getElementById('otp').addEventListener('input', function() {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
