<?php
require_once __DIR__ . '/includes/mail_config.php';

echo "<h1>Testing Reminder Email</h1>";

$test_email = 'becypher01@gmail.com';
$test_name = 'Test Customer';
$test_message = 'This is a test reminder. Please make your payment by the due date.';

echo "Sending test email to: $test_email<br>";

$result = sendReminderEmail($test_email, $test_name, $test_message);

if ($result) {
    echo "<div style='color: green; padding: 10px; border: 1px solid green; border-radius: 5px; margin-top: 10px;'>";
    echo "✅ Test reminder email sent successfully!<br>";
    echo "Check your inbox at: $test_email";
    echo "</div>";
} else {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; border-radius: 5px; margin-top: 10px;'>";
    echo "❌ Failed to send test email.<br><br>";
    echo "<strong>Possible issues:</strong><br>";
    echo "1. App password not set correctly in mail_config.php<br>";
    echo "2. Internet connection issue<br>";
    echo "3. Gmail account security settings<br>";
    echo "</div>";
}

echo "<br><br>";
echo "<strong>Current SMTP_PASSWORD status:</strong> ";
if (SMTP_PASSWORD == 'YOUR_16_CHARACTER_APP_PASSWORD_HERE') {
    echo "<span style='color: red;'>❌ Not set! Please update mail_config.php with your actual app password.</span>";
} else {
    echo "<span style='color: green;'>✅ Set (length: " . strlen(SMTP_PASSWORD) . " characters)</span>";
}
?>