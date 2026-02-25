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
    setFlash('success', 'Welcome, ' . $userName . '!');
    redirect('?page=dashboard');
}

if (isPost()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $privacyConsent = (string) ($_POST['privacy_consent'] ?? '') === '1';

        if ($name === '' || $email === '' || $password === '') {
            setFlash('error', 'Please fill all registration fields.');
            redirect('?page=register');
        }

        if (!$privacyConsent) {
            setFlash('error', 'You must agree to the Data Privacy Consent before registering.');
            redirect('?page=register');
        }

        try {
            $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'student']);
            setFlash('success', 'Registration successful. You can now login.');
            redirect('?page=login');
        } catch (Throwable $e) {
            setFlash('error', 'Email already exists.');
            redirect('?page=register');
        }
    }

    if ($action === 'login') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $candidate = $stmt->fetch();

        if (!$candidate || !password_verify($password, $candidate['password_hash'])) {
            setFlash('error', 'Invalid credentials.');
            redirect('?page=login');
        }

        $_SESSION['user_id'] = (int) $candidate['id'];
        setFlash('success', 'Welcome back, ' . $candidate['name'] . '!');
        redirect('?page=dashboard');
    }

    requireLogin();
    $user = currentUser();

    if ($action === 'create_org') {
        requireRole(['admin']);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            setFlash('error', 'Organization name is required.');
            redirect('?page=admin_orgs');
        }

        try {
            $stmt = $db->prepare('INSERT INTO organizations (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description]);
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

        $stmt = $db->prepare('UPDATE organizations SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $description, $orgId]);
        setFlash('success', 'Organization updated.');
        redirect('?page=admin_orgs');
    }

    if ($action === 'delete_org') {
        requireRole(['admin']);
        $orgId = (int) ($_POST['org_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
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
                setFlash('success', 'Transaction change request approved.');
            } else {
                $stmt = $db->prepare('UPDATE transaction_change_requests SET status = ?, admin_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['rejected', $adminNote, $requestId]);
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

                setFlash('success', 'You accepted the owner assignment.');
            } else {
                $stmt = $db->prepare('UPDATE owner_assignments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['declined', $assignmentId]);

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

        $stmt = $db->prepare('SELECT COUNT(*) FROM organization_members WHERE organization_id = ? AND user_id = ?');
        $stmt->execute([$orgId, (int) $user['id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            setFlash('error', 'You are already a member of this organization.');
            redirect('?page=dashboard');
        }

        try {
            $stmt = $db->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status) VALUES (?, ?, ?)');
            $stmt->execute([$orgId, (int) $user['id'], 'pending']);
            setFlash('success', 'Join request sent. Please wait for approval.');
        } catch (Throwable $e) {
            $stmt = $db->prepare('SELECT status FROM organization_join_requests WHERE organization_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$orgId, (int) $user['id']]);
            $existingStatus = (string) ($stmt->fetchColumn() ?: 'pending');
            if ($existingStatus === 'declined') {
                $stmt = $db->prepare('UPDATE organization_join_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE organization_id = ? AND user_id = ?');
                $stmt->execute(['pending', $orgId, (int) $user['id']]);
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

                setFlash('success', 'Join request approved.');
            } else {
                $stmt = $db->prepare('UPDATE organization_join_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute(['declined', $requestId]);
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

        if (!empty($_FILES['receipt']['name']) && is_uploaded_file($_FILES['receipt']['tmp_name'])) {
            ensureUploadDir($config['upload_dir']);
            $ext = strtolower(pathinfo((string) $_FILES['receipt']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            if (in_array($ext, $allowed, true)) {
                $filename = uniqid('receipt_', true) . '.' . $ext;
                $target = $config['upload_dir'] . '/' . $filename;
                if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target)) {
                    $receiptPath = 'public/uploads/' . $filename;
                }
            }
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
                    <a href="?page=dashboard" class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition shadow-[0_0_22px_rgba(45,212,191,0.45)] hover:shadow-[0_0_30px_rgba(45,212,191,0.62)]">Open Dashboard</a>
                <?php else: ?>
                    <a href="?page=register" class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition shadow-[0_0_22px_rgba(45,212,191,0.45)] hover:shadow-[0_0_30px_rgba(45,212,191,0.62)]">Get Started</a>
                    <a href="?page=login" class="border border-emerald-200/50 text-emerald-800 px-5 py-2.5 rounded-lg hover:bg-white/30">Login</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass lg:col-span-4 p-6 snapshot-panel">
            <h2 class="text-lg font-semibold mb-4 snapshot-title">Platform Snapshot</h2>
            <div class="space-y-3 text-sm snapshot-list">
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label">Organizations</span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $orgCount ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label">Students & Owners</span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $memberCount ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label">Announcements</span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $announcementCount ?></span>
                </div>
            </div>
        </div>

        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2">For Admin</h3>
            <p class="text-sm text-slate-600">Create organizations, assign one owner, and filter all student records.</p>
        </div>
        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2">For Owners</h3>
            <p class="text-sm text-slate-600">Update organization profile, post announcements, and maintain income/expense logs.</p>
        </div>
        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2">For Students</h3>
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
        <h1 class="text-2xl font-semibold mb-1">Welcome back</h1>
        <p class="text-sm text-slate-600 mb-4">Sign in to continue to your organization dashboard.</p>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="login">
            <input name="email" type="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
            <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
            <button class="bg-indigo-700 text-white px-4 py-2 rounded w-full">Login</button>
        </form>
        <?php if ($googleLoginReady): ?>
            <div class="my-3 text-center text-gray-500 text-sm">or</div>
            <a href="?page=google_login" class="block w-full border rounded px-4 py-2 text-center hover:bg-gray-50 font-medium">
                Continue with Google
            </a>
        <?php else: ?>
            <p class="text-xs text-amber-700 mt-3">Google login is disabled. Add Google keys in src/config.php.</p>
        <?php endif; ?>
        <p class="text-sm text-gray-600 mt-4">Default admin: admin@campus.local / admin123</p>
    </div>
    <?php
    renderFooter();
    exit;
}

if ($page === 'register') {
    renderHeader('Register');
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-4">Student Registration</h1>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="register">
            <input name="name" placeholder="Full Name" required class="w-full border rounded px-3 py-2">
            <input name="email" type="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
            <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
            <div class="rounded border border-emerald-200/40 p-3 bg-white/20">
                <div class="flex items-start gap-2">
                    <input id="privacyConsent" name="privacy_consent" type="checkbox" value="1" required class="mt-1">
                    <label for="privacyConsent" class="text-sm text-slate-700">
                        I agree to the
                        <button type="button" id="openPrivacyModal" class="font-medium text-emerald-700 underline">Data Privacy Consent</button>.
                    </label>
                </div>
            </div>
            <button class="bg-indigo-700 text-white px-4 py-2 rounded w-full">Create Account</button>
        </form>
    </div>

    <div id="privacyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4">
        <div class="glass w-full max-w-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">Data Privacy Consent</h2>
                <button type="button" id="closePrivacyModal" class="text-slate-600 hover:text-slate-900 text-xl leading-none">&times;</button>
            </div>
            <div class="text-sm text-slate-700 space-y-2 max-h-[60vh] overflow-auto pr-1">
                <p>By creating an account, you agree that this system may collect and process your personal data, such as your name, email address, role, organization memberships, and activity records, for account management and transparency reporting.</p>
                <p>Your data is used only for legitimate school organization operations, including authentication, organization management, announcement publishing, and finance report visibility.</p>
                <p>Your information is stored securely and access is limited based on system roles (admin, owner, student). We do not intentionally share your personal data with unauthorized third parties.</p>
                <p>You may request correction of inaccurate profile data through the system administrator. By proceeding, you confirm that the information you submit is accurate and that you consent to this processing.</p>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="declinePrivacy" class="px-3 py-2 rounded border border-slate-300 text-slate-700">Close</button>
                <button type="button" id="acceptPrivacy" class="px-3 py-2 rounded bg-emerald-600 text-white">I Agree</button>
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
                modal.classList.add('flex');
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
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

if ($page === 'admin_orgs') {
    requireRole(['admin']);

    $orgs = $db->query("SELECT o.*, u.name AS owner_name, oa.status AS assignment_status, su.name AS assigned_student_name
        FROM organizations o
        LEFT JOIN users u ON u.id = o.owner_id
        LEFT JOIN owner_assignments oa ON oa.organization_id = o.id AND oa.status = 'pending'
        LEFT JOIN users su ON su.id = oa.student_id
        ORDER BY o.id DESC")->fetchAll();
    $students = $db->query("SELECT id, name, email FROM users WHERE role IN ('student','owner') ORDER BY name ASC")->fetchAll();

    renderHeader('Manage Organizations');
    ?>
    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white shadow rounded p-4">
            <h2 class="text-lg font-semibold mb-3">Create Organization</h2>
            <form method="post" class="space-y-2">
                <input type="hidden" name="action" value="create_org">
                <input name="name" placeholder="Organization name" required class="w-full border rounded px-3 py-2">
                <textarea name="description" placeholder="Description" class="w-full border rounded px-3 py-2"></textarea>
                <button class="bg-indigo-700 text-white px-4 py-2 rounded">Create</button>
            </form>
        </div>

        <div class="md:col-span-2 bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-3">All Organizations</h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Name</th>
                    <th>Description</th>
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
                                <button class="bg-slate-700 text-white text-xs px-2 py-1 rounded">Save</button>
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
                                <button class="bg-blue-600 text-white text-xs px-2 py-1 rounded">Update</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete this organization?')">
                                <input type="hidden" name="action" value="delete_org">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <button class="bg-red-600 text-white text-xs px-2 py-1 rounded">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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

    renderHeader('Filter Students');
    ?>
    <div class="bg-white shadow rounded p-4">
        <h1 class="text-xl font-semibold mb-3">Filter All Student Information</h1>
        <form method="get" class="flex gap-2 mb-4">
            <input type="hidden" name="page" value="admin_students">
            <input name="q" value="<?= e($q) ?>" placeholder="Search by name or email" class="border rounded px-3 py-2 w-full">
            <button class="bg-indigo-700 text-white px-4 py-2 rounded">Filter</button>
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

    renderHeader('Transaction Requests');
    ?>
    <div class="bg-white shadow rounded p-4 overflow-auto">
        <h1 class="text-xl font-semibold mb-3">Owner Requests for Transaction Edit/Delete</h1>
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
                    <td><?= e((string) $req['status']) ?></td>
                    <td><?= e((string) ($req['admin_note'] ?? '')) ?></td>
                    <td class="min-w-56">
                        <?php if ((string) $req['status'] === 'pending'): ?>
                            <form method="post" class="space-y-1">
                                <input type="hidden" name="action" value="process_tx_change_request">
                                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                <input name="admin_note" placeholder="Optional note" class="w-full border rounded px-2 py-1 text-xs">
                                <div class="flex gap-2">
                                    <button name="decision" value="approve" class="bg-emerald-600 text-white px-2 py-1 rounded text-xs">Approve</button>
                                    <button name="decision" value="reject" class="bg-red-600 text-white px-2 py-1 rounded text-xs">Reject</button>
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
    </div>
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
            <h1 class="text-xl font-semibold mb-3">Organization Overview (Admin)</h1>
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
                <button class="bg-indigo-700 text-white px-4 py-2 rounded">Open</button>
            </form>

            <?php if ($org): ?>
                <?php
                $txStmt = $db->prepare('SELECT * FROM financial_transactions WHERE organization_id = ? ORDER BY transaction_date DESC, id DESC');
                $txStmt->execute([(int) $org['id']]);
                $tx = $txStmt->fetchAll();
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

    $stmt = $db->prepare('SELECT * FROM announcements WHERE organization_id = ? ORDER BY id DESC');
    $stmt->execute([(int) $org['id']]);
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

    renderHeader('My Organization');
    ?>
    <div class="space-y-4">
        <div class="bg-white shadow rounded p-4">
            <h2 class="text-lg font-semibold mb-3">Pending Membership Requests</h2>
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
                                    <button class="bg-emerald-600 text-white px-3 py-1 rounded text-xs">Approve</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="action" value="respond_join_request">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="decision" value="decline">
                                    <button class="bg-red-600 text-white px-3 py-1 rounded text-xs">Decline</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h1 class="text-xl font-semibold mb-3">My Organization</h1>
            <form method="get" class="mb-4 flex gap-2">
                <input type="hidden" name="page" value="my_org">
                <select name="org_id" class="border rounded px-3 py-2">
                    <?php foreach ($ownedOrganizations as $ownedOption): ?>
                        <option value="<?= (int) $ownedOption['id'] ?>" <?= (int) $org['id'] === (int) $ownedOption['id'] ? 'selected' : '' ?>>
                            <?= e($ownedOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-indigo-700 text-white px-4 py-2 rounded">Open</button>
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
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded">Save Organization Info</button>
                </div>
            </form>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white shadow rounded p-4">
                <h2 class="text-lg font-semibold mb-2">Post Announcement</h2>
                <form method="post" class="space-y-2">
                    <input type="hidden" name="action" value="add_announcement">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <input name="title" placeholder="Title" class="w-full border rounded px-3 py-2" required>
                    <textarea name="content" placeholder="Announcement details" class="w-full border rounded px-3 py-2" required></textarea>
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded">Post</button>
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
                                <button class="bg-red-600 text-white px-2 py-1 rounded text-xs">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white shadow rounded p-4">
                <h2 class="text-lg font-semibold mb-2">Add Income / Expense</h2>
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
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded">Save Transaction</button>
                </form>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-2">Transaction History (CRUD)</h2>
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
                                <a href="<?= e($row['receipt_path']) ?>" target="_blank" class="text-indigo-700 underline">View</a>
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
                                <button class="bg-blue-600 text-white px-2 py-1 rounded text-xs">Request Update</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete transaction?')">
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                <button class="bg-red-600 text-white px-2 py-1 rounded text-xs">Request Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-2">My Pending/Recent Transaction Requests</h2>
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
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}

// Default Dashboard (all logged-in users)
$orgs = $db->query('SELECT o.*, u.name AS owner_name FROM organizations o LEFT JOIN users u ON u.id = o.owner_id ORDER BY o.name ASC')->fetchAll();
$membershipStmt = $db->prepare('SELECT organization_id FROM organization_members WHERE user_id = ?');
$membershipStmt->execute([(int) $user['id']]);
$joinedIds = array_map('intval', array_column($membershipStmt->fetchAll(), 'organization_id'));

$requestStmt = $db->prepare('SELECT organization_id, status FROM organization_join_requests WHERE user_id = ?');
$requestStmt->execute([(int) $user['id']]);
$joinRequestStatus = [];
foreach ($requestStmt->fetchAll() as $req) {
    $joinRequestStatus[(int) $req['organization_id']] = (string) $req['status'];
}

$announcements = $db->query('SELECT a.*, o.name AS organization_name FROM announcements a JOIN organizations o ON o.id = a.organization_id ORDER BY a.id DESC LIMIT 12')->fetchAll();
$transactions = $db->query('SELECT t.*, o.name AS organization_name FROM financial_transactions t JOIN organizations o ON o.id = t.organization_id ORDER BY t.transaction_date DESC, t.id DESC LIMIT 30')->fetchAll();

$summary = $db->query("SELECT o.id, o.name,
    COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income,
    COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_expense
    FROM organizations o
    LEFT JOIN financial_transactions t ON t.organization_id = o.id
    GROUP BY o.id, o.name
    ORDER BY o.name")->fetchAll();

$kpi = $db->query("SELECT
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
    FROM financial_transactions")->fetch();

$kpiIncome = (float) ($kpi['income'] ?? 0);
$kpiExpense = (float) ($kpi['expense'] ?? 0);
$kpiBalance = $kpiIncome - $kpiExpense;

$activity = $db->query("SELECT 'announcement' AS type, title AS label, created_at, organization_id FROM announcements
    UNION ALL
    SELECT 'transaction' AS type, description AS label, created_at, organization_id FROM financial_transactions
    ORDER BY created_at DESC
    LIMIT 12")->fetchAll();

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

renderHeader('Dashboard');
?>
<div class="space-y-4">
    <div class="glass p-5 text-center md:text-left">
        <h1 class="text-xl font-semibold text-slate-900">Welcome, <?= e($user['name']) ?></h1>
        <p class="text-gray-600">Budget transparency is visible to all students. You can review all income and expenses below.</p>
    </div>

    <div class="grid md:grid-cols-4 gap-4">
        <div class="glass p-4 text-center md:text-left">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Total Income</div>
            <div class="text-2xl font-semibold text-green-300">₱<?= number_format($kpiIncome, 2) ?></div>
        </div>
        <div class="glass p-4 text-center md:text-left">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Total Expense</div>
            <div class="text-2xl font-semibold text-red-300">₱<?= number_format($kpiExpense, 2) ?></div>
        </div>
        <div class="glass p-4 text-center md:text-left">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Net Balance</div>
            <div class="text-2xl font-semibold <?= $kpiBalance >= 0 ? 'text-emerald-200' : 'text-red-200' ?>">₱<?= number_format($kpiBalance, 2) ?></div>
        </div>
        <div class="glass p-4 text-center md:text-left">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Organizations</div>
            <div class="text-2xl font-semibold text-slate-900"><?= count($orgs) ?></div>
        </div>
    </div>

    <div class="glass p-4">
        <h2 class="text-lg font-semibold mb-3 text-center md:text-left">Quick Actions</h2>
        <div class="flex flex-wrap gap-2 justify-center md:justify-start">
            <a href="?page=dashboard" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm">Refresh Dashboard</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="?page=admin_orgs" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm">Manage Organizations</a>
                <a href="?page=admin_students" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm">Filter Students</a>
                <a href="?page=admin_requests" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm">Review Requests</a>
            <?php endif; ?>
            <?php if ($user['role'] === 'owner' || $user['role'] === 'admin'): ?>
                <a href="?page=my_org" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm">Go to My Organization</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($pendingAssignments) > 0): ?>
        <div class="glass p-5">
            <h2 class="text-lg font-semibold mb-3 text-center md:text-left">Organization Owner Assignments</h2>
            <div class="space-y-3">
                <?php foreach ($pendingAssignments as $assignment): ?>
                    <div class="border border-emerald-200/30 rounded-lg p-3 flex flex-wrap items-center justify-center md:justify-between gap-3 text-center md:text-left">
                        <div class="w-full md:w-auto">
                            <div class="font-medium"><?= e($assignment['organization_name']) ?></div>
                            <div class="text-xs text-gray-500">Assigned on <?= e($assignment['created_at']) ?></div>
                        </div>
                        <div class="flex gap-2">
                            <form method="post">
                                <input type="hidden" name="action" value="respond_owner_assignment">
                                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                <input type="hidden" name="decision" value="accept">
                                <button class="bg-emerald-600 text-white px-3 py-1 rounded text-xs">Accept</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="action" value="respond_owner_assignment">
                                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                <input type="hidden" name="decision" value="decline">
                                <button class="bg-red-600 text-white px-3 py-1 rounded text-xs">Decline</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid md:grid-cols-12 gap-4">
        <div class="glass md:col-span-7 p-4">
            <h2 class="text-lg font-semibold mb-3 text-center md:text-left">Organizations</h2>
            <div class="space-y-3">
                <?php foreach ($orgs as $org): ?>
                    <div class="border rounded p-3 flex flex-col md:flex-row justify-between items-center md:items-start gap-2 text-center md:text-left">
                        <div>
                            <div class="font-medium"><?= e($org['name']) ?></div>
                            <p class="text-sm text-gray-600"><?= e($org['description']) ?></p>
                            <div class="text-xs text-gray-500 mt-1">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
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
                                        ? 'bg-white/10 border-emerald-200/30 text-white/85'
                                        : ($requestStatus === 'pending'
                                            ? 'bg-amber-500/25 border-amber-300/50 text-white/90'
                                            : 'bg-emerald-500/25 border-emerald-300/50 text-white hover:bg-emerald-500/35');
                                    $label = $isJoined ? 'Joined' : ($requestStatus === 'pending' ? 'Requested' : 'Request Join');
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

        <div class="glass md:col-span-5 p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-3">Financial Summary by Organization</h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b text-left">
                    <th class="py-2">Organization</th>
                    <th>Income</th>
                    <th>Expense</th>
                    <th>Balance</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary as $row): ?>
                    <?php $balance = (float) $row['total_income'] - (float) $row['total_expense']; ?>
                    <tr class="border-b">
                        <td class="py-2"><?= e($row['name']) ?></td>
                        <td class="text-green-700">₱<?= number_format((float) $row['total_income'], 2) ?></td>
                        <td class="text-red-700">₱<?= number_format((float) $row['total_expense'], 2) ?></td>
                        <td class="<?= $balance >= 0 ? 'text-green-800' : 'text-red-800' ?>">₱<?= number_format($balance, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="glass md:col-span-5 p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-3">Latest Announcements</h2>
            <div class="space-y-2">
                <?php foreach ($announcements as $item): ?>
                    <div class="border rounded p-2">
                        <div class="font-medium"><?= e($item['title']) ?></div>
                        <div class="text-xs text-gray-500"><?= e($item['organization_name']) ?> · <?= e($item['created_at']) ?></div>
                        <div class="text-sm mt-1"><?= e($item['content']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="glass md:col-span-7 p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-3">Recent Income & Expense Reports</h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b text-left">
                    <th class="py-2">Date</th>
                    <th>Org</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Receipt</th>
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
                                <a class="text-indigo-700 underline" target="_blank" href="<?= e($tx['receipt_path']) ?>">Open</a>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="glass md:col-span-7 p-4">
            <h2 class="text-lg font-semibold mb-3">Monthly Trend (Income vs Expense)</h2>
            <canvas id="trendChart" height="130"></canvas>
        </div>

        <div class="glass md:col-span-5 p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-3">Activity Feed</h2>
            <div class="space-y-2 text-sm">
                <?php foreach ($activity as $item): ?>
                    <div class="border rounded p-2">
                        <div class="font-medium"><?= e($item['label']) ?></div>
                        <div class="text-xs text-gray-500">
                            <?= e($item['type']) ?> · <?= e($item['created_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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

        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            themeBtn.addEventListener('change', function () {
                const willBeDark = !document.body.classList.contains('theme-dark');
                const nextAxis = willBeDark ? '#a7f3d0' : '#065f46';
                const nextLegend = willBeDark ? '#d1fae5' : '#14532d';
                const nextGrid = willBeDark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';
                chart.options.plugins.legend.labels.color = nextLegend;
                chart.options.scales.x.ticks.color = nextAxis;
                chart.options.scales.y.ticks.color = nextAxis;
                chart.options.scales.x.grid.color = nextGrid;
                chart.options.scales.y.grid.color = nextGrid;
                chart.update();
            });
        }
    })();
</script>
<?php
renderFooter();
