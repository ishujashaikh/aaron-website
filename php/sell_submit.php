<?php
// Detect AJAX vs direct POST submission
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
            header("Location: ../thank-you");
        } else {
            http_response_code(400);
            echo "<!DOCTYPE html><html lang='en'><head><meta charset='utf-8'><title>Submission Notice</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{background:#09090b;color:#f4f4f0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;text-align:center;} .box{background:#18181b;padding:40px;border-radius:16px;border:1px solid #27272a;max-width:440px;box-shadow:0 20px 40px rgba(0,0,0,0.6);} h2{margin-top:0;font-family:Georgia,serif;} a{color:#f4f4f0;margin-top:20px;display:inline-block;text-decoration:underline;}</style></head><body><div class='box'><h2>Inquiry Notice</h2><p>" . htmlspecialchars($message) . "</p><a href='javascript:history.back()'>← Return to Form</a></div></body></html>";
        }
    }
    exit;
}

// Block direct GET access (typing URL in browser bar) and redirect to home
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../", true, 301);
    exit;
}

// Load config and mailer
require_once __DIR__ . '/mailer.php';
    $config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];

    // Cloudflare Turnstile Verification (active when secret key is provided and not placeholder)
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
        
        $context  = stream_context_create($options);
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
    $street_address = htmlspecialchars(strip_tags(trim($_POST["street_address"] ?? '')));
    $city = htmlspecialchars(strip_tags(trim($_POST["city"] ?? '')));
    $state = htmlspecialchars(strip_tags(trim($_POST["state"] ?? '')));
    $zip_code = htmlspecialchars(strip_tags(trim($_POST["zip_code"] ?? '')));
    $phone = htmlspecialchars(strip_tags(trim($_POST["phone"] ?? '')));
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);

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
    
    $full_address = "$street_address, $city, $state $zip_code";
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($street_address) || empty($city) || empty($state) || empty($zip_code) || empty($phone) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_form_response(false, "Please provide all required fields with valid information.", $is_ajax);
    }

    // Load mailer and config
    require_once __DIR__ . '/mailer.php';
    $config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $agent_email = $config['recipient_email'] ?? 'aaron@aaronpeskowitz.com';

    // 1. Email to Agent (Seller Lead Notification)
    $notification_subject = "🏡 NEW SELLER LEAD: $street_address";
    $notification_content = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='color-scheme' content='light dark'><meta name='supported-color-schemes' content='light dark'>";
    $notification_content .= "<style>:root{color-scheme:light dark;supported-color-schemes:light dark;} @media (prefers-color-scheme: dark){body,.body-table{background-color:#09090b !important;color:#f4f4f0 !important;}.card-container{background-color:#18181b !important;border-color:#27272a !important;}.data-box{background-color:#121214 !important;border-color:#27272a !important;}.text-main{color:#f4f4f0 !important;}.text-muted{color:#a1a1aa !important;}}</style></head>";
    $notification_content .= "<body style='margin:0; padding:30px 10px; background-color:#09090b !important; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#f4f4f0 !important;'>";
    $notification_content .= "<table role='presentation' width='100%' class='body-table' style='background-color:#09090b !important;'><tr><td align='center' style='background-color:#09090b !important;'>";
    $notification_content .= "<table role='presentation' width='100%' class='card-container' style='max-width:600px; margin:0 auto; background-color:#18181b !important; border-radius:16px; overflow:hidden; border:1px solid #27272a !important; box-shadow:0 20px 40px rgba(0,0,0,0.6);'>";
    $notification_content .= "<tr><td style='background-color:#09090b !important; padding:35px 30px; text-align:center; border-bottom:2px solid #27272a !important;'>";
    $notification_content .= "<img src='https://aaronpeskowitz.com/assets/images/logo_full.webp' alt='Aaron Peskowitz Real Estate' style='max-width:240px; width:100%; height:auto; display:block; margin:0 auto;'>";
    $notification_content .= "</td></tr>";
    $notification_content .= "<tr><td style='padding:30px 30px 10px 30px; text-align:center;'><div style='background-color:rgba(244,244,240,0.08) !important; border:1px solid #3f3f46 !important; color:#f4f4f0 !important; font-size:12px; font-weight:700; padding:8px 18px; border-radius:50px; text-transform:uppercase; letter-spacing:0.08em; display:inline-block;'>🏡 NEW SELLER HOME EVALUATION LEAD</div></td></tr>";
    $notification_content .= "<tr><td style='padding:10px 35px 25px 35px; text-align:center;'><h1 class='text-main' style='font-size:24px; font-weight:700; color:#f4f4f0 !important; margin:0 0 8px 0; font-family:Georgia,serif;'>Valuation Requested</h1><p class='text-muted' style='font-size:14px; color:#a1a1aa !important; margin:0;'>A seller submitted their address on <a href='https://aaronpeskowitz.com/sell' style='color:#f4f4f0 !important; font-weight:600;'>aaronpeskowitz.com/sell</a>.</p></td></tr>";
    $notification_content .= "<tr><td style='padding:0 35px 20px 35px;'><div class='data-box' style='background-color:#121214 !important; color:#f4f4f0 !important; padding:18px 22px; border-radius:12px; border:1px solid #27272a !important; border-left:4px solid #f4f4f0 !important;'><div class='text-muted' style='font-size:11px; text-transform:uppercase; letter-spacing:0.1em; color:#a1a1aa !important; font-weight:700; margin-bottom:4px;'>Target Property Address</div><div class='text-main' style='font-size:18px; font-weight:700; font-family:Georgia,serif; color:#f4f4f0 !important;'>$full_address</div></div></td></tr>";
    $notification_content .= "<tr><td style='padding:0 35px 25px 35px;'><table role='presentation' width='100%' class='data-box' style='background-color:#121214 !important; border:1px solid #27272a !important; border-radius:12px; padding:22px;'>";
    $notification_content .= "<tr><td style='padding:10px 0; border-bottom:1px solid #27272a !important;'><span class='text-muted' style='color:#a1a1aa !important; display:inline-block; width:120px;'>Seller Name:</span><strong class='text-main' style='color:#f4f4f0 !important;'>$first_name $last_name</strong></td></tr>";
    $notification_content .= "<tr><td style='padding:10px 0; border-bottom:1px solid #27272a !important;'><span class='text-muted' style='color:#a1a1aa !important; display:inline-block; width:120px;'>Full Address:</span><strong class='text-main' style='color:#f4f4f0 !important;'>$full_address</strong></td></tr>";
    $notification_content .= "<tr><td style='padding:10px 0; border-bottom:1px solid #27272a !important;'><span class='text-muted' style='color:#a1a1aa !important; display:inline-block; width:120px;'>Email:</span><a href='mailto:$email' style='color:#f4f4f0 !important; font-weight:600; text-decoration:underline;'>$email</a></td></tr>";
    $notification_content .= "<tr><td style='padding:10px 0;'><span class='text-muted' style='color:#a1a1aa !important; display:inline-block; width:120px;'>Phone:</span><a href='tel:$phone' style='color:#f4f4f0 !important; font-weight:700; text-decoration:underline;'>$phone</a></td></tr>";
    $notification_content .= "</table></td></tr>";
    $notification_content .= "<tr><td style='padding:0 35px 30px 35px;'><div style='background-color:rgba(22,101,52,0.2) !important; border:1px solid #166534 !important; border-left:4px solid #22c55e !important; padding:14px 18px; border-radius:8px; font-size:13px; color:#86efac !important;'><strong>✅ Electronic Consent Agreement Verified:</strong> Seller acknowledged electronic disclosure terms.</div></td></tr>";
    $notification_content .= "<tr><td style='padding:0 35px 35px 35px; text-align:center;'><a href='tel:$phone' style='background-color:#f4f4f0 !important; color:#09090b !important; font-size:15px; font-weight:700; text-decoration:none; padding:16px 32px; border-radius:8px; display:inline-block; text-transform:uppercase; letter-spacing:0.05em;'>📞 Call Seller Now: $phone</a></td></tr>";
    $notification_content .= "<tr><td style='background-color:#09090b !important; padding:25px 30px; text-align:center; border-top:1px solid #27272a !important; font-size:12px; color:#a1a1aa !important;'><strong style='color:#f4f4f0 !important;'>Aaron Peskowitz Real Estate</strong> &bull; REALTOR® with <a href='https://mny.exprealty.com/agents/1748711/Aaron+Peskowitz' style='color:#f4f4f0 !important; text-decoration:underline;'>eXp Realty</a><br>Serving Chadwicks, NY and surrounding communities &bull; Office: (315) 796-9255</td></tr>";
    $notification_content .= "</table></td></tr></table></body></html>";
    
    // Send email to Agent using mailer engine
    send_app_email($agent_email, $notification_subject, $notification_content, $email, "$first_name $last_name");

    // 2. Email to Customer (Auto-Responder)
    $autoresponder_subject = "Thank you for contacting Aaron Peskowitz";
    $autoresponder_content = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='color-scheme' content='light dark'><meta name='supported-color-schemes' content='light dark'>";
    $autoresponder_content .= "<style>:root{color-scheme:light dark;supported-color-schemes:light dark;} @media (prefers-color-scheme: dark){body,.body-table{background-color:#09090b !important;color:#f4f4f0 !important;}.card-container{background-color:#18181b !important;border-color:#27272a !important;}.data-box{background-color:#121214 !important;border-color:#27272a !important;}.text-main{color:#f4f4f0 !important;}.text-muted{color:#a1a1aa !important;}}</style></head>";
    $autoresponder_content .= "<body style='margin:0; padding:30px 10px; background-color:#09090b !important; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#f4f4f0 !important;'>";
    $autoresponder_content .= "<table role='presentation' width='100%' class='body-table' style='background-color:#09090b !important;'><tr><td align='center' style='background-color:#09090b !important;'>";
    $autoresponder_content .= "<table role='presentation' width='100%' class='card-container' style='max-width:600px; margin:0 auto; background-color:#18181b !important; border-radius:16px; overflow:hidden; border:1px solid #27272a !important; box-shadow:0 20px 40px rgba(0,0,0,0.6);'>";
    $autoresponder_content .= "<tr><td style='background-color:#09090b !important; padding:35px 30px; text-align:center; border-bottom:2px solid #27272a !important;'>";
    $autoresponder_content .= "<img src='https://aaronpeskowitz.com/assets/images/logo_full.webp' alt='Aaron Peskowitz Real Estate' style='max-width:240px; width:100%; height:auto; display:block; margin:0 auto;'>";
    $autoresponder_content .= "</td></tr>";
    $autoresponder_content .= "<tr><td style='padding:35px 35px 20px 35px;'><h1 class='text-main' style='font-size:24px; font-weight:700; color:#f4f4f0 !important; margin:0 0 16px 0; font-family:Georgia,serif;'>Thank You for Reaching Out, $first_name!</h1>";
    $autoresponder_content .= "<p class='text-main' style='font-size:15px; color:#f4f4f0 !important; line-height:1.7; margin:0 0 18px 0;'>Thank you for contacting our office regarding your property inquiry for <strong>$full_address</strong>.</p>";
    $autoresponder_content .= "<p class='text-muted' style='font-size:15px; color:#a1a1aa !important; line-height:1.7; margin:0 0 25px 0;'>I have received your submission and am currently reviewing active MLS listing data, recent sales history, and neighborhood trends across Chadwicks, Utica, Herkimer, and surrounding communities. I will be getting in touch with you personally very shortly to answer your questions and provide a comprehensive evaluation.</p>";
    $autoresponder_content .= "<table role='presentation' width='100%' class='data-box' style='background-color:#121214 !important; border:1px solid #27272a !important; border-radius:12px; padding:22px; margin-bottom:25px; border-left:4px solid #f4f4f0 !important;'><tr><td width='75' valign='top'>";
    $autoresponder_content .= "<img src='https://aaronpeskowitz.com/assets/images/headshot.webp' alt='Aaron Peskowitz' style='width:65px; height:65px; border-radius:50%; object-fit:cover; border:2px solid #27272a; display:block;'></td>";
    $autoresponder_content .= "<td valign='middle' style='padding-left:15px;'><h3 class='text-main' style='margin:0 0 3px 0; font-size:17px; color:#f4f4f0 !important; font-family:Georgia,serif;'>Aaron Peskowitz</h3>";
    $autoresponder_content .= "<p class='text-muted' style='margin:0 0 6px 0; font-size:13px; color:#a1a1aa !important; font-weight:600;'>REALTOR® &bull; <a href='https://mny.exprealty.com/agents/1748711/Aaron+Peskowitz' style='color:#f4f4f0 !important; text-decoration:underline;'>eXp Realty</a></p>";
    $autoresponder_content .= "<p class='text-muted' style='margin:0; font-size:13px; color:#a1a1aa !important; line-height:1.5;'>Direct: <a href='tel:+13157969255' style='color:#f4f4f0 !important; font-weight:700;'> (315) 796-9255</a><br>Email: <a href='mailto:$agent_email' style='color:#f4f4f0 !important; text-decoration:underline;'>$agent_email</a></p>";
    $autoresponder_content .= "</td></tr></table>";
    $autoresponder_content .= "<p style='text-align:center; padding:5px 0 10px 0;'><a href='https://aaronpeskowitz.com' style='background-color:#f4f4f0 !important; color:#09090b !important; font-size:14px; font-weight:700; text-decoration:none; padding:14px 28px; border-radius:8px; display:inline-block; border:1px solid #ffffff !important;'>Explore Website &amp; Featured Listings ↗</a></p>";
    $autoresponder_content .= "</td></tr>";
    $autoresponder_content .= "<tr><td style='background-color:#09090b !important; padding:25px 30px; text-align:center; border-top:1px solid #27272a !important; font-size:12px; color:#a1a1aa !important; line-height:1.6;'>";
    $autoresponder_content .= "<strong style='color:#f4f4f0 !important;'>Aaron Peskowitz Real Estate</strong> &bull; REALTOR® with <a href='https://mny.exprealty.com/agents/1748711/Aaron+Peskowitz' style='color:#f4f4f0 !important; text-decoration:underline;'>eXp Realty</a><br>Serving Chadwicks, NY and surrounding communities &bull; Phone: (315) 796-9255<br>";
    $autoresponder_content .= "<div style='margin-top:12px; padding-top:12px; border-top:1px solid #27272a !important; font-size:11px; color:#71717a !important;'>";
    $autoresponder_content .= "<a href='https://aaronpeskowitz.com/fair-housing' style='color:#a1a1aa !important; text-decoration:underline;'>Fair Housing Act</a> &bull; <a href='https://aaronpeskowitz.com/privacy' style='color:#a1a1aa !important; text-decoration:underline;'>Privacy Policy</a> &bull; <a href='https://aaronpeskowitz.com/terms' style='color:#a1a1aa !important; text-decoration:underline;'>Terms &amp; Conditions</a>";
    $autoresponder_content .= "</div></td></tr>";
    $autoresponder_content .= "</table></td></tr></table></body></html>";
    
    // Send autoresponder using mailer engine
    send_app_email($email, $autoresponder_subject, $autoresponder_content, $agent_email, "Aaron Peskowitz Real Estate");

    // Log lead to protected CSV in root /leads/ directory
    log_lead_to_csv(
        "Seller Home Valuation",
        $first_name,
        $last_name,
        $email,
        $phone,
        "Property: $full_address",
        "Agreed to Electronic Disclosure Consent & Marketing Terms"
    );

    // Success Response
    send_form_response(true, "Thank you! Aaron Peskowitz will contact you very soon.", $is_ajax);

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
