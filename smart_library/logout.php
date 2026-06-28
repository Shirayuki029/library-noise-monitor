<?php
require_once 'config.php';

// Check if this is a confirmation request
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // User confirmed logout
    $username = $_SESSION['username'] ?? 'User';
    
    // Log the logout
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    
    // Destroy session
    session_destroy();
    
    // Store logout message for login page
    session_start();
    $_SESSION['logout_success'] = true;
    $_SESSION['logout_username'] = $username;
    
    header("Location: login.php");
    exit();
} else {
    // Show confirmation dialog
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Logout Confirmation</title>
        <link rel="stylesheet" href="style.css">
        <style>
            /* Full page blur overlay */
            .logout-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                z-index: 9999;
                display: flex;
                justify-content: center;
                align-items: center;
                animation: fadeIn 0.3s ease;
            }

            .logout-box {
                background: rgba(15, 23, 42, 0.95);
                border-radius: 24px;
                padding: 50px 60px;
                max-width: 450px;
                width: 90%;
                text-align: center;
                box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);
                border: 1px solid rgba(255, 255, 255, 0.1);
                animation: slideDown 0.4s ease;
            }

            .logout-box .icon {
                font-size: 70px;
                margin-bottom: 15px;
            }

            .logout-box .title {
                font-size: 28px;
                font-weight: bold;
                color: #f8fafc;
                margin-bottom: 10px;
            }

            .logout-box .message {
                font-size: 16px;
                color: #94a3b8;
                margin-bottom: 30px;
                line-height: 1.6;
            }

            .logout-box .btn-group {
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }

            .logout-box .btn {
                padding: 14px 45px;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                min-width: 120px;
            }

            .logout-box .btn-yes {
                background: #ef4444;
                color: white;
            }

            .logout-box .btn-yes:hover {
                background: #dc2626;
                transform: scale(1.05);
            }

            .logout-box .btn-no {
                background: rgba(255, 255, 255, 0.1);
                color: #94a3b8;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .logout-box .btn-no:hover {
                background: rgba(255, 255, 255, 0.2);
                transform: scale(1.05);
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes slideDown {
                from {
                    transform: translateY(-50px) scale(0.95);
                    opacity: 0;
                }
                to {
                    transform: translateY(0) scale(1);
                    opacity: 1;
                }
            }

            /* Responsive */
            @media (max-width: 500px) {
                .logout-box {
                    padding: 30px 25px;
                }
                .logout-box .btn {
                    padding: 12px 30px;
                    min-width: 100px;
                }
                .logout-box .btn-group {
                    flex-direction: column;
                    gap: 10px;
                }
            }
        </style>
    </head>
    <body>
        <div class="logout-overlay">
            <div class="logout-box">
                <div class="icon">🚪</div>
                <div class="title">Confirm Logout</div>
                <div class="message">
                    Are you sure you want to logout?<br>
                    <span style="color: #64748b; font-size: 14px;">You will need to login again to access your dashboard.</span>
                </div>
                <div class="btn-group">
                    <a href="?confirm=yes" class="btn btn-yes">✅ Yes, Logout</a>
                    <a href="dashboard.php" class="btn btn-no">❌ Cancel</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>