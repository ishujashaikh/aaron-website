<?php
/**
 * Aaron Peskowitz Real Estate — Mailer Engine
 * Supports Authenticated Spaceship Spacemail SMTP with PHP mail() Fallback
 */

function send_app_email($to_email, $subject, $html_body, $reply_to_email = null, $reply_to_name = null) {
    $config_file = __DIR__ . '/config.php';
    $config = file_exists($config_file) ? require $config_file : [];

    $from_email = $config['from_email'] ?? 'aaron@aaronpeskowitz.com';
    $from_name  = $config['from_name'] ?? 'Aaron Peskowitz Real Estate';

    $smtp_cfg = $config['smtp'] ?? [];
    $smtp_enabled = !empty($smtp_cfg['enabled']);
    $smtp_host = $smtp_cfg['host'] ?? 'mail.spacemail.com';
    $smtp_port = $smtp_cfg['port'] ?? 465;
    $smtp_user = $smtp_cfg['username'] ?? 'aaron@aaronpeskowitz.com';
    $smtp_pass = $smtp_cfg['password'] ?? '';
    $smtp_enc  = strtolower($smtp_cfg['encryption'] ?? 'ssl');
    $timeout   = $smtp_cfg['timeout'] ?? 15;

    // Check if real SMTP password is provided
    $has_valid_smtp_pass = !empty($smtp_pass) && $smtp_pass !== 'YOUR_SPACESHIP_EMAIL_PASSWORD_HERE';

    if ($smtp_enabled && $has_valid_smtp_pass) {
        $smtp_result = send_via_smtp(
            $smtp_host,
            $smtp_port,
            $smtp_enc,
            $smtp_user,
            $smtp_pass,
            $from_email,
            $from_name,
            $to_email,
            $subject,
            $html_body,
            $reply_to_email,
            $reply_to_name,
            $timeout
        );

        if ($smtp_result['success']) {
            return true;
        }

        // Log SMTP error and fallback to mail()
        error_log("[Spaceship SMTP Error] " . ($smtp_result['error'] ?? 'Unknown error') . " - Falling back to PHP mail()");
    }

    // Fallback to PHP native mail()
    return send_via_php_mail($from_email, $from_name, $to_email, $subject, $html_body, $reply_to_email, $reply_to_name);
}

/**
 * Socket-based SMTP Implementation for Spaceship (Zero external dependencies)
 */
function send_via_smtp($host, $port, $encryption, $username, $password, $from_email, $from_name, $to_email, $subject, $html_body, $reply_to_email = null, $reply_to_name = null, $timeout = 15) {
    $remote = ($encryption === 'ssl' ? "ssl://" : "") . $host;
    $socket = @fsockopen($remote, $port, $errno, $errstr, $timeout);

    if (!$socket) {
        return ['success' => false, 'error' => "Cannot connect to $remote:$port ($errstr)"];
    }

    stream_set_timeout($socket, $timeout);

    $read = function() use ($socket) {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $data;
    };

    $send = function($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    // 1. Initial Greeting
    $res = $read();
    if (substr($res, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => "Invalid greeting: $res"];
    }

    // 2. EHLO
    $client_host = gethostname() ?: 'localhost';
    $res = $send("EHLO " . $client_host);
    if (substr($res, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "EHLO failed: $res"];
    }

    // 3. STARTTLS if TLS port 587
    if ($encryption === 'tls') {
        $res = $send("STARTTLS");
        if (substr($res, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'error' => "STARTTLS failed: $res"];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['success' => false, 'error' => "TLS crypto negotiation failed"];
        }
        $send("EHLO " . $client_host);
    }

    // 4. AUTH LOGIN
    $res = $send("AUTH LOGIN");
    if (substr($res, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH LOGIN command failed: $res"];
    }

    // Send Username
    $res = $send(base64_encode($username));
    if (substr($res, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP Username rejected: $res"];
    }

    // Send Password
    $res = $send(base64_encode($password));
    if (substr($res, 0, 3) !== '235') {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP Authentication failed (Check Spaceship password): $res"];
    }

    // 5. MAIL FROM
    $res = $send("MAIL FROM:<" . $from_email . ">");
    if (substr($res, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM failed: $res"];
    }

    // 6. RCPT TO
    $res = $send("RCPT TO:<" . $to_email . ">");
    if (substr($res, 0, 3) !== '250' && substr($res, 0, 3) !== '251') {
        fclose($socket);
        return ['success' => false, 'error' => "RCPT TO failed for $to_email: $res"];
    }

    // 7. DATA
    $res = $send("DATA");
    if (substr($res, 0, 3) !== '354') {
        fclose($socket);
        return ['success' => false, 'error' => "DATA initiation failed: $res"];
    }

    // 8. Headers & Body construction
    $boundary = "----=_Part_" . md5(uniqid(time(), true));
    $headers  = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from_email>\r\n";
    $headers .= "To: <$to_email>\r\n";
    if (!empty($reply_to_email)) {
        $rt_name = !empty($reply_to_name) ? "=?UTF-8?B?" . base64_encode($reply_to_name) . "?= " : "";
        $headers .= "Reply-To: $rt_name<$reply_to_email>\r\n";
    }
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . md5(uniqid(time(), true)) . "@" . ($host ?: 'aaronpeskowitz.com') . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    // Format body line endings to CRLF and escape leading dots
    $clean_body = str_replace(["\r\n", "\r"], "\n", $html_body);
    $clean_body = str_replace("\n", "\r\n", $clean_body);
    $clean_body = preg_replace('/^\./m', '..', $clean_body);

    $payload = $headers . "\r\n" . $clean_body . "\r\n.\r\n";
    fputs($socket, $payload);

    $res = $read();
    if (substr($res, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "Message body transmission failed: $res"];
    }

    // 9. QUIT
    $send("QUIT");
    fclose($socket);

    return ['success' => true];
}

/**
 * Standard PHP mail() fallback
 */
function send_via_php_mail($from_email, $from_name, $to_email, $subject, $html_body, $reply_to_email = null, $reply_to_name = null) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $from_name <$from_email>\r\n";
    if (!empty($reply_to_email)) {
        $headers .= "Reply-To: " . (!empty($reply_to_name) ? "$reply_to_name " : "") . "<$reply_to_email>\r\n";
    }

    return @mail($to_email, $subject, $html_body, $headers);
}
