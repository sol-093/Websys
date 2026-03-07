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
    $program = trim((string) ($_POST['program'] ?? ''));
    $privacyConsent = (string) ($_POST['privacy_consent'] ?? '') === '1';
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $registerRateKey = 'register:' . strtolower($email) . ':' . $clientIp;
    $programInstituteMap = getProgramInstituteMap();

    if (rateLimitIsBlocked($registerRateKey, 5, 300)) {
        setFlash('error', 'Too many registration attempts. Please wait a few minutes and try again.');
        redirect('?page=register');
    }

    if ($name === '' || $email === '' || $password === '' || $program === '') {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Please fill all registration fields.');
        redirect('?page=register');
    }

    if (!isset($programInstituteMap[$program])) {
        rateLimitIncrement($registerRateKey, 300);
        setFlash('error', 'Please select a valid program.');
        redirect('?page=register');
    }

    $institute = (string) $programInstituteMap[$program];

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
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, institute, program) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'student', $institute, $program]);
        $newUserId = (int) $db->lastInsertId();
        rateLimitClear($registerRateKey);
        auditLog($newUserId, 'auth.register_success', 'user', $newUserId, 'Student registration completed');
        setFlash('success', 'Registration successful. You can now login.');
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

    rateLimitClear($loginRateKey);
    $_SESSION['user_id'] = (int) $candidate['id'];
    session_regenerate_id(true);
    queueLoginUpdatesPopup((int) $candidate['id']);
    auditLog((int) $candidate['id'], 'auth.login_success', 'user', (int) $candidate['id'], 'Email login succeeded');
    setFlash('success', 'Welcome back, ' . $candidate['name'] . '!');
    redirect('?page=dashboard');
}
