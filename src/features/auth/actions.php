<?php

declare(strict_types=1);

function handleGoogleLoginPage(array $config): void
{
    if (!googleOauthEnabled($config)) {
        setFlash('error', 'Google login is not configured yet.');
        redirect('?page=login');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $google = $config['google_oauth'];
    $redirectUri = appBaseUrl($config) . '/index.php?page=google_callback';
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $google['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ]);

    redirect($authUrl);
}

function handleGoogleCallbackPage(PDO $db, array $config): void
{
    if (!googleOauthEnabled($config)) {
        setFlash('error', 'Google login is not configured yet.');
        redirect('?page=login');
    }

    $expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
    $state = (string) ($_GET['state'] ?? '');
    unset($_SESSION['google_oauth_state']);

    if ($expectedState === '' || $state === '' || !hash_equals($expectedState, $state)) {
        setFlash('error', 'Invalid Google login state. Please try again.');
        redirect('?page=login');
    }

    if (!empty($_GET['error'])) {
        setFlash('error', 'Google login was cancelled.');
        redirect('?page=login');
    }

    $code = (string) ($_GET['code'] ?? '');
    if ($code === '') {
        setFlash('error', 'Google login failed: missing code.');
        redirect('?page=login');
    }

    $google = $config['google_oauth'];
    $redirectUri = appBaseUrl($config) . '/index.php?page=google_callback';

    $token = fetchJson('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $google['client_id'],
        'client_secret' => $google['client_secret'],
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]);

    $accessToken = (string) ($token['access_token'] ?? '');
    if ($accessToken === '') {
        setFlash('error', 'Google login failed while getting access token.');
        redirect('?page=login');
    }

    $profile = fetchJson('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . urlencode($accessToken));
    $email = trim((string) ($profile['email'] ?? ''));
    $name = trim((string) ($profile['name'] ?? 'Google User'));
    $emailVerified = (bool) ($profile['email_verified'] ?? false);

    if ($email === '' || !$emailVerified) {
        setFlash('error', 'Google account email is not available or not verified.');
        redirect('?page=login');
    }

    $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $insert = $db->prepare('INSERT INTO users (name, email, password_hash, role, institute, program, year_level, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $name,
            $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'student',
            null,
            null,
            null,
            null,
        ]);
        $userId = (int) $db->lastInsertId();
        $userName = $name;
    } else {
        $userId = (int) $existing['id'];
        $userName = (string) $existing['name'];
    }

    $_SESSION['user_id'] = $userId;
    session_regenerate_id(true);
    queueLoginUpdatesPopup($userId);
    auditLog($userId, 'auth.google_login_success', 'user', $userId, 'Google OAuth login succeeded');
    setFlash('success', 'Welcome, ' . $userName . '!');
    redirect('?page=dashboard');
}

function handleRegisterAction(PDO $db): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $program = trim((string) ($_POST['program'] ?? ''));
    $section = trim((string) ($_POST['section'] ?? ''));
    $yearLevel = null;
    $privacyConsent = (string) ($_POST['privacy_consent'] ?? '') === '1';
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $registerRateKey = 'register:' . strtolower($email) . ':' . $clientIp;
    $programOptions = getProgramOptions();
    $institute = getInstituteForProgram($program);

    if (rateLimitIsBlocked($registerRateKey, 5, 300)) {
        setFlash('error', 'Too many registration attempts. Please wait a few minutes and try again.');
        redirect('?page=register');
    }

    if ($name === '' || $email === '' || $password === '' || $program === '' || $section === '') {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Please fill all registration fields.');
        redirect('?page=register');
    }

    if (!in_array($program, $programOptions, true) || $institute === null) {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Please select a valid program.');
        redirect('?page=register');
    }

    if (!preg_match('/^[A-Za-z0-9\- ]{1,40}$/', $section)) {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Please enter a valid year and section.');
        redirect('?page=register');
    }

    if (preg_match('/\b([1-4])(?:st|nd|rd|th)?\b/i', $section, $matches)) {
        $yearLevel = (int) $matches[1];
    }

    if (!$privacyConsent) {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'You must agree to the Data Privacy Consent before registering.');
        redirect('?page=register');
    }

    $passwordStrengthError = validatePasswordStrength($password);
    if ($passwordStrengthError !== null) {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', $passwordStrengthError);
        redirect('?page=register');
    }

    if ($password !== $confirmPassword) {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Passwords do not match.');
        redirect('?page=register');
    }

    try {
        $activationToken = bin2hex(random_bytes(32));
        $activationTokenHash = hash('sha256', $activationToken);
        $activationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, institute, program, year_level, section, email_verified, activation_token, activation_expires, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $name, 
            $email, 
            password_hash($password, PASSWORD_DEFAULT), 
            'student', 
            $institute, 
            $program,
            $yearLevel,
            $section,
            0,
            $activationTokenHash,
            $activationExpires,
            'active'
        ]);
        $newUserId = (int) $db->lastInsertId();
        rateLimitClear($registerRateKey);
        
        $emailSent = sendActivationEmail($email, $name, $activationToken);
        if (!$emailSent) {
            error_log('Verification email failed for new user id ' . $newUserId . ' (' . $email . ').');
        }
        
        auditLog($newUserId, 'auth.register_success', 'user', $newUserId, $emailSent ? 'Student registration completed, verification email sent' : 'Student registration completed, verification email failed');
        setFlash(
            $emailSent ? 'success' : 'error',
            $emailSent
                ? 'Registration successful! Please check your email to verify your account before logging in.'
                : 'Registration successful, but the verification email could not be sent. Please try resending it from the login page or contact the administrator.'
        );
        redirect('?page=login');
    } catch (Throwable $e) {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Email already exists.');
        redirect('?page=register');
    }
}

function handleLoginAction(PDO $db): void
{
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $loginRateKey = 'login:' . strtolower($email) . ':' . $clientIp;

    if (rateLimitIsBlocked($loginRateKey, 5, 300)) {
        setFlash('error', 'Too many login attempts. Please wait a few minutes and try again.');
        redirect('?page=login');
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $candidate = $stmt->fetch();

    if (!$candidate || !password_verify($password, $candidate['password_hash'])) {
        rateLimitIncrement($loginRateKey, 300);
        setFlash('error', 'Invalid credentials.');
        redirect('?page=login');
    }

    $accountStatus = (string) ($candidate['account_status'] ?? 'active');
    if ($accountStatus === 'suspended') {
        setFlash('error', 'Your account has been suspended. Please contact the administrator.');
        redirect('?page=login');
    }
    
    if ($accountStatus === 'banned') {
        setFlash('error', 'Your account has been banned. Please contact the administrator.');
        redirect('?page=login');
    }

    $emailVerified = (int) ($candidate['email_verified'] ?? 1);
    if ($emailVerified === 0) {
        setFlash('error', 'Please verify your email address before logging in. Check your email for the verification link.');
        $_SESSION['pending_verification_email'] = $email;
        redirect('?page=login&show_resend=1');
    }

    rateLimitClear($loginRateKey);
    $_SESSION['user_id'] = (int) $candidate['id'];
    session_regenerate_id(true);
    if ((string) ($candidate['role'] ?? '') === 'student' && (int) ($candidate['onboarding_done'] ?? 0) === 0) {
        $_SESSION['show_onboarding'] = true;
    }
    queueLoginUpdatesPopup((int) $candidate['id']);
    auditLog((int) $candidate['id'], 'auth.login_success', 'user', (int) $candidate['id'], 'Email login succeeded');
    setFlash('success', 'Welcome back, ' . $candidate['name'] . '!');
    redirect('?page=dashboard');
}

function handleCompleteOnboardingAction(PDO $db, array $user): void
{
    requireLogin();

    header('Content-Type: application/json; charset=UTF-8');

    if (($user['role'] ?? '') !== 'student') {
        http_response_code(403);
        echo json_encode(['ok' => false], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt = $db->prepare('UPDATE users SET onboarding_done = 1 WHERE id = ?');
    $stmt->execute([(int) $user['id']]);

    $_SESSION['show_onboarding'] = false;

    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
    exit;
}

function handleRestartOnboardingAction(PDO $db, array $user): void
{
    requireLogin();

    if (($user['role'] ?? '') !== 'student') {
        setFlash('error', 'Only student accounts can replay onboarding.');
        redirect('?page=' . urlencode((string) ($_GET['page'] ?? 'dashboard')));
    }

    $stmt = $db->prepare('UPDATE users SET onboarding_done = 0 WHERE id = ?');
    $stmt->execute([(int) $user['id']]);

    $_SESSION['show_onboarding'] = true;
    setFlash('success', 'Onboarding tour restarted.');

    redirect('?page=' . urlencode((string) ($_GET['page'] ?? 'dashboard')));
}

function handleVerifyEmailAction(PDO $db, ?string $providedToken = null): array
{
    $token = trim((string) ($providedToken ?? ($_GET['token'] ?? '')));

    if ($token === '') {
        return ['success' => false, 'error' => 'Invalid verification link.'];
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $db->prepare('SELECT id, name, email, activation_expires, activation_token FROM users WHERE activation_token = ? AND email_verified = 0 LIMIT 1');
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();

    $storedToken = (string) ($user['activation_token'] ?? '');
    if (!$user || $storedToken === '' || !hash_equals($tokenHash, $storedToken)) {
        return ['success' => false, 'error' => 'Invalid or expired verification link.'];
    }

    $expiresAt = (string) ($user['activation_expires'] ?? '');
    if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
        return ['success' => false, 'error' => 'Verification link has expired. Please request a new one.'];
    }

    $updateStmt = $db->prepare('UPDATE users SET email_verified = 1, email_verified_at = CURRENT_TIMESTAMP, activation_token = NULL, activation_expires = NULL WHERE id = ?');
    $updateStmt->execute([(int) $user['id']]);

    auditLog((int) $user['id'], 'auth.email_verified', 'user', (int) $user['id'], 'Email address verified successfully');

    return ['success' => true];
}

function handleResendVerificationAction(PDO $db): void
{
    $email = trim((string) ($_POST['email'] ?? ''));
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $resendRateKey = 'resend_verification:' . strtolower($email) . ':' . $clientIp;
    
    if (rateLimitIsBlocked($resendRateKey, 3, 3600)) {
        setFlash('error', 'Too many verification emails sent. Please try again later.');
        redirect('?page=login');
    }
    
    if ($email === '') {
        setFlash('error', 'Please provide your email address.');
        redirect('?page=login');
    }
    
    $stmt = $db->prepare('SELECT id, name, email, email_verified FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        if ((int) ($user['email_verified'] ?? 1) === 0) {
            // Unverified: allow resend
            $activationToken = bin2hex(random_bytes(32));
            $activationTokenHash = hash('sha256', $activationToken);
            $activationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $updateStmt = $db->prepare('UPDATE users SET activation_token = ?, activation_expires = ? WHERE id = ?');
            $updateStmt->execute([$activationTokenHash, $activationExpires, (int) $user['id']]);

            $emailSent = sendActivationEmail($user['email'], $user['name'], $activationToken);
            rateLimitIncrement($resendRateKey, 3600);

            auditLog((int) $user['id'], 'auth.resend_verification', 'user', (int) $user['id'], $emailSent ? 'Verification email resent' : 'Verification email resend failed');
            if (!$emailSent) {
                error_log('Verification email resend failed for user id ' . (int) $user['id'] . ' (' . $user['email'] . ').');
                setFlash('error', 'We found your account, but the verification email could not be sent. Please contact the administrator.');
                redirect('?page=login');
            }
            setFlash('success', 'A verification email has been sent to your address.');
            redirect('?page=login');
        } else {
            // Already verified
            setFlash('info', 'This account is already verified. Please log in.');
            redirect('?page=login');
        }
    }
    // No such account: generic message
    setFlash('success', 'If your account exists and is unverified, a verification email has been sent.');
    redirect('?page=login');
}

function handleForgotPasswordAction(PDO $db): void
{
    $email = trim((string) ($_POST['email'] ?? ''));
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $forgotRateKey = 'forgot_password:' . strtolower($email) . ':' . $clientIp;

    if (!passwordResetEmailConfigured()) {
        error_log('Password reset requested but SMTP is not fully configured. Set SMTP_HOST, SMTP_USER, and SMTP_PASS.');
        setFlash('error', 'Password reset is temporarily unavailable. Please contact administrator.');
        redirect('?page=forgot_password');
    }
    
    // Rate limit: 3 requests per hour
    if (rateLimitIsBlocked($forgotRateKey, 3, 3600)) {
        setFlash('error', 'Too many password reset requests. Please try again later.');
        redirect('?page=forgot_password');
    }
    
    if ($email === '') {
        setFlash('error', 'Please provide your email address.');
        redirect('?page=forgot_password');
    }
    
    // Find user by email
    $stmt = $db->prepare('SELECT id, name, email, account_status, password_reset_at FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && in_array($user['account_status'] ?? 'active', ['active', 'pending'], true)) {
        $lastPasswordResetAt = (string) ($user['password_reset_at'] ?? '');
        if ($lastPasswordResetAt !== '' && strtotime($lastPasswordResetAt . ' +7 days') > time()) {
            rateLimitIncrement($forgotRateKey, 3600);
            auditLog((int) $user['id'], 'auth.forgot_password_cooldown', 'user', (int) $user['id'], 'Password reset request blocked by 7-day cooldown');
            setFlash('success', 'If your account exists and is eligible, a password reset link has been sent to your email.');
            redirect('?page=login');
        }

        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetTokenHash = hash('sha256', $resetToken);
        $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update user with reset token
        $updateStmt = $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?');
        $updateStmt->execute([$resetTokenHash, $resetExpires, (int) $user['id']]);
        
        // Send password reset email
        $emailSent = sendPasswordResetEmail($user['email'], $user['name'], $resetToken);
        rateLimitIncrement($forgotRateKey, 3600);
        
        auditLog((int) $user['id'], 'auth.forgot_password', 'user', (int) $user['id'], $emailSent ? 'Password reset requested' : 'Password reset requested, email failed');
        if (!$emailSent) {
            error_log('Password reset email failed for user id ' . (int) $user['id'] . ' (' . $user['email'] . ').');
        }
    }
    
    // Always show generic success message (prevent email enumeration)
    setFlash('success', 'If your account exists and is eligible, a password reset link has been sent to your email.');
    redirect('?page=login');
}

function handleResetPasswordAction(PDO $db): void
{
    $token = trim((string) ($_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    
    if ($token === '') {
        setFlash('error', 'Invalid password reset link.');
        redirect('?page=login');
    }
    
    if ($password === '' || $confirmPassword === '') {
        setFlash('error', 'Please fill in all fields.');
        redirect('?page=reset_password&token=' . urlencode($token));
    }
    
    if ($password !== $confirmPassword) {
        setFlash('error', 'Passwords do not match.');
        redirect('?page=reset_password&token=' . urlencode($token));
    }
    
    if (strlen($password) < 8) {
        setFlash('error', 'Password must be at least 8 characters long.');
        redirect('?page=reset_password&token=' . urlencode($token));
    }

    $passwordStrengthError = validatePasswordStrength($password);
    if ($passwordStrengthError !== null) {
        setFlash('error', $passwordStrengthError);
        redirect('?page=reset_password&token=' . urlencode($token));
    }
    
    $tokenHash = hash('sha256', $token);

    // Find user by reset token hash
    $stmt = $db->prepare('SELECT id, email, name, reset_expires, reset_token, password_reset_at FROM users WHERE reset_token = ? LIMIT 1');
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();

    $storedToken = (string) ($user['reset_token'] ?? '');
    if (!$user || $storedToken === '' || !hash_equals($tokenHash, $storedToken)) {
        setFlash('error', 'Invalid or expired password reset link.');
        redirect('?page=login');
    }
    
    // Check if token is expired
    $resetExpires = $user['reset_expires'] ?? '';
    if ($resetExpires === '' || strtotime($resetExpires) < time()) {
        $clearExpiredStmt = $db->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?');
        $clearExpiredStmt->execute([(int) $user['id']]);
        setFlash('error', 'This password reset link has expired. Please request a new one.');
        redirect('?page=forgot_password');
    }

    $lastPasswordResetAt = (string) ($user['password_reset_at'] ?? '');
    if ($lastPasswordResetAt !== '' && strtotime($lastPasswordResetAt . ' +7 days') > time()) {
        $clearCooldownStmt = $db->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?');
        $clearCooldownStmt->execute([(int) $user['id']]);
        auditLog((int) $user['id'], 'auth.password_reset_cooldown', 'user', (int) $user['id'], 'Password reset blocked by 7-day cooldown');
        setFlash('error', 'Your password was recently reset. Please wait 7 days before requesting another forgot-password reset.');
        redirect('?page=login');
    }
    
    // Hash new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password and clear reset token
    $updateStmt = $db->prepare('
        UPDATE users 
        SET password_hash = ?, 
            reset_token = NULL, 
            reset_expires = NULL,
            password_changed_at = ?,
            password_reset_at = ?
        WHERE id = ? AND reset_token = ?
    ');
    $passwordChangedAt = date('Y-m-d H:i:s');
    $updateStmt->execute([$hashedPassword, $passwordChangedAt, $passwordChangedAt, (int) $user['id'], $tokenHash]);

    if ($updateStmt->rowCount() !== 1) {
        setFlash('error', 'This password reset link has already been used. Please request a new one.');
        redirect('?page=forgot_password');
    }
    
    // Log password change in password_history table
    $historyStmt = $db->prepare('INSERT INTO password_history (user_id, password_hash, created_at) VALUES (?, ?, ?)');
    $historyStmt->execute([(int) $user['id'], $hashedPassword, date('Y-m-d H:i:s')]);
    
    // Send password changed notification
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    sendPasswordChangedNotification($user['email'], $user['name'], $clientIp);
    
    // Audit log
    auditLog((int) $user['id'], 'auth.password_reset', 'user', (int) $user['id'], 'Password reset completed');
    
    // Clear all active sessions for this user (force re-login)
    $deleteStmt = $db->prepare('DELETE FROM user_sessions WHERE user_id = ?');
    $deleteStmt->execute([(int) $user['id']]);
    
    setFlash('success', 'Your password has been reset successfully. Please log in with your new password.');
    redirect('?page=login');
}

function handleChangePasswordAction(PDO $db, array $user): void
{
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        setFlash('error', 'Please fill in all fields.');
        redirect('?page=profile');
    }
    
    if ($newPassword !== $confirmPassword) {
        setFlash('error', 'New passwords do not match.');
        redirect('?page=profile');
    }
    
    if (strlen($newPassword) < 8) {
        setFlash('error', 'New password must be at least 8 characters long.');
        redirect('?page=profile');
    }
    
    // Verify current password
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $user['id']]);
    $userData = $stmt->fetch();
    
    if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
        setFlash('error', 'Current password is incorrect.');
        redirect('?page=profile');
    }
    
    // Check if new password is same as current
    if (password_verify($newPassword, $userData['password_hash'])) {
        setFlash('error', 'New password must be different from current password.');
        redirect('?page=profile');
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $db->prepare('UPDATE users SET password_hash = ?, password_changed_at = ? WHERE id = ?');
    $updateStmt->execute([$hashedPassword, date('Y-m-d H:i:s'), (int) $user['id']]);
    
    // Log password change in password_history table
    $historyStmt = $db->prepare('INSERT INTO password_history (user_id, password_hash, created_at) VALUES (?, ?, ?)');
    $historyStmt->execute([(int) $user['id'], $hashedPassword, date('Y-m-d H:i:s')]);
    
    // Send password changed notification
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    sendPasswordChangedNotification($user['email'], $user['name'], $clientIp);
    
    // Audit log
    auditLog((int) $user['id'], 'auth.password_change', 'user', (int) $user['id'], 'Password changed by user');
    
    setFlash('success', 'Your password has been changed successfully.');
    redirect('?page=profile');
}

function handleUpdateProfileAction(PDO $db, array $user): void
{
    $config = require dirname(__DIR__, 2) . '/core/config.php';
    $email = trim((string) ($_POST['email'] ?? ''));
    $name = (string) ($user['name'] ?? '');
    $program = trim((string) ($user['program'] ?? ''));
    $section = trim((string) ($user['section'] ?? ''));
    $institute = trim((string) ($user['institute'] ?? ''));
    $yearLevel = isset($user['year_level']) && $user['year_level'] !== '' ? (int) $user['year_level'] : null;
    $profilePicturePath = trim((string) ($user['profile_picture_path'] ?? ''));
    $profilePictureCropX = (float) ($_POST['profile_picture_crop_x'] ?? ($user['profile_picture_crop_x'] ?? 50));
    $profilePictureCropY = (float) ($_POST['profile_picture_crop_y'] ?? ($user['profile_picture_crop_y'] ?? 50));
    $profilePictureZoom = (float) ($_POST['profile_picture_zoom'] ?? ($user['profile_picture_zoom'] ?? 1));

    if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
        $uploadedProfilePicture = handleProfileImageUpload($_FILES['profile_picture'], (string) $config['upload_dir'], 'user_');
        if ($uploadedProfilePicture === false) {
            redirect('?page=profile');
        }

        if ($profilePicturePath !== '' && $profilePicturePath !== $uploadedProfilePicture) {
            deleteStoredUpload($profilePicturePath);
        }
        $profilePicturePath = $uploadedProfilePicture;
    }
    
    if ($email === '') {
        setFlash('error', 'Email is required.');
        redirect('?page=profile');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Please provide a valid email address.');
        redirect('?page=profile');
    }
    
    // Check if email is being changed
    $emailChanged = strtolower($email) !== strtolower($user['email']);
    
    if ($emailChanged) {
        // Check if new email is already taken
        $stmt = $db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ? LIMIT 1');
        $stmt->execute([$email, (int) $user['id']]);
        if ($stmt->fetch()) {
            setFlash('error', 'This email is already in use by another account.');
            redirect('?page=profile');
        }
        
        // Generate new activation token for email verification
        $activationToken = bin2hex(random_bytes(32));
        $activationTokenHash = hash('sha256', $activationToken);
        $activationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update user with new email and mark as unverified
        $updateStmt = $db->prepare('
            UPDATE users 
            SET name = ?, 
                email = ?, 
                institute = ?,
                program = ?,
                section = ?,
                year_level = ?,
                profile_picture_path = ?,
                profile_picture_crop_x = ?,
                profile_picture_crop_y = ?,
                profile_picture_zoom = ?,
                email_verified = 0, 
                activation_token = ?, 
                activation_expires = ?
            WHERE id = ?
        ');
        $updateStmt->execute([$name, $email, $institute, $program, $section, $yearLevel, $profilePicturePath !== '' ? $profilePicturePath : null, $profilePictureCropX, $profilePictureCropY, $profilePictureZoom, $activationTokenHash, $activationExpires, (int) $user['id']]);

        $removedMemberships = removeIneligibleOrganizationMemberships($db, (int) $user['id'], $institute, $program);
        if ($removedMemberships !== []) {
            queueMembershipRemovalNotification((int) $user['id'], $removedMemberships, 'program/institute update');
        }
        
        // Send verification email to new address
        $emailSent = sendActivationEmail($email, $name, $activationToken);
        if (!$emailSent) {
            error_log('Verification email failed after profile email change for user id ' . (int) $user['id'] . ' (' . $email . ').');
        }
        
        // Audit log
        auditLog((int) $user['id'], 'profile.email_change', 'user', (int) $user['id'], 'Email changed from ' . $user['email'] . ' to ' . $email);
        
        setFlash(
            $emailSent ? 'success' : 'error',
            $emailSent
                ? 'Profile updated. Please verify your new email address. A verification link has been sent to ' . htmlspecialchars($email) . '.'
                : 'Profile updated, but the verification email could not be sent. Please use resend verification from the login page or contact the administrator.'
        );
        
        // Log out user since email changed and needs verification
        session_destroy();
        redirect('?page=login');
    } else {
        // Update editable profile fields
        $updateStmt = $db->prepare('UPDATE users SET email = ?, institute = ?, program = ?, section = ?, year_level = ?, profile_picture_path = ?, profile_picture_crop_x = ?, profile_picture_crop_y = ?, profile_picture_zoom = ? WHERE id = ?');
        $updateStmt->execute([$email, $institute, $program, $section, $yearLevel, $profilePicturePath !== '' ? $profilePicturePath : null, $profilePictureCropX, $profilePictureCropY, $profilePictureZoom, (int) $user['id']]);

        $removedMemberships = removeIneligibleOrganizationMemberships($db, (int) $user['id'], $institute, $program);
        if ($removedMemberships !== []) {
            queueMembershipRemovalNotification((int) $user['id'], $removedMemberships, 'program/institute update');
        }
        
        // Audit log
        auditLog((int) $user['id'], 'profile.update', 'user', (int) $user['id'], 'Profile updated');
        
        setFlash('success', 'Profile updated successfully.');
        redirect('?page=profile');
    }
}
