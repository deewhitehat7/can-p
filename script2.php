<?php
// Start session and generate CSRF token
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }

    // Sanitize inputs
    $amount = filter_var($_POST['donation_amount'] ?? $_POST['custom_amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $frequency = htmlspecialchars($_POST['donation_frequency']);
    $name = htmlspecialchars($_POST['full_name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars($_POST['dedication'] ?? '');
    $method = htmlspecialchars($_POST['payment_method']);
    $subscribe = isset($_POST['subscribe']) ? 1 : 0;

    // Validate inputs
    if (empty($amount) || empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.html?error=invalid_input");
        exit;
    }

    // Process payment based on method
    if ($method === 'paypal') {
        // Redirect to PayPal
        header("Location: https://www.paypal.com/donate?amount=$amount");
        exit;
    } else {
        // Process credit card (in real app, use Stripe/other processor)
        require_once 'vendor/autoload.php'; // Composer dependencies

        try {
            // This would be replaced with actual payment processor code
            $payment_success = true; // Simulated success

            if ($payment_success) {
                // Save to database
                $pdo = new PDO('mysql:host=localhost;dbname=charity_db', 'username', 'password');
                $stmt = $pdo->prepare("INSERT INTO donations (amount, frequency, name, email, message, payment_method, subscribe, donation_date) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$amount, $frequency, $name, $email, $message, $method, $subscribe]);

                // Send confirmation email
                $to = $email;
                $subject = "Thank you for your donation to Hope Against Cancer";
                $message = "Dear $name,\n\nThank you for your generous donation of $$amount.\n\n";
                $message .= "Donation Type: " . ($frequency === 'monthly' ? 'Monthly' : 'One-Time') . "\n";
                $message .= "Payment Method: " . ($method === 'paypal' ? 'PayPal' : 'Credit Card') . "\n";
                $message .= "Dedication: $message\n\n";
                $message .= "Tax Receipt Number: RC-" . rand(100000, 999999) . "\n\n";
                $message .= "Hope Against Cancer Foundation\nTax ID: 12-3456789\n";
                $headers = "From: donations@hopeagainstcancer.org";

                mail($to, $subject, $message, $headers);

                // Redirect to thank you page
                header("Location: thank-you.html?amount=$amount&name=" . urlencode($name));
                exit;
            } else {
                header("Location: index.html?error=payment_failed");
                exit;
            }
        } catch (Exception $e) {
            error_log("Donation error: " . $e->getMessage());
            header("Location: index.html?error=processing_error");
            exit;
        }
    }
}

// If not POST request, redirect to home
header("Location: index.html");
exit;
?>