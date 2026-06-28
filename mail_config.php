<?php
// mail_config.php - SendGrid version (works on Railway)

function sendOTPEmail($to_email, $username, $otp) {
    // Check if email is valid
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to_email);
        return false;
    }
    
    // Get SendGrid API key from environment variable
    $api_key = getenv('SENDGRID_API_KEY');
    
    // If no SendGrid, try using mail()
    if (empty($api_key)) {
        error_log("SendGrid API key not found, using mail() fallback");
        return sendOTPEmailMail($to_email, $username, $otp);
    }
    
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to_email]],
                'subject' => "🔐 Your OTP Code - Library Noise Monitor"
            ]
        ],
        'from' => ['email' => 'albanodc2006@gmail.com', 'name' => 'Library Noise Monitor'],
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
        return true;
    } else {
        error_log("SendGrid error: HTTP $http_code - $response");
        return false;
    }
}

// Fallback using PHP mail()
function sendOTPEmailMail($to_email, $username, $otp) {
    $subject = "🔐 Your OTP Code - Library Noise Monitor";
    $message = "Hello {$username}!\n\nYour OTP verification code is: {$otp}\n\nThis code will expire in 5 minutes.\n\n---\nLibrary Noise Monitor System";
    $headers = "From: albanodc2006@gmail.com\r\n";
    $headers .= "Reply-To: albanodc2006@gmail.com\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

// Password change OTP
function sendPasswordChangeOTP($to_email, $username, $otp) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to_email);
        return false;
    }
    
    $api_key = getenv('SENDGRID_API_KEY');
    
    if (empty($api_key)) {
        return sendPasswordChangeOTPMail($to_email, $username, $otp);
    }
    
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to_email]],
                'subject' => "🔐 Password Change OTP - Library Noise Monitor"
            ]
        ],
        'from' => ['email' => 'albanodc2006@gmail.com', 'name' => 'Library Noise Monitor'],
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
    $headers = "From: albanodc2006@gmail.com\r\n";
    $headers .= "Reply-To: albanodc2006@gmail.com\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}
?>
