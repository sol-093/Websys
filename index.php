<?php

declare(strict_types=1);

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    error_log('Composer autoload not found. Run `composer install` once dependencies are available.');
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        error_log('Security headers were not sent because headers have already been sent.');
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' https://accounts.google.com; frame-ancestors 'none'");

    $https = (string) ($_SERVER['HTTPS'] ?? '');
    if ($https !== '' && strtolower($https) !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function handleGlobalSearchAction(PDO $db): void
{
    header('Content-Type: application/json; charset=UTF-8');

    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['results' => []], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $query = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($query) < 2) {
        http_response_code(400);
        echo json_encode(['results' => []], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $normalizedQuery = mb_strtolower($query);
    $likeQuery = '%' . $normalizedQuery . '%';

    $results = [];

    $addResult = static function (array &$results, string $type, string $label, string $sublabel, string $url, string $matchText, string $queryText, array $scoringHints = []): void {
        $normalizedMatch = mb_strtolower(trim($matchText));
        $normalizedQueryText = mb_strtolower(trim($queryText));

        $score = 0;
        if ($normalizedMatch === $normalizedQueryText) {
            $score += 300;
        } elseif (str_starts_with($normalizedMatch, $normalizedQueryText)) {
            $score += 200;
        } elseif (str_contains($normalizedMatch, $normalizedQueryText)) {
            $score += 120;
        }

        foreach ($scoringHints as $hint) {
            if ($hint === 'exact') {
                $score += 100;
            } elseif ($hint === 'primary') {
                $score += 40;
            }
        }

        $results[] = [
            'type' => $type,
            'label' => $label,
            'sublabel' => $sublabel,
            'url' => $url,
            '_score' => $score,
            '_label' => mb_strtolower($label),
        ];
    };

    $userStmt = $db->prepare('SELECT id, name, email, role FROM users WHERE LOWER(name) LIKE ? OR LOWER(email) LIKE ? LIMIT 20');
    $userStmt->execute([$likeQuery, $likeQuery]);
    foreach ($userStmt->fetchAll() as $row) {
        $name = (string) $row['name'];
        $email = (string) $row['email'];
        $role = (string) $row['role'];
        $userUrl = (($user['role'] ?? '') === 'admin')
            ? '?page=admin_students&q=' . rawurlencode($query)
            : (((string) ($row['id'] ?? '') === (string) ($user['id'] ?? '')) ? '?page=profile' : '?page=dashboard');
        $addResult(
            $results,
            'user',
            $name,
            strtoupper($role) . ' • ' . $email,
            $userUrl,
            $name . ' ' . $email,
            $query,
            [($name === $query || $email === $query) ? 'exact' : 'primary']
        );
    }

    $orgStmt = $db->prepare('SELECT id, name, description, org_category FROM organizations WHERE LOWER(name) LIKE ? OR LOWER(COALESCE(org_category, "")) LIKE ? LIMIT 20');
    $orgStmt->execute([$likeQuery, $likeQuery]);
    foreach ($orgStmt->fetchAll() as $row) {
        $name = (string) $row['name'];
        $category = trim((string) ($row['org_category'] ?? 'collegewide'));
        $description = trim((string) ($row['description'] ?? ''));
        $sublabel = $category !== '' ? 'Category: ' . $category : 'Organization';
        if ($description !== '') {
            $sublabel .= ' • ' . mb_substr(preg_replace('/\s+/', ' ', $description), 0, 90);
        }

        $addResult(
            $results,
            'org',
            $name,
            $sublabel,
            '?page=organizations',
            $name . ' ' . $category,
            $query,
            [($name === $query || mb_strtolower($category) === $normalizedQuery) ? 'exact' : 'primary']
        );
    }

    $announcementStmt = $db->prepare('SELECT a.id, a.title, a.content, o.name AS organization_name
        FROM announcements a
        JOIN organizations o ON o.id = a.organization_id
        WHERE LOWER(a.title) LIKE ? OR LOWER(a.content) LIKE ?
        LIMIT 20');
    $announcementStmt->execute([$likeQuery, $likeQuery]);
    foreach ($announcementStmt->fetchAll() as $row) {
        $title = (string) $row['title'];
        $content = preg_replace('/\s+/', ' ', trim(strip_tags((string) ($row['content'] ?? ''))));
        $snippet = $content !== '' ? mb_substr($content, 0, 90) : 'Announcement';
        $sublabel = (string) $row['organization_name'];
        if ($snippet !== '') {
            $sublabel .= ' • ' . $snippet;
        }

        $addResult(
            $results,
            'announcement',
            $title,
            $sublabel,
            '?page=announcements',
            $title . ' ' . $content,
            $query,
            [mb_strtolower($title) === $normalizedQuery ? 'exact' : 'primary']
        );
    }

    usort($results, static function (array $left, array $right): int {
        $scoreDiff = ($right['_score'] <=> $left['_score']);
        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        return $left['_label'] <=> $right['_label'];
    });

    $results = array_slice($results, 0, 10);
    $results = array_map(static function (array $result): array {
        unset($result['_score'], $result['_label']);
        return $result;
    }, $results);

    echo json_encode(['results' => $results], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();
sendSecurityHeaders();

$config = require __DIR__ . '/src/core/config.php';
$appTimezone = (string) ($config['timezone'] ?? 'Asia/Manila');
if (!date_default_timezone_set($appTimezone)) {
    date_default_timezone_set('Asia/Manila');
}

require __DIR__ . '/src/core/db.php';
require __DIR__ . '/src/core/helpers.php';
require __DIR__ . '/src/core/auth.php';
require __DIR__ . '/src/core/layout.php';
require __DIR__ . '/src/lib/pagination.php';
require __DIR__ . '/src/lib/organization.php';
require __DIR__ . '/src/lib/notifications.php';
require __DIR__ . '/src/lib/integrations.php';
require __DIR__ . '/src/lib/email.php';
require __DIR__ . '/src/lib/uploads.php';
require __DIR__ . '/src/lib/maintenance.php';
require __DIR__ . '/src/actions/auth_flows.php';
require __DIR__ . '/src/actions/workflows.php';
require __DIR__ . '/src/actions/content_actions.php';
require __DIR__ . '/src/pages/public_pages.php';
require __DIR__ . '/src/pages/admin_pages.php';
require __DIR__ . '/src/pages/community_pages.php';
require __DIR__ . '/src/pages/owner_pages.php';
require __DIR__ . '/src/services/dashboard_data.php';
require __DIR__ . '/src/pages/dashboard_page.php';

try {
    $db = db();
} catch (Throwable $e) {
    error_log('[websys] Database bootstrap failed: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    $debug = strtolower((string) getenv('APP_DEBUG'));
    $showDetails = in_array($debug, ['1', 'true', 'yes', 'on'], true);
    $requiredEnvGroups = [
        ['DB_HOST', 'MYSQLHOST'],
        ['DB_PORT', 'MYSQLPORT'],
        ['DB_DATABASE', 'MYSQLDATABASE', 'MYSQL_DATABASE'],
        ['DB_USERNAME', 'MYSQLUSER'],
        ['DB_PASSWORD', 'MYSQLPASSWORD'],
    ];

    $missingGroups = [];
    foreach ($requiredEnvGroups as $group) {
        $hasAny = false;
        foreach ($group as $name) {
            $value = getenv($name);
            if ($value !== false && $value !== '') {
                $hasAny = true;
                break;
            }
        }

        if (!$hasAny) {
            $missingGroups[] = implode(' or ', $group);
        }
    }

    $detail = $showDetails ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : 'Check Railway variables and service logs.';
    if (!$showDetails && !empty($missingGroups)) {
        $detail .= ' Missing variable groups: ' . htmlspecialchars(implode(', ', $missingGroups), ENT_QUOTES, 'UTF-8') . '.';
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Websys Startup Error</title></head><body style="font-family:Arial,sans-serif;padding:24px">';
    echo '<h1>Application startup error</h1>';
    echo '<p>Database connection failed while starting the app.</p>';
    echo '<p><strong>Details:</strong> ' . $detail . '</p>';
    echo '<p>Expected environment variables for MySQL: DB_DRIVER=mysql, DB_HOST/MYSQLHOST, DB_PORT/MYSQLPORT, DB_DATABASE/MYSQLDATABASE/MYSQL_DATABASE, DB_USERNAME/MYSQLUSER, DB_PASSWORD/MYSQLPASSWORD.</p>';
    echo '</body></html>';
    exit;
}

$user = currentUser();
$page = $_GET['page'] ?? ($user ? 'dashboard' : 'home');

if (($_GET['action'] ?? '') === 'search') {
    handleGlobalSearchAction($db);
}

if (($_GET['action'] ?? '') === 'export_transactions') {
    handleExportTransactionsAction($db, $user);
}

if ($page === 'google_login') {
    handleGoogleLoginPage($config);
}

if ($page === 'google_callback') {
    handleGoogleCallbackPage($db, $config);
}

csrfMiddleware();

if (isPost()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        handleRegisterAction($db);
    }

    if ($action === 'login') {
        handleLoginAction($db);
    }

    if ($action === 'resend_verification') {
        handleResendVerificationAction($db);
    }

    if ($action === 'forgot_password') {
        handleForgotPasswordAction($db);
    }

    if ($action === 'reset_password') {
        handleResetPasswordAction($db);
    }

    requireLogin();
    $user = currentUser();

    if ($action === 'change_password') {
        if (($user['role'] ?? '') === 'admin') {
            setFlash('error', 'Profile settings are not available for admin accounts.');
            redirect('?page=dashboard');
        }
        handleChangePasswordAction($db, $user);
    }

    if ($action === 'update_profile') {
        if (($user['role'] ?? '') === 'admin') {
            setFlash('error', 'Profile settings are not available for admin accounts.');
            redirect('?page=dashboard');
        }
        handleUpdateProfileAction($db, $user);
    }

    if ($action === 'create_org') {
        handleCreateOrgAction($db, $user);
    }

    if ($action === 'update_org_admin') {
        handleUpdateOrgAdminAction($db, $user);
    }

    if ($action === 'delete_org') {
        handleDeleteOrgAction($db, $user);
    }

    if ($action === 'assign_owner') {
        handleAssignOwnerAction($db, $user);
    }

    if ($action === 'process_tx_change_request') {
        handleProcessTxChangeRequestAction($db, $user);
    }

    if ($action === 'respond_owner_assignment') {
        handleRespondOwnerAssignmentAction($db, $user);
    }

    if ($action === 'join_org') {
        handleJoinOrgAction($db, $user);
    }

    if ($action === 'respond_join_request') {
        handleRespondJoinRequestAction($db, $user);
    }

    if ($action === 'update_my_org') {
        handleUpdateMyOrgAction($db, $user);
    }

    if ($action === 'add_announcement') {
        handleAddAnnouncementAction($db, $user);
    }

    if ($action === 'delete_announcement') {
        handleDeleteAnnouncementAction($db, $user);
    }

    if ($action === 'pin_announcement_admin') {
        handlePinAnnouncementAdminAction($db, $user);
    }

    if ($action === 'unpin_announcement_admin') {
        handleUnpinAnnouncementAdminAction($db, $user);
    }

    if ($action === 'add_transaction') {
        handleAddTransactionAction($db, $user, $config);
    }

    if ($action === 'update_transaction') {
        handleUpdateTransactionAction($db, $user);
    }

    if ($action === 'delete_transaction') {
        handleDeleteTransactionAction($db, $user);
    }

    if ($action === 'complete_onboarding') {
        handleCompleteOnboardingAction($db, $user);
    }

    if ($action === 'restart_onboarding') {
        handleRestartOnboardingAction($db, $user);
    }
}

if ($page === 'logout') {
    handleLogoutPage();
}

if ($page === 'home') {
    handleHomePage($db, $user);
}

if ($page === 'login') {
    handleLoginPage($config);
}

if ($page === 'register') {
    handleRegisterPage();
}

if ($page === 'verify_email') {
    handleVerifyEmailPage();
}

if ($page === 'forgot_password') {
    handleForgotPasswordPage();
}

if ($page === 'reset_password') {
    handleResetPasswordPage($db);
}

requireLogin();
$user = currentUser();
$announcementCutoff = (new DateTimeImmutable('now'))->modify('-30 days')->format('Y-m-d H:i:s');
$recentReportCutoffDate = (new DateTimeImmutable('now'))->modify('-30 days')->format('Y-m-d');
purgeExpiredAnnouncements($db, 30);

if ($page === 'profile') {
    if (($user['role'] ?? '') === 'admin') {
        setFlash('error', 'Profile settings are not available for admin accounts.');
        redirect('?page=dashboard');
    }
    handleProfilePage($user);
}

if ($page === 'admin_orgs') {
    handleAdminOrgsPage($db);
}

if ($page === 'admin_students') {
    handleAdminStudentsPage($db);
}

if ($page === 'admin_requests') {
    handleAdminRequestsPage($db);
}

if ($page === 'admin_audit') {
    handleAdminAuditPage($db, $user);
}

if ($page === 'announcements') {
    handleAnnouncementsPage($db, $user, $announcementCutoff);
}

if ($page === 'organizations') {
    handleOrganizationsPage($db, $user);
}

if ($page === 'my_org') {
    if ($user['role'] === 'admin') {
        handleMyOrgAdminPage($db);
    }

    handleMyOrgUserOverviewPage($db, $user);
}

if ($page === 'my_org_manage') {
    handleMyOrgOwnerPage($db, $user, $announcementCutoff);
}

// Default Dashboard (all logged-in users)
$dashboardData = buildDashboardViewData($db, $user, $config, $announcementCutoff, $recentReportCutoffDate);

handleDashboardPage($dashboardData, $user);

