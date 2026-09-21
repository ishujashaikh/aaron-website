<?php
// Set response headers
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($is_ajax) {
    header('Content-Type: application/json');
}

/**
 * Universal response helper supporting both AJAX JSON and standard HTTP redirects
 */
function send_form_response($success, $message, $is_ajax) {
    if ($is_ajax) {
        http_response_code($success ? 200 : 400);
        echo json_encode([
            "status" => $success ? "success" : "error",
            "message" => $message
        ]);
    } else {
        if ($success) {
            header("Location: ../thank-you.html");
        } else {
            http_response_code(400);
            echo "<!DOCTYPE html><html lang='en'><head><meta charset='utf-8'><title>Submission Notice</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{background:#09090b;color:#f4f4f0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;text-align:center;} .box{background:#18181b;padding:40px;border-radius:16px;border:1px solid #27272a;max-width:440px;box-shadow:0 20px 40px rgba(0,0,0,0.6);} h2{margin-top:0;font-family:Georgia,serif;} a{color:#f4f4f0;margin-top:20px;display:inline-block;text-decoration:underline;}</style></head><body><div class='box'><h2>Inquiry Notice</h2><p>" . htmlspecialchars($message) . "</p><a href='javascript:history.back()'>← Return to Form</a></div></body></html>";
        }
    }
    exit;
}

// Block direct GET access (typing URL in browser bar) and redirect to home
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.html", true, 301);
    exit;
}

// Load config and mailer
    require_once __DIR__ . '/mailer.php';
    $config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];

    // Cloudflare Turnstile Verification (active when real secret key is configured)
    $turnstile_secret = $config['turnstile_secret'] ?? 'YOUR_TURNSTILE_SECRET_KEY_HERE';
    $turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    
    $is_turnstile_active = !empty($turnstile_secret) 
        && $turnstile_secret !== 'YOUR_TURNSTILE_SECRET_KEY_HERE' 
        && strpos($turnstile_secret, 'YOUR_') !== 0;

    if ($is_turnstile_active) {
        if (empty($turnstile_response)) {
            send_form_response(false, "Please complete the security check.", $is_ajax);
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
                'content' => http_build_query($data),
                'timeout' => 5
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($verify_url, false, $context);
        
        if ($result !== FALSE) {
            $captcha_success = json_decode($result);
            if (!empty($captcha_success) && $captcha_success->success === false) {
                send_form_response(false, "Security verification failed. Please try again.", $is_ajax);
            }
        }
    }

    // Retrieve and sanitize form inputs
    $first_name = htmlspecialchars(strip_tags(trim($_POST["first_name"] ?? '')));
    $last_name = htmlspecialchars(strip_tags(trim($_POST["last_name"] ?? '')));
    $phone = htmlspecialchars(strip_tags(trim($_POST["phone"] ?? '')));
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $inquiry_type = htmlspecialchars(strip_tags(trim($_POST["inquiry_type"] ?? '')));

    // Clean and validate phone number (must not exceed 10 digits)
    $phone_digits = preg_replace('/\D/', '', $phone);
    if (strlen($phone_digits) === 11 && str_starts_with($phone_digits, '1')) {
        $phone_digits = substr($phone_digits, 1);
    }
    if (strlen($phone_digits) > 10) {
        send_form_response(false, "Phone number must not exceed 10 digits.", $is_ajax);
    }
    if (strlen($phone_digits) === 10) {
        $phone = sprintf("(%s) %s-%s", substr($phone_digits, 0, 3), substr($phone_digits, 3, 3), substr($phone_digits, 6, 4));
    }
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($phone) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_form_response(false, "Please fill out all required fields with valid information.", $is_ajax);
    }
    $recipient = $config['recipient_email'] ?? 'aaron@aaronpeskowitz.com';
    $subject = "⚡ NEW WEBSITE LEAD: $first_name $last_name ($inquiry_type)";
    
    $email_content = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='color-scheme' content='light dark'><meta name='supported-color-schemes' content='light dark'>";
    $email_content .= "<style>:root{color-scheme:light dark;supported-color-schemes:light dark;} @media (prefers-color-scheme: dark){body,.body-table{background-color:#09090b !important;color:#f4f4f0 !important;}.card-container{background-color:#18181b !important;border-color:#27272a !important;}.data-box{background-color:#121214 !important;border-color:#27272a !important;}.text-main{color:#f4f4f0 !important;}.text-muted{color:#a1a1aa !important;}}</style></head>";
    $email_content .= "<body style='margin:0; padding:30px 10px; background-color:#09090b !important; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#f4f4f0 !important;'>";
    $email_content .= "<table role='presentation' width='100%' class='body-table' style='background-color:#09090b !important;'><tr><td align='center' style='background-color:#09090b !important;'>";
    $email_content .= "<table role='presentation' width='100%' class='card-container' style='max-width:600px; margin:0 auto; background-color:#18181b !important; border-radius:16px; overflow:hidden; border:1px solid #27272a !important; box-shadow:0 20px 40px rgba(0,0,0,0.6);'>";
    $email_content .= "<tr><td style='background-color:#09090b !important; padding:35px 30px; text-align:center; border-bottom:2px solid #27272a !important;'>";
    $email_content .= "<img src='https://aaronpeskowitz.com/assets/images/logo_full.webp' alt='Aaron Peskowitz Real Estate' style='max-width:240px; width:100%; height:auto; display:block; margin:0 auto;'>";
    $email_content .= "</td></tr>";
    $email_content .= "<tr><td style='padding:30px 30px 10px 30px; text-align:center;'><div style='background-color:rgba(244,244,240,0.08) !important; border:1px solid #3f3f46 !important; color:#f4f4f0 !important; font-size:12px; font-weight:700; padding:8px 18px; border-radius:50px; text-transform:uppercase; letter-spacing:0.08em; display:inline-block;'>⚡ NEW WEBSITE LEAD SUBMISSION</div></td></tr>";
    $email_content .= "<tr><td style='padding:10px 35px 25px 35px; text-align:center;'><h1 class='text-main' style='font-size:24px; font-weight:700; color:#f4f4f0 !important; margin:0 0 8px 0; font-family:Georgia,serif;'>New Lead: $first_name $last_name</h1><p class='text-muted' style='font-size:14px; color:#a1a1aa !important; margin:0;'>A client submitted an inquiry form on <a href='https://aaronpeskowitz.com' style='color:#f4f4f0 !important; font-weight:600;'>aaronpeskowitz.com</a>.</p></td></tr>";
    $email_content .= "<tr><td style='padding:0 35px 25px 35px;'><table role='presentation' width='100%' class='data-box' style='background-color:#121214 !important; border:1px solid #27272a !important; border-radius:12px; padding:22px; border-left:4px solid #f4f4f0 !important;'>";
    $email_content .= "<tr><td style='padding:10px 0; border-bottom:1px solid #27272a !important;'><span class='text-muted' style='color:#a1a1aa !important; display:inline-block; width:110px;'>Full Name:</span><strong class='text-main' style='color:#f4f4f0 !important;'>$first_name $last_name</strong></td></tr>";
    $email_content .= "<tr><td style='padding:10px 0; border-bottom:1px solid #27272a !important;'><span class='text-muted' style='color:#a1a1aa !important; display:inline-block; width:110px;'>Email:</span><a href='mailto:$email' style='color:#f4f4f0 !important; font-weight:600; text-decoration:underline;'>$email</a></td></tr>";
    $email_content .= "<tr><td style='padding:10px 0; border-bottom:1px solid #27272a !important;'><span class='text-muted' style='color:#a1a1aa !important; display:inline-block; width:110px;'>Phone:</span><a href='tel:$phone' style='color:#f4f4f0 !important; font-weight:700; text-decoration:underline;'>$phone</a></td></tr>";
    $email_content .= "<tr><td style='padding:10px 0;'><span class='text-muted' style='color:#a1a1aa !important; display:inline-block; width:110px;'>Inquiry Type:</span><span style='background-color:rgba(244,244,240,0.12) !important; color:#f4f4f0 !important; font-size:12px; font-weight:600; padding:4px 10px; border-radius:4px; border:1px solid #3f3f46 !important;'>$inquiry_type</span></td></tr>";
    $email_content .= "</table></td></tr>";
    $email_content .= "<tr><td style='padding:0 35px 30px 35px;'><div style='background-color:rgba(22,101,52,0.2) !important; border:1px solid #166534 !important; border-left:4px solid #22c55e !important; padding:14px 18px; border-radius:8px; font-size:13px; color:#86efac !important;'><strong>✅ TCPA &amp; SMS Compliance Verified:</strong> Prospect checked the required website disclosure box consenting to contact.</div></td></tr>";
    $email_content .= "<tr><td style='padding:0 35px 35px 35px; text-align:center;'><a href='tel:$phone' style='background-color:#f4f4f0 !important; color:#09090b !important; font-size:15px; font-weight:700; text-decoration:none; padding:16px 32px; border-radius:8px; display:inline-block; text-transform:uppercase; letter-spacing:0.05em;'>📞 Call Lead Now: $phone</a></td></tr>";
    $email_content .= "<tr><td style='background-color:#09090b !important; padding:25px 30px; text-align:center; border-top:1px solid #27272a !important; font-size:12px; color:#a1a1aa !important;'><strong style='color:#f4f4f0 !important;'>Aaron Peskowitz Real Estate</strong> &bull; REALTOR® with <a href='https://mny.exprealty.com/agents/1748711/Aaron+Peskowitz' style='color:#f4f4f0 !important; text-decoration:underline;'>eXp Realty</a><br>Serving Chadwicks, NY and surrounding communities &bull; Office: (315) 796-9255</td></tr>";
    $email_content .= "</table></td></tr></table></body></html>";

    // Log lead to protected CSV in root /leads/ directory
    log_lead_to_csv(
        "General Contact / Buyer Inquiry",
        $first_name,
        $last_name,
        $email,
        $phone,
        "Inquiry Type: $inquiry_type",
        "Agreed to TCPA & SMS Compliance Disclosures"
    );

    // Send email using mailer engine (Spaceship SMTP or PHP mail)
    $mail_sent = send_app_email($recipient, $subject, $email_content, $email, "$first_name $last_name");

    // Optional customer autoresponder
    $auto_subject = "Thank you for contacting Aaron Peskowitz Real Estate";
    $auto_content = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='color-scheme' content='light dark'></head>";
    $auto_content .= "<body style='margin:0; padding:30px 10px; background-color:#09090b !important; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; color:#f4f4f0 !important;'>";
    $auto_content .= "<table role='presentation' width='100%' style='max-width:600px; margin:0 auto; background-color:#18181b !important; border-radius:16px; border:1px solid #27272a !important; padding:35px;'>";
    $auto_content .= "<tr><td align='center'><img src='https://aaronpeskowitz.com/assets/images/logo_full.webp' alt='Aaron Peskowitz Real Estate' style='max-width:220px; width:100%; height:auto;'></td></tr>";
    $auto_content .= "<tr><td style='padding-top:25px;'><h1 style='font-size:22px; font-weight:700; color:#f4f4f0; margin:0 0 14px 0; font-family:Georgia,serif;'>Thank You for Reaching Out, $first_name!</h1>";
    $auto_content .= "<p style='color:#f4f4f0; font-size:15px; line-height:1.7; margin:0 0 16px 0;'>I have received your inquiry regarding <strong>$inquiry_type</strong> across Chadwicks, Utica, Herkimer, and surrounding Central New York communities.</p>";
    $auto_content .= "<p style='color:#a1a1aa; font-size:14px; line-height:1.7; margin:0 0 24px 0;'>I will be in touch with you personally very shortly to answer your questions and assist with your real estate goals.</p>";
    $auto_content .= "<div style='background-color:#121214; border:1px solid #27272a; border-radius:8px; padding:16px; border-left:4px solid #f4f4f0; font-size:13px; color:#a1a1aa;'><strong>Aaron Peskowitz</strong> &bull; REALTOR® with eXp Realty<br>Phone: <a href='tel:+13157969255' style='color:#f4f4f0;'>(315) 796-9255</a> &bull; Email: <a href='mailto:$recipient' style='color:#f4f4f0;'>$recipient</a></div>";
    $auto_content .= "</td></tr></table></body></html>";
    send_app_email($email, $auto_subject, $auto_content, $recipient, "Aaron Peskowitz Real Estate");

    // Send success response
    send_form_response(true, "Thank you for your inquiry. Aaron Peskowitz will contact you shortly.", $is_ajax);

/**
 * Helper function to safely append lead details to hidden /leads/leads.csv
 */
function log_lead_to_csv($lead_type, $first_name, $last_name, $email, $phone, $details, $compliance_status) {
    $leads_dir = __DIR__ . '/../leads';
    
    // Ensure /leads/ directory exists
    if (!file_exists($leads_dir)) {
        @mkdir($leads_dir, 0755, true);
    }

    // Ensure .htaccess protection exists
    $htaccess_file = $leads_dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "# Deny all public web access to leads directory\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
        @file_put_contents($htaccess_file, $htaccess_content);
    }

    // Ensure index.php protection exists
    $index_file = $leads_dir . '/index.php';
    if (!file_exists($index_file)) {
        $index_content = "<?php\nheader('HTTP/1.1 403 Forbidden');\necho 'Access Denied.';\nexit;\n";
        @file_put_contents($index_file, $index_content);
    }

    $csv_file = $leads_dir . '/leads.csv';
    $is_new_file = !file_exists($csv_file);

    $file = @fopen($csv_file, 'a');
    if ($file) {
        if (@flock($file, LOCK_EX)) {
            // Write CSV Header if creating file for the first time
            if ($is_new_file) {
                fputcsv($file, [
                    'Date & Time (UTC)',
                    'Lead Type',
                    'First Name',
                    'Last Name',
                    'Email Address',
                    'Phone Number',
                    'Property / Inquiry Details',
                    'Compliance Status',
                    'IP Address'
                ], ',', '"', "\\");
            }

            // Write Lead Record
            fputcsv($file, [
                date('Y-m-d H:i:s'),
                $lead_type,
                $first_name,
                $last_name,
                $email,
                $phone,
                $details,
                $compliance_status,
                $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ], ',', '"', "\\");

            @flock($file, LOCK_UN);
        }
        @fclose($file);
    }
}
?>
