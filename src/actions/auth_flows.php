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
        $insert = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $insert->execute([
            $name,
            $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'student',
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
    $institute = trim((string) ($_POST['institute'] ?? ''));
    $privacyConsent = (string) ($_POST['privacy_consent'] ?? '') === '1';
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $registerRateKey = 'register:' . strtolower($email) . ':' . $clientIp;
    $instituteOptions = getInstituteOptions();

    if (rateLimitIsBlocked($registerRateKey, 5, 300)) {
        setFlash('error', 'Too many registration attempts. Please wait a few minutes and try again.');
        redirect('?page=register');
    }

    if ($name === '' || $email === '' || $password === '' || $institute === '') {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Please fill all registration fields.');
        redirect('?page=register');
    }

    if (!in_array($institute, $instituteOptions, true)) {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Please select a valid institute.');
        redirect('?page=register');
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

    try {
        $activationToken = bin2hex(random_bytes(32));
        $activationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, institute, program, email_verified, activation_token, activation_expires, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $name, 
            $email, 
            password_hash($password, PASSWORD_DEFAULT), 
            'student', 
            $institute, 
            null,
            0,
            $activationToken,
            $activationExpires,
            'active'
        ]);
        $newUserId = (int) $db->lastInsertId();
        rateLimitClear($registerRateKey);
        
        sendActivationEmail($email, $name, $activationToken);
        
        auditLog($newUserId, 'auth.register_success', 'user', $newUserId, 'Student registration completed, verification email sent');
        setFlash('success', 'Registration successful! Please check your email to verify your account before logging in.');
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
    queueLoginUpdatesPopup((int) $candidate['id']);
    auditLog((int) $candidate['id'], 'auth.login_success', 'user', (int) $candidate['id'], 'Email login succeeded');
    setFlash('success', 'Welcome back, ' . $candidate['name'] . '!');
    redirect('?page=dashboard');
}

function handleVerifyEmailAction(PDO $db): void
{
    $token = trim((string) ($_GET['token'] ?? ''));
    
    if ($token === '') {
        setFlash('error', 'Invalid verification link.');
        redirect('?page=login');
    }
    
    $stmt = $db->prepare('SELECT id, name, email, activation_expires FROM users WHERE activation_token = ? AND email_verified = 0 LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlash('error', 'Invalid or expired verification link.');
        redirect('?page=login');
    }
    
    $expiresAt = (string) ($user['activation_expires'] ?? '');
    if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
        setFlash('error', 'Verification link has expired. Please request a new one.');
        redirect('?page=login&show_resend=1');
    }
    
    $updateStmt = $db->prepare('UPDATE users SET email_verified = 1, email_verified_at = CURRENT_TIMESTAMP, activation_token = NULL, activation_expires = NULL WHERE id = ?');
    $updateStmt->execute([(int) $user['id']]);
    
    auditLog((int) $user['id'], 'auth.email_verified', 'user', (int) $user['id'], 'Email address verified successfully');
    
    setFlash('success', 'Email verified successfully! You can now login.');
    redirect('?page=login');
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
    
    if ($user && (int) ($user['email_verified'] ?? 1) === 0) {
        $activationToken = bin2hex(random_bytes(32));
        $activationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $updateStmt = $db->prepare('UPDATE users SET activation_token = ?, activation_expires = ? WHERE id = ?');
        $updateStmt->execute([$activationToken, $activationExpires, (int) $user['id']]);
        
        sendActivationEmail($user['email'], $user['name'], $activationToken);
        rateLimitIncrement($resendRateKey, 3600);
        
        auditLog((int) $user['id'], 'auth.resend_verification', 'user', (int) $user['id'], 'Verification email resent');
    }
    
    setFlash('success', 'If your account exists and is unverified, a verification email has been sent.');
    redirect('?page=login');
}

function handleForgotPasswordAction(PDO $db): void
{
    $email = trim((string) ($_POST['email'] ?? ''));
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $forgotRateKey = 'forgot_password:' . strtolower($email) . ':' . $clientIp;
    
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
    $stmt = $db->prepare('SELECT id, name, email, account_status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && in_array($user['account_status'] ?? 'active', ['active', 'pending'], true)) {
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update user with reset token
        $updateStmt = $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?');
        $updateStmt->execute([$resetToken, $resetExpires, (int) $user['id']]);
        
        // Send password reset email
        sendPasswordResetEmail($user['email'], $user['name'], $resetToken);
        rateLimitIncrement($forgotRateKey, 3600);
        
        auditLog((int) $user['id'], 'auth.forgot_password', 'user', (int) $user['id'], 'Password reset requested');
    }
    
    // Always show generic success message (prevent email enumeration)
    setFlash('success', 'If your account exists, a password reset link has been sent to your email.');
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
    
    // Find user by reset token
    $stmt = $db->prepare('SELECT id, email, name, reset_expires FROM users WHERE reset_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlash('error', 'Invalid or expired password reset link.');
        redirect('?page=login');
    }
    
    // Check if token is expired
    $resetExpires = $user['reset_expires'] ?? '';
    if ($resetExpires === '' || strtotime($resetExpires) < time()) {
        setFlash('error', 'This password reset link has expired. Please request a new one.');
        redirect('?page=forgot_password');
    }
    
    // Hash new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password and clear reset token
    $updateStmt = $db->prepare('
        UPDATE users 
        SET password_hash = ?, 
            reset_token = NULL, 
            reset_expires = NULL,
            password_changed_at = ?
        WHERE id = ?
    ');
    $updateStmt->execute([$hashedPassword, date('Y-m-d H:i:s'), (int) $user['id']]);
    
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
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    
    if ($name === '' || $email === '') {
        setFlash('error', 'Name and email are required.');
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
        $activationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update user with new email and mark as unverified
        $updateStmt = $db->prepare('
            UPDATE users 
            SET name = ?, 
                email = ?, 
                email_verified = 0, 
                activation_token = ?, 
                activation_expires = ?
            WHERE id = ?
        ');
        $updateStmt->execute([$name, $email, $activationToken, $activationExpires, (int) $user['id']]);
        
        // Send verification email to new address
        sendActivationEmail($email, $name, $activationToken);
        
        // Audit log
        auditLog((int) $user['id'], 'profile.email_change', 'user', (int) $user['id'], 'Email changed from ' . $user['email'] . ' to ' . $email);
        
        setFlash('success', 'Profile updated. Please verify your new email address. A verification link has been sent to ' . htmlspecialchars($email) . '.');
        
        // Log out user since email changed and needs verification
        session_destroy();
        redirect('?page=login');
    } else {
        // Just update name
        $updateStmt = $db->prepare('UPDATE users SET name = ? WHERE id = ?');
        $updateStmt->execute([$name, (int) $user['id']]);
        
        // Audit log
        auditLog((int) $user['id'], 'profile.update', 'user', (int) $user['id'], 'Profile updated');
        
        setFlash('success', 'Profile updated successfully.');
        redirect('?page=profile');
    }
}
