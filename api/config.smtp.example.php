<?php
/**
 * Campus APP ERP — SMTP configuration TEMPLATE
 *
 * On the server:
 *   1. Copy this file to config.smtp.php
 *   2. Fill in real values (CHANGE_ME → your secrets)
 *   3. Never commit config.smtp.php or put passwords in HTML/JS
 *
 * Requires PHP openssl extension enabled on the host.
 */

return [
    // 'sendgrid' or 'gmail' (informational; host/port below are what matter)
    'SMTP_PROVIDER'   => 'sendgrid',

    // --- SendGrid ---
    // SMTP_HOST   = smtp.sendgrid.net
    // SMTP_PORT   = 587
    // SMTP_SECURE = tls
    // SMTP_USER   = apikey          (literal word "apikey")
    // SMTP_PASS   = SG.xxxxxxxxxxxx (API key from SendGrid → Settings → API Keys)

    // --- Gmail ---
    // SMTP_HOST   = smtp.gmail.com
    // SMTP_PORT   = 587
    // SMTP_SECURE = tls
    // SMTP_USER   = your@gmail.com
    // SMTP_PASS   = 16-char App Password (Google Account → Security → App passwords)
    //               (2-Step Verification must be on; do NOT use your normal Gmail password)

    'SMTP_HOST'       => 'smtp.sendgrid.net',
    'SMTP_PORT'       => 587,
    'SMTP_USER'       => 'apikey',
    'SMTP_PASS'       => 'CHANGE_ME_SG_OR_GMAIL_APP_PASSWORD',
    'SMTP_SECURE'     => 'tls', // 'tls' (STARTTLS on 587) or 'ssl' (465)

    'MAIL_FROM'       => 'noreply@campusapperp.com', // must be allowed by your SMTP provider
    'MAIL_FROM_NAME'  => 'Campus APP ERP',

    // Admin inbox for enquiry notifications (SMTP form submissions).
    // Public "Contact us" display email is info@campusapperp.com — do NOT route ADMIN_TO there
    // unless intentional; founder confirmed enquiry mail stays at kk@quickwayinfosystems.com.
    'ADMIN_TO'        => 'kk@quickwayinfosystems.com',
];
