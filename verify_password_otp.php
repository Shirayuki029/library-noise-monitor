<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

// Check if user is logged in
if (!isAuthenticated()) {
    header("Location: login.php");
    exit();
}

// Get user info from session
$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'User';
$email = $_SESSION['email'] ?? '';

// If no email in session, get from database
if (empty($email) && $user_id > 0) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    if ($user) {
        $email = $user['email'];
        $_SESSION['email'] = $email;
    }
}

$error = '';
$success = '';

// Handle Send OTP
if (isset($_POST['send_otp'])) {
    // Generate OTP
    $otp = rand(100000, 999999);
    $_SESSION['password_change_otp'] = $otp;
    $_SESSION['password_change_otp_expiry'] = time() + 300; // 5 minutes
    $_SESSION['password_change_otp_attempts'] = 0;
    
    // Store the email for verification
    $_SESSION['password_change_email'] = $email;
    
    // Show OTP on screen (no email sending)
    $success = "✅ OTP generated! Enter the code below to verify your identity.";
    
    // Log the OTP for debugging
    error_log("Password Change OTP for {$username}: {$otp}");
}

// Handle Verify OTP
if (isset($_POST['verify_otp'])) {
    $user_otp = trim($_POST['otp'] ?? '');
    $stored_otp = $_SESSION['password_change_otp'] ?? '';
    $expiry = $_SESSION['password_change_otp_expiry'] ?? 0;
    $stored_email = $_SESSION['password_change_email'] ?? '';
    
    if (empty($user_otp)) {
        $error = 'Please enter the OTP code';
    } elseif (time() > $expiry) {
        $error = 'OTP has expired. Please request a new one.';
        unset($_SESSION['password_change_otp']);
        unset($_SESSION['password_change_otp_expiry']);
    } elseif ($user_otp === $stored_otp) {
        // OTP verified - redirect to change password page
        $_SESSION['password_change_verified'] = true;
        $_SESSION['password_change_verified_at'] = time();
        
        // Clear OTP session data
        unset($_SESSION['password_change_otp']);
        unset($_SESSION['password_change_otp_expiry']);
        
        header("Location: change_password.php");
        exit();
    } else {
        $_SESSION['password_change_otp_attempts'] = ($_SESSION['password_change_otp_attempts'] ?? 0) + 1;
        $attempts_left = 3 - $_SESSION['password_change_otp_attempts'];
        
        if ($_SESSION['password_change_otp_attempts'] >= 3) {
            $error = "❌ Too many failed attempts. Please request a new OTP.";
            unset($_SESSION['password_change_otp']);
            unset($_SESSION['password_change_otp_expiry']);
        } else {
            $error = "❌ Invalid OTP. You have {$attempts_left} attempt(s) left.";
        }
    }
}

// Handle Resend OTP
if (isset($_GET['resend'])) {
    $otp = rand(100000, 999999);
    $_SESSION['password_change_otp'] = $otp;
    $_SESSION['password_change_otp_expiry'] = time() + 300;
    $_SESSION['password_change_otp_attempts'] = 0;
    
    $success = "✅ New OTP generated! Enter the code below.";
    error_log("New Password Change OTP for {$username}: {$otp}");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Identity - Library Noise Monitor</title>
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
            max-width: 440px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            color: #a5b4fc;
        }
        
        .header-icon {
            font-size: 48px;
            margin-bottom: 10px;
            text-align: center;
        }
        
        h2 {
            color: #e2e8f0;
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: 600;
            text-align: center;
        }
        
        .subtitle {
            color: #94a3b8;
            font-size: 14px;
            text-align: center;
            margin-bottom: 25px;
            line-height: 1.6;
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
            text-align: center;
        }
        
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 12px;
            border-radius: 10px;
            margin: 10px 0;
            font-size: 14px;
            text-align: center;
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
        
        .btn-primary {
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
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .btn-primary:active {
            transform: scale(0.98);
        }
        
        .btn-success {
            width: 100%;
            padding: 14px;
            background: #22c55e;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-success:hover {
            background: #16a34a;
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
            text-align: center;
        }
        
        .timer span {
            color: #a5b4fc;
            font-weight: 600;
        }
        
        .attempts {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 10px;
            text-align: center;
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
        <a href="profile.php" class="back-btn">← Back to Profile</a>
        
        <div class="header-icon">🔐</div>
        <h2>Verify Your Identity</h2>
        <p class="subtitle">
            Enter the OTP code shown below to verify your identity before changing your password.
        </p>
        
        <?php if ($success): ?>
            <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- OTP Display Box -->
        <?php if (isset($_SESSION['password_change_otp'])): ?>
        <div class="debug-box">
            <div class="label">📱 YOUR OTP CODE</div>
            <div class="code"><?php echo htmlspecialchars($_SESSION['password_change_otp']); ?></div>
            <div class="hint">Enter this code to verify your identity (email sending is disabled for testing)</div>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>
                👤 Verifying identity for<br>
                <span class="email-highlight"><?php echo htmlspecialchars($username); ?></span>
            </p>
        </div>
        
        <!-- If OTP exists, show verification form -->
        <?php if (isset($_SESSION['password_change_otp'])): ?>
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
            <button type="submit" name="verify_otp" class="btn-success">Verify OTP</button>
        </form>
        
        <div class="links">
            <a href="?resend=1" class="resend-btn" id="resendBtn">🔄 Resend OTP</a>
        </div>
        
        <div class="timer">
            ⏱️ Code expires in <span id="timer">5:00</span>
        </div>
        
        <?php if (isset($_SESSION['password_change_otp_attempts']) && $_SESSION['password_change_otp_attempts'] > 0): ?>
        <div class="attempts">
            Attempts: <span><?php echo $_SESSION['password_change_otp_attempts']; ?></span> / 3
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- If no OTP, show Send OTP button -->
        <form method="POST">
            <button type="submit" name="send_otp" class="btn-primary">📧 Generate OTP</button>
        </form>
        <?php endif; ?>
    </div>
    
    <script>
        <?php if (isset($_SESSION['password_change_otp'])): ?>
        // Timer countdown
        let timeLeft = 300;
        let timerInterval;
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const timerElement = document.getElementById('timer');
            if (timerElement) {
                timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
            
            if (timeLeft <= 0) {
                if (timerElement) {
                    timerElement.textContent = 'Expired!';
                    timerElement.style.color = '#ef4444';
                }
                clearInterval(timerInterval);
            } else {
                timeLeft--;
            }
        }
        
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);
        
        // Auto-submit when 6 digits are entered
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function() {
                if (this.value.length === 6) {
                    this.form.submit();
                }
            });
        }
        
        // Handle resend button
        const resendBtn = document.getElementById('resendBtn');
        if (resendBtn) {
            resendBtn.addEventListener('click', function(e) {
                this.textContent = '⏳ Generating...';
                this.style.color = '#94a3b8';
                this.style.pointerEvents = 'none';
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
