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
    $street_address = htmlspecialchars(strip_tags(trim($_POST["street_address"])));
    $city = htmlspecialchars(strip_tags(trim($_POST["city"])));
    $state = htmlspecialchars(strip_tags(trim($_POST["state"])));
    $zip_code = htmlspecialchars(strip_tags(trim($_POST["zip_code"])));
    $phone = htmlspecialchars(strip_tags(trim($_POST["phone"])));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    
    $full_address = "$street_address, $city, $state $zip_code";
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($street_address) || empty($city) || empty($state) || empty($zip_code) || empty($phone) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Please provide all required fields with valid information."]);
        exit;
    }

    $mike_email = "mike@michaelrisser.com";

    // 1. Email to Mike (Lead Notification)
    $notification_subject = "NEW SELLER LEAD: $street_address";
    $notification_content = "Lead Type: SELLER\n\n";
    $notification_content .= "Name: $first_name $last_name\n";
    $notification_content .= "Property Address: $full_address\n";
    $notification_content .= "Email: $email\n";
    $notification_content .= "Phone: $phone\n\n";
    $notification_content .= "Compliance: The prospect has checked the required compliance box acknowledging the Electronic Disclosure Consent Agreement and marketing communications from Gary Mercer Team.";
    
    $notification_headers = "From: leads@michaelrisser.com\r\n";
    $notification_headers .= "Reply-To: $email\r\n";
    
    mail($mike_email, $notification_subject, $notification_content, $notification_headers);

    // 2. Email to Customer (Auto-Responder)
    $autoresponder_subject = "Thank you for contacting Michael Risser";
    $autoresponder_content = "Hello $first_name,\n\n";
    $autoresponder_content .= "Thank you for reaching out regarding your home at $full_address.\n\n";
    $autoresponder_content .= "I have received your information and I will be getting in touch with you very soon to discuss your custom home evaluation.\n\n";
    $autoresponder_content .= "Best regards,\nMichael Risser\nAssociate Broker\n610-348-7654";
    
    $autoresponder_headers = "From: $mike_email\r\n";
    $autoresponder_headers .= "Reply-To: $mike_email\r\n";
    
    mail($email, $autoresponder_subject, $autoresponder_content, $autoresponder_headers);

    // Success Response
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Thank you! Michael Risser will contact you very soon."]);

} else {
    // Not a POST request
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "There was a problem with your submission, please try again."]);
}
?>
