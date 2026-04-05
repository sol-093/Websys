<?php

declare(strict_types=1);

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
