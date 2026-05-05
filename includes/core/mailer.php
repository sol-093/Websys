<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - LOW-LEVEL MAILER
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. PHPMailer Class Loading
 * 2. sendSystemEmail()
 * 3. Activation and Password Reset Senders
 *
 * EDIT GUIDE:
 * - Edit this file for low-level mail transport behavior.
 * - Edit includes/lib/email.php for email templates/content.
 * ================================================
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include the PHPMailer library files
require_once __DIR__ . '/../lib/PHPMailer-master/includes/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer-master/includes/PHPMailer.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../lib/PHPMailer-master/includes/SMTP.php';

function sendSystemEmail(string $recipientEmail, string $recipientName, string $subject, string $htmlBody): bool
{
    $config = require __DIR__ . '/config.php';
    $smtp = $config['smtp'] ?? [];

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();                                            
        $mail->Host       = $smtp['host'] ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = $smtp['user'];
        $mail->Password   = $smtp['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = $smtp['port'] ?: 587;                                    

        // Sender info
        $mail->setFrom($smtp['from'], $smtp['from_name']);
        
        // Recipient info
        $mail->addAddress($recipientEmail, $recipientName);     

        // Content
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);                                  
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody); // Plain text fallback

        return $mail->send();
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Sends the initial account activation email.
 */
function sendActivationEmail(string $email, string $name, string $token): bool
{
    $config = require __DIR__ . '/config.php';
    $baseUrl = rtrim($config['base_url'] ?: 'http://localhost/websys', '/');
    $verifyUrl = "{$baseUrl}/index.php?page=verify_email&token=" . urlencode($token);

    $subject = "Verify your account - " . $config['app_name'];
    $htmlBody = "
        <h1>Welcome, " . e($name) . "!</h1>
        <p>Thank you for registering. Please click the link below to verify your email address and activate your account:</p>
        <p><a href='" . e($verifyUrl) . "'>Verify Email Address</a></p>
        <p>If you did not create an account, no further action is required.</p>
        <p>This link will expire in 24 hours.</p>
    ";

    return sendSystemEmail($email, $name, $subject, $htmlBody);
}

/**
 * Checks if the SMTP settings are actually filled out in the config.
 */
function passwordResetEmailConfigured(): bool
{
    $config = require __DIR__ . '/config.php';
    return !empty($config['smtp']['host']) && !empty($config['smtp']['user']);
}

/**
 * Sends the password reset email.
 */
function sendPasswordResetEmail(string $email, string $name, string $token): bool
{
    $config = require __DIR__ . '/config.php';
    $baseUrl = rtrim($config['base_url'] ?: 'http://localhost/websys', '/');
    $resetUrl = "{$baseUrl}/index.php?page=reset_password&token=" . urlencode($token);

    $subject = "Password Reset Request - " . $config['app_name'];
    $htmlBody = "
        <h1>Hello, " . e($name) . "!</h1>
        <p>You have requested to reset your password. Please click the link below to reset it:</p>
        <p><a href='" . e($resetUrl) . "'>Reset Your Password</a></p>
        <p>If you did not request a password reset, please ignore this email.</p>
        <p>This link will expire in 1 hour.</p>
    ";

    return sendSystemEmail($email, $name, $subject, $htmlBody);
}
