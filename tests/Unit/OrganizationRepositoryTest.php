<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Repositories\OrganizationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class OrganizationRepositoryTest extends TestCase
{
    public function testOrganizationCrudHelpers(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $repository = new OrganizationRepository($db);
        $organizationId = $repository->create(
            'JPCS',
            'Computing org',
            'collegewide',
            null,
            null,
            'uploads/organizations/org.png',
            50.0,
            51.0,
            1.2
        );

        $editState = $repository->editState($organizationId);
        self::assertNotNull($editState);
        self::assertSame('uploads/organizations/org.png', $editState['logo_path']);

        $repository->updateDetails(
            $organizationId,
            'JPCS Updated',
            'Updated description',
            'institutewide',
            'Institute of Computing and Digital Innovations',
            null,
            null,
            40.0,
            41.0,
            1.5
        );

        $updated = $db->query('SELECT name, description, org_category, target_institute, logo_path, logo_zoom FROM organizations WHERE id = ' . $organizationId)->fetch();
        self::assertSame('JPCS Updated', $updated['name']);
        self::assertSame('Updated description', $updated['description']);
        self::assertSame('institutewide', $updated['org_category']);
        self::assertSame('Institute of Computing and Digital Innovations', $updated['target_institute']);
        self::assertNull($updated['logo_path']);
        self::assertSame(1.5, (float) $updated['logo_zoom']);

        $repository->delete($organizationId);
        self::assertNull($repository->editState($organizationId));
    }

    public function testAdminListIncludesOwnerAndCounts(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([22, 'Owner']);
        $db->prepare('INSERT INTO organizations (id, name, owner_id, org_category) VALUES (?, ?, ?, ?)')->execute([8, 'JPCS', 22, 'collegewide']);
        $db->prepare('INSERT INTO organization_members (organization_id, user_id) VALUES (?, ?)')->execute([8, 22]);
        $db->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status) VALUES (?, ?, ?)')->execute([8, 33, 'pending']);

        $repository = new OrganizationRepository($db);
        $result = $repository->adminList('JPCS', 10, 0);

        self::assertSame(1, $result['total']);
        self::assertSame('Owner', $result['items'][0]['owner_name']);
        self::assertSame(1, (int) $result['items'][0]['member_count']);
        self::assertSame(1, (int) $result['items'][0]['pending_join_count']);
    }

    public function testVisibleDirectoryForUserBuildsApiPayload(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/lib/organization.php';

        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([22, 'Owner']);
        $db->prepare('INSERT INTO users (id, name, role, institute, program) VALUES (?, ?, ?, ?, ?)')->execute([33, 'Student', 'student', 'Institute of Computing and Digital Innovations', 'BS Information Systems']);
        $db->prepare('INSERT INTO organizations (id, name, description, owner_id, org_category) VALUES (?, ?, ?, ?, ?)')->execute([8, 'JPCS', 'Computing org', 22, 'collegewide']);
        $db->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status) VALUES (?, ?, ?)')->execute([8, 33, 'declined']);

        $repository = new OrganizationRepository($db);
        $result = $repository->visibleDirectoryForUser([
            'id' => 33,
            'role' => 'student',
            'institute' => 'Institute of Computing and Digital Innovations',
            'program' => 'BS Information Systems',
        ], 'jpcs', 10, 0);

        self::assertSame(1, $result['total']);
        self::assertSame('JPCS', $result['items'][0]['name']);
        self::assertSame('Owner', $result['items'][0]['owner_name']);
        self::assertSame('Collegewide', $result['items'][0]['visibility']);
        self::assertFalse($result['items'][0]['joined']);
        self::assertSame('declined', $result['items'][0]['join_request_status']);
        self::assertTrue($result['items'][0]['can_join']);
    }

    public function testJoinRequestAndMembershipHelpers(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO organizations (id, name, org_category, target_institute, target_program) VALUES (?, ?, ?, ?, ?)')->execute([8, 'JPCS', 'collegewide', null, null]);
        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([12, 'Student']);

        $repository = new OrganizationRepository($db);

        $target = $repository->findJoinTarget(8);
        self::assertNotNull($target);
        self::assertSame('collegewide', $target['org_category']);
        self::assertFalse($repository->isMember(8, 12));

        $requestId = $repository->createJoinRequest(8, 12);
        self::assertSame('pending', $repository->joinRequestStatus(8, 12));

        $pending = $repository->pendingJoinRequest($requestId, 8);
        self::assertNotNull($pending);
        $repository->markJoinRequestDecision($requestId, 'declined');
        self::assertNull($repository->pendingJoinRequest($requestId, 8));
        self::assertSame('declined', $repository->joinRequestStatus(8, 12));

        $repository->resubmitJoinRequest(8, 12);
        self::assertSame('pending', $repository->joinRequestStatus(8, 12));

        $joinRequests = $repository->joinRequestList(8, 'pending', 10, 0);
        self::assertSame(1, $joinRequests['total']);
        self::assertSame('Student', $joinRequests['items'][0]['name']);

        $db->prepare('INSERT INTO organization_members (organization_id, user_id) VALUES (?, ?)')->execute([8, 12]);
        self::assertTrue($repository->isMember(8, 12));

        $member = $repository->memberForOrganization(8, 12);
        self::assertNotNull($member);
        self::assertSame('Student', $member['name']);

        $members = $repository->memberList(8, 10, 0);
        self::assertSame(1, $members['total']);
        self::assertSame('Student', $members['items'][0]['name']);

        $repository->removeMember(8, 12);
        self::assertFalse($repository->isMember(8, 12));
    }

    public function testJoinRequestDecisionRequiresPendingStatus(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO organizations (id, name, org_category, target_institute, target_program) VALUES (?, ?, ?, ?, ?)')->execute([8, 'JPCS', 'collegewide', null, null]);
        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([12, 'Student']);

        $repository = new OrganizationRepository($db);
        $requestId = $repository->createJoinRequest(8, 12);
        $repository->markJoinRequestDecision($requestId, 'declined');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Join request is no longer pending.');

        $repository->markJoinRequestDecision($requestId, 'approved');
    }

    public function testOwnerAssignmentHelpers(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO organizations (id, name, org_category, target_institute, target_program) VALUES (?, ?, ?, ?, ?)')->execute([8, 'JPCS', 'collegewide', null, null]);
        $db->prepare('INSERT INTO users (id, name, role, institute, program) VALUES (?, ?, ?, ?, ?)')->execute([12, 'Student', 'student', 'Institute of Computing and Digital Innovations', 'BS Information Systems']);

        $repository = new OrganizationRepository($db);

        self::assertNotNull($repository->findOwnerAssignmentTarget(8));
        self::assertNotNull($repository->findAssignableOwner(12));

        $assignmentId = $repository->createPendingOwnerAssignment(8, 12);
        $assignment = $repository->pendingOwnerAssignmentForStudent($assignmentId, 12);
        self::assertNotNull($assignment);
        self::assertSame(8, (int) $assignment['organization_id']);

        $repository->markOwnerAssignmentDecision($assignmentId, 'accepted');
        $repository->setOwner(8, 12);
        $repository->promoteStudentToOwner(12);

        self::assertSame(12, (int) $db->query('SELECT owner_id FROM organizations WHERE id = 8')->fetchColumn());
        self::assertSame('owner', (string) $db->query('SELECT role FROM users WHERE id = 12')->fetchColumn());
        self::assertNull($repository->pendingOwnerAssignmentForStudent($assignmentId, 12));

        $repository->createPendingOwnerAssignment(8, 12);
        $repository->clearPendingOwnerAssignments(8);
        self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM owner_assignments WHERE organization_id = 8 AND status = 'pending'")->fetchColumn());

        $repository->createPendingOwnerAssignment(8, 12);
        $repository->clearOwnerAssignment(8);
        self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM owner_assignments WHERE organization_id = 8')->fetchColumn());
        self::assertNull($db->query('SELECT owner_id FROM organizations WHERE id = 8')->fetchColumn());
    }

    public function testOwnerAssignmentDecisionRequiresPendingStatus(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO organizations (id, name, org_category, target_institute, target_program) VALUES (?, ?, ?, ?, ?)')->execute([8, 'JPCS', 'collegewide', null, null]);
        $db->prepare('INSERT INTO users (id, name, role, institute, program) VALUES (?, ?, ?, ?, ?)')->execute([12, 'Student', 'student', 'Institute of Computing and Digital Innovations', 'BS Information Systems']);

        $repository = new OrganizationRepository($db);
        $assignmentId = $repository->createPendingOwnerAssignment(8, 12);
        $repository->markOwnerAssignmentDecision($assignmentId, 'declined');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Owner assignment is no longer pending.');

        $repository->markOwnerAssignmentDecision($assignmentId, 'accepted');
    }

    private function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE organizations (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT NULL,
            owner_id INTEGER NULL,
            org_category TEXT NOT NULL,
            target_institute TEXT NULL,
            target_program TEXT NULL,
            logo_path TEXT NULL,
            logo_crop_x REAL NOT NULL DEFAULT 50,
            logo_crop_y REAL NOT NULL DEFAULT 50,
            logo_zoom REAL NOT NULL DEFAULT 1
        )');

        $db->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NULL,
            role TEXT NOT NULL DEFAULT \'student\',
            institute TEXT NULL,
            program TEXT NULL,
            year_level INTEGER NULL,
            section TEXT NULL
        )');

        $db->exec('CREATE TABLE organization_members (
            organization_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (organization_id, user_id)
        )');

        $db->exec('CREATE TABLE organization_join_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NULL,
            UNIQUE (organization_id, user_id)
        )');

        $db->exec('CREATE TABLE owner_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NULL
        )');
    }
}
