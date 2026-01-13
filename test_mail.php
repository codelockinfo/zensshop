<?php
// Report all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'classes/Email.php';

echo "<h1>Email Test Debugger</h1>";

// Check Settings
echo "<h3>Current Configuration</h3>";
echo "Host: " . (defined('SMTP_HOST') ? SMTP_HOST : 'Not Defined') . "<br>";
echo "Port: " . (defined('SMTP_PORT') ? SMTP_PORT : 'Not Defined') . "<br>";
echo "Username: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'Not Defined') . "<br>";
echo "Encryption: " . (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'Not Defined') . "<br>";

$email = new Email();
$recipient = 'dipak.codelock99@gmail.com'; // Default to user email or placeholder
$subject = 'Test Email from Zens Shop';
$message = 'This is a test email sent at ' . date('Y-m-d H:i:s') . '<br>If you see this, SMTP is working!';

echo "<h3>Attempting to send email...</h3>";
echo "To: $recipient<br>";

if (isset($_GET['send'])) {
    $result = $email->send($recipient, $subject, $message);
    
    if ($result) {
        echo "<h2 style='color:green'>SUCCESS: Email sent successfully!</h2>";
    } else {
        echo "<h2 style='color:red'>FAILURE: Email could not be sent.</h2>";
        echo "<p>Check error log for details.</p>";
    }
} else {
    echo "<a href='?send=1'><button>Send Test Email</button></a>";
}
?>
