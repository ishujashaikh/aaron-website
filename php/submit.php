<?php
// Set response headers
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Cloudflare Turnstile Verification
    $turnstile_secret = "YOUR_TURNSTILE_SECRET_KEY_HERE";
    $turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    
    if (empty($turnstile_response)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Please complete the security check."]);
        exit;
    }
    
    $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $turnstile_secret,
        'response' => $turnstile_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context  = stream_context_create($options);
    $result = file_get_contents($verify_url, false, $context);
    
    if ($result === FALSE) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error verifying security check."]);
        exit;
    }
    
    $captcha_success = json_decode($result);
    if ($captcha_success->success == false) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Security check failed. Please try again."]);
        exit;
    }

    // Retrieve and sanitize form inputs
    $first_name = htmlspecialchars(strip_tags(trim($_POST["first_name"])));
    $last_name = htmlspecialchars(strip_tags(trim($_POST["last_name"])));
    $phone = htmlspecialchars(strip_tags(trim($_POST["phone"])));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $inquiry_type = htmlspecialchars(strip_tags(trim($_POST["inquiry_type"])));
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($phone) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Please fill out all required fields with valid information."]);
        exit;
    }

    // Email Setup
    $recipient = "mike@michaelrisser.com";
    $subject = "New Lead Inquiry from $first_name $last_name ($inquiry_type)";
    
    $email_content = "Name: $first_name $last_name\n";
    $email_content .= "Email: $email\n";
    $email_content .= "Phone: $phone\n";
    $email_content .= "Inquiry Type: $inquiry_type\n\n";
    $email_content .= "A prospect has agreed to the SMS compliance terms on the website.";

    $email_headers = "From: leads@michaelrisser.com\r\n";
    $email_headers .= "Reply-To: $email\r\n";

    // Send email using PHP mail() standard on GoDaddy
    if (mail($recipient, $subject, $email_content, $email_headers)) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Thank you for your inquiry. Michael Risser will contact you shortly."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Oops! Something went wrong and we couldn't send your message."]);
    }

} else {
    // Not a POST request
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "There was a problem with your submission, please try again."]);
}
?>
