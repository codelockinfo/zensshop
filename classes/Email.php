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
    private $settings;
    private $embeddedImages = []; // Track images to embed
    
    public function __construct($storeId = null) {
        $this->storeId = $storeId;
        require_once __DIR__ . '/Settings.php';
        $this->settings = new Settings();
        $this->embeddedImages = []; // Reset for each instance
        
        // Use provided storeId or fallback to current context
        $this->fromEmail = $this->settings->get('smtp_from_email', defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '', $storeId);
        $this->fromName = $this->settings->get('smtp_from_name', defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME, $storeId);
        $this->smtpHost = $this->settings->get('smtp_host', $this->settings->get('smtp_host', 'smtp.gmail.com', $storeId), $storeId);
        $this->smtpPort = $this->settings->get('smtp_port', $this->settings->get('smtp_port', 587, $storeId), $storeId);
        $this->smtpUsername = $this->settings->get('smtp_username', '', $storeId);
        $this->smtpPassword = $this->settings->get('smtp_password', '', $storeId);
        $this->smtpEncryption = $this->settings->get('smtp_encryption', 'tls', $storeId);
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
            
            // Add Embedded Images (if any)
            foreach ($this->embeddedImages as $img) {
                if (file_exists($img['path'])) {
                    $mail->addEmbeddedImage($img['path'], $img['cid']);
                }
            }

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
        $siteName = $this->settings->get('site_name', SITE_NAME, $this->storeId);
        $subject = "Password Reset OTP - " . $siteName;
        $content = "
            <h1 style='text-align: center;'>Password Reset Request</h1>
            <p>You have requested to reset your password for <strong>$siteName</strong>. Use the following OTP code:</p>
            <div style='background: #f3f4f6; padding: 30px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                <div style='font-size: 36px; font-weight: 800; color: #000; letter-spacing: 5px; font-family: monospace;'>{$otp}</div>
            </div>
            <p>This code will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
            <p style='color: #6b7280; font-size: 14px;'>If you didn't request this, please ignore this email.</p>
        ";
        
        $message = $this->getEmailTemplate($subject, $content);
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
        $siteName = $this->settings->get('site_name', SITE_NAME, $this->storeId);
        $subject = "Welcome to " . $siteName . "!";
        
        $content = "
            <h1 style='text-align: center;'>Thanks for subscribing!</h1>
            <p>Welcome to the <strong>" . $siteName . "</strong> family. You'll be the first to know about:</p>
            <ul>
                <li>New product launches</li>
                <li>Exclusive deals and promotions</li>
                <li>Special offers just for subscribers</li>
                <li>Industry news and updates</li>
            </ul>
            <p>We're excited to have you with us!</p>
            <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
            <p style='color: #6b7280; font-size: 13px; text-align: center;'>If you didn't subscribe to " . $siteName . ", you can safely ignore this email.</p>
        ";
        
        $message = $this->getEmailTemplate($subject, $content);
        return $this->send($to, $subject, $message);
    }

    /**
     * Send development notification email
     */
    public function sendDevelopmentNotification($to) {
        $siteName = $this->settings->get('site_name', SITE_NAME, $this->storeId);
        $subject = "You're on the list! - " . $siteName;
        
        $content = "
            <h1 style='text-align: center;'>Thank You for Subscribing!</h1>
            <p>We appreciate your interest in <strong>" . $siteName . "</strong>.</p>
            <p>Our site is currently under construction, and we are working hard to bring you the best experience possible.</p>
            <div style='background: #f0fdf4; border-left: 4px solid #16a34a; padding: 20px; margin: 25px 0;'>
                <p style='margin: 0; color: #166534; font-weight: 500;'>We will notify you as soon as the site is fully live.</p>
            </div>
            <p>Stay tuned!</p>
        ";
        
        $message = $this->getEmailTemplate($subject, $content);
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send support message notification to admin
     */
    public function sendSupportNotificationToAdmin($adminEmail, $customerName, $customerEmail, $subject, $message) {
        $emailSubject = "New Support Message from $customerName";
        $content = "
            <h1 style='text-align: center;'>New Support Message</h1>
            <p><strong>From:</strong> $customerName ($customerEmail)</p>
            <p><strong>Subject:</strong> $subject</p>
            
            <div style='background: #f9fafb; padding: 20px; border-left: 4px solid #000; margin: 20px 0;'>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
            </div>
            
            <div style='text-align: center; margin-top: 25px;'>
                <a href='" . getBaseUrl() . "/admin/support.php' class='button'>View in Admin Panel</a>
            </div>
        ";
        
        $emailMessage = $this->getEmailTemplate($emailSubject, $content);
        return $this->send($adminEmail, $emailSubject, $emailMessage);
    }
    
    /**
     * Send support reply to customer
     */
    public function sendSupportReply($to, $customerName, $originalSubject, $reply) {
        $subject = "Re: $originalSubject";
        $content = "
            <h1 style='text-align: center;'>Support Team Reply</h1>
            <p>Dear $customerName,</p>
            <p>Thank you for contacting us. Here's our response to your inquiry:</p>
            
            <div style='background: #f0f9ff; padding: 20px; border-left: 4px solid #000; margin: 20px 0;'>
                <p>" . nl2br(htmlspecialchars($reply)) . "</p>
            </div>
            
            <p>If you have any further questions, please don't hesitate to reach out.</p>
            <p>Best regards,<br>Support Team</p>
        ";
        
        $message = $this->getEmailTemplate($subject, $content);
        return $this->send($to, $subject, $message);
    }

    /**
     * Send welcome email to new customer
     */
    public function sendWelcomeEmail($to, $name) {
        $siteName = $this->settings->get('site_name', SITE_NAME, $this->storeId);
        $subject = "Welcome to " . $siteName . "!";
        $siteUrl = getBaseUrl();
        
        $content = "
            <h1 style='text-align: center;'>Welcome to " . $siteName . "!</h1>
            <p>Dear $name,</p>
            <p>We're thrilled to have you on board! Thank you for creating an account with <strong>$siteName</strong>.</p>
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
        $logoType = $this->settings->get('site_logo_type', 'image', $this->storeId);
        $logoText = $this->settings->get('site_logo_text', SITE_NAME, $this->storeId);
        if (empty($logoText)) $logoText = defined('SITE_NAME') ? SITE_NAME : 'Store';

        // Try to get Email Specific Logo first, fallback to Site Logo
        $logoImage = $this->settings->get('email_logo', '', $this->storeId);
        
        if (empty($logoImage)) {
            $logoImage = $this->settings->get('site_logo', 'logo.png', $this->storeId);
        } else {
            // If an email logo is explicitly uploaded, we treat it as an image type
            $logoType = 'image';
        }

        error_log("Email Template - Store: " . ($this->storeId ?: 'DEFAULT') . " | Type: $logoType | Logo: $logoImage");
        
        // Determine Logo HTML
        $logoHtml = '';
        
        if ($logoType === 'text') {
            $logoHtml = "<span style='font-size: 28px; font-weight: 800; color: #000000; text-transform: uppercase; letter-spacing: -0.5px; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>$logoText</span>";
        } else {
            // Use Image Embedding (best for local dev and professional emails)
            $logoUrl = '';
            if (empty($logoImage)) {
                $logoUrl = 'https://placehold.co/200x50?text=' . urlencode($logoText);
            } elseif (strpos($logoImage, 'http://') === 0 || strpos($logoImage, 'https://') === 0 || strpos($logoImage, 'data:') === 0) {
                $logoUrl = $logoImage;
            } else {
                $basePath = defined('BASE_PATH') ? BASE_PATH : realpath(__DIR__ . '/../');
                $cleanPath = ltrim($logoImage, '/');
                
                // If path doesn't already contain assets/images/
                if (strpos($cleanPath, 'assets/images/') === false) {
                     if (file_exists($basePath . '/assets/images/' . $cleanPath)) {
                         $cleanPath = 'assets/images/' . $cleanPath;
                     } else {
                         // Default to uploads if not found in root images
                         if (file_exists($basePath . '/assets/images/uploads/' . $cleanPath)) {
                             $cleanPath = 'assets/images/uploads/' . $cleanPath;
                         }
                     }
                }
                
                $fullPath = $basePath . '/' . $cleanPath;
                if (file_exists($fullPath)) {
                    $this->embeddedImages['logo'] = [
                        'path' => $fullPath,
                        'cid' => 'logo_id'
                    ];
                    $logoUrl = 'cid:logo_id';
                } else {
                    // Final fallback to URL if file not readable for some reason
                    $logoUrl = getBaseUrl() . '/' . $cleanPath;
                }
            }
            error_log("Email logo URL set to: $logoUrl");
            $logoHtml = "<img src='$logoUrl' alt='$logoText' style='max-height: 60px; width: auto; display: block; margin: 0 auto; outline: none; border: none; text-decoration: none;'>";
        }

        $year = date('Y');
        $siteName = $this->settings->get('site_name', SITE_NAME, $this->storeId);
        if (empty($siteName)) $siteName = defined('SITE_NAME') ? SITE_NAME : 'Store';
        $siteUrl = getBaseUrl();
        
        return "
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>$title</title>
<style>
    /* Reset styles */ 
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    table { border-collapse: collapse !important; }
    body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #ffffff; }
    
    /* Component styles */
    .button { display: inline-block; padding: 14px 28px; background-color: #000000; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 10px; }
    
    /* Mobile styles */
    @media screen and (max-width: 600px) {
        .email-container { width: 100% !important; margin: auto !important; }
        .padding-mobile { padding: 20px !important; }
        h1 { font-size: 20px !important; }
        .logo-img { height: 32px !important; width: auto !important; }
    }
</style>
</head>
<body style='margin: 0; padding: 0; background-color: #ffffff;'>
    <center style='width: 100%; background-color: #ffffff;'>
        <div style='display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;'>
            $title
        </div>
        
        <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
            <tr>
                <td style='padding: 20px 0;' align='center'>
                    <!-- Email Container -->
                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #f0f0f0;' class='email-container'>
                        <!-- Header -->
                        <tr>
                            <td style='padding: 20px 20px 5px 20px; text-align: center; border-bottom: 1px solid #f0f0f0;'>
                                $logoHtml
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style='padding: 5px 30px 40px 30px; font-size: 16px; line-height: 1.6; color: #333333; text-align: left;' class='padding-mobile'>
                                $content
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style='padding: 20px; text-align: center; background-color: #f9fafb; font-size: 12px; color: #999999;'>
                                <p style='margin: 0;'>&copy; $year $siteName. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
        ";
    }
}


