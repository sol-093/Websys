<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - ORGANIZATION WORKFLOWS
 * ================================================
 *
 * SECTION MAP:
 * 1. Owner Assignment Eligibility
 * 2. Admin Organization CRUD
 * 3. Owner Assignment Responses
 * 4. Join Requests and Responses
 * 5. Transaction Change Request Decisions
 *
 * WORK GUIDE:
 * - Edit this file for organization and membership POST workflows.
 * ================================================
 */

function assertOwnerAssignmentEligibility(PDO $db, int $orgId, int $ownerId): void
{
    if ($ownerId <= 0) {
        return;
    }

    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $org = $organizations->findOwnerAssignmentTarget($orgId);
    if (!$org) {
        throw new RuntimeException('Organization not found.');
    }

    $owner = $organizations->findAssignableOwner($ownerId);
    if (!$owner) {
        throw new RuntimeException('Selected owner is invalid.');
    }

    if (!canUserJoinOrganization($org, $owner)) {
        $category = (string) ($org['org_category'] ?? 'collegewide');
        if ($category === 'institutewide') {
            throw new RuntimeException('Selected user cannot be assigned. Institutewide organizations require a matching institute.');
        }

        if ($category === 'program_based') {
            throw new RuntimeException('Selected user cannot be assigned. Program-based organizations require a matching program.');
        }

        throw new RuntimeException('Selected user cannot be assigned to this organization.');
    }
}

function handleCreateOrgAction(PDO $db, array $user): void
{
    requirePermission('manage_organizations');
    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $config = require dirname(__DIR__, 2) . '/core/config.php';
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

    $logoPath = null;
    $logoCropX = (float) ($_POST['logo_crop_x'] ?? 50);
    $logoCropY = (float) ($_POST['logo_crop_y'] ?? 50);
    $logoZoom = (float) ($_POST['logo_zoom'] ?? 1);
    if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
        $uploadedLogo = handleProfileImageUpload($_FILES['logo'], (string) $config['upload_dir'], 'org_');
        if ($uploadedLogo === false) {
            redirect('?page=admin_orgs');
        }
        $logoPath = $uploadedLogo;
    }

    try {
        $orgId = withTransaction($db, static fn(): int => $organizations->create($name, $description, $orgCategory, $targetInstitute !== '' ? $targetInstitute : null, $targetProgram !== '' ? $targetProgram : null, $logoPath, $logoCropX, $logoCropY, $logoZoom));
        auditLog((int) $user['id'], 'organization.create', 'organization', $orgId, 'Created organization: ' . $name);
        setFlash('success', 'Organization created.');
    } catch (Throwable $e) {
        if ($logoPath !== null) {
            deleteStoredUpload($logoPath);
        }
        setFlash('error', 'Organization name already exists.');
    }

    redirect('?page=admin_orgs');
}

function handleUpdateOrgAdminAction(PDO $db, array $user): void
{
    requirePermission('manage_organizations');
    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $config = require dirname(__DIR__, 2) . '/core/config.php';
    $orgId = (int) ($_POST['org_id'] ?? 0);
    $ownerId = (int) ($_POST['owner_id'] ?? 0);
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

    $existingOrg = $organizations->editState($orgId);
    if (!$existingOrg) {
        setFlash('error', 'Organization not found.');
        redirect('?page=admin_orgs');
    }

    $existingOwnerId = (int) ($existingOrg['owner_id'] ?? 0);
    $logoPath = trim((string) ($existingOrg['logo_path'] ?? ''));
    $logoCropX = (float) ($_POST['logo_crop_x'] ?? ($existingOrg['logo_crop_x'] ?? 50));
    $logoCropY = (float) ($_POST['logo_crop_y'] ?? ($existingOrg['logo_crop_y'] ?? 50));
    $logoZoom = (float) ($_POST['logo_zoom'] ?? ($existingOrg['logo_zoom'] ?? 1));

    if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
        $uploadedLogo = handleProfileImageUpload($_FILES['logo'], (string) $config['upload_dir'], 'org_');
        if ($uploadedLogo === false) {
            redirect('?page=admin_orgs');
        }

        $logoPath = $uploadedLogo;
    }

    try {
        withTransaction($db, static function () use ($db, $organizations, $name, $description, $orgCategory, $targetInstitute, $targetProgram, $logoPath, $logoCropX, $logoCropY, $logoZoom, $orgId, $ownerId, $existingOwnerId): void {
            $organizations->updateDetails($orgId, $name, $description, $orgCategory, $targetInstitute !== '' ? $targetInstitute : null, $targetProgram !== '' ? $targetProgram : null, $logoPath !== '' ? $logoPath : null, $logoCropX, $logoCropY, $logoZoom);

            if ($ownerId <= 0) {
                $organizations->clearOwnerAssignment($orgId);
            } elseif ($ownerId !== $existingOwnerId) {
                assertOwnerAssignmentEligibility($db, $orgId, $ownerId);
                $organizations->clearOwnerAssignment($orgId);
                $organizations->createPendingOwnerAssignment($orgId, $ownerId);
            } else {
                $organizations->clearPendingOwnerAssignments($orgId);
            }
        });

        auditLog((int) $user['id'], 'organization.update', 'organization', $orgId, 'Updated organization details');
        if ($ownerId !== $existingOwnerId) {
            auditLog((int) $user['id'], 'organization.assign_owner', 'organization', $orgId, $ownerId > 0 ? 'Assignment set to pending via organization update' : 'Owner assignment cleared via organization update');
        }

        if ($ownerId !== $existingOwnerId && $ownerId > 0) {
            setFlash('success', 'Organization updated. Owner assignment sent and awaiting student response.');
        } elseif ($ownerId !== $existingOwnerId && $ownerId <= 0) {
            setFlash('success', 'Organization updated. Owner assignment cleared.');
        } else {
            setFlash('success', 'Organization updated.');
        }
        if (isset($uploadedLogo) && $uploadedLogo !== false && trim((string) ($existingOrg['logo_path'] ?? '')) !== '' && trim((string) ($existingOrg['logo_path'] ?? '')) !== $uploadedLogo) {
            deleteStoredUpload((string) $existingOrg['logo_path']);
        }
    } catch (Throwable $e) {
        if (isset($uploadedLogo) && $uploadedLogo !== false && $uploadedLogo !== $existingOrg['logo_path']) {
            deleteStoredUpload((string) $uploadedLogo);
        }

        if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
            setFlash('error', 'Organization name already exists.');
        } elseif ($e instanceof RuntimeException) {
            setFlash('error', $e->getMessage());
        } else {
            setFlash('error', 'Could not update organization.');
        }
    }

    redirect('?page=admin_orgs');
}

function handleDeleteOrgAction(PDO $db, array $user): void
{
    requirePermission('manage_organizations');
    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $orgId = (int) ($_POST['org_id'] ?? 0);
    $organizations->delete($orgId);
    auditLog((int) $user['id'], 'organization.delete', 'organization', $orgId, 'Deleted organization');
    setFlash('success', 'Organization deleted.');
    redirect('?page=admin_orgs');
}

function handleAssignOwnerAction(PDO $db, array $user): void
{
    requirePermission('assign_owners');
    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $orgId = (int) ($_POST['org_id'] ?? 0);
    $ownerId = (int) ($_POST['owner_id'] ?? 0);

    try {
        withTransaction($db, static function () use ($db, $organizations, $orgId, $ownerId): void {
            if ($ownerId <= 0) {
                $organizations->clearOwnerAssignment($orgId);
                return;
            }

            assertOwnerAssignmentEligibility($db, $orgId, $ownerId);
            $organizations->clearOwnerAssignment($orgId);
            $organizations->createPendingOwnerAssignment($orgId, $ownerId);
        });
        auditLog((int) $user['id'], 'organization.assign_owner', 'organization', $orgId, $ownerId > 0 ? 'Assignment set to pending' : 'Owner assignment cleared');
        setFlash('success', $ownerId > 0 ? 'Owner assignment sent. Student must accept first.' : 'Owner assignment cleared.');
    } catch (Throwable $e) {
        if ($e instanceof RuntimeException) {
            setFlash('error', $e->getMessage());
        } else {
            setFlash('error', 'Could not assign owner.');
        }
    }

    redirect('?page=admin_orgs');
}

function handleRespondOwnerAssignmentAction(PDO $db, array $user): void
{
    requirePermission('join_organizations');
    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? 'decline');

    if (!in_array($decision, ['accept', 'decline'], true)) {
        setFlash('error', 'Invalid assignment response.');
        redirect('?page=dashboard');
    }

    $assignment = $organizations->pendingOwnerAssignmentForStudent($assignmentId, (int) $user['id']);
    if (!$assignment) {
        setFlash('error', 'Assignment is no longer available.');
        redirect('?page=dashboard');
    }

    try {
        withTransaction($db, static function () use ($db, $organizations, $decision, $assignmentId, $assignment, $user): void {
            if ($decision === 'accept') {
                $organizations->markOwnerAssignmentDecision($assignmentId, 'accepted');
                $organizations->setOwner((int) $assignment['organization_id'], (int) $user['id']);
                ensureOrganizationMember($db, (int) $assignment['organization_id'], (int) $user['id']);
                $organizations->promoteStudentToOwner((int) $user['id']);
                return;
            }

            $organizations->markOwnerAssignmentDecision($assignmentId, 'declined');
        });
        auditLog((int) $user['id'], $decision === 'accept' ? 'assignment.accept' : 'assignment.decline', 'owner_assignment', $assignmentId, ($decision === 'accept' ? 'Accepted' : 'Declined') . ' organization owner assignment');
        setFlash('success', $decision === 'accept' ? 'You accepted the owner assignment.' : 'You declined the owner assignment.');
    } catch (Throwable $e) {
        setFlash('error', 'Unable to update assignment response.');
    }

    redirect('?page=dashboard');
}

function handleJoinOrgAction(PDO $db, array $user): void
{
    requirePermission('join_organizations');
    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $orgId = (int) ($_POST['org_id'] ?? 0);

    $org = $organizations->findJoinTarget($orgId);
    if (!$org) {
        setFlash('error', 'Organization not found.');
        redirect('?page=dashboard');
    }

    if (!canUserJoinOrganization($org, $user)) {
        setFlash('error', 'You are not eligible to join this organization based on your institute/program.');
        redirect('?page=dashboard');
    }

    if ($organizations->isMember($orgId, (int) $user['id'])) {
        setFlash('error', 'You are already a member of this organization.');
        redirect('?page=dashboard');
    }

    try {
        $organizations->createJoinRequest($orgId, (int) $user['id']);
        auditLog((int) $user['id'], 'join_request.submit', 'organization', $orgId, 'Submitted join request');
        setFlash('success', 'Join request sent. Please wait for approval.');
    } catch (Throwable $e) {
        $existingStatus = (string) ($organizations->joinRequestStatus($orgId, (int) $user['id']) ?: 'pending');
        if ($existingStatus === 'declined') {
            $organizations->resubmitJoinRequest($orgId, (int) $user['id']);
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

function handleProcessTxChangeRequestAction(PDO $db, array $user): void
{
    requirePermission('approve_transactions');
    $transactions = new Involve\Repositories\TransactionRepository($db);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? 'reject');
    $adminNote = trim((string) ($_POST['admin_note'] ?? ''));

    if (!in_array($decision, ['approve', 'reject'], true)) {
        setFlash('error', 'Invalid request decision.');
        redirect('?page=admin_requests');
    }

    $request = $transactions->pendingChangeRequest($requestId);
    if (!$request) {
        setFlash('error', 'Request is no longer pending.');
        redirect('?page=admin_requests');
    }

    try {
        withTransaction($db, static function () use ($transactions, $decision, $request, $adminNote, $requestId): void {
            if ($decision === 'approve') {
                $transactions->applyApprovedChangeRequest($request);
                $transactions->markChangeRequestDecision($requestId, 'approved', $adminNote);
                return;
            }

            $transactions->markChangeRequestDecision($requestId, 'rejected', $adminNote);
        });
        auditLog((int) $user['id'], $decision === 'approve' ? 'finance.request_approve' : 'finance.request_reject', 'transaction_change_request', $requestId, ($decision === 'approve' ? 'Approved' : 'Rejected') . ' transaction change request');
        setFlash('success', $decision === 'approve' ? 'Transaction change request approved.' : 'Transaction change request rejected.');
    } catch (Throwable $e) {
        setFlash('error', 'Unable to process transaction change request.');
    }

    redirect('?page=admin_requests');
}

function handleRespondJoinRequestAction(PDO $db, array $user): void
{
    requirePermission('manage_own_organization');
    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $orgId = (int) ($_POST['org_id'] ?? 0);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? 'decline');

    $org = getOwnedOrganizationById((int) $user['id'], $orgId);
    if (!$org) {
        setFlash('error', 'You are not allowed to manage this organization.');
        redirect('?page=my_org_members');
    }

    if (!in_array($decision, ['approve', 'decline'], true)) {
        setFlash('error', 'Invalid request action.');
        redirect('?page=my_org_members&org_id=' . $orgId);
    }

    $request = $organizations->pendingJoinRequest($requestId, $orgId);
    if (!$request) {
        setFlash('error', 'Request is no longer pending.');
        redirect('?page=my_org_members&org_id=' . $orgId);
    }

    try {
        withTransaction($db, static function () use ($db, $organizations, $decision, $requestId, $orgId, $request): void {
            if ($decision === 'approve') {
                $organizations->markJoinRequestDecision($requestId, 'approved');
                ensureOrganizationMember($db, $orgId, (int) $request['user_id']);
                return;
            }

            $organizations->markJoinRequestDecision($requestId, 'declined');
        });
        auditLog((int) $user['id'], $decision === 'approve' ? 'join_request.approve' : 'join_request.decline', 'organization_join_request', $requestId, ($decision === 'approve' ? 'Approved' : 'Declined') . ' join request');
        setFlash('success', $decision === 'approve' ? 'Join request approved.' : 'Join request declined.');
    } catch (Throwable $e) {
        setFlash('error', 'Unable to update join request.');
    }

    redirect('?page=my_org_members&org_id=' . $orgId);
}

function handleRemoveOrganizationMemberAction(PDO $db, array $user): void
{
    requirePermission('manage_own_organization');
    $organizations = new Involve\Repositories\OrganizationRepository($db);
    $orgId = (int) ($_POST['org_id'] ?? 0);
    $memberUserId = (int) ($_POST['member_user_id'] ?? 0);

    $org = getOwnedOrganizationById((int) $user['id'], $orgId);
    if (!$org) {
        setFlash('error', 'You are not allowed to manage this organization.');
        redirect('?page=my_org_members');
    }

    if ($memberUserId <= 0) {
        setFlash('error', 'Invalid member selected.');
        redirect('?page=my_org_members&org_id=' . $orgId);
    }

    $ownerId = (int) ($org['owner_id'] ?? 0);
    if ($memberUserId === $ownerId || $memberUserId === (int) $user['id']) {
        setFlash('error', 'The organization owner cannot be removed from the member roster.');
        redirect('?page=my_org_members&org_id=' . $orgId);
    }

    $member = $organizations->memberForOrganization($orgId, $memberUserId);
    if (!$member) {
        setFlash('error', 'That member is no longer part of the organization.');
        redirect('?page=my_org_members&org_id=' . $orgId);
    }

    try {
        $organizations->removeMember($orgId, $memberUserId);

        auditLog(
            (int) $user['id'],
            'organization.member_remove',
            'organization',
            $orgId,
            'Removed member: ' . (string) ($member['name'] ?? 'Member')
        );
        setFlash('success', 'Member removed from the organization.');
    } catch (Throwable $e) {
        setFlash('error', 'Unable to remove that member right now.');
    }

    redirect('?page=my_org_members&org_id=' . $orgId);
}
