<?php
// mail_config.php - Simple version using cURL with SendGrid
// NO PHPMailer required!

function sendOTPEmail($to_email, $username, $otp) {
    // Check if email is valid
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to_email);
        return false;
    }
    
    // Try SendGrid first (works best on Railway)
    $api_key = getenv('SENDGRID_API_KEY');
    
    if (!empty($api_key)) {
        return sendOTPEmailSendGrid($to_email, $username, $otp, $api_key);
    }
    
    // Fallback to PHP mail() if SendGrid not available
    return sendOTPEmailMail($to_email, $username, $otp);
}

// SendGrid version (recommended for Railway)
function sendOTPEmailSendGrid($to_email, $username, $otp, $api_key) {
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to_email]],
                'subject' => "🔐 Your OTP Code - Library Noise Monitor"
            ]
        ],
        'from' => ['email' => 'noreply@librarymonitor.com', 'name' => 'Library Noise Monitor'],
        'content' => [
            [
                'type' => 'text/plain',
                'value' => "Hello {$username}!\n\nYour OTP verification code is: {$otp}\n\nThis code will expire in 5 minutes.\n\nYou have 3 attempts to enter the correct OTP.\n\nIf you didn't request this, please ignore this email.\n\n---\nLibrary Noise Monitor System"
            ]
        ]
    ];
    
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 202) {
        error_log("OTP email sent via SendGrid to: " . $to_email);
        return true;
    } else {
        error_log("SendGrid error: HTTP $http_code - " . substr($response, 0, 200));
        return false;
    }
}

// Fallback using PHP mail()
function sendOTPEmailMail($to_email, $username, $otp) {
    $subject = "🔐 Your OTP Code - Library Noise Monitor";
    $message = "Hello {$username}!\n\nYour OTP verification code is: {$otp}\n\nThis code will expire in 5 minutes.\n\n---\nLibrary Noise Monitor System";
    $headers = "From: noreply@librarymonitor.com\r\n";
    $headers .= "Reply-To: noreply@librarymonitor.com\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

// Password change OTP
function sendPasswordChangeOTP($to_email, $username, $otp) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to_email);
        return false;
    }
    
    $api_key = getenv('SENDGRID_API_KEY');
    
    if (!empty($api_key)) {
        return sendPasswordChangeOTPSendGrid($to_email, $username, $otp, $api_key);
    }
    
    return sendPasswordChangeOTPMail($to_email, $username, $otp);
}

function sendPasswordChangeOTPSendGrid($to_email, $username, $otp, $api_key) {
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to_email]],
                'subject' => "🔐 Password Change OTP - Library Noise Monitor"
            ]
        ],
        'from' => ['email' => 'noreply@librarymonitor.com', 'name' => 'Library Noise Monitor'],
        'content' => [
            [
                'type' => 'text/plain',
                'value' => "Hello {$username}!\n\nYou requested to change your password.\n\nYour OTP verification code is: {$otp}\n\nThis code will expire in 5 minutes.\n\nIf you didn't request this, please ignore this email.\n\n---\nLibrary Noise Monitor System"
            ]
        ]
    ];
    
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 202;
}

function sendPasswordChangeOTPMail($to_email, $username, $otp) {
    $subject = "🔐 Password Change OTP - Library Noise Monitor";
    $message = "Hello {$username}!\n\nYou requested to change your password.\n\nYour OTP verification code is: {$otp}\n\nThis code will expire in 5 minutes.\n\n---\nLibrary Noise Monitor System";
    $headers = "From: noreply@librarymonitor.com\r\n";
    $headers .= "Reply-To: noreply@librarymonitor.com\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}
?>
