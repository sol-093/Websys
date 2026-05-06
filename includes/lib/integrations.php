<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - INTEGRATION HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. Base URL Resolution
 * 2. Google OAuth Readiness
 * 3. JSON Fetch Helper
 *
 * WORK GUIDE:
 * - Edit this file for external integration helpers.
 * ================================================
 */

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
