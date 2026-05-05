<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - PAGE ROUTER
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. Public Pages
 * 2. Auth Gate
 * 3. Authenticated Pages
 * 4. Dashboard Fallback
 *
 * EDIT GUIDE:
 * - Add new ?page=... routes here.
 * - Keep page markup in src/features/* page files.
 * - Put access checks near the route they protect.
 * ================================================
 */

if ($page === 'logout') {
    handleLogoutPage();
}

if ($page === 'home') {
    handleHomePage($db, $user);
}

if ($page === 'about') {
    handleAboutPage($user);
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
