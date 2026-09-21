<?php
/**
 * Aaron Peskowitz Real Estate — Central Configuration
 * Spaceship Spacemail SMTP & Form Notification Settings
 */

return [
    // Primary recipient for website lead notifications
    'recipient_email'  => 'aaron@aaronpeskowitz.com',
    'recipient_name'   => 'Aaron Peskowitz Real Estate',

    // From identity (matches your Spaceship authenticated email)
    'from_email'       => 'aaron@aaronpeskowitz.com',
    'from_name'        => 'Aaron Peskowitz Real Estate',

    // Spaceship Spacemail SMTP Configuration
    'smtp' => [
        'enabled'      => true,                     // Set to false to use PHP mail() fallback
        'host'         => 'mail.spacemail.com',     // Spaceship Spacemail Host
        'port'         => 465,                      // Port 465 (SSL) or 587 (TLS)
        'encryption'   => 'ssl',                    // 'ssl' (port 465) or 'tls' (port 587)
        'username'     => 'aaron@aaronpeskowitz.com',
        'password'     => 'YOUR_SPACESHIP_EMAIL_PASSWORD_HERE', // Replace with your Spaceship email password
        'timeout'      => 15,
    ],

    // Cloudflare Turnstile Secret Key (set your secret key here)
    'turnstile_secret' => 'YOUR_TURNSTILE_SECRET_KEY_HERE',
];
