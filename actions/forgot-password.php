<?php
/**
 * Forgot Password Action Handler
 * 
 * Processes password reset requests
 * Uses PHPMailer for sending emails
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once '../includes/config.php';
require_once '../includes/functions.php';

// PHPMailer dependencies
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    // Basic validation
    if (!$email) {
        setFlashMessage('error', 'Please enter a valid email address.');
        redirect('../?page=forgot-password');
        exit;
    }
    
    try {
        // Connect to database
        $db = getDbConnection();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // Token expires in 1 hour
            
            // Store token in database
            $stmt = $db->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], password_hash($token, PASSWORD_DEFAULT), $expires]);
            
            // Create reset link
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/?page=reset-password&token=" . $token . "&email=" . urlencode($email);
            
            // Create email message
            $subject = "SoSol - Password Reset Request";
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #0066cc;'>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($user['full_name']) . ",</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    <p style='text-align: center;'>
                        <a href='" . $resetLink . "' 
                           style='display: inline-block; padding: 10px 20px; background-color: #0066cc; color: white; 
                                  text-decoration: none; border-radius: 4px; font-weight: bold;'>
                            Reset Password
                        </a>
                    </p>
                    <p>If you didn't request a password reset, you can ignore this email or let us know.</p>
                    <p>This link will expire in 1 hour for security reasons.</p>
                    <p>Thank you,<br>The SoSol Team</p>
                </div>
            </body>
            </html>
            ";
            
            // For development only: Display reset link on page
            $_SESSION['reset_link'] = $resetLink;
            
            // Try to send email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = getenv('SMTP_HOST') ?: 'smtp.example.com';
                $mail->SMTPAuth = true;
                $mail->Username = getenv('SMTP_USER') ?: 'user@example.com';
                $mail->Password = getenv('SMTP_PASS') ?: 'password';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = getenv('SMTP_PORT') ?: 587;
                
                // Recipients
                $mail->setFrom('noreply@sosol.com', 'SoSol');
                $mail->addAddress($email, $user['full_name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $message;
                $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
                
                // Attempt to send email
                // Uncomment in production, comment out for development
                // $mail->send();
                
                setFlashMessage('success', 'Password reset instructions have been sent to your email address.');
                
                // For development: Add message about checking the page for the link
                setFlashMessage('info', 'DEVELOPMENT MODE: Check the next page for your reset link.');
                
            } catch (Exception $e) {
                error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                
                // In development, still allow reset since email might not be configured
                setFlashMessage('warning', 'Email server is not configured, but your password reset was initiated.');
            }
        } else {
            // Don't reveal if email exists or not (security best practice)
            // But for user experience, we'll still show a success message
        }
        
        // Always redirect to confirmation page (whether email exists or not)
        redirect('../?page=reset-confirmation');
        
    } catch (PDOException $e) {
        // Log error and show friendly message
        error_log('Forgot password error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred. Please try again later.');
        redirect('../?page=forgot-password');
    }
} else {
    // Not a POST request
    redirect('../?page=forgot-password');
}