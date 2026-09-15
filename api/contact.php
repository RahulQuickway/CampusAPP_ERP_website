<?php
/**
 * Campus APP ERP — Contact form endpoint
 * POST JSON or form-urlencoded → admin HTML email + auto-reply via SMTP
 *
 * Setup: copy config.smtp.example.php → config.smtp.php on the server and fill secrets.
 * Never put SMTP passwords in frontend JS/HTML.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// --- CORS / Origin check (same-site preferred) ---
$allowedHosts = ['campusapperp.com', 'www.campusapperp.com', 'localhost', '127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$hostOk = false;
foreach ([$origin, $referer] as $src) {
    if ($src === '') {
        continue;
    }
    $h = parse_url($src, PHP_URL_HOST);
    if ($h && in_array(strtolower($h), $allowedHosts, true)) {
        $hostOk = true;
        if ($origin !== '' && $h && in_array(strtolower($h), $allowedHosts, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
        break;
    }
}
// Allow missing Origin on same-origin classic form posts from production host
if (!$hostOk && $origin === '' && $referer === '') {
    $reqHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $reqHost = preg_replace('/:\d+$/', '', $reqHost);
    if (in_array($reqHost, $allowedHosts, true)) {
        $hostOk = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!$hostOk) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden origin']);
    exit;
}

$configPath = __DIR__ . '/config.smtp.php';
if (!is_readable($configPath)) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'Mail is not configured yet. On the server, copy api/config.smtp.example.php to api/config.smtp.php and fill SMTP settings.',
    ]);
    exit;
}

/** @var array $cfg */
$cfg = require $configPath;
$requiredKeys = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_SECURE', 'MAIL_FROM', 'MAIL_FROM_NAME', 'ADMIN_TO'];
foreach ($requiredKeys as $k) {
    if (!isset($cfg[$k]) || $cfg[$k] === '' || (is_string($cfg[$k]) && strpos($cfg[$k], 'CHANGE_ME') === 0)) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'SMTP config incomplete. Edit api/config.smtp.php on the server (replace CHANGE_ME values).']);
        exit;
    }
}

// --- Parse body (JSON or form-urlencoded) ---
$raw = file_get_contents('php://input') ?: '';
$data = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false || (strlen($raw) > 0 && ($raw[0] === '{' || $raw[0] === '['))) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
} else {
    $data = $_POST;
}

function field(array $data, $key, $max = 500)
{
    $v = isset($data[$key]) ? trim((string) $data[$key]) : '';
    $len = function_exists('mb_strlen') ? mb_strlen($v) : strlen($v);
    if ($len > $max) {
        $v = function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
    }
    return $v;
}

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Honeypot — bots fill this; humans leave empty
$honeypot = field($data, 'website', 200);
if ($honeypot !== '') {
    // Silent success to avoid tipping off bots
    echo json_encode(['ok' => true]);
    exit;
}

$name = field($data, 'name', 120);
$email = field($data, 'email', 200);
$phone = field($data, 'phone', 40);
$institution = field($data, 'institution', 200);
$role = field($data, 'role', 80);
$campusType = field($data, 'campus-type', 80);
if ($campusType === '') {
    $campusType = field($data, 'campus_type', 80);
}
$interest = field($data, 'interest', 80);
$message = field($data, 'message', 4000);
$pageUrl = field($data, 'page_url', 500);
if ($pageUrl === '') {
    $pageUrl = field($data, 'pageUrl', 500);
}

$errors = [];
if ($name === '') {
    $errors[] = 'Name is required';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}
if ($phone === '') {
    $errors[] = 'Phone is required';
}
if ($institution === '') {
    $errors[] = 'Institution is required';
}
if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode('. ', $errors)]);
    exit;
}

// Light IP rate limit: max 5 submissions / 15 minutes
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}
$rateFile = __DIR__ . '/rate_limit.json';
$now = time();
$window = 900;
$maxHits = 5;
$rates = [];
if (is_readable($rateFile)) {
    $rates = json_decode((string) file_get_contents($rateFile), true) ?: [];
}
$rates = array_filter($rates, static function ($ts) use ($now, $window) {
    return is_int($ts) && ($now - $ts) < $window;
});
$ipHitCount = 0;
foreach ($rates as $key => $ts) {
    if (strpos((string) $key, $ip . '|') === 0) {
        $ipHitCount++;
    }
}
if ($ipHitCount >= $maxHits) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests. Please try again in a few minutes.']);
    exit;
}
$rates[$ip . '|' . $now . '|' . bin2hex(random_bytes(2))] = $now;
@file_put_contents($rateFile, json_encode($rates), LOCK_EX);

$submittedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s T');
$adminTo = (string) $cfg['ADMIN_TO']; // default: kk@quickwayinfosystems.com

$roleLabels = [
    'principal-head' => 'Principal / Head',
    'administrator' => 'Administrator',
    'trustee-management' => 'Trustee / Management',
    'it-systems' => 'IT / Systems',
    'other' => 'Other',
];
$campusLabels = [
    'school-k-12' => 'School (K–12)',
    'junior-college' => 'Junior college',
    'degree-college' => 'Degree college',
    'multi-campus-group' => 'Multi-campus group',
    'other' => 'Other',
];
$interestLabels = [
    'product-demo' => 'Product demo',
    'tailored-pricing-quote' => 'Tailored pricing quote',
    'module-consultation' => 'Module consultation',
    'partnership-early-access' => 'Partnership / early access',
];

$roleDisp = $roleLabels[$role] ?? ($role !== '' ? $role : '—');
$campusDisp = $campusLabels[$campusType] ?? ($campusType !== '' ? $campusType : '—');
$interestDisp = $interestLabels[$interest] ?? ($interest !== '' ? $interest : '—');
$msgDisp = $message !== '' ? $message : '—';
$pageDisp = $pageUrl !== '' ? $pageUrl : '—';

$row = static function (string $label, string $value): string {
    return '<tr>'
        . '<td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:600;width:180px;color:#003087;">'
        . h($label) . '</td>'
        . '<td style="padding:10px 12px;border:1px solid #e5e7eb;color:#1f2937;">'
        . nl2br(h($value)) . '</td></tr>';
};

$adminHtml = '<!DOCTYPE html><html><body style="margin:0;padding:24px;font-family:Inter,Arial,sans-serif;background:#f1f5f9;">'
    . '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">'
    . '<div style="background:linear-gradient(135deg,#003087 0%,#1b9aef 100%);padding:20px 24px;color:#fff;">'
    . '<h1 style="margin:0;font-size:20px;">New Campus APP ERP enquiry</h1>'
    . '<p style="margin:6px 0 0;opacity:.9;font-size:13px;">Website contact form</p></div>'
    . '<div style="padding:20px 24px;">'
    . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
    . $row('Name', $name)
    . $row('Email', $email)
    . $row('Phone', $phone)
    . $row('School/College', $institution)
    . $row('Role', $roleDisp)
    . $row('Campus type', $campusDisp)
    . $row('Interest', $interestDisp)
    . $row('Message', $msgDisp)
    . $row('Submitted At', $submittedAt)
    . $row('Page URL', $pageDisp)
    . $row('IP', $ip)
    . '</table></div></div></body></html>';

$adminText = "New enquiry\n"
    . "Name: $name\nEmail: $email\nPhone: $phone\nSchool/College: $institution\n"
    . "Role: $roleDisp\nCampus type: $campusDisp\nInterest: $interestDisp\n"
    . "Message: $msgDisp\nSubmitted At: $submittedAt\nPage URL: $pageDisp\nIP: $ip\n";

$autoHtml = '<!DOCTYPE html><html><body style="margin:0;padding:24px;font-family:Inter,Arial,sans-serif;background:#f1f5f9;">'
    . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">'
    . '<div style="background:linear-gradient(135deg,#003087 0%,#1b9aef 100%);padding:24px;color:#fff;">'
    . '<h1 style="margin:0;font-size:22px;">Thank you for contacting Campus APP ERP</h1></div>'
    . '<div style="padding:28px 24px;color:#1f2937;font-size:15px;line-height:1.6;">'
    . '<p style="margin:0 0 12px;">Hi ' . h($name) . ',</p>'
    . '<p style="margin:0 0 12px;">We received your enquiry about <strong>' . h($institution) . '</strong>. '
    . 'Our team will reach out within <strong>24 business hours</strong>.</p>'
    . '<p style="margin:0 0 12px;">If your message is urgent, reply to this email or write to '
    . '<a href="mailto:' . h($adminTo) . '" style="color:#1b9aef;">' . h($adminTo) . '</a>.</p>'
    . '<p style="margin:24px 0 0;color:#64748b;font-size:13px;">— Campus APP ERP<br>'
    . '<a href="https://campusapperp.com" style="color:#003087;">campusapperp.com</a></p>'
    . '</div></div></body></html>';

$autoText = "Hi $name,\n\nThank you for contacting Campus APP ERP. "
    . "We received your enquiry about $institution. "
    . "Our team will reach out within 24 business hours.\n\n"
    . "— Campus APP ERP\nhttps://campusapperp.com\n";

require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

function makeMailer(array $cfg)
{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string) $cfg['SMTP_HOST'];
    $mail->Port = (int) $cfg['SMTP_PORT'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $cfg['SMTP_USER'];
    $mail->Password = (string) $cfg['SMTP_PASS'];
    $secure = strtolower((string) $cfg['SMTP_SECURE']);
    if ($secure === 'ssl') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->CharSet = 'UTF-8';
    $mail->setFrom((string) $cfg['MAIL_FROM'], (string) $cfg['MAIL_FROM_NAME']);
    return $mail;
}

try {
    // 1) Admin notification
    $adminMail = makeMailer($cfg);
    $adminMail->addAddress($adminTo);
    $adminMail->addReplyTo($email, $name);
    $adminMail->Subject = 'Campus APP ERP enquiry — ' . $name . ' (' . $institution . ')';
    $adminMail->isHTML(true);
    $adminMail->Body = $adminHtml;
    $adminMail->AltBody = $adminText;
    $adminMail->send();

    // 2) Auto-reply to submitter
    $reply = makeMailer($cfg);
    $reply->addAddress($email, $name);
    $reply->addReplyTo($adminTo, (string) $cfg['MAIL_FROM_NAME']);
    $reply->Subject = 'We received your enquiry — Campus APP ERP';
    $reply->isHTML(true);
    $reply->Body = $autoHtml;
    $reply->AltBody = $autoText;
    $reply->send();

    echo json_encode(['ok' => true]);
} catch (\PHPMailer\PHPMailer\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not send email. Check SMTP settings on the server.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error while sending mail.']);
}
