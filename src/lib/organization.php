<?php

declare(strict_types=1);

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
