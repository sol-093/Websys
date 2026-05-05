<?php

declare(strict_types=1);

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
