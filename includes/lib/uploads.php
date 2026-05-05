<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - UPLOAD HELPERS
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. Secure Generic Uploads
 * 2. Profile/Organization Image Uploads
 * 3. Stored Upload Deletion
 *
 * EDIT GUIDE:
 * - Edit this file for upload validation, naming, storage paths, or cleanup behavior.
 * ================================================
 */

function handleSecureUpload(array $file, string $uploadDir): string|false
{
    if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        setFlash('error', 'No valid uploaded file was received.');
        return false;
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($errorCode !== UPLOAD_ERR_OK) {
        setFlash('error', 'File upload failed. Please try again.');
        return false;
    }

    $size = (int) ($file['size'] ?? 0);
    $maxBytes = 8 * 1024 * 1024;
    if ($size <= 0 || $size > $maxBytes) {
        setFlash('error', 'File size exceeds the 8MB upload limit.');
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file((string) $file['tmp_name']);

    $allowedMimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    if (!isset($allowedMimeToExtension[$mimeType])) {
        setFlash('error', 'Only JPG, PNG, GIF, WEBP images or PDF files are allowed.');
        return false;
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        setFlash('error', 'Unable to prepare upload directory.');
        return false;
    }

    $safeExtension = $allowedMimeToExtension[$mimeType];
    $filename = bin2hex(random_bytes(16)) . '.' . $safeExtension;
    $destination = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        setFlash('error', 'Unable to store uploaded file.');
        return false;
    }

    return 'uploads/' . $filename;
}

function handleProfileImageUpload(array $file, string $uploadDir, string $filenamePrefix = 'img_'): string|false
{
    if (empty($file['name']) || empty($file['tmp_name'])) {
        return false;
    }

    if (!is_uploaded_file((string) $file['tmp_name'])) {
        setFlash('error', 'No valid uploaded image was received.');
        return false;
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($errorCode !== UPLOAD_ERR_OK) {
        setFlash('error', 'Image upload failed. Please try again.');
        return false;
    }

    $size = (int) ($file['size'] ?? 0);
    $maxBytes = 3 * 1024 * 1024;
    if ($size <= 0 || $size > $maxBytes) {
        setFlash('error', 'Image size must be between 1 byte and 3MB.');
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file((string) $file['tmp_name']);
    $allowedMimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeToExtension[$mimeType])) {
        setFlash('error', 'Only JPG, PNG, GIF, or WEBP images are allowed.');
        return false;
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        setFlash('error', 'Unable to prepare upload directory.');
        return false;
    }

    $targetFolder = str_starts_with($filenamePrefix, 'org_') ? 'organizations' : 'users';
    $targetDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $targetFolder;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        setFlash('error', 'Unable to prepare profile image directory.');
        return false;
    }

    $filename = $filenamePrefix . bin2hex(random_bytes(16)) . '.' . $allowedMimeToExtension[$mimeType];
    $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        setFlash('error', 'Unable to store uploaded image.');
        return false;
    }

    return 'uploads/' . $targetFolder . '/' . $filename;
}

function deleteStoredUpload(?string $path): void
{
    $normalized = trim((string) $path);
    if (str_starts_with($normalized, 'public/uploads/')) {
        $normalized = 'uploads/' . substr($normalized, strlen('public/uploads/'));
    }

    if ($normalized === '' || !str_starts_with($normalized, 'uploads/')) {
        return;
    }

    $relativePath = substr($normalized, strlen('uploads/'));
    $relativePath = str_replace('\\', '/', $relativePath);
    if ($relativePath === '' || str_contains($relativePath, '..')) {
        return;
    }

    $pathParts = array_filter(explode('/', $relativePath), static fn (string $part): bool => $part !== '');
    if (empty($pathParts)) {
        return;
    }

    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $pathParts);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}
