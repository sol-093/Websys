<?php

declare(strict_types=1);

use Involve\Repositories\AuthRepository;
use Involve\Services\AuthService;
use Involve\Support\ApiRequest;
use Involve\Support\JsonResponse;

require dirname(__DIR__) . '/bootstrap.php';

ApiRequest::requireMethod('POST');
apiRequireCsrf();

$body = ApiRequest::jsonBody();
$email = trim((string) ($_POST['email'] ?? $body['email'] ?? ''));
$password = (string) ($_POST['password'] ?? $body['password'] ?? '');
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$loginRateKey = 'login:' . strtolower($email) . ':' . $clientIp;

if (rateLimitIsBlocked($loginRateKey, 5, 300)) {
    JsonResponse::error('Too many login attempts. Please wait a few minutes and try again.', 429);
}

$auth = new AuthService(new AuthRepository($db));
$result = $auth->authenticate($email, $password);

if (!$result['ok']) {
    $error = (string) ($result['error'] ?? 'invalid_credentials');
    if ($error === 'invalid_credentials') {
        rateLimitIncrement($loginRateKey, 300);
        JsonResponse::error('Invalid credentials.', 401);
    }

    if ($error === 'suspended') {
        JsonResponse::error('Your account has been suspended.', 403);
    }

    if ($error === 'banned') {
        JsonResponse::error('Your account has been banned.', 403);
    }

    if ($error === 'email_unverified') {
        $_SESSION['pending_verification_email'] = (string) ($result['pending_verification_email'] ?? $email);
        JsonResponse::error('Please verify your email address before logging in.', 403, [
            'code' => 'email_unverified',
        ]);
    }

    JsonResponse::error('Login failed.', 400);
}

$candidate = $result['user'] ?? null;
if (!is_array($candidate)) {
    rateLimitIncrement($loginRateKey, 300);
    JsonResponse::error('Invalid credentials.', 401);
}

rateLimitClear($loginRateKey);
$_SESSION['user_id'] = (int) $candidate['id'];
session_regenerate_id(true);
if ((string) ($candidate['role'] ?? '') === 'student' && (int) ($candidate['onboarding_done'] ?? 0) === 0) {
    $_SESSION['show_onboarding'] = true;
}

queueLoginUpdatesPopup((int) $candidate['id']);
auditLog((int) $candidate['id'], 'auth.login_success', 'user', (int) $candidate['id'], 'API email login succeeded');

unset($candidate['password_hash']);

JsonResponse::ok([
    'user' => $candidate,
]);
