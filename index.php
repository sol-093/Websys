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

$db = db();
$config = require __DIR__ . '/src/core/config.php';

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

    requireLogin();
    $user = currentUser();

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

requireLogin();
$user = currentUser();
$announcementCutoff = (new DateTimeImmutable('now'))->modify('-30 days')->format('Y-m-d H:i:s');
$recentReportCutoffDate = (new DateTimeImmutable('now'))->modify('-30 days')->format('Y-m-d');
purgeExpiredAnnouncements($db, 30);

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

