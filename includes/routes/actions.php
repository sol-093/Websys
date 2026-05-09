<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - ACTION ROUTER
 * ================================================
 *
 * SECTION MAP:
 * 1. GET Actions
 * 2. OAuth Pages
 * 3. Public POST Actions
 * 4. Authenticated POST Actions
 *
 * WORK GUIDE:
 * - Add ?action=... handlers in section 1.
 * - Add form action values in sections 3 or 4.
 * - Keep business logic in includes/features/* files.
 * ================================================
 */

if (($_GET['action'] ?? '') === 'search') {
    handleGlobalSearchAction($db);
}

if (($_GET['action'] ?? '') === 'export_transactions') {
    rateLimitOrRedirect('export_transactions:' . (int) ($user['id'] ?? 0) . ':' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'export', 'Too many exports requested. Please wait a few minutes and try again.', '?page=dashboard');
    handleExportTransactionsAction($db, $user);
}

if (($_GET['action'] ?? '') === 'export_budget_overview') {
    rateLimitOrRedirect('export_budget_overview:' . (int) ($user['id'] ?? 0) . ':' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'export', 'Too many exports requested. Please wait a few minutes and try again.', '?page=admin_budget_overview');
    handleExportBudgetOverviewAction($db);
}

if (($_GET['action'] ?? '') === 'export_expense_requests') {
    rateLimitOrRedirect('export_expense_requests:' . (int) ($user['id'] ?? 0) . ':' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'export', 'Too many exports requested. Please wait a few minutes and try again.', '?page=admin_expense_requests');
    handleExportExpenseRequestsAction($db);
}

if (($_GET['action'] ?? '') === 'export_owner_budget') {
    rateLimitOrRedirect('export_owner_budget:' . (int) ($user['id'] ?? 0) . ':' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'export', 'Too many exports requested. Please wait a few minutes and try again.', '?page=my_org_budget');
    handleExportOwnerBudgetAction($db, $user);
}

if ($page === 'google_login') {
    handleGoogleLoginPage($config);
}

if ($page === 'google_callback') {
    handleGoogleCallbackPage($db, $config);
}

// All POST actions pass through the shared CSRF gate before any handler runs.
csrfMiddleware();

if (isPost()) {
    $action = $_POST['action'] ?? '';
    $cacheInvalidatingActions = [
        'create_org',
        'update_org_admin',
        'delete_org',
        'assign_owner',
        'respond_owner_assignment',
        'join_org',
        'respond_join_request',
        'remove_org_member',
        'update_my_org',
        'add_announcement',
        'delete_announcement',
        'pin_announcement_admin',
        'unpin_announcement_admin',
        'add_transaction',
        'update_transaction',
        'delete_transaction',
        'process_tx_change_request',
        'create_budget',
        'add_budget_line_item',
        'update_budget_status',
        'submit_expense_request',
        'process_expense_request',
    ];

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

    // Authenticated POST actions are gated here; privileged handlers add requirePermission() locally.
    requireLogin();
    $user = currentUser();

    if (in_array($action, $cacheInvalidatingActions, true)) {
        invalidatePerformanceCaches();
    }

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

    if ($action === 'remove_org_member') {
        handleRemoveOrganizationMemberAction($db, $user);
    }

    if ($action === 'update_my_org') {
        handleUpdateMyOrgAction($db, $user);
    }

    if ($action === 'create_budget') {
        handleCreateBudgetAction($db, $user);
    }

    if ($action === 'add_budget_line_item') {
        handleAddBudgetLineItemAction($db, $user);
    }

    if ($action === 'update_budget_status') {
        handleUpdateBudgetStatusAction($db, $user);
    }

    if ($action === 'submit_expense_request') {
        handleSubmitExpenseRequestAction($db, $user, $config);
    }

    if ($action === 'process_expense_request') {
        handleProcessExpenseRequestAction($db, $user);
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
