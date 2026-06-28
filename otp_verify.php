<?php
// otp_verify.php - Corrected version

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'mail_config.php';

// Check if user is already authenticated
if (isAuthenticated()) {
    $_SESSION['login_success'] = true;
    $_SESSION['login_username'] = $_SESSION['username'];
    header("Location: dashboard.php");
    exit();
}

// --- CRITICAL FIX: Check for resend action FIRST ---
// Allow the page to load for resend requests, even if session data is partially missing.
// The resend logic will re-populate the OTP.
$is_resend = isset($_GET['resend']);

// Only redirect to login if this is NOT a resend request AND session data is missing.
if (!$is_resend && (!isset($_SESSION['otp']) || !isset($_SESSION['temp_user_id']))) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Handle resend OTP request - This runs BEFORE the session data check above
if ($is_resend) {
    // Regenerate OTP
    $new_otp = rand(100000, 999999);
    $_SESSION['otp'] = $new_otp;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
    
    // Reset attempts
    $_SESSION['otp_attempts'] = 0;
    
    // Resend email
    $user_email = $_SESSION['temp_email'] ?? '';
    $username = $_SESSION['temp_username'] ?? '';
    
    if (!empty($user_email)) {
        if (sendOTPEmail($user_email, $username, $new_otp)) {
            $success = "✅ New OTP sent to your email!";
            error_log("Resend OTP sent to: " . $user_email);
        } else {
            $error = "❌ Failed to send OTP. Please try again.";
            error_log("Resend OTP failed for: " . $user_email);
        }
    } else {
        $error = "❌ Email address not found. Please login again.";
        error_log("Resend OTP failed: No email in session");
    }
    
    // CRITICAL: Do NOT redirect. Just show the message on the same page.
    // The page will continue to load and display the form below.
}

// --- Normal OTP Verification Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_resend) {
    $user_otp = trim($_POST['otp'] ?? '');
    $stored_otp = $_SESSION['otp'] ?? '';
    $expiry = $_SESSION['otp_expiry'] ?? 0;
    
    if (empty($user_otp)) {
        $error = 'Please enter the OTP code';
    } elseif (time() > $expiry) {
        $error = 'OTP has expired. Please request a new one.';
        // Clear expired OTP but keep user data to allow resend
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
    } elseif ($user_otp === $stored_otp) {
        // --- Login Successful ---
        $_SESSION['user_id'] = $_SESSION['temp_user_id'];
        $_SESSION['username'] = $_SESSION['temp_username'];
        $_SESSION['role'] = $_SESSION['temp_role'];
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        
        // Clear temp session data
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
        // Increment failed attempts
        $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
        $attempts_left = 3 - $_SESSION['otp_attempts'];
        
        if ($_SESSION['otp_attempts'] >= 3) {
            $error = "❌ Too many failed attempts. Please request a new OTP.";
            // Clear OTP but keep temp user data
            unset($_SESSION['otp']);
            unset($_SESSION['otp_expiry']);
        } else {
            $error = "❌ Invalid OTP. You have {$attempts_left} attempt(s) left.";
        }
    }
}

// --- The rest of your HTML page remains the same ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <!-- Your existing CSS styles -->
</head>
<body>
    <div class="container">
        <div class="header-icon">📧</div>
        <h2>OTP Verification</h2>
        <p class="subtitle">Enter the 6-digit code sent to your email</p>
        
        <?php if ($success): ?>
            <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>
                📨 We've sent a 6-digit verification code to<br>
                <span class="email-highlight"><?php echo htmlspecialchars($_SESSION['temp_email'] ?? 'your email'); ?></span>
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
            <!-- The resend link stays the same -->
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
            this.textContent = '⏳ Sending...';
            this.style.color = '#94a3b8';
            this.style.pointerEvents = 'none';
            
            // Allow the link to work
            setTimeout(function() {
                // The page will reload with ?resend=1
            }, 100);
        });
    </script>
</body>
</html>
