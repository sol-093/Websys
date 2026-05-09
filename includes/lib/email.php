<?php

declare(strict_types=1);

/**
 * Email Service Module
 * Handles sending emails for authentication and security notifications
 *
 * ================================================
 * SECTION MAP:
 * 1. Transport Readiness
 * 2. Email Template Builders
 * 3. Activation and Password Reset Emails
 * 4. Security and Account Status Emails
 *
 * WORK GUIDE:
 * - Edit this file for email copy, layout, and high-level account mail behavior.
 * ================================================
 */

function ensurePhpMailerAvailable(): bool
{
    $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
    if (class_exists($mailerClass)) {
        return true;
    }

    error_log('PHPMailer Composer dependency is unavailable. Run `composer install`.');
    return false;
}

function emailHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sendEmail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $config = require __DIR__ . '/../core/config.php';
    $appName = (string) ($config['app_name'] ?? 'Student Organization Management');
    $baseUrl = appBaseUrl($config);

    $emailBody = buildEmailTemplate($subject, $htmlBody, $appName, $baseUrl);

    $smtp = $config['smtp'] ?? [];
    $smtpHost = (string) ($smtp['host'] ?? '');
    $smtpPort = (int) ($smtp['port'] ?? 587);
    $smtpUser = (string) ($smtp['user'] ?? '');
    $smtpPass = (string) ($smtp['pass'] ?? '');
    $from = (string) ($smtp['from'] ?? 'noreply@campus.local');
    $fromName = (string) ($smtp['from_name'] ?? $appName);

    if ($smtpHost === '') {
        error_log('SMTP_HOST is empty; falling back to PHP mail().');

        $headers = [
            'From' => $from,
            'Reply-To' => $from,
            'X-Mailer' => 'PHP/' . phpversion(),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8',
        ];

        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }

        try {
            return mail($to, $subject, $emailBody, $headerString);
        } catch (Throwable $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    try {
        $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
        if (!ensurePhpMailerAvailable()) {
            error_log('PHPMailer class not found. Ensure composer dependencies are installed or bundled PHPMailer files exist.');
            return false;
        }

        $mailer = new $mailerClass(true);
        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->Port = $smtpPort;
        $mailer->SMTPAuth = $smtpUser !== '';

        if ($mailer->SMTPAuth) {
            $mailer->Username = $smtpUser;
            $mailer->Password = $smtpPass;
        }

        $mailer->SMTPSecure = $smtpPort === 465 ? 'ssl' : 'tls';

        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($from, $fromName);
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $emailBody;
        $mailer->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));

        return $mailer->send();
    } catch (Throwable $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}

function passwordResetEmailConfigured(): bool
{
    $config = require __DIR__ . '/../core/config.php';
    $smtp = $config['smtp'] ?? [];

    $smtpHost = trim((string) ($smtp['host'] ?? ''));
    $smtpUser = trim((string) ($smtp['user'] ?? ''));
    $smtpPass = trim((string) ($smtp['pass'] ?? ''));

    return $smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '';
}

function buildEmailTemplate(string $subject, string $content, string $appName, string $baseUrl): string
{
    $safeSubject = emailHtml($subject);
    $safeAppName = emailHtml($appName);
    $safeBaseUrl = emailHtml($baseUrl);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$safeSubject}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Comfortaa', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #0f172a;
            background: linear-gradient(180deg, #fcfefd 0%, #f4f8f7 100%);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 32px auto;
            background: linear-gradient(145deg, rgba(244,255,238,0.29), rgba(212,245,229,0.18)), #fff;
            border: 1px solid rgba(16,185,129,0.28);
            border-radius: 1rem;
            box-shadow: 0 10px 22px rgba(15,23,42,0.05);
            overflow: hidden;
            backdrop-filter: blur(8px);
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.02em;
            text-shadow: 0 0 14px rgba(16,185,129,0.22);
            font-family: 'Comfortaa', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            text-transform: lowercase;
        }
        .content {
            padding: 30px 20px;
        }
        .content p, .content ul, .content li {
            color: #0f172a;
            font-size: 16px;
            margin: 0 0 15px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #10b981;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px 0;
            box-shadow: 0 2px 8px rgba(16,185,129,0.12);
            transition: background 0.2s;
        }
        .button:hover {
            background: #059669;
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .footer a {
            color: #10b981;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            .container { margin: 0.5rem; border-radius: 0.5rem; }
            .header, .content, .footer { padding: 16px 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>involve</h1>
        </div>
        <div class="content">
            {$content}
        </div>
        <div class="footer">
            <p>This is an automated message from involve.</p>
            <p><a href="{$safeBaseUrl}">Visit Dashboard</a></p>
        </div>
    </div>
</body>
</html>
HTML;
}

function sendActivationEmail(string $email, string $name, string $activationToken): bool
{
    $config = require __DIR__ . '/../core/config.php';
    $baseUrl = appBaseUrl($config);
    $verifyUrl = $baseUrl . '/index.php?page=verify_email&token=' . urlencode($activationToken);
    $safeName = emailHtml($name);
    $safeVerifyUrl = emailHtml($verifyUrl);
    
    $subject = 'Be Involved - Verify Your Email';
    $htmlBody = <<<HTML
<p>Hello {$safeName},</p>
<p>Thank you for joining INVOLVE. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
<p style="text-align: center;">
    <a href="{$safeVerifyUrl}" class="button">Be Involved</a>
</p>
<p>Or copy and paste this link into your browser:</p>
<p style="word-break: break-all; color: #6b7280; font-size: 14px;">{$safeVerifyUrl}</p>
<p>This verification link will expire in 24 hours for security reasons.</p>
<p>If you did not create this account, please ignore this email.</p>
<p>Best regards,<br>INVOLVE Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}

function sendPasswordResetEmail(string $email, string $name, string $resetToken): bool
{
    $config = require __DIR__ . '/../core/config.php';
    $baseUrl = appBaseUrl($config);
    $resetUrl = $baseUrl . '/index.php?page=reset_password&token=' . urlencode($resetToken);
    $safeName = emailHtml($name);
    $safeResetUrl = emailHtml($resetUrl);
    
    $subject = 'Password Reset Request';
    $htmlBody = <<<HTML
<p>Hello {$safeName},</p>
<p>We received a request to reset your password. Click the button below to choose a new password:</p>
<p style="text-align: center;">
    <a href="{$safeResetUrl}" class="button">Reset Password</a>
</p>
<p>Or copy and paste this link into your browser:</p>
<p style="word-break: break-all; color: #6b7280; font-size: 14px;">{$safeResetUrl}</p>
<p>This password reset link will expire in 1 hour for security reasons.</p>
<p><strong>If you did not request a password reset, please ignore this email.</strong> Your password will remain unchanged.</p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}

function sendPasswordChangedNotification(string $email, string $name, string $ipAddress): bool
{
    $timestamp = emailHtml(date('F j, Y \a\t g:i A'));
    $safeName = emailHtml($name);
    $safeIpAddress = emailHtml($ipAddress);
    $subject = 'Your Password Has Been Changed';
    $htmlBody = <<<HTML
<p>Hello {$safeName},</p>
<p>This is a confirmation that your password was successfully changed.</p>
<p><strong>Change Details:</strong></p>
<ul>
    <li>Date: {$timestamp}</li>
    <li>IP Address: {$safeIpAddress}</li>
</ul>
<p>If you did not make this change, please contact the administrator immediately and secure your account.</p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}

function sendLoginNotification(string $email, string $name, string $ipAddress, string $userAgent): bool
{
    $subject = 'New Login to Your Account';
    $timestamp = emailHtml(date('F j, Y \a\t g:i A'));
    $safeName = emailHtml($name);
    $safeIpAddress = emailHtml($ipAddress);
    $safeUserAgent = emailHtml($userAgent);
    
    $htmlBody = <<<HTML
<p>Hello {$safeName},</p>
<p>We detected a new login to your account:</p>
<p><strong>Login Details:</strong></p>
<ul>
    <li>Date: {$timestamp}</li>
    <li>IP Address: {$safeIpAddress}</li>
    <li>Device: {$safeUserAgent}</li>
</ul>
<p>If this was you, you can safely ignore this email.</p>
<p><strong>If you did not perform this login, please secure your account immediately by changing your password.</strong></p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}

function sendSecurityAlert(string $email, string $name, string $eventType, string $details): bool
{
    $subject = 'Security Alert for Your Account';
    $timestamp = emailHtml(date('F j, Y \a\t g:i A'));
    $safeName = emailHtml($name);
    $safeEventType = emailHtml($eventType);
    $safeDetails = nl2br(emailHtml($details));
    
    $htmlBody = <<<HTML
<p>Hello {$safeName},</p>
<p>We detected the following security event on your account:</p>
<p><strong>Event:</strong> {$safeEventType}</p>
<p><strong>Date:</strong> {$timestamp}</p>
<p><strong>Details:</strong></p>
<p>{$safeDetails}</p>
<p>If you recognize this activity, you can safely ignore this email.</p>
<p><strong>If you did not perform this action, please secure your account immediately.</strong></p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}

function sendAccountStatusNotification(string $email, string $name, string $status, string $reason = ''): bool
{
    $statusMessages = [
        'suspended' => 'Your account has been temporarily suspended.',
        'banned' => 'Your account has been permanently banned.',
        'active' => 'Your account has been reactivated.',
    ];
    
    $message = $statusMessages[$status] ?? 'Your account status has been updated.';
    $subject = 'Account Status Update';
    $safeName = emailHtml($name);
    $safeMessage = emailHtml($message);
    
    $htmlBody = <<<HTML
<p>Hello {$safeName},</p>
<p>{$safeMessage}</p>
HTML;
    
    if ($reason !== '') {
        $htmlBody .= '<p><strong>Reason:</strong> ' . emailHtml($reason) . '</p>';
    }
    
    $htmlBody .= <<<HTML
<p>If you believe this action was taken in error, please contact the administrator.</p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}
