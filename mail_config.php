<?php
// mail_config.php
// Email configuration for sending OTP via Gmail

// Your Gmail credentials
define('SMTP_EMAIL', 'albanodc2006@gmail.com');
define('SMTP_PASSWORD', 'bewgpbdyftxeuiyn');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);

// ============================================================
// FIX: PHPMailer files are in the PHPMailer folder
// ============================================================

function sendOTPEmail($to_email, $username, $otp) {
    try {
        // The PHPMailer folder is in the same directory as this file
        // So we use: __DIR__ . '/PHPMailer/src/'
        $base_dir = __DIR__ . '/PHPMailer/src/';
        
        require_once $base_dir . 'Exception.php';
        require_once $base_dir . 'PHPMailer.php';
        require_once $base_dir . 'SMTP.php';
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_EMAIL;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        
        $mail->setFrom(SMTP_EMAIL, 'Library Noise Monitor');
        $mail->addAddress($to_email, $username);
        
        $mail->isHTML(true);
        $mail->Subject = '🔐 Your OTP Code - Library Noise Monitor';
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { text-align: center; color: #6366f1; font-size: 24px; }
                    .otp { font-size: 48px; font-weight: bold; text-align: center; color: #22c55e; padding: 20px; background: #f0fdf4; border-radius: 10px; letter-spacing: 8px; margin: 20px 0; }
                    .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 20px; }
                    .warning { color: #ef4444; font-size: 14px; }
                    .info { color: #6366f1; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>📚 Library Noise Monitor</div>
                    <h2 style='text-align: center;'>OTP Authentication</h2>
                    <p>Hello <strong>$username</strong>,</p>
                    <p>Enter the following OTP code to complete your login:</p>
                    <div class='otp'>$otp</div>
                    <p>This OTP will expire in <strong>5 minutes</strong>.</p>
                    <p class='info'>You have <strong>3 attempts</strong> to enter the correct OTP.</p>
                    <p class='warning'>⚠️ If you didn't request this, please ignore this email.</p>
                    <div class='footer'>This is an automated message. Do not reply to this email.</div>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Your OTP code is: $otp\n\nThis OTP will expire in 5 minutes.\nYou have 3 attempts to enter the correct OTP.\n\nLibrary Noise Monitor System";
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return sendOTPEmailSimple($to_email, $username, $otp);
    }
}

// Fallback: Simple email using mail() function
function sendOTPEmailSimple($to_email, $username, $otp) {
    $subject = "🔐 Your OTP Code - Library Noise Monitor";
    
    $message = "
Library Noise Monitor - OTP Authentication

Hello $username,

Your OTP code is: $otp

This OTP will expire in 5 minutes.
You have 3 attempts to enter the correct OTP.

If you didn't request this, please ignore this email.

---
Library Noise Monitor System
    ";
    
    $headers = "From: " . SMTP_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SMTP_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to_email, $subject, $message, $headers);
}

// ============================================================
// Password Change OTP Email
// ============================================================

function sendPasswordChangeOTP($to_email, $username, $otp) {
    try {
        $base_dir = __DIR__ . '/PHPMailer/src/';
        
        require_once $base_dir . 'Exception.php';
        require_once $base_dir . 'PHPMailer.php';
        require_once $base_dir . 'SMTP.php';
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_EMAIL;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        
        $mail->setFrom(SMTP_EMAIL, 'Library Noise Monitor');
        $mail->addAddress($to_email, $username);
        
        $mail->isHTML(true);
        $mail->Subject = '🔐 Password Change OTP - Library Noise Monitor';
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { text-align: center; color: #6366f1; font-size: 24px; }
                    .otp { font-size: 48px; font-weight: bold; text-align: center; color: #22c55e; padding: 20px; background: #f0fdf4; border-radius: 10px; letter-spacing: 8px; margin: 20px 0; }
                    .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 20px; }
                    .warning { color: #ef4444; font-size: 14px; }
                    .info { color: #6366f1; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>📚 Library Noise Monitor</div>
                    <h2 style='text-align: center;'>Password Change Request</h2>
                    <p>Hello <strong>$username</strong>,</p>
                    <p>You requested to change your password. Enter the following OTP code to proceed:</p>
                    <div class='otp'>$otp</div>
                    <p>This OTP will expire in <strong>5 minutes</strong>.</p>
                    <p class='info'>You have <strong>3 attempts</strong> to enter the correct OTP.</p>
                    <p class='warning'>⚠️ If you didn't request this, please ignore this email.</p>
                    <div class='footer'>This is an automated message. Do not reply to this email.</div>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Password Change OTP: $otp\n\nThis OTP will expire in 5 minutes.\nYou have 3 attempts to enter the correct OTP.\n\nLibrary Noise Monitor System";
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Password change email error: " . $e->getMessage());
        return sendPasswordChangeOTPSimple($to_email, $username, $otp);
    }
}

// Fallback for password change OTP
function sendPasswordChangeOTPSimple($to_email, $username, $otp) {
    $subject = "🔐 Password Change OTP - Library Noise Monitor";
    
    $message = "
Library Noise Monitor - Password Change OTP

Hello $username,

You requested to change your password.
Your OTP code is: $otp

This OTP will expire in 5 minutes.
You have 3 attempts to enter the correct OTP.

If you didn't request this, please ignore this email.

---
Library Noise Monitor System
    ";
    
    $headers = "From: " . SMTP_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SMTP_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to_email, $subject, $message, $headers);
}
?>
