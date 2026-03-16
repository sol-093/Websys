<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/src/core/db.php';
require __DIR__ . '/src/core/helpers.php';
require __DIR__ . '/src/core/auth.php';
require __DIR__ . '/src/core/layout.php';
require __DIR__ . '/src/lib/pagination.php';
require __DIR__ . '/src/lib/organization.php';
require __DIR__ . '/src/lib/notifications.php';
require __DIR__ . '/src/lib/integrations.php';
require __DIR__ . '/src/lib/email.php';
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

$config = require __DIR__ . '/src/core/config.php';

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

if ($page === 'google_login') {
    handleGoogleLoginPage($config);
}

if ($page === 'google_callback') {
    handleGoogleCallbackPage($db, $config);
}

if (isPost()) {
    if (!verifyCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
        setFlash('error', 'Invalid form session. Please try again.');
        $fallbackPage = (string) ($_GET['page'] ?? ($user ? 'dashboard' : 'login'));
        redirect('?page=' . urlencode($fallbackPage));
    }

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
    handleResetPasswordPage();
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

    handleMyOrgOwnerPage($db, $user, $announcementCutoff);
}

// Default Dashboard (all logged-in users)
$dashboardData = buildDashboardViewData($db, $user, $config, $announcementCutoff, $recentReportCutoffDate);

handleDashboardPage($dashboardData, $user);

