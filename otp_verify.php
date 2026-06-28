<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php'; // FIXED: Removed extra ')'
// NO mail_config.php - we're showing OTP on screen

if (isAuthenticated()) {
    $_SESSION['login_success'] = true;
    $_SESSION['login_username'] = $_SESSION['username'];
    header("Location: dashboard.php");
    exit();
}

// Allow resend to work without redirecting
$is_resend = isset($_GET['resend']);

// Only redirect to login if this is NOT a resend request AND session data is missing
if (!$is_resend && (!isset($_SESSION['otp']) || !isset($_SESSION['temp_user_id']))) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Handle resend OTP request
if ($is_resend) {
    $new_otp = rand(100000, 999999);
    $_SESSION['otp'] = $new_otp;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
    $_SESSION['otp_attempts'] = 0;
    
    $success = "✅ New OTP generated! Check the box below.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_resend) {
    $user_otp = trim($_POST['otp'] ?? '');
    $stored_otp = $_SESSION['otp'] ?? '';
    $expiry = $_SESSION['otp_expiry'] ?? 0;
    
    if (empty($user_otp)) {
        $error = 'Please enter the OTP code';
    } elseif (time() > $expiry) {
        $error = 'OTP has expired. Please request a new one.';
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
        unset($_SESSION['temp_email']);
        unset($_SESSION['temp_role']);
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        unset($_SESSION['otp_attempts']);
        
        $_SESSION['login_success'] = true;
        $_SESSION['login_username'] = $_SESSION['username'];
        
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
        $attempts_left = 3 - $_SESSION['otp_attempts'];
        
        if ($_SESSION['otp_attempts'] >= 3) {
            $error = "❌ Too many failed attempts. Please request a new OTP.";
            unset($_SESSION['otp']);
            unset($_SESSION['otp_expiry']);
        } else {
            $error = "❌ Invalid OTP. You have {$attempts_left} attempt(s) left.";
        }
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
        
        /* DEBUG BOX - Shows OTP on screen */
        .debug-box {
            background: #0f172a;
            padding: 20px;
            border-radius: 12px;
            margin: 15px 0;
            border: 2px solid #22c55e;
        }
        
        .debug-box .label {
            color: #94a3b8;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }
        
        .debug-box .code {
            color: #22c55e;
            font-size: 52px;
            font-weight: bold;
            letter-spacing: 15px;
            font-family: 'Courier New', monospace;
            padding: 10px 0;
        }
        
        .debug-box .hint {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
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
        
        .attempts {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .attempts span {
            color: #f59e0b;
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
            
            .debug-box .code {
                font-size: 36px;
                letter-spacing: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-icon">🔐</div>
        <h2>OTP Verification</h2>
        <p class="subtitle">Enter the 6-digit code shown below</p>
        
        <?php if ($success): ?>
            <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- DEBUG BOX - Shows the OTP -->
        <div class="debug-box">
            <div class="label">📱 YOUR OTP CODE</div>
            <div class="code"><?php echo htmlspecialchars($_SESSION['otp'] ?? '------'); ?></div>
            <div class="hint">Enter this code to login (email sending is disabled)</div>
        </div>
        
        <div class="info-box">
            <p>
                👤 Logging in as<br>
                <span class="email-highlight"><?php echo htmlspecialchars($_SESSION['temp_username'] ?? 'User'); ?></span>
            </p>
        </div>
        
        <form method="POST" id="otpForm">
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
            <a href="?resend=1" class="resend-btn" id="resendBtn">🔄 Resend OTP</a>
        </div>
        
        <div class="timer">
            ⏱️ Code expires in <span id="timer">5:00</span>
        </div>
        
        <?php if (isset($_SESSION['otp_attempts']) && $_SESSION['otp_attempts'] > 0): ?>
        <div class="attempts">
            Attempts: <span><?php echo $_SESSION['otp_attempts']; ?></span> / 3
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Timer countdown
        let timeLeft = 300; // 5 minutes in seconds
        let timerInterval;
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const timerElement = document.getElementById('timer');
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                timerElement.textContent = 'Expired!';
                timerElement.style.color = '#ef4444';
                clearInterval(timerInterval);
            } else {
                timeLeft--;
            }
        }
        
        // Start timer
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);
        
        // Auto-submit when 6 digits are entered
        document.getElementById('otp').addEventListener('input', function() {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Handle resend button - show loading state
        document.getElementById('resendBtn').addEventListener('click', function(e) {
            this.textContent = '⏳ Generating...';
            this.style.color = '#94a3b8';
            this.style.pointerEvents = 'none';
        });
    </script>
</body>
</html>
