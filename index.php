<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/src/db.php';
require __DIR__ . '/src/helpers.php';
require __DIR__ . '/src/auth.php';
require __DIR__ . '/src/layout.php';

$db = db();
$config = require __DIR__ . '/src/config.php';

function getOwnedOrganizations(int $ownerId): array
{
    $stmt = db()->prepare('SELECT * FROM organizations WHERE owner_id = ? ORDER BY name ASC');
    $stmt->execute([$ownerId]);

    return $stmt->fetchAll() ?: [];
}

function getOwnedOrganizationById(int $ownerId, int $organizationId): ?array
{
    $stmt = db()->prepare('SELECT * FROM organizations WHERE owner_id = ? AND id = ? LIMIT 1');
    $stmt->execute([$ownerId, $organizationId]);
    $org = $stmt->fetch();

    return $org ?: null;
}

function ensureUploadDir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function appBaseUrl(array $config): string
{
    $configured = trim((string) ($config['base_url'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $scriptDir = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');

    return $scheme . '://' . $host . $scriptDir;
}

function googleOauthEnabled(array $config): bool
{
    $google = $config['google_oauth'] ?? [];
    return !empty($google['client_id']) && !empty($google['client_secret']);
}

function fetchJson(string $url, ?array $postFields = null): ?array
{
    $options = [
        'http' => [
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ];

    if ($postFields !== null) {
        $options['http']['method'] = 'POST';
        $options['http']['header'] = "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['http']['content'] = http_build_query($postFields);
    }

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

function collectUserRequestUpdates(int $userId): array
{
    $updates = [];
    $db = db();

    $joinStmt = $db->prepare("SELECT o.name AS organization_name, r.status, COALESCE(r.updated_at, r.created_at) AS event_at
        FROM organization_join_requests r
        JOIN organizations o ON o.id = r.organization_id
        WHERE r.user_id = ? AND r.status IN ('approved', 'declined')
        ORDER BY event_at DESC
        LIMIT 5");
    $joinStmt->execute([$userId]);
    foreach ($joinStmt->fetchAll() as $row) {
        $updates[] = [
            'kind' => 'Join Request',
            'status' => (string) $row['status'],
            'message' => 'Organization: ' . (string) $row['organization_name'],
            'event_at' => (string) $row['event_at'],
        ];
    }

    $financeStmt = $db->prepare("SELECT o.name AS organization_name, r.status, r.action_type, COALESCE(r.updated_at, r.created_at) AS event_at
        FROM transaction_change_requests r
        JOIN organizations o ON o.id = r.organization_id
        WHERE r.requested_by = ? AND r.status IN ('approved', 'rejected')
        ORDER BY event_at DESC
        LIMIT 5");
    $financeStmt->execute([$userId]);
    foreach ($financeStmt->fetchAll() as $row) {
        $updates[] = [
            'kind' => 'Finance ' . ucfirst((string) $row['action_type']) . ' Request',
            'status' => (string) $row['status'],
            'message' => 'Organization: ' . (string) $row['organization_name'],
            'event_at' => (string) $row['event_at'],
        ];
    }

    $assignmentStmt = $db->prepare("SELECT o.name AS organization_name, a.status, COALESCE(a.updated_at, a.created_at) AS event_at
        FROM owner_assignments a
        JOIN organizations o ON o.id = a.organization_id
        WHERE a.student_id = ? AND a.status IN ('accepted', 'declined')
        ORDER BY event_at DESC
        LIMIT 5");
    $assignmentStmt->execute([$userId]);
    foreach ($assignmentStmt->fetchAll() as $row) {
        $updates[] = [
            'kind' => 'Organization Assignment',
            'status' => (string) $row['status'],
            'message' => 'Organization: ' . (string) $row['organization_name'],
            'event_at' => (string) $row['event_at'],
        ];
    }

    usort($updates, static function (array $a, array $b): int {
        return strcmp((string) $b['event_at'], (string) $a['event_at']);
    });

    $cutoffTs = time() - (7 * 24 * 60 * 60);
    $updates = array_values(array_filter($updates, static function (array $item) use ($cutoffTs): bool {
        $eventAt = (string) ($item['event_at'] ?? '');
        $eventTs = strtotime($eventAt);
        return $eventTs !== false && $eventTs >= $cutoffTs;
    }));

    return array_slice($updates, 0, 8);
}

function buildUpdatesMarker(array $updates): string
{
    return sha1(json_encode($updates));
}

function queueLoginUpdatesPopup(int $userId): void
{
    $updates = collectUserRequestUpdates($userId);
    if (count($updates) === 0) {
        $_SESSION['login_updates_popup'] = [];
        return;
    }

    $marker = buildUpdatesMarker($updates);
    $cookieName = 'websys_updates_seen_' . $userId;
    $seenMarker = (string) ($_COOKIE[$cookieName] ?? '');

    if ($seenMarker !== '' && hash_equals($seenMarker, $marker)) {
        $_SESSION['login_updates_popup'] = [];
        return;
    }

    $_SESSION['login_updates_popup'] = $updates;
    setcookie($cookieName, $marker, [
        'expires' => time() + (60 * 60 * 24 * 30),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function paginateArray(array $items, string $queryKey, int $perPage = 10): array
{
    $totalItems = count($items);
    $totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
    $page = max(1, (int) ($_GET[$queryKey] ?? 1));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage);

    return [
        'items' => $slice,
        'page' => $page,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'query_key' => $queryKey,
    ];
}

function renderPagination(array $pagination): void
{
    $totalPages = (int) ($pagination['total_pages'] ?? 1);
    if ($totalPages <= 1) {
        return;
    }

    $currentPage = (int) ($pagination['page'] ?? 1);
    $queryKey = (string) ($pagination['query_key'] ?? 'p');
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    $isDashboardPage = (string) ($_GET['page'] ?? '') === 'dashboard';
    $preserveScroll = $isDashboardPage && str_starts_with($queryKey, 'pg_dash_');
    $preserveScrollAttr = $preserveScroll ? ' data-preserve-scroll="1"' : '';

    $buildUrl = static function (int $targetPage) use ($queryKey): string {
        $params = $_GET;
        $params[$queryKey] = $targetPage;
        return '?' . http_build_query($params);
    };

    ?>
    <div class="mt-3 flex items-center gap-2 text-xs">
        <a class="px-2 py-1 rounded border <?= $currentPage <= 1 ? 'opacity-40 pointer-events-none' : '' ?>" href="<?= e($buildUrl(max(1, $currentPage - 1))) ?>"<?= $preserveScrollAttr ?>><span class="icon-label"><?= uiIcon('prev', 'ui-icon ui-icon-sm') ?><span>Prev</span></span></a>
        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <a class="px-2 py-1 rounded border <?= $p === $currentPage ? 'bg-indigo-700 text-white border-indigo-700' : '' ?>" href="<?= e($buildUrl($p)) ?>"<?= $preserveScrollAttr ?>><?= $p ?></a>
        <?php endfor; ?>
        <a class="px-2 py-1 rounded border <?= $currentPage >= $totalPages ? 'opacity-40 pointer-events-none' : '' ?>" href="<?= e($buildUrl(min($totalPages, $currentPage + 1))) ?>"<?= $preserveScrollAttr ?>><span class="icon-label"><span>Next</span><?= uiIcon('next', 'ui-icon ui-icon-sm') ?></span></a>
        <span class="text-gray-500 ml-1">Page <?= $currentPage ?> of <?= $totalPages ?></span>
    </div>
    <?php
}

function purgeExpiredAnnouncements(PDO $db, int $days = 30): void
{
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
    $stmt = $db->prepare('DELETE FROM announcements WHERE created_at < ?');
    $stmt->execute([$cutoff]);
}

function getInstituteOptions(): array
{
    return [
        'Institute of Computing and Digital Innovations',
        'Institute of Nursing',
        'Institute of Engineering',
        'Institute of Midwifery',
        'Institute of Science and Mathematics',
        'Institute of Behavioral Science',
    ];
}

function getProgramInstituteMap(): array
{
    return [
        'BS Information Systems' => 'Institute of Computing and Digital Innovations',
        'BS Data Science' => 'Institute of Computing and Digital Innovations',
        'BS Computer Science' => 'Institute of Computing and Digital Innovations',
        'BS Civil Engineering' => 'Institute of Engineering',
        'BS Psychology' => 'Institute of Behavioral Science',
        'BS Nursing' => 'Institute of Nursing',
        'BS Midwifery' => 'Institute of Midwifery',
        'BS Social Work' => 'Institute of Science and Mathematics',
    ];
}

function getOrgCategoryOptions(): array
{
    return [
        'collegewide' => 'Collegewide (Open to all students)',
        'institutewide' => 'Institutewide (Per institute)',
        'program_based' => 'Program-based',
    ];
}

function normalizeAcademicValue(?string $value): string
{
    return strtolower(trim((string) $value));
}

function deriveInstituteFromProgram(string $program): ?string
{
    $map = getProgramInstituteMap();
    return $map[$program] ?? null;
}

function sortOrganizationsByCategory(array $organizations): array
{
    $order = ['collegewide' => 1, 'institutewide' => 2, 'program_based' => 3];
    usort($organizations, static function (array $a, array $b) use ($order): int {
        $aCategory = (string) ($a['org_category'] ?? 'collegewide');
        $bCategory = (string) ($b['org_category'] ?? 'collegewide');
        $aRank = $order[$aCategory] ?? 99;
        $bRank = $order[$bCategory] ?? 99;

        if ($aRank !== $bRank) {
            return $aRank <=> $bRank;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return $organizations;
}

function applyOrganizationVisibilityForUser(array $organizations, array $user): array
{
    $role = (string) ($user['role'] ?? 'student');
    if ($role !== 'student') {
        return sortOrganizationsByCategory($organizations);
    }

    $userInstitute = normalizeAcademicValue((string) ($user['institute'] ?? ''));
    $userProgram = normalizeAcademicValue((string) ($user['program'] ?? ''));

    $filtered = array_values(array_filter($organizations, static function (array $org) use ($userInstitute, $userProgram): bool {
        $category = (string) ($org['org_category'] ?? 'collegewide');
        if ($category === 'collegewide') {
            return true;
        }

        if ($category === 'institutewide') {
            return $userInstitute !== '' && normalizeAcademicValue((string) ($org['target_institute'] ?? '')) === $userInstitute;
        }

        if ($category === 'program_based') {
            return $userProgram !== '' && normalizeAcademicValue((string) ($org['target_program'] ?? '')) === $userProgram;
        }

        return true;
    }));

    return sortOrganizationsByCategory($filtered);
}

function getOrganizationVisibilityLabel(array $org): string
{
    $category = (string) ($org['org_category'] ?? 'collegewide');
    if ($category === 'institutewide') {
        $institute = trim((string) ($org['target_institute'] ?? ''));
        return 'Institutewide' . ($institute !== '' ? ' • ' . $institute : '');
    }

    if ($category === 'program_based') {
        $program = trim((string) ($org['target_program'] ?? ''));
        return 'Program-based' . ($program !== '' ? ' • ' . $program : '');
    }

    return 'Collegewide';
}

function canUserJoinOrganization(array $org, array $user): bool
{
    $category = (string) ($org['org_category'] ?? 'collegewide');
    if ($category === 'collegewide') {
        return true;
    }

    $userInstitute = normalizeAcademicValue((string) ($user['institute'] ?? ''));
    $userProgram = normalizeAcademicValue((string) ($user['program'] ?? ''));

    if ($category === 'institutewide') {
        $targetInstitute = normalizeAcademicValue((string) ($org['target_institute'] ?? ''));
        return $targetInstitute !== '' && $userInstitute !== '' && $targetInstitute === $userInstitute;
    }

    if ($category === 'program_based') {
        $targetProgram = normalizeAcademicValue((string) ($org['target_program'] ?? ''));
        return $targetProgram !== '' && $userProgram !== '' && $targetProgram === $userProgram;
    }

    return false;
}

function getJoinRestrictionLabel(array $org): string
{
    $category = (string) ($org['org_category'] ?? 'collegewide');
    if ($category === 'institutewide') {
        return 'Restricted (Institute mismatch)';
    }

    if ($category === 'program_based') {
        return 'Restricted (Program mismatch)';
    }

    return 'Restricted';
}

$user = currentUser();
$page = $_GET['page'] ?? ($user ? 'dashboard' : 'home');

if ($page === 'google_login') {
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

if ($page === 'google_callback') {
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

if (isPost()) {
    if (!verifyCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
        setFlash('error', 'Invalid form session. Please try again.');
        $fallbackPage = (string) ($_GET['page'] ?? ($user ? 'dashboard' : 'login'));
        redirect('?page=' . urlencode($fallbackPage));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
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

    if ($action === 'login') {
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

    requireLogin();
    $user = currentUser();

    if ($action === 'create_org') {
        requireRole(['admin']);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $orgCategory = (string) ($_POST['org_category'] ?? 'collegewide');
        $targetInstitute = trim((string) ($_POST['target_institute'] ?? ''));
        $targetProgram = trim((string) ($_POST['target_program'] ?? ''));
        $categoryOptions = getOrgCategoryOptions();
        $programInstituteMap = getProgramInstituteMap();

        if ($name === '') {
            setFlash('error', 'Organization name is required.');
            redirect('?page=admin_orgs');
        }

        if (!isset($categoryOptions[$orgCategory])) {
            setFlash('error', 'Invalid organization category.');
            redirect('?page=admin_orgs');
        }

        if ($orgCategory === 'institutewide') {
            if (!in_array($targetInstitute, getInstituteOptions(), true)) {
                setFlash('error', 'Please select a valid institute for institutewide organizations.');
                redirect('?page=admin_orgs');
            }
            $targetProgram = '';
        } elseif ($orgCategory === 'program_based') {
            if (!isset($programInstituteMap[$targetProgram])) {
                setFlash('error', 'Please select a valid program for program-based organizations.');
                redirect('?page=admin_orgs');
            }
            $targetInstitute = (string) $programInstituteMap[$targetProgram];
        } else {
            $targetInstitute = '';
            $targetProgram = '';
        }

        try {
            $stmt = $db->prepare('INSERT INTO organizations (name, description, org_category, target_institute, target_program) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $description, $orgCategory, $targetInstitute !== '' ? $targetInstitute : null, $targetProgram !== '' ? $targetProgram : null]);
            auditLog((int) $user['id'], 'organization.create', 'organization', (int) $db->lastInsertId(), 'Created organization: ' . $name);
            setFlash('success', 'Organization created.');
        } catch (Throwable $e) {
            setFlash('error', 'Organization name already exists.');
        }

        redirect('?page=admin_orgs');
    }

    if ($action === 'update_org_admin') {
        requireRole(['admin']);
        $orgId = (int) ($_POST['org_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $orgCategory = (string) ($_POST['org_category'] ?? 'collegewide');
        $targetInstitute = trim((string) ($_POST['target_institute'] ?? ''));
        $targetProgram = trim((string) ($_POST['target_program'] ?? ''));
        $categoryOptions = getOrgCategoryOptions();
        $programInstituteMap = getProgramInstituteMap();

        if (!isset($categoryOptions[$orgCategory])) {
            setFlash('error', 'Invalid organization category.');
            redirect('?page=admin_orgs');
        }

        if ($orgCategory === 'institutewide') {
            if (!in_array($targetInstitute, getInstituteOptions(), true)) {
                setFlash('error', 'Please select a valid institute for institutewide organizations.');
                redirect('?page=admin_orgs');
            }
            $targetProgram = '';
        } elseif ($orgCategory === 'program_based') {
            if (!isset($programInstituteMap[$targetProgram])) {
                setFlash('error', 'Please select a valid program for program-based organizations.');
                redirect('?page=admin_orgs');
            }
            $targetInstitute = (string) $programInstituteMap[$targetProgram];
        } else {
            $targetInstitute = '';
            $targetProgram = '';
        }

        $stmt = $db->prepare('UPDATE organizations SET name = ?, description = ?, org_category = ?, target_institute = ?, target_program = ? WHERE id = ?');
        $stmt->execute([$name, $description, $orgCategory, $targetInstitute !== '' ? $targetInstitute : null, $targetProgram !== '' ? $targetProgram : null, $orgId]);
        auditLog((int) $user['id'], 'organization.update', 'organization', $orgId, 'Updated organization details');
        setFlash('success', 'Organization updated.');
        redirect('?page=admin_orgs');
    }

    if ($action === 'delete_org') {
        requireRole(['admin']);
        $orgId = (int) ($_POST['org_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
        auditLog((int) $user['id'], 'organization.delete', 'organization', $orgId, 'Deleted organization');
        setFlash('success', 'Organization deleted.');
        redirect('?page=admin_orgs');
    }

    if ($action === 'assign_owner') {
        requireRole(['admin']);
        $orgId = (int) ($_POST['org_id'] ?? 0);
        $ownerId = (int) ($_POST['owner_id'] ?? 0);

        $db->beginTransaction();
        try {
            if ($ownerId <= 0) {
                $stmt = $db->prepare('UPDATE organizations SET owner_id = NULL WHERE id = ?');
                $stmt->execute([$orgId]);
                $stmt = $db->prepare('DELETE FROM owner_assignments WHERE organization_id = ?');
                $stmt->execute([$orgId]);
            } else {
                $stmt = $db->prepare('UPDATE organizations SET owner_id = NULL WHERE id = ?');
                $stmt->execute([$orgId]);

                $stmt = $db->prepare('DELETE FROM owner_assignments WHERE organization_id = ?');
                $stmt->execute([$orgId]);

                $stmt = $db->prepare('INSERT INTO owner_assignments (organization_id, student_id, status) VALUES (?, ?, ?)');
                $stmt->execute([$orgId, $ownerId, 'pending']);
            }

            $db->commit();
            auditLog((int) $user['id'], 'organization.assign_owner', 'organization', $orgId, $ownerId > 0 ? 'Assignment set to pending' : 'Owner assignment cleared');
            setFlash('success', $ownerId > 0 ? 'Owner assignment sent. Student must accept first.' : 'Owner assignment cleared.');
        } catch (Throwable $e) {
            $db->rollBack();
            setFlash('error', 'Could not assign owner.');
        }

        redirect('?page=admin_orgs');
    }

    if ($action === 'process_tx_change_request') {
        requireRole(['admin']);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? 'reject');
        $adminNote = trim((string) ($_POST['admin_note'] ?? ''));

        if (!in_array($decision, ['approve', 'reject'], true)) {
            setFlash('error', 'Invalid request decision.');
            redirect('?page=admin_requests');
        }

        $stmt = $db->prepare('SELECT * FROM transaction_change_requests WHERE id = ? AND status = ? LIMIT 1');
        $stmt->execute([$requestId, 'pending']);
        $request = $stmt->fetch();

        if (!$request) {
            setFlash('error', 'Request is no longer pending.');
            redirect('?page=admin_requests');
        }

        $db->beginTransaction();
        try {
            if ($decision === 'approve') {
                if ((string) $request['action_type'] === 'update') {
                    $stmt = $db->prepare('UPDATE financial_transactions SET type = ?, amount = ?, description = ?, transaction_date = ? WHERE id = ? AND organization_id = ?');
                    $stmt->execute([
                        (string) $request['proposed_type'],
                        (float) $request['proposed_amount'],
                        (string) $request['proposed_description'],
                        (string) $request['proposed_transaction_date'],
                        (int) $request['transaction_id'],
                        (int) $request['organization_id'],
                    ]);
                } else {
                    $stmt = $db->prepare('DELETE FROM financial_transactions WHERE id = ? AND organization_id = ?');
                    $stmt->execute([(int) $request['transaction_id'], (int) $request['organization_id']]);
                }

                $stmt = $db->prepare('UPDATE transaction_change_requests SET status = ?, admin_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['approved', $adminNote, $requestId]);
                auditLog((int) $user['id'], 'finance.request_approve', 'transaction_change_request', $requestId, 'Approved transaction change request');
                setFlash('success', 'Transaction change request approved.');
            } else {
                $stmt = $db->prepare('UPDATE transaction_change_requests SET status = ?, admin_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['rejected', $adminNote, $requestId]);
                auditLog((int) $user['id'], 'finance.request_reject', 'transaction_change_request', $requestId, 'Rejected transaction change request');
                setFlash('success', 'Transaction change request rejected.');
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            setFlash('error', 'Unable to process transaction change request.');
        }

        redirect('?page=admin_requests');
    }

    if ($action === 'respond_owner_assignment') {
        requireRole(['student', 'owner']);
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? 'decline');

        if (!in_array($decision, ['accept', 'decline'], true)) {
            setFlash('error', 'Invalid assignment response.');
            redirect('?page=dashboard');
        }

        $stmt = $db->prepare('SELECT * FROM owner_assignments WHERE id = ? AND student_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$assignmentId, (int) $user['id'], 'pending']);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            setFlash('error', 'Assignment is no longer available.');
            redirect('?page=dashboard');
        }

        $db->beginTransaction();
        try {
            if ($decision === 'accept') {
                $stmt = $db->prepare('UPDATE owner_assignments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['accepted', $assignmentId]);

                $stmt = $db->prepare('UPDATE organizations SET owner_id = ? WHERE id = ?');
                $stmt->execute([(int) $user['id'], (int) $assignment['organization_id']]);

                $stmt = $db->prepare("UPDATE users SET role = 'owner' WHERE id = ? AND role = 'student'");
                $stmt->execute([(int) $user['id']]);

                auditLog((int) $user['id'], 'assignment.accept', 'owner_assignment', $assignmentId, 'Accepted organization owner assignment');

                setFlash('success', 'You accepted the owner assignment.');
            } else {
                $stmt = $db->prepare('UPDATE owner_assignments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['declined', $assignmentId]);

                auditLog((int) $user['id'], 'assignment.decline', 'owner_assignment', $assignmentId, 'Declined organization owner assignment');

                setFlash('success', 'You declined the owner assignment.');
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            setFlash('error', 'Unable to update assignment response.');
        }

        redirect('?page=dashboard');
    }

    if ($action === 'join_org') {
        requireRole(['student', 'owner']);
        $orgId = (int) ($_POST['org_id'] ?? 0);

        $orgStmt = $db->prepare('SELECT id, org_category, target_institute, target_program FROM organizations WHERE id = ? LIMIT 1');
        $orgStmt->execute([$orgId]);
        $org = $orgStmt->fetch();
        if (!$org) {
            setFlash('error', 'Organization not found.');
            redirect('?page=dashboard');
        }

        if (!canUserJoinOrganization($org, $user)) {
            setFlash('error', 'You are not eligible to join this organization based on your institute/program.');
            redirect('?page=dashboard');
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM organization_members WHERE organization_id = ? AND user_id = ?');
        $stmt->execute([$orgId, (int) $user['id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            setFlash('error', 'You are already a member of this organization.');
            redirect('?page=dashboard');
        }

        try {
            $stmt = $db->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status) VALUES (?, ?, ?)');
            $stmt->execute([$orgId, (int) $user['id'], 'pending']);
            auditLog((int) $user['id'], 'join_request.submit', 'organization', $orgId, 'Submitted join request');
            setFlash('success', 'Join request sent. Please wait for approval.');
        } catch (Throwable $e) {
            $stmt = $db->prepare('SELECT status FROM organization_join_requests WHERE organization_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$orgId, (int) $user['id']]);
            $existingStatus = (string) ($stmt->fetchColumn() ?: 'pending');
            if ($existingStatus === 'declined') {
                $stmt = $db->prepare('UPDATE organization_join_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE organization_id = ? AND user_id = ?');
                $stmt->execute(['pending', $orgId, (int) $user['id']]);
                auditLog((int) $user['id'], 'join_request.resubmit', 'organization', $orgId, 'Resubmitted join request');
                setFlash('success', 'Join request sent again.');
            } elseif ($existingStatus === 'approved') {
                setFlash('error', 'Your join request is already approved.');
            } else {
                setFlash('error', 'You already have a pending request for this organization.');
            }
        }
        redirect('?page=dashboard');
    }

    if ($action === 'respond_join_request') {
        requireRole(['owner']);
        $orgId = (int) ($_POST['org_id'] ?? 0);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? 'decline');

        $org = getOwnedOrganizationById((int) $user['id'], $orgId);
        if (!$org) {
            setFlash('error', 'You are not allowed to manage this organization.');
            redirect('?page=my_org');
        }

        if (!in_array($decision, ['approve', 'decline'], true)) {
            setFlash('error', 'Invalid request action.');
            redirect('?page=my_org&org_id=' . $orgId);
        }

        $stmt = $db->prepare('SELECT * FROM organization_join_requests WHERE id = ? AND organization_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$requestId, $orgId, 'pending']);
        $request = $stmt->fetch();

        if (!$request) {
            setFlash('error', 'Request is no longer pending.');
            redirect('?page=my_org&org_id=' . $orgId);
        }

        $db->beginTransaction();
        try {
            if ($decision === 'approve') {
                $stmt = $db->prepare('UPDATE organization_join_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['approved', $requestId]);

                $stmt = $db->prepare('INSERT INTO organization_members (organization_id, user_id) VALUES (?, ?)');
                $stmt->execute([$orgId, (int) $request['user_id']]);

                auditLog((int) $user['id'], 'join_request.approve', 'organization_join_request', $requestId, 'Approved join request');

                setFlash('success', 'Join request approved.');
            } else {
                $stmt = $db->prepare('UPDATE organization_join_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['declined', $requestId]);
                auditLog((int) $user['id'], 'join_request.decline', 'organization_join_request', $requestId, 'Declined join request');
                setFlash('success', 'Join request declined.');
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            setFlash('error', 'Unable to update join request.');
        }

        redirect('?page=my_org&org_id=' . $orgId);
    }

    if ($action === 'update_my_org') {
        requireRole(['owner']);
        $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
        $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
        if (!$org) {
            setFlash('error', 'No organization assigned to your account.');
            redirect('?page=dashboard');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($name === '') {
            setFlash('error', 'Organization name is required.');
            redirect('?page=my_org');
        }

        $stmt = $db->prepare('UPDATE organizations SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $description, (int) $org['id']]);
        setFlash('success', 'Organization details updated.');
        redirect('?page=my_org&org_id=' . (int) $org['id']);
    }

    if ($action === 'add_announcement') {
        requireRole(['owner']);
        $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
        $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
        if (!$org) {
            setFlash('error', 'No organization assigned.');
            redirect('?page=dashboard');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($title === '' || $content === '') {
            setFlash('error', 'Announcement title and content are required.');
            redirect('?page=my_org');
        }

        $stmt = $db->prepare('INSERT INTO announcements (organization_id, title, content) VALUES (?, ?, ?)');
        $stmt->execute([(int) $org['id'], $title, $content]);
        setFlash('success', 'Announcement posted.');
        redirect('?page=my_org&org_id=' . (int) $org['id']);
    }

    if ($action === 'delete_announcement') {
        requireRole(['owner']);
        $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
        $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
        $id = (int) ($_POST['announcement_id'] ?? 0);
        if ($org) {
            $stmt = $db->prepare('DELETE FROM announcements WHERE id = ? AND organization_id = ?');
            $stmt->execute([$id, (int) $org['id']]);
            setFlash('success', 'Announcement deleted.');
        }
        redirect('?page=my_org&org_id=' . (int) ($org['id'] ?? 0));
    }

    if ($action === 'pin_announcement_admin') {
        requireRole(['admin']);
        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        $returnPage = (string) ($_POST['return_page'] ?? 'announcements');
        if (!in_array($returnPage, ['announcements', 'dashboard'], true)) {
            $returnPage = 'announcements';
        }

        if ($announcementId <= 0) {
            setFlash('error', 'Invalid announcement selected.');
            redirect('?page=' . $returnPage);
        }

        $db->beginTransaction();
        try {
            $existsStmt = $db->prepare('SELECT id FROM announcements WHERE id = ? LIMIT 1');
            $existsStmt->execute([$announcementId]);
            if (!$existsStmt->fetch()) {
                throw new RuntimeException('Announcement not found.');
            }

            $db->exec('UPDATE announcements SET is_pinned = 0, pinned_at = NULL WHERE is_pinned = 1');
            $pinStmt = $db->prepare('UPDATE announcements SET is_pinned = 1, pinned_at = CURRENT_TIMESTAMP WHERE id = ?');
            $pinStmt->execute([$announcementId]);

            $db->commit();
            auditLog((int) $user['id'], 'announcement.pin', 'announcement', $announcementId, 'Pinned announcement as important');
            setFlash('success', 'Announcement pinned as important.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            setFlash('error', 'Unable to pin announcement.');
        }

        redirect('?page=' . $returnPage);
    }

    if ($action === 'unpin_announcement_admin') {
        requireRole(['admin']);
        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        $returnPage = (string) ($_POST['return_page'] ?? 'announcements');
        if (!in_array($returnPage, ['announcements', 'dashboard'], true)) {
            $returnPage = 'announcements';
        }

        if ($announcementId <= 0) {
            setFlash('error', 'Invalid announcement selected.');
            redirect('?page=' . $returnPage);
        }

        try {
            $stmt = $db->prepare('UPDATE announcements SET is_pinned = 0, pinned_at = NULL WHERE id = ?');
            $stmt->execute([$announcementId]);
            auditLog((int) $user['id'], 'announcement.unpin', 'announcement', $announcementId, 'Unpinned important announcement');
            setFlash('success', 'Announcement unpinned.');
        } catch (Throwable $e) {
            setFlash('error', 'Unable to unpin announcement.');
        }

        redirect('?page=' . $returnPage);
    }

    if ($action === 'add_transaction') {
        requireRole(['owner']);
        $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
        $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
        if (!$org) {
            setFlash('error', 'No organization assigned.');
            redirect('?page=dashboard');
        }

        $type = (string) ($_POST['type'] ?? 'expense');
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));
        $transactionDate = (string) ($_POST['transaction_date'] ?? date('Y-m-d'));
        $receiptPath = null;

        if (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $description === '') {
            setFlash('error', 'Please provide valid transaction values.');
            redirect('?page=my_org');
        }

        if (!empty($_FILES['receipt']['name'])) {
            $uploadResult = validateAndStoreReceiptUpload($_FILES['receipt'], (string) $config['upload_dir']);
            if (!empty($uploadResult['error'])) {
                setFlash('error', (string) $uploadResult['error']);
                redirect('?page=my_org&org_id=' . (int) $org['id']);
            }

            $receiptPath = (string) ($uploadResult['path'] ?? '') ?: null;
        }

        $stmt = $db->prepare('INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int) $org['id'], $type, $amount, $description, $transactionDate, $receiptPath]);

        setFlash('success', 'Transaction saved.');
        redirect('?page=my_org&org_id=' . (int) $org['id']);
    }

    if ($action === 'update_transaction') {
        requireRole(['owner']);
        $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
        $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
        if (!$org) {
            redirect('?page=dashboard');
        }

        $txId = (int) ($_POST['tx_id'] ?? 0);
        $type = (string) ($_POST['type'] ?? 'expense');
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));
        $transactionDate = (string) ($_POST['transaction_date'] ?? date('Y-m-d'));

        if (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $description === '') {
            setFlash('error', 'Invalid transaction update request.');
            redirect('?page=my_org&org_id=' . (int) $org['id']);
        }

        $existingStmt = $db->prepare('SELECT id FROM financial_transactions WHERE id = ? AND organization_id = ? LIMIT 1');
        $existingStmt->execute([$txId, (int) $org['id']]);
        if (!$existingStmt->fetch()) {
            setFlash('error', 'Transaction not found.');
            redirect('?page=my_org&org_id=' . (int) $org['id']);
        }

        $pendingCheck = $db->prepare('SELECT id FROM transaction_change_requests WHERE transaction_id = ? AND action_type = ? AND status = ? LIMIT 1');
        $pendingCheck->execute([$txId, 'update', 'pending']);
        if ($pendingCheck->fetch()) {
            setFlash('error', 'An update request for this transaction is already pending.');
            redirect('?page=my_org&org_id=' . (int) $org['id']);
        }

        $stmt = $db->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, proposed_type, proposed_amount, proposed_description, proposed_transaction_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $txId,
            (int) $org['id'],
            (int) $user['id'],
            'update',
            $type,
            $amount,
            $description,
            $transactionDate,
            'pending',
        ]);

        setFlash('success', 'Update request sent to admin for approval.');
        redirect('?page=my_org&org_id=' . (int) $org['id']);
    }

    if ($action === 'delete_transaction') {
        requireRole(['owner']);
        $selectedOrgId = (int) ($_POST['org_id'] ?? 0);
        $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
        $txId = (int) ($_POST['tx_id'] ?? 0);
        if ($org) {
            $existingStmt = $db->prepare('SELECT id FROM financial_transactions WHERE id = ? AND organization_id = ? LIMIT 1');
            $existingStmt->execute([$txId, (int) $org['id']]);
            if (!$existingStmt->fetch()) {
                setFlash('error', 'Transaction not found.');
                redirect('?page=my_org&org_id=' . (int) $org['id']);
            }

            $pendingCheck = $db->prepare('SELECT id FROM transaction_change_requests WHERE transaction_id = ? AND action_type = ? AND status = ? LIMIT 1');
            $pendingCheck->execute([$txId, 'delete', 'pending']);
            if ($pendingCheck->fetch()) {
                setFlash('error', 'A delete request for this transaction is already pending.');
                redirect('?page=my_org&org_id=' . (int) $org['id']);
            }

            $stmt = $db->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$txId, (int) $org['id'], (int) $user['id'], 'delete', 'pending']);

            setFlash('success', 'Delete request sent to admin for approval.');
        }
        redirect('?page=my_org&org_id=' . (int) ($org['id'] ?? 0));
    }
}

if ($page === 'logout') {
    $logoutUserId = (int) ($_SESSION['user_id'] ?? 0);
    if ($logoutUserId > 0) {
        auditLog($logoutUserId, 'auth.logout', 'user', $logoutUserId, 'User logged out');
    }
    session_destroy();
    session_start();
    setFlash('success', 'You are logged out.');
    redirect('?page=login');
}

if ($page === 'home') {
    $orgCount = (int) $db->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
    $memberCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','owner')")->fetchColumn();
    $announcementCount = (int) $db->query('SELECT COUNT(*) FROM announcements')->fetchColumn();

    renderHeader('Home');
    ?>
    <section class="grid lg:grid-cols-12 gap-4 lg:gap-5">
        <div class="glass lg:col-span-8 p-6 md:p-8">
            <div class="hero-kicker inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-300/20 text-xs font-semibold mb-4 border border-emerald-200/30">
                <?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?>
                Budget Transparency • Student Organizations
            </div>
            <h1 class="modern-title text-3xl md:text-6xl font-bold tracking-tight leading-tight">
                Modern Student Organization Management for Transparent Campus Finance
            </h1>
            <p class="mt-4 text-slate-600 max-w-2xl text-base md:text-lg">
                Manage organizations, publish announcements, and track income and expenses in one shared platform. Students can join groups and view verified reports for accountability.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <?php if ($user): ?>
                    <a href="?page=dashboard" class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition shadow-[0_0_22px_rgba(45,212,191,0.45)] hover:shadow-[0_0_30px_rgba(45,212,191,0.62)]"><span class="icon-label"><?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?><span>Open Dashboard</span></span></a>
                <?php else: ?>
                    <a href="?page=register" class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition shadow-[0_0_22px_rgba(45,212,191,0.45)] hover:shadow-[0_0_30px_rgba(45,212,191,0.62)]"><span class="icon-label"><?= uiIcon('register', 'ui-icon ui-icon-sm') ?><span>Get Started</span></span></a>
                    <a href="?page=login" class="border border-emerald-200/50 text-emerald-800 px-5 py-2.5 rounded-lg hover:bg-white/30"><span class="icon-label"><?= uiIcon('login', 'ui-icon ui-icon-sm') ?><span>Login</span></span></a>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass lg:col-span-4 p-6 snapshot-panel">
            <h2 class="text-lg font-semibold mb-4 snapshot-title icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Platform Snapshot</span></h2>
            <div class="space-y-3 text-sm snapshot-list">
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label icon-label"><?= uiIcon('orgs', 'ui-icon ui-icon-sm') ?><span>Organizations</span></span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $orgCount ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label icon-label"><?= uiIcon('students', 'ui-icon ui-icon-sm') ?><span>Students & Owners</span></span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $memberCount ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label icon-label"><?= uiIcon('announce', 'ui-icon ui-icon-sm') ?><span>Announcements</span></span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $announcementCount ?></span>
                </div>
            </div>
        </div>

        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('audit', 'ui-icon') ?><span>For Admin</span></h3>
            <p class="text-sm text-slate-600">Create organizations, assign one owner, and filter all student records.</p>
        </div>
        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>For Owners</span></h3>
            <p class="text-sm text-slate-600">Update organization profile, post announcements, and maintain income/expense logs.</p>
        </div>
        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('students', 'ui-icon') ?><span>For Students</span></h3>
            <p class="text-sm text-slate-600">Join organizations and monitor complete budget reports with transparency.</p>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}

if ($page === 'login') {
    renderHeader('Login');
    $googleLoginReady = googleOauthEnabled($config);
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-1 icon-label"><?= uiIcon('login', 'ui-icon') ?><span>Welcome back</span></h1>
        <p class="text-sm text-slate-600 mb-4">Sign in to continue to your organization dashboard.</p>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="login">
            <input name="email" type="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
            <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
            <button class="bg-indigo-700 text-white px-4 py-2 rounded w-full"><span class="icon-label justify-center"><?= uiIcon('login', 'ui-icon ui-icon-sm') ?><span>Login</span></span></button>
        </form>
        <?php if ($googleLoginReady): ?>
            <div class="my-3 text-center text-gray-500 text-sm">or</div>
            <a href="?page=google_login" class="block w-full border rounded px-4 py-2 text-center hover:bg-gray-50 font-medium">
                <span class="icon-label justify-center"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Continue with Google</span></span>
            </a>
        <?php else: ?>
            <p class="text-xs text-amber-700 mt-3">Google login is disabled. Add Google keys in src/config.php.</p>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

if ($page === 'register') {
    renderHeader('Register');
    $programInstituteMap = getProgramInstituteMap();
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-4 icon-label"><?= uiIcon('register', 'ui-icon') ?><span>Student Registration</span></h1>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="register">
            <input name="name" placeholder="Full Name" required class="w-full border rounded px-3 py-2">
            <input name="email" type="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
            <select name="program" required class="w-full border rounded px-3 py-2">
                <option value="">Select Program</option>
                <?php foreach ($programInstituteMap as $programName => $instituteName): ?>
                    <option value="<?= e($programName) ?>"><?= e($programName) ?> (<?= e($instituteName) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
            <div class="rounded border border-emerald-200/40 p-3 bg-white/20">
                <div class="flex items-start gap-2">
                    <input id="privacyConsent" name="privacy_consent" type="checkbox" value="1" required class="mt-1">
                    <label for="privacyConsent" class="text-sm text-slate-700">
                        I agree to the
                        <button type="button" id="openPrivacyModal" class="font-medium text-emerald-700 underline"><span class="icon-label"><?= uiIcon('audit', 'ui-icon ui-icon-sm') ?><span>Data Privacy Consent</span></span></button>.
                    </label>
                </div>
            </div>
            <button class="bg-indigo-700 text-white px-4 py-2 rounded w-full"><span class="icon-label justify-center"><?= uiIcon('register', 'ui-icon ui-icon-sm') ?><span>Create Account</span></span></button>
        </form>
    </div>

    <div id="privacyModal" class="updates-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="privacyModalTitle">
        <div class="glass w-full max-w-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 id="privacyModalTitle" class="text-lg font-semibold icon-label"><?= uiIcon('audit', 'ui-icon') ?><span>Data Privacy Consent</span></h2>
                <button type="button" id="closePrivacyModal" class="text-slate-600 hover:text-slate-900 text-xl leading-none">&times;</button>
            </div>
            <div class="text-sm text-slate-700 space-y-2 max-h-[60vh] overflow-auto pr-1">
                <p>By creating an account, you agree that this system may collect and process your personal data, such as your name, email address, role, organization memberships, and activity records, for account management and transparency reporting.</p>
                <p>Your data is used only for legitimate school organization operations, including authentication, organization management, announcement publishing, and finance report visibility.</p>
                <p>Your information is stored securely and access is limited based on system roles (admin, owner, student). We do not intentionally share your personal data with unauthorized third parties.</p>
                <p>You may request correction of inaccurate profile data through the system administrator. By proceeding, you confirm that the information you submit is accurate and that you consent to this processing.</p>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="declinePrivacy" class="px-3 py-2 rounded border border-slate-300 text-slate-700"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Close</span></span></button>
                <button type="button" id="acceptPrivacy" class="px-3 py-2 rounded bg-emerald-600 text-white"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>I Agree</span></span></button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('privacyModal');
            const openBtn = document.getElementById('openPrivacyModal');
            const closeBtn = document.getElementById('closePrivacyModal');
            const declineBtn = document.getElementById('declinePrivacy');
            const acceptBtn = document.getElementById('acceptPrivacy');
            const checkbox = document.getElementById('privacyConsent');

            if (!modal || !openBtn || !checkbox) return;

            function openModal() {
                modal.classList.remove('hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
            }

            openBtn.addEventListener('click', openModal);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (declineBtn) declineBtn.addEventListener('click', closeModal);
            if (acceptBtn) {
                acceptBtn.addEventListener('click', function () {
                    checkbox.checked = true;
                    closeModal();
                });
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        })();
    </script>
    <?php
    renderFooter();
    exit;
}

requireLogin();
$user = currentUser();
$announcementCutoff = (new DateTimeImmutable('now'))->modify('-30 days')->format('Y-m-d H:i:s');
$recentReportCutoffDate = (new DateTimeImmutable('now'))->modify('-30 days')->format('Y-m-d');
purgeExpiredAnnouncements($db, 30);

if ($page === 'admin_orgs') {
    requireRole(['admin']);
    $instituteOptions = getInstituteOptions();
    $programInstituteMap = getProgramInstituteMap();
    $programOptions = array_keys($programInstituteMap);
    $orgCategoryOptions = getOrgCategoryOptions();

    $orgs = $db->query("SELECT o.*, u.name AS owner_name, oa.status AS assignment_status, su.name AS assigned_student_name
        FROM organizations o
        LEFT JOIN users u ON u.id = o.owner_id
        LEFT JOIN owner_assignments oa ON oa.organization_id = o.id AND oa.status = 'pending'
        LEFT JOIN users su ON su.id = oa.student_id
        ORDER BY o.id DESC")->fetchAll();
    $orgsPagination = paginateArray($orgs, 'pg_admin_orgs', 8);
    $orgs = $orgsPagination['items'];
    $students = $db->query("SELECT id, name, email FROM users WHERE role IN ('student','owner') ORDER BY name ASC")->fetchAll();

    renderHeader('Manage Organizations');
    ?>
    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white shadow rounded p-4">
            <h2 class="text-lg font-semibold mb-3 icon-label"><?= uiIcon('create', 'ui-icon') ?><span>Create Organization</span></h2>
            <form method="post" class="space-y-2">
                <input type="hidden" name="action" value="create_org">
                <input name="name" placeholder="Organization name" required class="w-full border rounded px-3 py-2">
                <textarea name="description" placeholder="Description" class="w-full border rounded px-3 py-2"></textarea>
                <select name="org_category" class="w-full border rounded px-3 py-2" required>
                    <?php foreach ($orgCategoryOptions as $categoryKey => $categoryLabel): ?>
                        <option value="<?= e($categoryKey) ?>"><?= e($categoryLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="target_institute" class="w-full border rounded px-3 py-2">
                    <option value="">Institute target (for institutewide)</option>
                    <?php foreach ($instituteOptions as $institute): ?>
                        <option value="<?= e($institute) ?>"><?= e($institute) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="target_program" class="w-full border rounded px-3 py-2">
                    <option value="">Program target (for program-based)</option>
                    <?php foreach ($programOptions as $programOption): ?>
                        <option value="<?= e($programOption) ?>"><?= e($programOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('create', 'ui-icon ui-icon-sm') ?><span>Create</span></span></button>
            </form>
        </div>

        <div class="md:col-span-2 bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-3 icon-label"><?= uiIcon('orgs', 'ui-icon') ?><span>All Organizations</span></h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Name</th>
                    <th>Description</th>
                    <th>Visibility</th>
                    <th>Owner</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orgs as $org): ?>
                    <tr class="border-b align-top">
                        <td class="py-2 font-medium"><?= e($org['name']) ?></td>
                        <td><?= e($org['description']) ?></td>
                        <td>
                            <span class="text-xs"><?= e(getOrganizationVisibilityLabel($org)) ?></span>
                        </td>
                        <td>
                            <form method="post" class="flex gap-2 items-center">
                                <input type="hidden" name="action" value="assign_owner">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <select name="owner_id" class="border rounded px-2 py-1 text-xs">
                                    <option value="">-- none --</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= (int) $student['id'] ?>" <?= (int) $org['owner_id'] === (int) $student['id'] ? 'selected' : '' ?>>
                                            <?= e($student['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="bg-slate-700 text-white text-xs px-2 py-1 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save</span></span></button>
                            </form>
                            <span class="text-xs text-gray-500"><?= e($org['owner_name'] ?? 'Unassigned') ?></span>
                            <?php if (!empty($org['assignment_status'])): ?>
                                <div class="text-[11px] text-amber-200 mt-1">Pending: <?= e($org['assigned_student_name'] ?? 'Student') ?> (awaiting response)</div>
                            <?php endif; ?>
                        </td>
                        <td class="space-y-2 min-w-52">
                            <form method="post" class="space-y-1">
                                <input type="hidden" name="action" value="update_org_admin">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input name="name" value="<?= e($org['name']) ?>" class="w-full border rounded px-2 py-1 text-xs">
                                <textarea name="description" class="w-full border rounded px-2 py-1 text-xs"><?= e($org['description']) ?></textarea>
                                <select name="org_category" class="w-full border rounded px-2 py-1 text-xs">
                                    <?php foreach ($orgCategoryOptions as $categoryKey => $categoryLabel): ?>
                                        <option value="<?= e($categoryKey) ?>" <?= (string) ($org['org_category'] ?? 'collegewide') === $categoryKey ? 'selected' : '' ?>><?= e($categoryLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="target_institute" class="w-full border rounded px-2 py-1 text-xs">
                                    <option value="">Institute target (for institutewide)</option>
                                    <?php foreach ($instituteOptions as $institute): ?>
                                        <option value="<?= e($institute) ?>" <?= (string) ($org['target_institute'] ?? '') === $institute ? 'selected' : '' ?>><?= e($institute) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="target_program" class="w-full border rounded px-2 py-1 text-xs">
                                    <option value="">Program target (for program-based)</option>
                                    <?php foreach ($programOptions as $programOption): ?>
                                        <option value="<?= e($programOption) ?>" <?= (string) ($org['target_program'] ?? '') === $programOption ? 'selected' : '' ?>><?= e($programOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="bg-blue-600 text-white text-xs px-2 py-1 rounded"><span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Update</span></span></button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete this organization?')">
                                <input type="hidden" name="action" value="delete_org">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <button class="bg-red-600 text-white text-xs px-2 py-1 rounded"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Delete</span></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php renderPagination($orgsPagination); ?>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}

if ($page === 'admin_students') {
    requireRole(['admin']);
    $q = trim((string) ($_GET['q'] ?? ''));

    if ($q !== '') {
        $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE role IN ('student','owner') AND (name LIKE ? OR email LIKE ?) ORDER BY name");
        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
        $students = $stmt->fetchAll();
    } else {
        $students = $db->query("SELECT id, name, email, role, created_at FROM users WHERE role IN ('student','owner') ORDER BY name")->fetchAll();
    }
    $studentsPagination = paginateArray($students, 'pg_admin_students', 12);
    $students = $studentsPagination['items'];

    renderHeader('Filter Students');
    ?>
    <div class="bg-white shadow rounded p-4">
        <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('students', 'ui-icon') ?><span>Filter All Student Information</span></h1>
        <form method="get" class="flex gap-2 mb-4">
            <input type="hidden" name="page" value="admin_students">
            <input name="q" value="<?= e($q) ?>" placeholder="Search by name or email" class="border rounded px-3 py-2 w-full">
            <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Filter</span></span></button>
        </form>

        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b text-left">
                    <th class="py-2">Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr class="border-b">
                        <td class="py-2"><?= e($student['name']) ?></td>
                        <td><?= e($student['email']) ?></td>
                        <td><?= e($student['role']) ?></td>
                        <td><?= e($student['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php renderPagination($studentsPagination); ?>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}

if ($page === 'admin_requests') {
    requireRole(['admin']);

    $requests = $db->query("SELECT r.*, o.name AS organization_name, u.name AS requester_name
        FROM transaction_change_requests r
        JOIN organizations o ON o.id = r.organization_id
        JOIN users u ON u.id = r.requested_by
        ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.created_at DESC")->fetchAll();
    $requestsPagination = paginateArray($requests, 'pg_admin_requests', 10);
    $requests = $requestsPagination['items'];

    renderHeader('Transaction Requests');
    ?>
    <div class="bg-white shadow rounded p-4 overflow-auto">
        <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Owner Requests for Transaction Edit/Delete</span></h1>
        <table class="w-full text-sm">
            <thead>
            <tr class="border-b text-left">
                <th class="py-2">Org</th>
                <th>Requester</th>
                <th>Action</th>
                <th>Proposal</th>
                <th>Status</th>
                <th>Admin Note</th>
                <th>Decision</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $req): ?>
                <tr class="border-b align-top">
                    <td class="py-2"><?= e($req['organization_name']) ?></td>
                    <td><?= e($req['requester_name']) ?></td>
                    <td><?= e($req['action_type']) ?></td>
                    <td>
                        <?php if ($req['action_type'] === 'update'): ?>
                            <div class="text-xs">Type: <?= e((string) $req['proposed_type']) ?></div>
                            <div class="text-xs">Amount: ₱<?= number_format((float) $req['proposed_amount'], 2) ?></div>
                            <div class="text-xs">Date: <?= e((string) $req['proposed_transaction_date']) ?></div>
                            <div class="text-xs">Desc: <?= e((string) $req['proposed_description']) ?></div>
                        <?php else: ?>
                            <div class="text-xs">Delete transaction #<?= (int) $req['transaction_id'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="icon-label"><?php
                        $requestStatus = strtolower((string) $req['status']);
                        $requestStatusIcon = match ($requestStatus) {
                            'approved', 'accepted' => 'approved',
                            'rejected', 'declined' => 'rejected',
                            'pending' => 'pending',
                            default => 'default',
                        };
                        ?><?= uiIcon($requestStatusIcon, 'ui-icon ui-icon-sm') ?><?= e((string) $req['status']) ?></span></td>
                    <td><?= e((string) ($req['admin_note'] ?? '')) ?></td>
                    <td class="min-w-56">
                        <?php if ((string) $req['status'] === 'pending'): ?>
                            <form method="post" class="space-y-1">
                                <input type="hidden" name="action" value="process_tx_change_request">
                                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                <input name="admin_note" placeholder="Optional note" class="w-full border rounded px-2 py-1 text-xs">
                                <div class="flex gap-2">
                                    <button name="decision" value="approve" class="bg-emerald-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Approve</span></span></button>
                                    <button name="decision" value="reject" class="bg-red-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Reject</span></span></button>
                                </div>
                            </form>
                        <?php else: ?>
                            <span class="text-xs text-gray-500">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php renderPagination($requestsPagination); ?>
    </div>
    <?php
    renderFooter();
    exit;
}

if ($page === 'admin_audit') {
    requireLogin();
    if (($user['role'] ?? '') !== 'admin') {
        setFlash('error', 'Admin access required.');
        redirect('?page=dashboard');
    }

    $days = max(1, (int) ($_GET['days'] ?? 7));
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "SELECT al.*, u.name AS actor_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         WHERE al.created_at >= ?
         ORDER BY al.id DESC
         LIMIT 300"
    );
    $stmt->execute([$cutoff]);
    $logs = $stmt->fetchAll();
    $logsPagination = paginateArray($logs, 'pg_admin_audit', 20);
    $logs = $logsPagination['items'];

    renderHeader('Audit Logs', $user);
    ?>
    <section class="bg-white rounded shadow p-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Audit Logs</h2>
            <form method="get" class="flex items-center gap-2">
                <input type="hidden" name="page" value="admin_audit" />
                <label class="text-sm text-gray-600" for="days">Last</label>
                <select name="days" id="days" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                    <?php foreach ([1, 3, 7, 14, 30, 90] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $days === $opt ? 'selected' : '' ?>><?= $opt ?> days</option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (!$logs): ?>
            <p class="text-sm text-gray-600">No audit entries in the selected range.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 pr-3">Time</th>
                            <th class="text-left py-2 pr-3">Actor</th>
                            <th class="text-left py-2 pr-3">Action</th>
                            <th class="text-left py-2 pr-3">Entity</th>
                            <th class="text-left py-2 pr-3">Entity ID</th>
                            <th class="text-left py-2 pr-3">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="border-b align-top">
                                <td class="py-2 pr-3 whitespace-nowrap"><?= e($log['created_at']) ?></td>
                                <td class="py-2 pr-3"><?= e($log['actor_name'] ?: ('User#' . (int)$log['user_id'])) ?></td>
                                <td class="py-2 pr-3"><?= e($log['action']) ?></td>
                                <td class="py-2 pr-3"><?= e($log['entity_type'] ?? '-') ?></td>
                                <td class="py-2 pr-3"><?= $log['entity_id'] !== null ? (int)$log['entity_id'] : '-' ?></td>
                                <td class="py-2 pr-3 break-words max-w-xl"><?= e($log['details'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php renderPagination($logsPagination); ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    renderFooter();
    exit;
}

if ($page === 'announcements') {
    $stmt = $db->prepare('SELECT a.*, o.name AS organization_name
        FROM announcements a
        JOIN organizations o ON o.id = a.organization_id
        WHERE a.created_at >= ?
        ORDER BY a.is_pinned DESC, COALESCE(a.pinned_at, a.created_at) DESC, a.created_at DESC, a.id DESC');
    $stmt->execute([$announcementCutoff]);
    $allAnnouncements = $stmt->fetchAll();
    $slides = array_chunk($allAnnouncements, 3);
    $slideCount = count($slides);

    renderHeader('Announcements');
    ?>
    <section class="glass p-4">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-semibold icon-label"><?= uiIcon('announce', 'ui-icon') ?><span>Latest Announcements</span></h1>
            <a href="?page=dashboard" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm"><span class="icon-label"><?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?><span>Back to Dashboard</span></span></a>
        </div>

        <?php if ($slideCount === 0): ?>
            <p class="text-sm text-gray-600">No announcements in the last 30 days.</p>
        <?php else: ?>
            <div class="relative">
                <?php foreach ($slides as $index => $slideItems): ?>
                    <div class="announcement-slide <?= $index === 0 ? '' : 'hidden' ?>" data-slide-index="<?= $index ?>">
                        <div class="grid md:grid-cols-3 gap-3">
                            <?php foreach ($slideItems as $item): ?>
                                <article class="border rounded p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <h2 class="font-semibold"><?= e($item['title']) ?></h2>
                                        <?php if ((int) ($item['is_pinned'] ?? 0) === 1): ?>
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-500/25 border border-amber-300/40 icon-label"><?= uiIcon('pin', 'ui-icon ui-icon-sm') ?><span>Important</span></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1"><?= e($item['organization_name']) ?> · <?= e($item['created_at']) ?></div>
                                    <p class="text-sm mt-2"><?= e($item['content']) ?></p>
                                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="action" value="<?= (int) ($item['is_pinned'] ?? 0) === 1 ? 'unpin_announcement_admin' : 'pin_announcement_admin' ?>">
                                            <input type="hidden" name="announcement_id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="return_page" value="announcements">
                                            <button class="px-2 py-1 rounded text-xs border">
                                                <span class="icon-label"><?= uiIcon('pin', 'ui-icon ui-icon-sm') ?><span><?= (int) ($item['is_pinned'] ?? 0) === 1 ? 'Unpin' : 'Pin as Important' ?></span></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="flex items-center justify-between mt-4">
                    <button type="button" id="announcementPrev" class="px-3 py-1 rounded border text-sm"><span class="icon-label"><?= uiIcon('prev', 'ui-icon ui-icon-sm') ?><span>Prev</span></span></button>
                    <div id="announcementDots" class="flex gap-1"></div>
                    <button type="button" id="announcementNext" class="px-3 py-1 rounded border text-sm"><span class="icon-label"><span>Next</span><?= uiIcon('next', 'ui-icon ui-icon-sm') ?></span></button>
                </div>
            </div>

            <script>
                (function () {
                    const slides = Array.from(document.querySelectorAll('.announcement-slide'));
                    const prevBtn = document.getElementById('announcementPrev');
                    const nextBtn = document.getElementById('announcementNext');
                    const dotsWrap = document.getElementById('announcementDots');
                    if (!slides.length || !prevBtn || !nextBtn || !dotsWrap) return;

                    let index = 0;
                    const dots = slides.map(function (_, i) {
                        const dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = 'w-2 h-2 rounded-full border border-emerald-400';
                        dot.addEventListener('click', function () {
                            show(i);
                        });
                        dotsWrap.appendChild(dot);
                        return dot;
                    });

                    function show(target) {
                        index = (target + slides.length) % slides.length;
                        slides.forEach(function (slide, i) {
                            slide.classList.toggle('hidden', i !== index);
                        });
                        dots.forEach(function (dot, i) {
                            dot.classList.toggle('bg-emerald-500', i === index);
                            dot.classList.toggle('bg-transparent', i !== index);
                        });
                    }

                    prevBtn.addEventListener('click', function () {
                        show(index - 1);
                    });

                    nextBtn.addEventListener('click', function () {
                        show(index + 1);
                    });

                    show(0);
                })();
            </script>
        <?php endif; ?>
    </section>
    <?php
    renderFooter();
    exit;
}

if ($page === 'organizations') {
    $allOrgs = $db->query('SELECT o.*, u.name AS owner_name FROM organizations o LEFT JOIN users u ON u.id = o.owner_id ORDER BY o.name ASC')->fetchAll();
    $allOrgs = applyOrganizationVisibilityForUser($allOrgs, $user);

    $membershipStmt = $db->prepare('SELECT organization_id FROM organization_members WHERE user_id = ?');
    $membershipStmt->execute([(int) $user['id']]);
    $joinedIds = array_map('intval', array_column($membershipStmt->fetchAll(), 'organization_id'));

    $requestStmt = $db->prepare('SELECT organization_id, status FROM organization_join_requests WHERE user_id = ?');
    $requestStmt->execute([(int) $user['id']]);
    $joinRequestStatus = [];
    foreach ($requestStmt->fetchAll() as $req) {
        $joinRequestStatus[(int) $req['organization_id']] = (string) $req['status'];
    }

    renderHeader('Organizations');
    ?>
    <section class="glass p-4">
        <div class="flex items-center justify-between mb-3">
            <h1 class="text-xl font-semibold icon-label"><?= uiIcon('orgs', 'ui-icon') ?><span>All Organizations</span></h1>
            <a href="?page=dashboard" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm"><span class="icon-label"><?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?><span>Back to Dashboard</span></span></a>
        </div>

        <div class="space-y-3 max-h-[36rem] overflow-y-auto themed-scroll pr-1">
            <?php foreach ($allOrgs as $org): ?>
                <div class="border rounded p-3 flex flex-col md:flex-row justify-between items-center md:items-start gap-2 text-center md:text-left">
                    <div>
                        <div class="font-medium"><?= e($org['name']) ?></div>
                        <p class="text-sm text-gray-600"><?= e($org['description']) ?></p>
                        <div class="text-xs text-gray-500 mt-1">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
                        <div class="text-xs text-emerald-800 mt-1"><?= e(getOrganizationVisibilityLabel($org)) ?></div>
                    </div>
                    <?php if (in_array($user['role'], ['student', 'owner'], true)): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="join_org">
                            <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                            <?php
                                $orgId = (int) $org['id'];
                                $requestStatus = (string) ($joinRequestStatus[$orgId] ?? '');
                                $isJoined = in_array($orgId, $joinedIds, true);
                                $disabled = $isJoined || $requestStatus === 'pending';
                                $btnClass = $isJoined
                                    ? 'bg-white/10 border-emerald-200/30 text-slate-700'
                                    : ($requestStatus === 'pending'
                                        ? 'bg-amber-500/25 border-amber-300/50 text-amber-900'
                                        : 'bg-emerald-500/25 border-emerald-300/50 text-emerald-900 hover:bg-emerald-500/35');
                                $label = $isJoined ? 'Joined' : ($requestStatus === 'pending' ? 'Requested' : 'Request Join');
                                $joinIcon = $isJoined ? 'approved' : ($requestStatus === 'pending' ? 'pending' : 'requests');
                            ?>
                            <button class="px-3 py-1 rounded text-xs border backdrop-blur-md <?= $btnClass ?>" <?= $disabled ? 'disabled' : '' ?>>
                                <span class="icon-label"><?= uiIcon($joinIcon, 'ui-icon ui-icon-sm') ?><span><?= $label ?></span></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}

if ($page === 'my_org') {
    if ($user['role'] === 'admin') {
        $orgId = (int) ($_GET['org_id'] ?? 0);
        $orgs = $db->query('SELECT id, name FROM organizations ORDER BY name')->fetchAll();
        $org = null;
        if ($orgId > 0) {
            $stmt = $db->prepare('SELECT * FROM organizations WHERE id = ?');
            $stmt->execute([$orgId]);
            $org = $stmt->fetch();
        }

        renderHeader('Organization Overview');
        ?>
        <div class="bg-white shadow rounded p-4">
            <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>Organization Overview (Admin)</span></h1>
            <form method="get" class="mb-4 flex gap-2">
                <input type="hidden" name="page" value="my_org">
                <select name="org_id" class="border rounded px-3 py-2">
                    <option value="">Select organization</option>
                    <?php foreach ($orgs as $option): ?>
                        <option value="<?= (int) $option['id'] ?>" <?= $orgId === (int) $option['id'] ? 'selected' : '' ?>>
                            <?= e($option['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
            </form>

            <?php if ($org): ?>
                <?php
                $txStmt = $db->prepare('SELECT * FROM financial_transactions WHERE organization_id = ? ORDER BY transaction_date DESC, id DESC');
                $txStmt->execute([(int) $org['id']]);
                $tx = $txStmt->fetchAll();
                $adminTxPagination = paginateArray($tx, 'pg_myorg_admin_tx', 12);
                $tx = $adminTxPagination['items'];
                ?>
                <h2 class="text-lg font-semibold"><?= e($org['name']) ?></h2>
                <p class="text-gray-600 mb-3"><?= e($org['description']) ?></p>
                <table class="w-full text-sm">
                    <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">Date</th><th>Type</th><th>Amount</th><th>Description</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tx as $row): ?>
                        <tr class="border-b">
                            <td class="py-2"><?= e($row['transaction_date']) ?></td>
                            <td class="<?= $row['type'] === 'income' ? 'text-green-700' : 'text-red-700' ?>"><?= e($row['type']) ?></td>
                            <td>₱<?= number_format((float) $row['amount'], 2) ?></td>
                            <td><?= e($row['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php renderPagination($adminTxPagination); ?>
            <?php endif; ?>
        </div>
        <?php
        renderFooter();
        exit;
    }

    requireRole(['owner']);
    $ownedOrganizations = getOwnedOrganizations((int) $user['id']);
    if (count($ownedOrganizations) === 0) {
        setFlash('error', 'No organization is assigned to your account yet.');
        redirect('?page=dashboard');
    }

    $selectedOrgId = (int) ($_GET['org_id'] ?? 0);
    if ($selectedOrgId <= 0) {
        $selectedOrgId = (int) $ownedOrganizations[0]['id'];
    }

    $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
    if (!$org) {
        setFlash('error', 'Selected organization is not assigned to your account.');
        redirect('?page=my_org');
    }

    $stmt = $db->prepare('SELECT * FROM announcements WHERE organization_id = ? AND created_at >= ? ORDER BY id DESC');
    $stmt->execute([(int) $org['id'], $announcementCutoff]);
    $announcements = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM financial_transactions WHERE organization_id = ? ORDER BY transaction_date DESC, id DESC');
    $stmt->execute([(int) $org['id']]);
    $transactions = $stmt->fetchAll();

    $txRequestStmt = $db->prepare("SELECT * FROM transaction_change_requests WHERE organization_id = ? AND requested_by = ? ORDER BY created_at DESC LIMIT 20");
    $txRequestStmt->execute([(int) $org['id'], (int) $user['id']]);
    $myTxRequests = $txRequestStmt->fetchAll();

    $joinRequestStmt = $db->prepare("SELECT r.id, r.created_at, u.name, u.email
        FROM organization_join_requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.organization_id = ? AND r.status = 'pending'
        ORDER BY r.created_at DESC");
    $joinRequestStmt->execute([(int) $org['id']]);
    $pendingJoinRequests = $joinRequestStmt->fetchAll();

    $pendingJoinPagination = paginateArray($pendingJoinRequests, 'pg_myorg_join', 5);
    $pendingJoinRequests = $pendingJoinPagination['items'];
    $announcementsPagination = paginateArray($announcements, 'pg_myorg_ann', 5);
    $announcements = $announcementsPagination['items'];
    $transactionsPagination = paginateArray($transactions, 'pg_myorg_tx', 10);
    $transactions = $transactionsPagination['items'];
    $myTxRequestsPagination = paginateArray($myTxRequests, 'pg_myorg_req', 8);
    $myTxRequests = $myTxRequestsPagination['items'];

    renderHeader('My Organization');
    ?>
    <div class="space-y-4">
        <div class="bg-white shadow rounded p-4">
            <h2 class="text-lg font-semibold mb-3 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Pending Membership Requests</span></h2>
            <?php if (count($pendingJoinRequests) === 0): ?>
                <p class="text-sm text-gray-500">No pending join requests for this organization.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($pendingJoinRequests as $request): ?>
                        <div class="border rounded p-3 flex flex-wrap justify-between items-center gap-3">
                            <div>
                                <div class="font-medium"><?= e($request['name']) ?></div>
                                <div class="text-xs text-gray-500"><?= e($request['email']) ?> · <?= e($request['created_at']) ?></div>
                            </div>
                            <div class="flex gap-2">
                                <form method="post">
                                    <input type="hidden" name="action" value="respond_join_request">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="bg-emerald-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Approve</span></span></button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="action" value="respond_join_request">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="decision" value="decline">
                                    <button class="bg-red-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Decline</span></span></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php renderPagination($pendingJoinPagination); ?>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>My Organization</span></h1>
            <form method="get" class="mb-4 flex gap-2">
                <input type="hidden" name="page" value="my_org">
                <select name="org_id" class="border rounded px-3 py-2">
                    <?php foreach ($ownedOrganizations as $ownedOption): ?>
                        <option value="<?= (int) $ownedOption['id'] ?>" <?= (int) $org['id'] === (int) $ownedOption['id'] ? 'selected' : '' ?>>
                            <?= e($ownedOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
            </form>
            <form method="post" class="grid md:grid-cols-2 gap-3">
                <input type="hidden" name="action" value="update_my_org">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                <div>
                    <label class="text-sm text-gray-600">Organization Name</label>
                    <input name="name" value="<?= e($org['name']) ?>" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Description</label>
                    <textarea name="description" class="w-full border rounded px-3 py-2"><?= e($org['description']) ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Organization Info</span></span></button>
                </div>
            </form>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white shadow rounded p-4">
                <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('announce', 'ui-icon') ?><span>Post Announcement</span></h2>
                <form method="post" class="space-y-2">
                    <input type="hidden" name="action" value="add_announcement">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <input name="title" placeholder="Title" class="w-full border rounded px-3 py-2" required>
                    <textarea name="content" placeholder="Announcement details" class="w-full border rounded px-3 py-2" required></textarea>
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('create', 'ui-icon ui-icon-sm') ?><span>Post</span></span></button>
                </form>

                <div class="mt-4 space-y-2 max-h-72 overflow-auto">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="border rounded p-2">
                            <div class="font-medium"><?= e($announcement['title']) ?></div>
                            <div class="text-sm text-gray-700"><?= e($announcement['content']) ?></div>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="action" value="delete_announcement">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                                <button class="bg-red-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Delete</span></span></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php renderPagination($announcementsPagination); ?>
            </div>

            <div class="bg-white shadow rounded p-4">
                <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('create', 'ui-icon') ?><span>Add Income / Expense</span></h2>
                <form method="post" enctype="multipart/form-data" class="space-y-2">
                    <input type="hidden" name="action" value="add_transaction">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <div class="grid grid-cols-2 gap-2">
                        <select name="type" class="border rounded px-3 py-2">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                        <input type="number" step="0.01" name="amount" placeholder="Amount" class="border rounded px-3 py-2" required>
                    </div>
                    <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" class="w-full border rounded px-3 py-2" required>
                    <input name="description" placeholder="Description" class="w-full border rounded px-3 py-2" required>
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full border rounded px-3 py-2">
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Transaction</span></span></button>
                </form>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Transaction History</span></h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Receipt</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $row): ?>
                    <tr class="border-b align-top">
                        <td class="py-2"><?= e($row['transaction_date']) ?></td>
                        <td><?= e($row['type']) ?></td>
                        <td>₱<?= number_format((float) $row['amount'], 2) ?></td>
                        <td><?= e($row['description']) ?></td>
                        <td>
                            <?php if (!empty($row['receipt_path'])): ?>
                                <a href="<?= e($row['receipt_path']) ?>" target="_blank" class="text-indigo-700 underline"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View</span></span></a>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="space-y-1 min-w-56">
                            <form method="post" class="grid grid-cols-2 gap-1">
                                <input type="hidden" name="action" value="update_transaction">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                <select name="type" class="border rounded px-2 py-1 text-xs">
                                    <option value="income" <?= $row['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                                    <option value="expense" <?= $row['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                </select>
                                <input name="amount" type="number" step="0.01" value="<?= e((string) $row['amount']) ?>" class="border rounded px-2 py-1 text-xs">
                                <input name="transaction_date" type="date" value="<?= e($row['transaction_date']) ?>" class="border rounded px-2 py-1 text-xs">
                                <input name="description" value="<?= e($row['description']) ?>" class="col-span-2 border rounded px-2 py-1 text-xs">
                                <button class="bg-blue-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Request Update</span></span></button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete transaction?')">
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                <button class="bg-red-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Request Delete</span></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php renderPagination($transactionsPagination); ?>
        </div>

        <div class="bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>My Pending/Recent Transaction Requests</span></h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Date</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th>Admin Note</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($myTxRequests as $req): ?>
                    <tr class="border-b">
                        <td class="py-2"><?= e((string) $req['created_at']) ?></td>
                        <td><?= e((string) $req['action_type']) ?></td>
                        <td><?= e((string) $req['status']) ?></td>
                        <td><?= e((string) ($req['admin_note'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php renderPagination($myTxRequestsPagination); ?>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}

// Default Dashboard (all logged-in users)
$orgs = $db->query('SELECT o.*, u.name AS owner_name FROM organizations o LEFT JOIN users u ON u.id = o.owner_id ORDER BY o.name ASC')->fetchAll();
$orgs = applyOrganizationVisibilityForUser($orgs, $user);
$membershipStmt = $db->prepare('SELECT organization_id FROM organization_members WHERE user_id = ?');
$membershipStmt->execute([(int) $user['id']]);
$joinedIds = array_map('intval', array_column($membershipStmt->fetchAll(), 'organization_id'));

$requestStmt = $db->prepare('SELECT organization_id, status FROM organization_join_requests WHERE user_id = ?');
$requestStmt->execute([(int) $user['id']]);
$joinRequestStatus = [];
foreach ($requestStmt->fetchAll() as $req) {
    $joinRequestStatus[(int) $req['organization_id']] = (string) $req['status'];
}

$announcementsStmt = $db->prepare('SELECT a.*, o.name AS organization_name
    FROM announcements a
    JOIN organizations o ON o.id = a.organization_id
    WHERE a.created_at >= ?
    ORDER BY a.is_pinned DESC, COALESCE(a.pinned_at, a.created_at) DESC, a.created_at DESC, a.id DESC
    LIMIT 30');
$announcementsStmt->execute([$announcementCutoff]);
$announcements = $announcementsStmt->fetchAll();

$transactionsStmt = $db->prepare('SELECT t.*, o.name AS organization_name
    FROM financial_transactions t
    JOIN organizations o ON o.id = t.organization_id
    WHERE t.transaction_date >= ?
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 8');
$transactionsStmt->execute([$recentReportCutoffDate]);
$transactions = $transactionsStmt->fetchAll();

$visibleOrganizationIds = array_values(array_unique(array_map(static fn(array $org): int => (int) $org['id'], $orgs)));
$summary = [];
if (count($visibleOrganizationIds) > 0) {
    $summaryPlaceholders = implode(',', array_fill(0, count($visibleOrganizationIds), '?'));
    $summaryStmt = $db->prepare("SELECT o.id, o.name,
        COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_expense
        FROM organizations o
        LEFT JOIN financial_transactions t ON t.organization_id = o.id
        WHERE o.id IN ($summaryPlaceholders)
        GROUP BY o.id, o.name
        ORDER BY o.name");
    $summaryStmt->execute($visibleOrganizationIds);
    $summary = $summaryStmt->fetchAll();
}

$kpi = $db->query("SELECT
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
    FROM financial_transactions")->fetch();

$kpiIncome = (float) ($kpi['income'] ?? 0);
$kpiExpense = (float) ($kpi['expense'] ?? 0);
$kpiBalance = $kpiIncome - $kpiExpense;

$activityStmt = $db->prepare("SELECT 'announcement' AS type, title AS label, created_at, organization_id FROM announcements WHERE created_at >= ?
    UNION ALL
    SELECT 'transaction' AS type, description AS label, created_at, organization_id FROM financial_transactions WHERE transaction_date >= ?
    ORDER BY created_at DESC
    LIMIT 16");
$activityStmt->execute([$announcementCutoff, $recentReportCutoffDate]);
$activity = $activityStmt->fetchAll();

$dbDriver = (string) (($config['db']['driver'] ?? 'sqlite'));
if ($dbDriver === 'mysql') {
    $trendRows = $db->query("SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month,
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
        FROM financial_transactions
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6")->fetchAll();
} else {
    $trendRows = $db->query("SELECT strftime('%Y-%m', transaction_date) AS month,
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
        FROM financial_transactions
        GROUP BY strftime('%Y-%m', transaction_date)
        ORDER BY month DESC
        LIMIT 6")->fetchAll();
}

$trendRows = array_reverse($trendRows);
$trendLabels = array_map(static fn(array $r): string => (string) $r['month'], $trendRows);
$trendIncome = array_map(static fn(array $r): float => (float) $r['income'], $trendRows);
$trendExpense = array_map(static fn(array $r): float => (float) $r['expense'], $trendRows);

$trendPointCount = count($trendRows);
$latestTrendNet = 0.0;
$latestTrendDelta = null;
$peakExpenseMonth = '-';
$peakExpenseValue = 0.0;
$healthyMonthCount = 0;

if ($trendPointCount > 0) {
    $latest = $trendRows[$trendPointCount - 1];
    $latestTrendNet = (float) $latest['income'] - (float) $latest['expense'];

    if ($trendPointCount > 1) {
        $previous = $trendRows[$trendPointCount - 2];
        $previousTrendNet = (float) $previous['income'] - (float) $previous['expense'];
        $latestTrendDelta = $latestTrendNet - $previousTrendNet;
    }

    foreach ($trendRows as $row) {
        $income = (float) $row['income'];
        $expense = (float) $row['expense'];
        if ($income >= $expense) {
            $healthyMonthCount++;
        }
        if ($expense >= $peakExpenseValue) {
            $peakExpenseValue = $expense;
            $peakExpenseMonth = (string) $row['month'];
        }
    }
}

$latestTrendDirectionLabel = $latestTrendDelta === null
    ? 'No prior month baseline'
    : ($latestTrendDelta >= 0 ? 'Net improved vs previous month' : 'Net declined vs previous month');

$pendingAssignments = [];
if (in_array($user['role'], ['student', 'owner'], true)) {
    $stmt = $db->prepare("SELECT oa.id, oa.created_at, o.id AS organization_id, o.name AS organization_name
        FROM owner_assignments oa
        JOIN organizations o ON o.id = oa.organization_id
        WHERE oa.student_id = ? AND oa.status = 'pending'
        ORDER BY oa.created_at DESC");
    $stmt->execute([(int) $user['id']]);
    $pendingAssignments = $stmt->fetchAll();
}

$pendingAssignmentsPagination = paginateArray($pendingAssignments, 'pg_dash_assign', 2);
$pendingAssignments = $pendingAssignmentsPagination['items'];
$dashboardOrganizationsPreview = array_slice($orgs, 0, 3);
$summaryAll = $summary;
$summaryPagination = paginateArray($summaryAll, 'pg_dash_summary', 4);
$summary = $summaryPagination['items'];
$activityPagination = paginateArray($activity, 'pg_dash_activity', 2);
$activity = $activityPagination['items'];
$latestAnnouncementsPreview = array_slice($announcements, 0, 3);
$activityPreview = array_slice($activity, 0, 2);
$recentReportsDisplayLimit = 8;
$transactions = array_slice($transactions, 0, $recentReportsDisplayLimit);
$dashboardTimestamp = (new DateTimeImmutable('now'))->format('l, F j, Y | g:i A');
$expenseRatio = $kpiIncome > 0 ? (int) min(100, round(($kpiExpense / $kpiIncome) * 100)) : 0;
$balanceRatio = $kpiIncome > 0 ? (int) max(0, min(100, round((max($kpiBalance, 0) / $kpiIncome) * 100))) : 0;
$recentReportCount = count($transactions);
$latestAnnouncementCount = count($latestAnnouncementsPreview);
$pendingAssignmentCount = count($pendingAssignments);

$summaryChartRows = array_slice($summaryAll, 0, 8);
$summaryIncomeTotal = (float) array_reduce(
    $summaryAll,
    static fn(float $carry, array $row): float => $carry + (float) $row['total_income'],
    0.0
);
$summaryExpenseTotal = (float) array_reduce(
    $summaryAll,
    static fn(float $carry, array $row): float => $carry + (float) $row['total_expense'],
    0.0
);
$summaryNetTotal = $summaryIncomeTotal - $summaryExpenseTotal;

$summaryRankingRows = array_map(
    static function (array $row): array {
        $income = (float) $row['total_income'];
        $expense = (float) $row['total_expense'];
        $balance = $income - $expense;
        $expenseRatio = $income > 0 ? ($expense / $income) : 1.0;

        $status = 'Healthy';
        $statusClass = 'text-emerald-300 border-emerald-300/40 bg-emerald-500/10';
        if ($balance < 0) {
            $status = 'Risk';
            $statusClass = 'text-red-300 border-red-300/40 bg-red-500/10';
        } elseif ($expenseRatio >= 0.9) {
            $status = 'Watch';
            $statusClass = 'text-amber-300 border-amber-300/40 bg-amber-500/10';
        }

        return [
            'name' => (string) $row['name'],
            'income' => $income,
            'expense' => $expense,
            'balance' => $balance,
            'status' => $status,
            'status_class' => $statusClass,
            'expense_ratio' => $expenseRatio,
        ];
    },
    $summaryAll
);

usort(
    $summaryRankingRows,
    static fn(array $a, array $b): int => $b['balance'] <=> $a['balance']
);

$summaryRankingTop = array_slice($summaryRankingRows, 0, 8);
$summaryRankingLabels = array_map(static fn(array $row): string => (string) $row['name'], $summaryRankingTop);
$summaryRankingBalances = array_map(static fn(array $row): float => (float) $row['balance'], $summaryRankingTop);

$summaryAttentionRows = array_values(array_filter(
    $summaryRankingRows,
    static fn(array $row): bool => $row['balance'] < 0 || $row['expense_ratio'] >= 0.9
));
$summaryAttentionRows = array_slice($summaryAttentionRows, 0, 4);

$topPerformer = $summaryRankingRows[0] ?? null;
$highestPressure = null;
foreach ($summaryRankingRows as $row) {
    if ($highestPressure === null || $row['expense_ratio'] > $highestPressure['expense_ratio']) {
        $highestPressure = $row;
    }
}
$averageNet = count($summaryRankingRows) > 0
    ? (float) (array_sum(array_map(static fn(array $row): float => (float) $row['balance'], $summaryRankingRows)) / count($summaryRankingRows))
    : 0.0;

renderHeader('Dashboard');
?>
<div class="dashboard-shell space-y-3">
    <section class="grid xl:grid-cols-12 gap-3">
        <div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="dashboard-kicker">Overview</div>
                    <h1 class="dashboard-headline modern-title">Operations are on track, budgets are transparent, and every organization is in sync.</h1>
                    <p class="dashboard-copy mt-3">Welcome, <?= e($user['name']) ?>. Track collections, spending, announcements, and ownership activity from one focused workspace.</p>
                </div>
                <div class="dashboard-stamp"><?= e($dashboardTimestamp) ?></div>
            </div>
            <div class="dashboard-metric-grid mt-4">
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value">₱<?= number_format($kpiIncome, 2) ?></div>
                    <div class="dashboard-metric-label">Total income recorded</div>
                </div>
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value <?= $kpiBalance >= 0 ? 'text-green-300' : 'text-red-300' ?>">₱<?= number_format($kpiBalance, 2) ?></div>
                    <div class="dashboard-metric-label">Current net balance</div>
                </div>
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value"><?= count($orgs) ?></div>
                    <div class="dashboard-metric-label">Organizations in view</div>
                </div>
            </div>
        </div>

        <div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">
            <h2 class="dashboard-section-title">Finance status</h2>
            <p class="dashboard-section-copy mt-1">A compact reading of spend, balance, and workload based on live records.</p>
            <div class="mt-4 space-y-3">
                <div>
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Expense share of income</span>
                        <span class="dashboard-stat-value"><?= $expenseRatio ?>%</span>
                    </div>
                    <div class="dashboard-progress mt-3"><span style="width: <?= $expenseRatio ?>%"></span></div>
                </div>
                <div>
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Balance retained</span>
                        <span class="dashboard-stat-value"><?= $balanceRatio ?>%</span>
                    </div>
                    <div class="dashboard-progress mt-3"><span style="width: <?= $balanceRatio ?>%"></span></div>
                </div>
                <div class="dashboard-stat-list pt-1">
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Recent reports</span>
                        <span class="dashboard-stat-value"><?= $recentReportCount ?></span>
                    </div>
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Latest announcements</span>
                        <span class="dashboard-stat-value"><?= $latestAnnouncementCount ?></span>
                    </div>
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Pending assignments</span>
                        <span class="dashboard-stat-value"><?= $pendingAssignmentCount ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (count($pendingAssignments) > 0): ?>
        <section class="glass dashboard-panel p-4 md:p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="dashboard-section-title">Pending assignments</h2>
                    <p class="dashboard-section-copy mt-1">Assignments waiting for a student response.</p>
                </div>
                <div class="dashboard-stamp"><?= count($pendingAssignments) ?> awaiting action</div>
            </div>
            <div class="grid md:grid-cols-2 gap-2 mt-3">
                <?php foreach ($pendingAssignments as $assignment): ?>
                    <div class="dashboard-feed-item flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div class="w-full sm:w-auto">
                            <div class="font-medium"><?= e($assignment['organization_name']) ?></div>
                            <div class="dashboard-feed-meta mt-1">Assigned on <?= e($assignment['created_at']) ?></div>
                        </div>
                        <div class="flex gap-2">
                            <form method="post">
                                <input type="hidden" name="action" value="respond_owner_assignment">
                                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                <input type="hidden" name="decision" value="accept">
                                <button class="bg-emerald-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Accept</span></span></button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="action" value="respond_owner_assignment">
                                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                <input type="hidden" name="decision" value="decline">
                                <button class="bg-red-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Decline</span></span></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php renderPagination($pendingAssignmentsPagination); ?>
        </section>
    <?php endif; ?>

    <section class="grid xl:grid-cols-12 gap-3">
        <div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-3">
                <div>
                    <h2 class="dashboard-section-title">Monthly trend</h2>
                    <p class="dashboard-section-copy mt-1">Income and expense totals by month.</p>
                </div>
                <div class="dashboard-stamp"><?= $recentReportCount ?> recent reports tracked</div>
            </div>
            <div class="dashboard-metric-grid mb-3">
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value text-red-300">₱<?= number_format($kpiExpense, 2) ?></div>
                    <div class="dashboard-metric-label">Expense total</div>
                </div>
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value"><?= count($activityPreview) ?></div>
                    <div class="dashboard-metric-label">Activity items loaded</div>
                </div>
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value"><?= $latestAnnouncementCount ?></div>
                    <div class="dashboard-metric-label">Announcement highlights</div>
                </div>
            </div>
            <canvas id="trendChart" height="112"></canvas>
            <div class="trend-insight-grid mt-5 grid md:grid-cols-3 gap-2">
                <div class="dashboard-feed-item trend-insight-card">
                    <span class="dashboard-feed-dot"></span>
                    <div>
                        <div class="dashboard-feed-title">Current month net</div>
                        <div class="dashboard-feed-meta mt-1 <?= $latestTrendNet >= 0 ? 'text-green-300' : 'text-red-300' ?>">
                            ₱<?= number_format($latestTrendNet, 2) ?>
                        </div>
                        <div class="dashboard-feed-body mt-1"><?= e($latestTrendDirectionLabel) ?></div>
                    </div>
                </div>
                <div class="dashboard-feed-item trend-insight-card">
                    <span class="dashboard-feed-dot warn"></span>
                    <div>
                        <div class="dashboard-feed-title">Peak expense month</div>
                        <div class="dashboard-feed-meta mt-1"><?= e($peakExpenseMonth) ?></div>
                        <div class="dashboard-feed-body mt-1">₱<?= number_format($peakExpenseValue, 2) ?> spent</div>
                    </div>
                </div>
                <div class="dashboard-feed-item trend-insight-card">
                    <span class="dashboard-feed-dot"></span>
                    <div>
                        <div class="dashboard-feed-title">Healthy months</div>
                        <div class="dashboard-feed-meta mt-1"><?= $healthyMonthCount ?> of <?= $trendPointCount ?></div>
                        <div class="dashboard-feed-body mt-1">Months where income met or exceeded expense.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <h2 class="dashboard-section-title">Live activity</h2>
                    <p class="dashboard-section-copy mt-1">Recent announcements and audit items.</p>
                </div>
                <button type="button" id="openAnnouncementsModalQuick" class="text-xs underline text-indigo-100"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View all</span></span></button>
            </div>
            <div class="space-y-2">
                <?php foreach ($latestAnnouncementsPreview as $item): ?>
                    <?php $feedDotClass = (int) ($item['is_pinned'] ?? 0) === 1 ? 'dashboard-feed-dot warn' : 'dashboard-feed-dot'; ?>
                    <div class="dashboard-feed-item">
                        <span class="<?= e($feedDotClass) ?>"></span>
                        <div>
                            <div class="dashboard-feed-title"><?= e($item['title']) ?></div>
                            <div class="dashboard-feed-meta mt-1"><?= e($item['organization_name']) ?> · <?= e($item['created_at']) ?></div>
                            <div class="dashboard-feed-body mt-1"><?= e($item['content']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($activityPreview as $item): ?>
                    <div class="dashboard-feed-item">
                        <span class="dashboard-feed-dot"></span>
                        <div>
                            <div class="dashboard-feed-title"><?= e($item['label']) ?></div>
                            <div class="dashboard-feed-meta mt-1"><?= e($item['type']) ?> · <?= e($item['created_at']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (count($latestAnnouncementsPreview) === 0 && count($activityPreview) === 0): ?>
                    <p class="dashboard-section-copy">No recent announcements or activity items are available.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="dashboard-section-title">Organizations</h2>
                    <p class="dashboard-section-copy mt-1">Current organizations and join eligibility.</p>
                </div>
                <button type="button" id="openOrganizationsModal" class="text-xs underline text-indigo-100"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View all</span></span></button>
            </div>
            <div class="space-y-2">
                <?php foreach ($dashboardOrganizationsPreview as $org): ?>
                    <div class="dashboard-feed-item flex-col lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="dashboard-feed-title"><?= e($org['name']) ?></div>
                            <div class="dashboard-feed-body mt-1"><?= e($org['description']) ?></div>
                            <div class="dashboard-feed-meta mt-2">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
                            <div class="dashboard-feed-meta mt-1"><?= e(getOrganizationVisibilityLabel($org)) ?></div>
                        </div>
                        <?php if (in_array($user['role'], ['student', 'owner'], true)): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="join_org">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <?php
                                    $orgId = (int) $org['id'];
                                    $requestStatus = (string) ($joinRequestStatus[$orgId] ?? '');
                                    $isJoined = in_array($orgId, $joinedIds, true);
                                    $canJoin = canUserJoinOrganization($org, $user);
                                    $disabled = $isJoined || $requestStatus === 'pending' || !$canJoin;
                                    if (!$canJoin) {
                                        $btnClass = 'bg-slate-200/40 border-slate-300/60 text-slate-600';
                                        $label = getJoinRestrictionLabel($org);
                                    } elseif ($isJoined) {
                                        $btnClass = 'bg-white/10 border-emerald-200/30 text-slate-700';
                                        $label = 'Joined';
                                    } elseif ($requestStatus === 'pending') {
                                        $btnClass = 'bg-amber-500/25 border-amber-300/50 text-amber-900';
                                        $label = 'Requested';
                                    } else {
                                        $btnClass = 'bg-emerald-500/25 border-emerald-300/50 text-emerald-900 hover:bg-emerald-500/35';
                                        $label = 'Request Join';
                                    }
                                ?>
                                <button class="px-3 py-1 rounded text-xs border backdrop-blur-md <?= $btnClass ?>" <?= $disabled ? 'disabled' : '' ?>>
                                    <?= $label ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4 overflow-hidden">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-3">
                <div>
                    <h2 class="dashboard-section-title">Recent reports</h2>
                    <p class="dashboard-section-copy mt-1">Latest income and expense entries with receipt visibility.</p>
                </div>
                <div class="dashboard-stamp">Showing <?= $recentReportCount ?> latest items</div>
            </div>
            <table class="dashboard-table w-full text-sm table-fixed">
                <thead>
                <tr class="border-b text-left">
                    <th class="py-2 w-[20%]">Date</th>
                    <th class="w-[30%]">Organization</th>
                    <th class="w-[16%]">Type</th>
                    <th class="w-[20%]">Amount</th>
                    <th class="w-[14%]">Receipt</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $tx): ?>
                    <tr class="border-b">
                        <td class="py-2"><?= e($tx['transaction_date']) ?></td>
                        <td><?= e($tx['organization_name']) ?></td>
                        <td class="<?= $tx['type'] === 'income' ? 'text-green-700' : 'text-red-700' ?>"><?= e($tx['type']) ?></td>
                        <td>₱<?= number_format((float) $tx['amount'], 2) ?></td>
                        <td>
                            <?php if (!empty($tx['receipt_path'])): ?>
                                <a class="text-indigo-100 underline" target="_blank" href="<?= e($tx['receipt_path']) ?>"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></a>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="glass dashboard-panel xl:col-span-12 p-4 md:p-4 overflow-hidden">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-3">
                <div>
                    <h2 class="dashboard-section-title">Financial summary by organization</h2>
                    <p class="dashboard-section-copy mt-1">Income, expense, and balance grouped by organization.</p>
                </div>
                <button type="button" id="openFinancialSummaryModal" class="text-xs underline text-indigo-100"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View charts</span></span></button>
            </div>
            <div>
                <table class="dashboard-table w-full text-sm table-fixed">
                    <thead>
                    <tr class="border-b text-left">
                        <th class="py-2 pr-4 w-[46%]">Organization</th>
                        <th class="py-2 pr-3 w-[18%]">Income</th>
                        <th class="py-2 pr-3 w-[18%]">Expense</th>
                        <th class="py-2 w-[18%]">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($summary as $row): ?>
                        <?php $balance = (float) $row['total_income'] - (float) $row['total_expense']; ?>
                        <tr class="border-b">
                            <td class="py-2 pr-4"><?= e($row['name']) ?></td>
                            <td class="py-2 pr-3 text-green-700 whitespace-nowrap">₱<?= number_format((float) $row['total_income'], 2) ?></td>
                            <td class="py-2 pr-3 text-red-700 whitespace-nowrap">₱<?= number_format((float) $row['total_expense'], 2) ?></td>
                            <td class="py-2 whitespace-nowrap <?= $balance >= 0 ? 'text-green-800' : 'text-red-800' ?>">₱<?= number_format($balance, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pt-3">
                <?php renderPagination($summaryPagination); ?>
            </div>
        </div>
    </section>

    <div id="organizationsModal" class="updates-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="organizationsModalTitle">
        <div class="glass w-full max-w-5xl max-h-[86vh] overflow-hidden">
            <div class="flex items-center justify-between border-b border-emerald-200/30 px-4 py-3">
                <h3 id="organizationsModalTitle" class="text-lg font-semibold">All Organizations</h3>
                <button type="button" id="closeOrganizationsModal" class="px-2 py-1 rounded border text-sm">Close</button>
            </div>
            <div class="p-4 space-y-3 max-h-[74vh] overflow-y-auto themed-scroll pr-1">
                <?php foreach ($orgs as $org): ?>
                    <div class="border rounded p-3 flex flex-col md:flex-row justify-between items-center md:items-start gap-2 text-center md:text-left">
                        <div>
                            <div class="font-medium"><?= e($org['name']) ?></div>
                            <p class="text-sm text-gray-600"><?= e($org['description']) ?></p>
                            <div class="text-xs text-gray-500 mt-1">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
                            <div class="text-xs text-emerald-800 mt-1"><?= e(getOrganizationVisibilityLabel($org)) ?></div>
                        </div>
                        <?php if (in_array($user['role'], ['student', 'owner'], true)): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="join_org">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <?php
                                    $orgId = (int) $org['id'];
                                    $requestStatus = (string) ($joinRequestStatus[$orgId] ?? '');
                                    $isJoined = in_array($orgId, $joinedIds, true);
                                    $canJoin = canUserJoinOrganization($org, $user);
                                    $disabled = $isJoined || $requestStatus === 'pending' || !$canJoin;
                                    if (!$canJoin) {
                                        $btnClass = 'bg-slate-200/40 border-slate-300/60 text-slate-600';
                                        $label = getJoinRestrictionLabel($org);
                                    } elseif ($isJoined) {
                                        $btnClass = 'bg-white/10 border-emerald-200/30 text-slate-700';
                                        $label = 'Joined';
                                    } elseif ($requestStatus === 'pending') {
                                        $btnClass = 'bg-amber-500/25 border-amber-300/50 text-amber-900';
                                        $label = 'Requested';
                                    } else {
                                        $btnClass = 'bg-emerald-500/25 border-emerald-300/50 text-emerald-900 hover:bg-emerald-500/35';
                                        $label = 'Request Join';
                                    }
                                ?>
                                <button class="px-3 py-1 rounded text-xs border backdrop-blur-md <?= $btnClass ?>" <?= $disabled ? 'disabled' : '' ?>>
                                    <?= $label ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="announcementsModal" class="updates-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="announcementsModalTitle">
        <div class="glass w-full max-w-5xl max-h-[86vh] overflow-hidden">
            <div class="flex items-center justify-between border-b border-emerald-200/30 px-4 py-3">
                <h3 id="announcementsModalTitle" class="text-lg font-semibold">All Latest Announcements</h3>
                <button type="button" id="closeAnnouncementsModal" class="px-2 py-1 rounded border text-sm">Close</button>
            </div>
            <div class="p-4 space-y-3 max-h-[74vh] overflow-y-auto themed-scroll pr-1">
                <?php foreach ($announcements as $item): ?>
                    <div class="border rounded p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-medium"><?= e($item['title']) ?></div>
                            <?php if ((int) ($item['is_pinned'] ?? 0) === 1): ?>
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-500/25 border border-amber-300/40">Important</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-500"><?= e($item['organization_name']) ?> · <?= e($item['created_at']) ?></div>
                        <div class="text-sm mt-1"><?= e($item['content']) ?></div>
                        <?php if (($user['role'] ?? '') === 'admin'): ?>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="action" value="<?= (int) ($item['is_pinned'] ?? 0) === 1 ? 'unpin_announcement_admin' : 'pin_announcement_admin' ?>">
                                <input type="hidden" name="announcement_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="return_page" value="dashboard">
                                <button class="px-2 py-1 rounded text-xs border">
                                    <?= (int) ($item['is_pinned'] ?? 0) === 1 ? 'Unpin' : 'Pin as Important' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (count($announcements) === 0): ?>
                    <p class="text-sm text-gray-600">No announcements in the last 30 days.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="financialSummaryModal" class="updates-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="financialSummaryModalTitle">
        <div class="glass w-full max-w-6xl overflow-hidden">
            <div class="flex items-center justify-between border-b border-emerald-200/30 px-4 py-3">
                <h3 id="financialSummaryModalTitle" class="text-lg font-semibold">Financial Health Snapshot</h3>
                <button type="button" id="closeFinancialSummaryModal" class="px-2 py-1 rounded border text-sm">Close</button>
            </div>
            <div class="p-3 space-y-2 max-h-[calc(100dvh-8.5rem)] overflow-y-auto themed-scroll pr-1">
                <div class="grid md:grid-cols-3 gap-1">
                    <div class="dashboard-metric-card">
                        <div class="dashboard-metric-value text-green-300">₱<?= number_format($summaryIncomeTotal, 2) ?></div>
                        <div class="dashboard-metric-label">Total income (all organizations)</div>
                    </div>
                    <div class="dashboard-metric-card">
                        <div class="dashboard-metric-value text-red-300">₱<?= number_format($summaryExpenseTotal, 2) ?></div>
                        <div class="dashboard-metric-label">Total expense (all organizations)</div>
                    </div>
                    <div class="dashboard-metric-card">
                        <div class="dashboard-metric-value <?= $summaryNetTotal >= 0 ? 'text-green-300' : 'text-red-300' ?>">₱<?= number_format($summaryNetTotal, 2) ?></div>
                        <div class="dashboard-metric-label">Net balance (all organizations)</div>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-2">
                    <div class="glass p-2 w-full h-full flex flex-col">
                        <h4 class="dashboard-section-title">Top Organizations by Net Balance</h4>
                        <p class="dashboard-section-copy mt-1">Higher bars indicate stronger surplus after expenses.</p>
                        <canvas id="financialSummaryRankingChart" height="165"></canvas>
                        <div class="mt-2 grid sm:grid-cols-3 gap-1">
                            <div class="dashboard-feed-item trend-insight-card">
                                <div>
                                    <div class="dashboard-feed-title">Top performer</div>
                                    <div class="dashboard-feed-meta mt-1"><?= e((string) ($topPerformer['name'] ?? 'N/A')) ?></div>
                                    <div class="dashboard-feed-body mt-1 text-green-300">₱<?= number_format((float) ($topPerformer['balance'] ?? 0), 2) ?></div>
                                </div>
                            </div>
                            <div class="dashboard-feed-item trend-insight-card">
                                <div>
                                    <div class="dashboard-feed-title">Highest spend pressure</div>
                                    <div class="dashboard-feed-meta mt-1"><?= e((string) ($highestPressure['name'] ?? 'N/A')) ?></div>
                                    <div class="dashboard-feed-body mt-1"><?= (int) round(((float) ($highestPressure['expense_ratio'] ?? 0)) * 100) ?>% expense ratio</div>
                                </div>
                            </div>
                            <div class="dashboard-feed-item trend-insight-card">
                                <div>
                                    <div class="dashboard-feed-title">Average net</div>
                                    <div class="dashboard-feed-meta mt-1">Across <?= count($summaryRankingRows) ?> organizations</div>
                                    <div class="dashboard-feed-body mt-1 <?= $averageNet >= 0 ? 'text-green-300' : 'text-red-300' ?>">₱<?= number_format($averageNet, 2) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="glass p-2 w-full h-full space-y-2">
                        <div>
                            <h4 class="dashboard-section-title">Organization Priority List</h4>
                            <p class="dashboard-section-copy mt-1">Status reflects spending pressure and current balance.</p>
                        </div>
                        <div class="space-y-1.5">
                            <?php foreach (array_slice($summaryRankingRows, 0, 4) as $row): ?>
                                <div class="dashboard-feed-item trend-insight-card items-center justify-between">
                                    <div>
                                        <div class="dashboard-feed-title"><?= e($row['name']) ?></div>
                                        <div class="dashboard-feed-meta mt-1">Net: ₱<?= number_format((float) $row['balance'], 2) ?></div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] font-medium <?= e($row['status_class']) ?>"><?= e($row['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <div class="dashboard-section-title">Needs Attention</div>
                            <div class="mt-1 space-y-1">
                                <?php if (count($summaryAttentionRows) === 0): ?>
                                    <p class="dashboard-section-copy">No organizations are currently flagged for risk or heavy spend pressure.</p>
                                <?php else: ?>
                                    <?php foreach ($summaryAttentionRows as $row): ?>
                                        <div class="dashboard-feed-meta"><?= e($row['name']) ?> · Expense ratio <?= (int) round($row['expense_ratio'] * 100) ?>%</div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        const canvas = document.getElementById('trendChart');
        if (!canvas) return;

        const labels = <?= json_encode($trendLabels) ?>;
        const income = <?= json_encode($trendIncome) ?>;
        const expense = <?= json_encode($trendExpense) ?>;
        const summaryRankingLabels = <?= json_encode($summaryRankingLabels) ?>;
        const summaryRankingBalances = <?= json_encode($summaryRankingBalances) ?>;

        const isDark = document.body.classList.contains('theme-dark');
        const axisColor = isDark ? '#a7f3d0' : '#065f46';
        const legendColor = isDark ? '#d1fae5' : '#14532d';
        const gridColor = isDark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Income',
                        data: income,
                        borderColor: '#34d399',
                        backgroundColor: 'rgba(52, 211, 153, 0.2)',
                        fill: true,
                        tension: 0.35
                    },
                    {
                        label: 'Expense',
                        data: expense,
                        borderColor: '#f87171',
                        backgroundColor: 'rgba(248, 113, 113, 0.16)',
                        fill: true,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color: legendColor } }
                },
                scales: {
                    x: { ticks: { color: axisColor }, grid: { color: gridColor } },
                    y: { ticks: { color: axisColor }, grid: { color: gridColor } }
                }
            }
        });

        let financialRankingChart = null;

        const createFinancialCharts = function () {
            if (financialRankingChart) {
                return;
            }

            const rankingCanvas = document.getElementById('financialSummaryRankingChart');
            if (!rankingCanvas || summaryRankingLabels.length === 0) {
                return;
            }

            financialRankingChart = new Chart(rankingCanvas, {
                type: 'bar',
                data: {
                    labels: summaryRankingLabels,
                    datasets: [
                        {
                            label: 'Net Balance',
                            data: summaryRankingBalances,
                            backgroundColor: summaryRankingBalances.map(function (value) {
                                return value >= 0 ? 'rgba(52, 211, 153, 0.75)' : 'rgba(248, 113, 113, 0.72)';
                            }),
                            borderColor: summaryRankingBalances.map(function (value) {
                                return value >= 0 ? 'rgba(16, 185, 129, 1)' : 'rgba(239, 68, 68, 1)';
                            }),
                            borderWidth: 1,
                            borderRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            labels: { color: legendColor }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: axisColor },
                            grid: { color: gridColor }
                        },
                        y: {
                            ticks: { color: axisColor },
                            grid: { color: 'rgba(0,0,0,0)' }
                        }
                    }
                }
            });
        };

        const applyThemeToCharts = function () {
            const dark = document.body.classList.contains('theme-dark');
            const nextAxis = dark ? '#a7f3d0' : '#065f46';
            const nextLegend = dark ? '#d1fae5' : '#14532d';
            const nextGrid = dark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

            chart.options.plugins.legend.labels.color = nextLegend;
            chart.options.scales.x.ticks.color = nextAxis;
            chart.options.scales.y.ticks.color = nextAxis;
            chart.options.scales.x.grid.color = nextGrid;
            chart.options.scales.y.grid.color = nextGrid;
            chart.update();

            if (financialRankingChart) {
                financialRankingChart.options.plugins.legend.labels.color = nextLegend;
                financialRankingChart.options.scales.x.ticks.color = nextAxis;
                financialRankingChart.options.scales.y.ticks.color = nextAxis;
                financialRankingChart.options.scales.x.grid.color = nextGrid;
                financialRankingChart.update();
            }
        };

        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('change', function () {
                applyThemeToCharts();
            });
        }

        const organizationsModal = document.getElementById('organizationsModal');
        const announcementsModal = document.getElementById('announcementsModal');
        const financialSummaryModal = document.getElementById('financialSummaryModal');
        const openOrganizations = [
            document.getElementById('openOrganizationsModal'),
            document.getElementById('openOrganizationsModalQuick'),
        ].filter(Boolean);
        const openAnnouncements = [
            document.getElementById('openAnnouncementsModalQuick'),
        ].filter(Boolean);
        const openFinancialSummary = [
            document.getElementById('openFinancialSummaryModal'),
        ].filter(Boolean);
        const closeOrganizationsModal = document.getElementById('closeOrganizationsModal');
        const closeAnnouncementsModal = document.getElementById('closeAnnouncementsModal');
        const closeFinancialSummaryModal = document.getElementById('closeFinancialSummaryModal');

        const showModal = function (modal) {
            if (!modal) return;
            modal.classList.remove('hidden');
        };

        const hideModal = function (modal) {
            if (!modal) return;
            modal.classList.add('hidden');
        };

        openOrganizations.forEach(function (button) {
            button.addEventListener('click', function () {
                showModal(organizationsModal);
            });
        });

        openAnnouncements.forEach(function (button) {
            button.addEventListener('click', function () {
                showModal(announcementsModal);
            });
        });

        openFinancialSummary.forEach(function (button) {
            button.addEventListener('click', function () {
                showModal(financialSummaryModal);
                createFinancialCharts();
                applyThemeToCharts();
            });
        });

        if (closeOrganizationsModal) {
            closeOrganizationsModal.addEventListener('click', function () {
                hideModal(organizationsModal);
            });
        }

        if (closeAnnouncementsModal) {
            closeAnnouncementsModal.addEventListener('click', function () {
                hideModal(announcementsModal);
            });
        }

        if (closeFinancialSummaryModal) {
            closeFinancialSummaryModal.addEventListener('click', function () {
                hideModal(financialSummaryModal);
            });
        }

        if (organizationsModal) {
            organizationsModal.addEventListener('click', function (event) {
                if (event.target === organizationsModal) {
                    hideModal(organizationsModal);
                }
            });
        }

        if (announcementsModal) {
            announcementsModal.addEventListener('click', function (event) {
                if (event.target === announcementsModal) {
                    hideModal(announcementsModal);
                }
            });
        }

        if (financialSummaryModal) {
            financialSummaryModal.addEventListener('click', function (event) {
                if (event.target === financialSummaryModal) {
                    hideModal(financialSummaryModal);
                }
            });
        }
    })();
</script>
<?php
renderFooter();
