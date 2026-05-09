<?php

declare(strict_types=1);

use Involve\Repositories\OrganizationRepository;
use Involve\Support\ApiList;
use Involve\Support\ApiRequest;
use Involve\Support\JsonResponse;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/list_helpers.php';

ApiRequest::requireMethod('GET');

$user = apiRequireUser();
$organizations = new OrganizationRepository($db);
$membershipIds = $organizations->membershipIdsForUser((int) $user['id']);
$joinStatuses = $organizations->joinRequestStatusForUser((int) $user['id']);
$visibleOrganizations = applyOrganizationVisibilityForUser($organizations->allWithOwnerNames(), $user, $membershipIds);
$q = apiSearchTerm();
if ($q !== '') {
    $visibleOrganizations = array_values(array_filter($visibleOrganizations, static fn(array $organization): bool => str_contains(strtolower((string) ($organization['name'] ?? '')), strtolower($q))));
}

$payload = array_map(static function (array $organization) use ($user, $membershipIds, $joinStatuses): array {
    $organizationId = (int) $organization['id'];
    $isJoined = in_array($organizationId, $membershipIds, true);
    $requestStatus = (string) ($joinStatuses[$organizationId] ?? '');

    return [
        'id' => $organizationId,
        'name' => (string) ($organization['name'] ?? ''),
        'description' => (string) ($organization['description'] ?? ''),
        'owner_name' => $organization['owner_name'] ?? null,
        'visibility' => getOrganizationVisibilityLabel($organization),
        'joined' => $isJoined,
        'join_request_status' => $requestStatus !== '' ? $requestStatus : null,
        'can_join' => !$isJoined && $requestStatus !== 'pending' && canUserJoinOrganization($organization, $user),
    ];
}, $visibleOrganizations);

$pagination = apiListParams();
$total = count($payload);
$items = array_slice($payload, $pagination['offset'], $pagination['per_page']);

JsonResponse::ok([
    'items' => $items,
    'organizations' => $items,
    'pagination' => [
        'page' => (int) $pagination['page'],
        'per_page' => (int) $pagination['per_page'],
        'total' => $total,
        'total_pages' => max(1, (int) ceil($total / max(1, (int) $pagination['per_page']))),
    ],
    'filters' => [
        'q' => $q,
        'sort' => (string) ($_GET['sort'] ?? 'name'),
    ],
]);
