<?php

declare(strict_types=1);

/**
 * Email Service Module
 * Handles sending emails for authentication and security notifications
 */

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
        if (!class_exists($mailerClass)) {
            error_log('PHPMailer class not found. Ensure composer dependencies are installed.');
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
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .content { padding: 30px 20px; }
        .content p { margin: 0 0 15px; }
        .button { display: inline-block; padding: 12px 24px; background: #10b981; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 10px 0; }
        .button:hover { background: #059669; }
        .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 14px; color: #6b7280; border-top: 1px solid #e5e7eb; }
        .footer a { color: #10b981; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$appName}</h1>
        </div>
        <div class="content">
            {$content}
        </div>
        <div class="footer">
            <p>This is an automated message from {$appName}.</p>
            <p><a href="{$baseUrl}">Visit Dashboard</a></p>
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
    
    $subject = 'Verify Your Email Address';
    $htmlBody = <<<HTML
<p>Hello {$name},</p>
<p>Thank you for registering with our Student Organization Management System. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
<p style="text-align: center;">
    <a href="{$verifyUrl}" class="button">Verify Email Address</a>
</p>
<p>Or copy and paste this link into your browser:</p>
<p style="word-break: break-all; color: #6b7280; font-size: 14px;">{$verifyUrl}</p>
<p>This verification link will expire in 24 hours for security reasons.</p>
<p>If you did not create this account, please ignore this email.</p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}

function sendPasswordResetEmail(string $email, string $name, string $resetToken): bool
{
    $config = require __DIR__ . '/../core/config.php';
    $baseUrl = appBaseUrl($config);
    $resetUrl = $baseUrl . '/index.php?page=reset_password&token=' . urlencode($resetToken);
    
    $subject = 'Password Reset Request';
    $htmlBody = <<<HTML
<p>Hello {$name},</p>
<p>We received a request to reset your password. Click the button below to choose a new password:</p>
<p style="text-align: center;">
    <a href="{$resetUrl}" class="button">Reset Password</a>
</p>
<p>Or copy and paste this link into your browser:</p>
<p style="word-break: break-all; color: #6b7280; font-size: 14px;">{$resetUrl}</p>
<p>This password reset link will expire in 1 hour for security reasons.</p>
<p><strong>If you did not request a password reset, please ignore this email.</strong> Your password will remain unchanged.</p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}

function sendPasswordChangedNotification(string $email, string $name, string $ipAddress): bool
{
    $timestamp = date('F j, Y \a\t g:i A');
    $subject = 'Your Password Has Been Changed';
    $htmlBody = <<<HTML
<p>Hello {$name},</p>
<p>This is a confirmation that your password was successfully changed.</p>
<p><strong>Change Details:</strong></p>
<ul>
    <li>Date: {$timestamp}</li>
    <li>IP Address: {$ipAddress}</li>
</ul>
<p>If you did not make this change, please contact the administrator immediately and secure your account.</p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}

function sendLoginNotification(string $email, string $name, string $ipAddress, string $userAgent): bool
{
    $subject = 'New Login to Your Account';
    $timestamp = date('F j, Y \a\t g:i A');
    
    $htmlBody = <<<HTML
<p>Hello {$name},</p>
<p>We detected a new login to your account:</p>
<p><strong>Login Details:</strong></p>
<ul>
    <li>Date: {$timestamp}</li>
    <li>IP Address: {$ipAddress}</li>
    <li>Device: {$userAgent}</li>
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
    $timestamp = date('F j, Y \a\t g:i A');
    
    $htmlBody = <<<HTML
<p>Hello {$name},</p>
<p>We detected the following security event on your account:</p>
<p><strong>Event:</strong> {$eventType}</p>
<p><strong>Date:</strong> {$timestamp}</p>
<p><strong>Details:</strong></p>
<p>{$details}</p>
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
    
    $htmlBody = <<<HTML
<p>Hello {$name},</p>
<p>{$message}</p>
HTML;
    
    if ($reason !== '') {
        $htmlBody .= "<p><strong>Reason:</strong> {$reason}</p>";
    }
    
    $htmlBody .= <<<HTML
<p>If you believe this action was taken in error, please contact the administrator.</p>
<p>Best regards,<br>Student Organization Management Team</p>
HTML;
    
    return sendEmail($email, $subject, $htmlBody);
}
