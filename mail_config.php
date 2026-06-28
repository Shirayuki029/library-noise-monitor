<?php
// mail_config.php - Simple version using PHP's mail function
// NO PHPMailer required!

// Your email address (for the "From" field)
define('SMTP_EMAIL', 'albanodc2006@gmail.com');

// Function to send OTP email
function sendOTPEmail($to_email, $username, $otp) {
    // Check if email is valid
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to_email);
        return false;
    }
    
    $subject = "🔐 Your OTP Code - Library Noise Monitor";
    
    $message = "
============================================
   LIBRARY NOISE MONITOR - OTP VERIFICATION
============================================

Hello {$username}!

Your OTP verification code is: {$otp}

This code will expire in 5 minutes.
You have 3 attempts to enter the correct OTP.

If you didn't request this, please ignore this email.

============================================
© 2026 Library Noise Monitor System
    ";
    
    $headers = "From: " . SMTP_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SMTP_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email
    $result = mail($to_email, $subject, $message, $headers);
    
    if ($result) {
        error_log("OTP email sent to: " . $to_email);
    } else {
        error_log("Failed to send OTP email to: " . $to_email);
    }
    
    return $result;
}

// Password change OTP
function sendPasswordChangeOTP($to_email, $username, $otp) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to_email);
        return false;
    }
    
    $subject = "🔐 Password Change OTP - Library Noise Monitor";
    
    $message = "
============================================
   LIBRARY NOISE MONITOR - PASSWORD CHANGE
============================================

Hello {$username}!

You requested to change your password.
Your OTP verification code is: {$otp}

This code will expire in 5 minutes.
You have 3 attempts to enter the correct OTP.

If you didn't request this, please ignore this email.

============================================
© 2026 Library Noise Monitor System
    ";
    
    $headers = "From: " . SMTP_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SMTP_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}
?>
