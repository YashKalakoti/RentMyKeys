<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if PHPMailer is installed
if (!file_exists('vendor/autoload.php')) {
    function sendEmail($to, $subject, $body, $from_email, $from_name) {
        return array(
            'success' => false, 
            'message' => 'PHPMailer not installed. Please run composer install'
        );
    }
} else {
    // Include Composer's autoloader
    require 'vendor/autoload.php';

    /**
     * Send an email using PHPMailer with Gmail
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param string $from_email Sender email
     * @param string $from_name Sender name
     * @return array Status and message
     */
    function sendEmail($to, $subject, $body, $from_email, $from_name) {
        try {
            // Create a new PHPMailer instance
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();                                      // Send using SMTP
            $mail->Host       = 'smtp.gmail.com';                // Gmail SMTP server
            $mail->SMTPAuth   = true;                            // Enable SMTP authentication
            $mail->Username   = 'yashkalakotibackup@gmail.com';  // SMTP username (your Gmail)
            $mail->Password   = 'wlkvthdpejzymjrr';             // SMTP password (use App Password, not your Gmail password)
            $mail->SMTPSecure = 'tls';                           // Enable TLS encryption
            $mail->Port       =  587;                             // TCP port to connect to
            
            // For development testing only - helps with SSL certificate issues
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);                             // Add a recipient
            $mail->addReplyTo($from_email, $from_name);

            // Content
            $mail->isHTML(true);                                // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;                             // HTML message
            $mail->AltBody = strip_tags($body);                 // Plain text alternative

            // Send the email
            $mail->send();
            return array('success' => true, 'message' => 'Message has been sent successfully');
        } catch (Exception $e) {
            // Return detailed error information
            return array(
                'success' => false, 
                'message' => "Message could not be sent. Mailer Error: " . $mail->ErrorInfo,
                'detailed_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            );
        }
    }
}

// Fallback function if PHPMailer fails
function sendEmailFallback($to, $subject, $body, $from_email, $from_name) {
    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    if (mail($to, $subject, $body, $headers)) {
        return array('success' => true, 'message' => 'Message has been sent successfully (fallback)');
    } else {
        return array('success' => false, 'message' => 'Failed to send email (fallback method)');
    }
}