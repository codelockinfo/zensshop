<?php
/**
 * Email Service Class
 * Handles email sending using PHPMailer
 */

require_once __DIR__ . '/../config/email.php';

// Load Composer Autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Note: PHPMailer should be installed via Composer or manually included
// For now, we'll use a simple mail() function wrapper
// In production, install PHPMailer: composer require phpmailer/phpmailer

class Email {
    private $fromEmail;
    private $fromName;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpEncryption;
    private $storeId;
    
    public function __construct($storeId = null) {
        $this->storeId = $storeId;
        require_once __DIR__ . '/Settings.php';
        $settings = new Settings();
        
        // Use provided storeId or fallback to current context
        $this->fromEmail = $settings->get('smtp_from_email', defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '', $storeId);
        $this->fromName = $settings->get('smtp_from_name', defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME, $storeId);
        $this->smtpHost = $settings->get('smtp_host', defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com', $storeId);
        $this->smtpPort = $settings->get('smtp_port', defined('SMTP_PORT') ? SMTP_PORT : 587, $storeId);
        $this->smtpUsername = $settings->get('smtp_username', defined('SMTP_USERNAME') ? SMTP_USERNAME : '', $storeId);
        $this->smtpPassword = $settings->get('smtp_password', defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '', $storeId);
        $this->smtpEncryption = $settings->get('smtp_encryption', defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls', $storeId);
    }
    
    /**
     * Send email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param string $altMessage Plain text alternative
     * @return bool Success status
     */
    public function send($to, $subject, $message, $altMessage = '') {
        try {
            // Check if email is configured
            if (empty($this->fromEmail) || $this->fromEmail === 'your-email@gmail.com') {
                error_log("Email not configured. Skipping email to: $to with subject: $subject");
                return false; // Email not configured, skip silently
            }
            
            // If PHPMailer is available, use it
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                return $this->sendWithPHPMailer($to, $subject, $message, $altMessage);
            } else {
                // Don't use mail() function as it requires local mail server
                error_log("PHPMailer not available and SMTP not configured. Email not sent to: $to");
                return false;
            }
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendWithPHPMailer($to, $subject, $message, $altMessage = '') {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            $mail->Password = $this->smtpPassword;
            
            // Determine encryption
            if ($this->smtpPort == 465 || strtolower($this->smtpEncryption) === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = $this->smtpPort;
            
            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            if ($altMessage) {
                $mail->AltBody = $altMessage;
            }
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send email using PHP mail() function (fallback)
     */
    private function sendWithMail($to, $subject, $message) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $this->fromName . " <" . $this->fromEmail . ">" . "\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send OTP email
     */
    public function sendOTP($to, $otp) {
        $subject = "Password Reset OTP - " . SITE_NAME;
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .otp-box { background: #f3f4f6; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; }
                    .otp-code { font-size: 32px; font-weight: bold; color: #1a5d3a; letter-spacing: 5px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Password Reset Request</h2>
                    <p>You have requested to reset your password. Use the following OTP code:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>{$otp}</div>
                    </div>
                    <p>This code will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                </div>
            </body>
            </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation($to, $orderNumber, $customerName, $totalAmount, $items) {
        $subject = "Order Confirmation - Order #$orderNumber";
        
        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= "<tr>
                <td style='padding: 12px; border-bottom: 1px solid #e5e7eb;'>{$item['product_name']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: center;'>{$item['quantity']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: right;'>₹" . number_format($item['price'], 2) . "</td>
            </tr>";
        }
        
        $content = "
            <h1 style='text-align: center; margin-bottom: 24px;'>Thank You for Your Order!</h1>
            <p>Dear $customerName,</p>
            <p>Thank you for shopping with us! Your order has been successfully placed.</p>
            
            <div style='background: #f9fafb; padding: 24px; border-radius: 8px; margin: 25px 0;'>
                <h3 style='margin-top: 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 15px; font-size: 16px; color: #4b5563;'>Order Details</h3>
                <p style='margin: 5px 0;'><strong style='min-width: 120px; display: inline-block; color: #6b7280;'>Order Number:</strong> #$orderNumber</p>
                <p style='margin: 5px 0;'><strong style='min-width: 120px; display: inline-block; color: #6b7280;'>Order Date:</strong> " . date('F d, Y') . "</p>
                <div style='margin-top: 20px; text-align: center;'>
                    <a href='" . (function_exists('url') ? url("invoice?order_number=$orderNumber") : (getBaseUrl() . "/invoice.php?order_number=$orderNumber")) . "' style='padding: 12px 24px; background-color: #000000; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Download Invoice</a>
                </div>
            </div>
            
            <h3 style='margin-bottom: 15px;'>Order Items</h3>
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 25px;'>
                <thead>
                    <tr style='background: #f3f4f6; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;'>
                        <th style='padding: 12px; text-align: left; border-radius: 6px 0 0 6px;'>Product</th>
                        <th style='padding: 12px; text-align: center;'>Quantity</th>
                        <th style='padding: 12px; text-align: right; border-radius: 0 6px 6px 0;'>Price</th>
                    </tr>
                </thead>
                <tbody>
                    $itemsHtml
                </tbody>
            </table>
            
            <div style='text-align: right; margin-top: 20px; border-top: 2px solid #e5e7eb; padding-top: 20px;'>
                 <p style='font-size: 14px; margin: 0; color: #6b7280;'>Total Amount</p>
                 <p style='font-size: 24px; font-weight: bold; color: #111827; margin: 5px 0;'>₹" . number_format($totalAmount, 2) . "</p>
            </div>
            
            <div style='margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px; text-align: center;'>
                <p style='margin-bottom: 5px;'>We'll send you another email when your order ships.</p>
                <p style='color: #6b7280; font-size: 14px;'>If you have any questions, simply reply to this email.</p>
            </div>
        ";
        
        $message = $this->getEmailTemplate("Order Confirmation #$orderNumber", $content);
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send newsletter subscription confirmation
     */
    public function sendSubscriptionConfirmation($to) {
        $subject = "Welcome to " . SITE_NAME . "!";
        
        $content = "
            <h1 style='text-align: center;'>Thanks for subscribing to us!</h1>
            <p>Welcome to our " . SITE_NAME . " You'll be the first to know about:</p>
            <ul>
                <li>New product launches</li>
                <li>Exclusive deals and promotions</li>
                <li>Special offers just for subscribers</li>
                <li>Industry news and updates</li>
            </ul>
            <p>We're excited to have you with us!</p>
            <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
            <p style='color: #6b7280; font-size: 13px; text-align: center;'>If you didn't subscribe to " . SITE_NAME . ", you can safely ignore this email.</p>
        ";
        
        $message = $this->getEmailTemplate($subject, $content);
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send support message notification to admin
     */
    public function sendSupportNotificationToAdmin($adminEmail, $customerName, $customerEmail, $subject, $message) {
        $emailSubject = "New Support Message from $customerName";
        $emailMessage = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
                    .message-box { background: #f9fafb; padding: 20px; border-left: 4px solid #4F46E5; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin: 0;'>New Support Message</h1>
                    </div>
                    <div class='content'>
                        <p><strong>From:</strong> $customerName ($customerEmail)</p>
                        <p><strong>Subject:</strong> $subject</p>
                        
                        <div class='message-box'>
                            <p><strong>Message:</strong></p>
                            <p>" . nl2br(htmlspecialchars($message)) . "</p>
                        </div>
                        
                        <p><a href='" . getBaseUrl() . "/admin/support.php' style='background: #4F46E5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View in Admin Panel</a></p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        return $this->send($adminEmail, $emailSubject, $emailMessage);
    }
    
    /**
     * Send support reply to customer
     */
    public function sendSupportReply($to, $customerName, $originalSubject, $reply) {
        $subject = "Re: $originalSubject";
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
                    .reply-box { background: #f0f9ff; padding: 20px; border-left: 4px solid #4F46E5; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin: 0;'>Support Team Reply</h1>
                    </div>
                    <div class='content'>
                        <p>Dear $customerName,</p>
                        <p>Thank you for contacting us. Here's our response to your inquiry:</p>
                        
                        <div class='reply-box'>
                            <p>" . nl2br(htmlspecialchars($reply)) . "</p>
                        </div>
                        
                        <p>If you have any further questions, please don't hesitate to reach out.</p>
                        <p>Best regards,<br>Support Team</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        return $this->send($to, $subject, $message);
    }

    /**
     * Send welcome email to new customer
     */
    public function sendWelcomeEmail($to, $name) {
        $subject = "Welcome to " . SITE_NAME . "!";
        $siteUrl = getBaseUrl();
        
        $content = "
            <h1 style='text-align: center;'>Welcome to " . SITE_NAME . "!</h1>
            <p>Dear $name,</p>
            <p>We're thrilled to have you on board! Thank you for creating an account with us.</p>
            <p>As a registered member, you can now:</p>
            <ul>
                <li>Track your orders easily</li>
                <li>Save your favorite items to wishlist</li>
                <li>Checkout faster</li>
            </ul>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$siteUrl' class='button'>Start Shopping</a>
            </div>
        ";
        
        $message = $this->getEmailTemplate($subject, $content);
        return $this->send($to, $subject, $message);
    }

    /**
     * Send cancellation/refund request notification
     */
    public function sendOrderRequestNotification($to, $type, $orderNumber, $customerName, $reason, $comments, $isAdmin = false) {
        $typeLabel = ($type === 'refund') ? 'Refund' : 'Cancellation';
        $subject = ($isAdmin ? "New $typeLabel Request" : "$typeLabel Request Submitted") . " - Order #$orderNumber";
        
        $content = "
            <h1 style='text-align: center;'>$typeLabel Request</h1>
            <p>Dear " . ($isAdmin ? 'Admin' : $customerName) . ",</p>
            <p>" . ($isAdmin ? "A new $typeLabel request has been submitted by $customerName for order #$orderNumber." : "Your $typeLabel request for order #$orderNumber has been successfully submitted.") . "</p>
            
            <div style='background: #f9fafb; padding: 24px; border-radius: 8px; margin: 25px 0;'>
                <h3 style='margin-top: 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 15px; font-size: 16px; color: #4b5563;'>Request Details</h3>
                <p style='margin: 5px 0;'><strong style='min-width: 120px; display: inline-block; color: #6b7280;'>Order Number:</strong> #$orderNumber</p>
                <p style='margin: 5px 0;'><strong style='min-width: 120px; display: inline-block; color: #6b7280;'>Type:</strong> $typeLabel</p>
                <p style='margin: 5px 0;'><strong style='min-width: 120px; display: inline-block; color: #6b7280;'>Reason:</strong> " . htmlspecialchars($reason) . "</p>
                " . (!empty($comments) ? "<p style='margin: 5px 0;'><strong style='min-width: 120px; display: inline-block; color: #6b7280;'>Comments:</strong> " . nl2br(htmlspecialchars($comments)) . "</p>" : "") . "
                <p style='margin: 5px 0;'><strong style='min-width: 120px; display: inline-block; color: #6b7280;'>Date:</strong> " . date('F d, Y') . "</p>
            </div>
            
            " . ($isAdmin ? "<div style='text-align: center; margin: 30px 0;'>
                <a href='" . getBaseUrl() . "/admin/orders/detail.php?order_number=$orderNumber' class='button' style='color: #ffffff; text-decoration: none;'>Process Request</a>
            </div>" : "<p>We will review your request and get back to you shortly.</p>") . "
        ";
        
        $message = $this->getEmailTemplate($subject, $content);
        return $this->send($to, $subject, $message);
    }

    /**
     * Send cancellation/refund request status update notification to customer
     */
    public function sendOrderRequestStatusUpdate($to, $type, $orderNumber, $customerName, $status) {
        $typeLabel = ($type === 'refund') ? 'Refund' : 'Cancellation';
        $statusLabel = ucfirst($status);
        $subject = "$typeLabel Request $statusLabel - Order #$orderNumber";
        
        $content = "
            <h1 style='text-align: center;'>$typeLabel Request $statusLabel</h1>
            <p>Dear $customerName,</p>
            <p>We are writing to inform you that your $typeLabel request for order #$orderNumber has been <strong>$status</strong>.</p>
            
            <div style='background: #f9fafb; padding: 24px; border-radius: 8px; margin: 25px 0; text-align: center;'>
                <p style='margin: 0; font-size: 14px; color: #6b7280;'>Request Status</p>
                <div style='display: inline-block; padding: 8px 16px; border-radius: 99px; font-weight: bold; margin-top: 10px; font-size: 18px; " . ($status === 'approved' ? "background: #dcfce7; color: #166534;" : "background: #fee2e2; color: #991b1b;") . "'>
                    $statusLabel
                </div>
            </div>
            
            <p>" . ($status === 'approved' ? "The necessary changes have been made to your order. If a refund was involved, it will be processed according to our policy." : "If you have any questions regarding this decision, please contact our support team.") . "</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . getBaseUrl() . "/account' class='button' style='color: #ffffff; text-decoration: none;'>View Your Account</a>
            </div>
        ";
        
        $message = $this->getEmailTemplate($subject, $content);
        return $this->send($to, $subject, $message);
    }

    /**
     * Standard Email Template
     */
    private function getEmailTemplate($title, $content) {
        $logoType = defined('SITE_LOGO_TYPE') ? SITE_LOGO_TYPE : 'image';
        $logoText = defined('SITE_LOGO_TEXT') ? SITE_LOGO_TEXT : SITE_NAME;
        $logoImage = defined('SITE_LOGO') ? SITE_LOGO : 'logo.png';
        
        // Determine Logo HTML
        $logoHtml = '';
        $imagePath = __DIR__ . '/../assets/images/' . $logoImage;
        
        // Use text if type is text OR image file doesn't exist
        if ($logoType === 'text' || !file_exists($imagePath)) {
            $logoHtml = "<span style='font-size: 24px; font-weight: bold; color: #111827; text-transform: uppercase; letter-spacing: 1px;'>$logoText</span>";
        } else {
            $logoUrl = getBaseUrl() . '/assets/images/' . $logoImage;
            $logoHtml = "<img src='$logoUrl' alt='$logoText' class='logo'>";
        }

        $year = date('Y');
        $siteName = SITE_NAME;
        
        return "
<!DOCTYPE html>
<html>
<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f3f4f6; color: #374151; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f3f4f6; padding-bottom: 40px; }
        .webkit { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        .header { text-align: center; padding: 30px 20px; background-color: #ffffff; border-bottom: 1px solid #f3f4f6; }
        .logo { height: 40px; width: auto; object-fit: contain; }
        .content { padding: 40px 30px; }
        .footer { text-align: center; padding: 20px; color: #9ca3af; font-size: 12px; }
        .button { display: inline-block; padding: 14px 28px; background-color: #000000; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 10px; transition: background 0.3s; }
        h1 { color: #111827; margin-top: 0; font-size: 24px; font-weight: bold; }
        p { margin-bottom: 16px; line-height: 1.6; font-size: 16px; }
        ul { margin-bottom: 24px; padding-left: 20px; }
        li { margin-bottom: 10px; }
        @media screen and (max-width: 600px) {
            .content { padding: 30px 20px; }
            h1 { font-size: 20px; }
            .logo { height: 32px; }
        }
    </style>
</head>
<body>
    <div class='wrapper'>
        <div style='height: 40px;'></div>
        <div class='webkit'>
            <div class='header'>
                $logoHtml
            </div>
            <div class='content'>
                $content
            </div>
        </div>
        <div class='footer'>
            <p>&copy; $year $siteName. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
        ";
    }
}


