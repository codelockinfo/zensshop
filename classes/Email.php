<?php
/**
 * Email Service Class
 * Handles email sending using PHPMailer
 */

require_once __DIR__ . '/../config/email.php';

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
            // Determine encryption
            if (defined('SMTP_ENCRYPTION') && strtolower(SMTP_ENCRYPTION) === 'ssl') {
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
        $subject = "Welcome to Our Newsletter!";
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #10B981; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
                    .icon { font-size: 48px; margin-bottom: 10px; }
                    .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='icon'>✉️</div>
                        <h1 style='margin: 0;'>Thank You for Subscribing!</h1>
                    </div>
                    <div class='content'>
                        <p>Welcome to our newsletter community!</p>
                        <p>You'll be the first to know about:</p>
                        <ul>
                            <li>New product launches</li>
                            <li>Exclusive deals and promotions</li>
                            <li>Special offers just for subscribers</li>
                            <li>Industry news and updates</li>
                        </ul>
                        <p>We're excited to have you with us!</p>
                        <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>If you didn't subscribe to this newsletter, you can safely ignore this email.</p>
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
                    .header { background: #EF4444; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
                    .message-box { background: #f9fafb; padding: 20px; border-left: 4px solid #EF4444; margin: 20px 0; }
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
                        
                        <p><a href='" . getBaseUrl() . "/admin/support.php' style='background: #EF4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View in Admin Panel</a></p>
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
}


