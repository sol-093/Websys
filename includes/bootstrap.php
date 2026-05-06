<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - APPLICATION BOOTSTRAP
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. Composer Autoload
 * 2. Security Headers
 * 3. Global Search API
 * 4. Session, Config, and Timezone
 * 5. Core/Library/Feature Requires
 * 6. Database Startup Error Page
 * 7. Current User and Page State
 *
 * EDIT GUIDE:
 * - Add globally required modules in section 5.
 * - Change app-wide HTTP headers in section 2.
 * - Change startup variables for route files in section 7.
 * ================================================
 */

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    error_log('Composer autoload not found. Run `composer install` once dependencies are available.');
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        error_log('Security headers were not sent because headers have already been sent.');
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' https://accounts.google.com; frame-ancestors 'none'");

    $https = (string) ($_SERVER['HTTPS'] ?? '');
    if ($https !== '' && strtolower($https) !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function handleGlobalSearchAction(PDO $db): void
{
    header('Content-Type: application/json; charset=UTF-8');

    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['results' => []], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $query = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($query) < 2) {
        http_response_code(400);
        echo json_encode(['results' => []], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $normalizedQuery = mb_strtolower($query);
    $likeQuery = '%' . $normalizedQuery . '%';

    $results = [];

    $addResult = static function (array &$results, string $type, string $label, string $sublabel, string $url, string $matchText, string $queryText, array $scoringHints = []): void {
        $normalizedMatch = mb_strtolower(trim($matchText));
        $normalizedQueryText = mb_strtolower(trim($queryText));

        $score = 0;
        if ($normalizedMatch === $normalizedQueryText) {
            $score += 300;
        } elseif (str_starts_with($normalizedMatch, $normalizedQueryText)) {
            $score += 200;
        } elseif (str_contains($normalizedMatch, $normalizedQueryText)) {
            $score += 120;
        }

        foreach ($scoringHints as $hint) {
            if ($hint === 'exact') {
                $score += 100;
            } elseif ($hint === 'primary') {
                $score += 40;
            }
        }

        $results[] = [
            'type' => $type,
            'label' => $label,
            'sublabel' => $sublabel,
            'url' => $url,
            '_score' => $score,
            '_label' => mb_strtolower($label),
        ];
    };

    $userStmt = $db->prepare('SELECT id, name, email, role FROM users WHERE LOWER(name) LIKE ? OR LOWER(email) LIKE ? LIMIT 20');
    $userStmt->execute([$likeQuery, $likeQuery]);
    foreach ($userStmt->fetchAll() as $row) {
        $name = (string) $row['name'];
        $email = (string) $row['email'];
        $role = (string) $row['role'];
        $userUrl = (($user['role'] ?? '') === 'admin')
            ? '?page=admin_students&q=' . rawurlencode($query)
            : (((string) ($row['id'] ?? '') === (string) ($user['id'] ?? '')) ? '?page=profile' : '?page=dashboard');
        $addResult(
            $results,
            'user',
            $name,
            strtoupper($role) . ' • ' . $email,
            $userUrl,
            $name . ' ' . $email,
            $query,
            [($name === $query || $email === $query) ? 'exact' : 'primary']
        );
    }

    $orgStmt = $db->prepare('SELECT id, name, description, org_category FROM organizations WHERE LOWER(name) LIKE ? OR LOWER(COALESCE(org_category, "")) LIKE ? LIMIT 20');
    $orgStmt->execute([$likeQuery, $likeQuery]);
    foreach ($orgStmt->fetchAll() as $row) {
        $name = (string) $row['name'];
        $category = trim((string) ($row['org_category'] ?? 'collegewide'));
        $description = trim((string) ($row['description'] ?? ''));
        $sublabel = $category !== '' ? 'Category: ' . $category : 'Organization';
        if ($description !== '') {
            $sublabel .= ' • ' . mb_substr(preg_replace('/\s+/', ' ', $description), 0, 90);
        }

        $addResult(
            $results,
            'org',
            $name,
            $sublabel,
            '?page=organizations',
            $name . ' ' . $category,
            $query,
            [($name === $query || mb_strtolower($category) === $normalizedQuery) ? 'exact' : 'primary']
        );
    }

    $announcementStmt = $db->prepare('SELECT a.id, a.title, a.content, o.name AS organization_name
        FROM announcements a
        JOIN organizations o ON o.id = a.organization_id
        WHERE LOWER(a.title) LIKE ? OR LOWER(a.content) LIKE ?
        LIMIT 20');
    $announcementStmt->execute([$likeQuery, $likeQuery]);
    foreach ($announcementStmt->fetchAll() as $row) {
        $title = (string) $row['title'];
        $content = preg_replace('/\s+/', ' ', trim(strip_tags((string) ($row['content'] ?? ''))));
        $snippet = $content !== '' ? mb_substr($content, 0, 90) : 'Announcement';
        $sublabel = (string) $row['organization_name'];
        if ($snippet !== '') {
            $sublabel .= ' • ' . $snippet;
        }

        $addResult(
            $results,
            'announcement',
            $title,
            $sublabel,
            '?page=announcements',
            $title . ' ' . $content,
            $query,
            [mb_strtolower($title) === $normalizedQuery ? 'exact' : 'primary']
        );
    }

    usort($results, static function (array $left, array $right): int {
        $scoreDiff = ($right['_score'] <=> $left['_score']);
        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        return $left['_label'] <=> $right['_label'];
    });

    $results = array_slice($results, 0, 10);
    $results = array_map(static function (array $result): array {
        unset($result['_score'], $result['_label']);
        return $result;
    }, $results);

    echo json_encode(['results' => $results], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();
sendSecurityHeaders();

$config = require __DIR__ . '/core/config.php';
$appTimezone = (string) ($config['timezone'] ?? 'Asia/Manila');
if (!date_default_timezone_set($appTimezone)) {
    date_default_timezone_set('Asia/Manila');
}

require __DIR__ . '/core/db.php';
require __DIR__ . '/core/helpers.php';
require __DIR__ . '/core/auth.php';
require __DIR__ . '/core/layout.php';
require __DIR__ . '/lib/pagination.php';
require __DIR__ . '/lib/organization.php';
require __DIR__ . '/lib/notifications.php';
require __DIR__ . '/lib/integrations.php';
require __DIR__ . '/lib/email.php';
require __DIR__ . '/lib/uploads.php';
require __DIR__ . '/lib/maintenance.php';
require __DIR__ . '/features/auth/actions.php';
require __DIR__ . '/features/organizations/workflows.php';
require __DIR__ . '/features/transactions/actions.php';
require __DIR__ . '/features/auth/pages.php';
require __DIR__ . '/features/admin/pages.php';
require __DIR__ . '/features/organizations/pages.php';
require __DIR__ . '/features/organizations/owner_pages.php';
require __DIR__ . '/features/dashboard/data.php';
require __DIR__ . '/features/dashboard/page.php';
require __DIR__ . '/features/dashboard/notifications.php';

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

    $sanitizeDebugDetail = static function (string $message): string {
        $message = preg_replace('/:\/\/([^:\s\/]+):([^@\s\/]+)@/', '://[redacted-user]:[redacted-pass]@', $message) ?? $message;
        $message = preg_replace('/\b(DB_PASSWORD|MYSQLPASSWORD|SMTP_PASS|DATABASE_URL|MYSQL_URL)\b\s*[:=]\s*\S+/i', '$1=[redacted]', $message) ?? $message;
        return $message;
    };

    $detail = $showDetails
        ? htmlspecialchars($sanitizeDebugDetail($e->getMessage()), ENT_QUOTES, 'UTF-8')
        : 'Check your local database settings in .env and your PHP/MySQL service logs.';
    if (!$showDetails && !empty($missingGroups)) {
        $detail .= ' Missing variable groups: ' . htmlspecialchars(implode(', ', $missingGroups), ENT_QUOTES, 'UTF-8') . '.';
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Websys Startup Error</title></head><body style="font-family:Arial,sans-serif;padding:24px">';
    echo '<h1>Application startup error</h1>';
    echo '<p>Database connection failed while starting the app.</p>';
    echo '<p><strong>Details:</strong> ' . $detail . '</p>';
    echo '<p>Expected local MySQL settings: DB_DRIVER=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.</p>';
    echo '</body></html>';
    exit;
}

$user = currentUser();
$page = $_GET['page'] ?? ($user ? 'dashboard' : 'home');
