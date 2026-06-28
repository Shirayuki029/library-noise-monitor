<?php
// mail_config.php - SendGrid version for Railway

function sendOTPEmail($to_email, $username, $otp) {
    // Check if email is valid
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to_email);
        return false;
    }
    
    // Get SendGrid API key from environment
    $api_key = getenv('SENDGRID_API_KEY');
    
    // If no API key, log error
    if (empty($api_key)) {
        error_log("SENDGRID_API_KEY not found in environment variables!");
        error_log("Please add SendGrid add-on in Railway dashboard.");
        return false;
    }
    
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to_email]],
                'subject' => "🔐 Your OTP Code - Library Noise Monitor"
            ]
        ],
        'from' => ['email' => 'noreply@librarymonitor.com', 'name' => 'Library Noise Monitor'],
        'reply_to' => ['email' => 'noreply@librarymonitor.com', 'name' => 'Library Noise Monitor'],
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 202) {
        error_log("✅ OTP email sent via SendGrid to: " . $to_email);
        return true;
    } else {
        error_log("❌ SendGrid error: HTTP $http_code - " . substr($response, 0, 500));
        if (!empty($error)) {
            error_log("cURL error: " . $error);
        }
        return false;
    }
}

// Password change OTP
function sendPasswordChangeOTP($to_email, $username, $otp) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to_email);
        return false;
    }
    
    $api_key = getenv('SENDGRID_API_KEY');
    
    if (empty($api_key)) {
        error_log("SENDGRID_API_KEY not found!");
        return false;
    }
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 202) {
        error_log("✅ Password change OTP sent via SendGrid to: " . $to_email);
        return true;
    } else {
        error_log("❌ SendGrid error: HTTP $http_code - " . substr($response, 0, 500));
        return false;
    }
}
?>
