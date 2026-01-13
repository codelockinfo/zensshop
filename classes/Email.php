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
    
    public function __construct() {
        $this->fromEmail = SMTP_FROM_EMAIL;
        $this->fromName = SMTP_FROM_NAME;
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
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            // Determine encryption: Force SSL for Port 465, otherwise respect setting or default to TLS
            if (SMTP_PORT == 465 || (defined('SMTP_ENCRYPTION') && strtolower(SMTP_ENCRYPTION) === 'ssl')) {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = SMTP_PORT;
            
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
                <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>{$item['product_name']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: center;'>{$item['quantity']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;'>₹" . number_format($item['price'], 2) . "</td>
            </tr>";
        }
        
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
                    .order-box { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    .total { font-size: 18px; font-weight: bold; color: #4F46E5; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin: 0;'>Thank You for Your Order!</h1>
                    </div>
                    <div class='content'>
                        <p>Dear $customerName,</p>
                        <p>Thank you for shopping with us! Your order has been successfully placed.</p>
                        
                        <div class='order-box'>
                            <h3 style='margin-top: 0;'>Order Details</h3>
                            <p><strong>Order Number:</strong> $orderNumber</p>
                            <p><strong>Order Date:</strong> " . date('F d, Y') . "</p>
                        </div>
                        
                        <h3>Order Items</h3>
                        <table>
                            <thead>
                                <tr style='background: #f3f4f6;'>
                                    <th style='padding: 10px; text-align: left;'>Product</th>
                                    <th style='padding: 10px; text-align: center;'>Quantity</th>
                                    <th style='padding: 10px; text-align: right;'>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                $itemsHtml
                            </tbody>
                        </table>
                        
                        <p class='total' style='text-align: right; margin-top: 20px;'>Total: ₹" . number_format($totalAmount, 2) . "</p>
                        
                        <p>We'll send you another email when your order ships.</p>
                        <p>If you have any questions, please don't hesitate to contact us.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send newsletter subscription confirmation
     */
    public function sendSubscriptionConfirmation($to) {
        $subject = "Welcome to " . SITE_NAME . "!";
        
        $content = "
            <h1 style='text-align: center;'>Thanks for subscribing to us!</h1>
            <p>Welcome to our newsletter community! You'll be the first to know about:</p>
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


