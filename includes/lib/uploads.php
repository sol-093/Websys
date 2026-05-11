<?php

declare(strict_types=1);

use Involve\Support\Uploads\StoredUploadPath;

/*
 * ================================================
 * INVOLVE - UPLOAD HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. Secure Generic Uploads
 * 2. Profile/Organization Image Uploads
 * 3. Image Reprocessing
 * 4. Stored Upload Deletion
 *
 * WORK GUIDE:
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

    $uploadSettings = appConfig()['settings']['uploads'] ?? [];
    $size = (int) ($file['size'] ?? 0);
    $maxBytes = (int) ($uploadSettings['max_generic_bytes'] ?? (8 * 1024 * 1024));
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
    $allowedOriginalExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

    if (!uploadExtensionIsAllowed($file, $allowedOriginalExtensions)) {
        setFlash('error', 'Only JPG, PNG, GIF, WEBP images or PDF files are allowed.');
        return false;
    }

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
    $uploadSettings = appConfig()['settings']['uploads'] ?? [];
    $maxBytes = (int) ($uploadSettings['max_image_bytes'] ?? (3 * 1024 * 1024));
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
    $allowedOriginalExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!uploadExtensionIsAllowed($file, $allowedOriginalExtensions)) {
        setFlash('error', 'Only JPG, PNG, GIF, or WEBP images are allowed.');
        return false;
    }

    if (!isset($allowedMimeToExtension[$mimeType])) {
        setFlash('error', 'Only JPG, PNG, GIF, or WEBP images are allowed.');
        return false;
    }

    $imageSize = @getimagesize((string) $file['tmp_name']);
    if (!is_array($imageSize)) {
        setFlash('error', 'Uploaded image could not be inspected.');
        return false;
    }

    $maxWidth = (int) ($uploadSettings['image_max_width'] ?? 4096);
    $maxHeight = (int) ($uploadSettings['image_max_height'] ?? 4096);
    if ((int) ($imageSize[0] ?? 0) <= 0 || (int) ($imageSize[1] ?? 0) <= 0 || (int) $imageSize[0] > $maxWidth || (int) $imageSize[1] > $maxHeight) {
        setFlash('error', 'Image dimensions are too large. Please upload an image up to ' . $maxWidth . 'x' . $maxHeight . ' pixels.');
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

    $safeExtension = $allowedMimeToExtension[$mimeType];
    $filename = $filenamePrefix . bin2hex(random_bytes(16)) . '.' . $safeExtension;
    $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!reprocessUploadedImage((string) $file['tmp_name'], $destination, $mimeType)) {
        setFlash('error', 'Unable to store uploaded image.');
        return false;
    }

    return 'uploads/' . $targetFolder . '/' . $filename;
}

function reprocessUploadedImage(string $sourcePath, string $destinationPath, string $mimeType): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $image = loadUploadImageResource($sourcePath, $mimeType);
    if (!$image instanceof GdImage) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    if ($width <= 0 || $height <= 0) {
        cleanupUploadImageResource($image);
        return false;
    }

    $canvas = imagecreatetruecolor($width, $height);
    if (!$canvas instanceof GdImage) {
        cleanupUploadImageResource($image);
        return false;
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    if ($transparent !== false) {
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
    }
    imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

    $saved = saveReprocessedImage($canvas, $destinationPath, $mimeType);
    cleanupUploadImageResource($canvas);
    cleanupUploadImageResource($image);

    if (!$saved && is_file($destinationPath)) {
        @unlink($destinationPath);
    }

    return $saved;
}

function cleanupUploadImageResource(GdImage $image): void
{
    if (PHP_VERSION_ID < 80500) {
        imagedestroy($image);
    }
}

function loadUploadImageResource(string $sourcePath, string $mimeType): ?GdImage
{
    $image = match ($mimeType) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
        'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($sourcePath) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default => false,
    };

    return $image instanceof GdImage ? $image : null;
}

function saveReprocessedImage(GdImage $image, string $destinationPath, string $mimeType): bool
{
    return match ($mimeType) {
        'image/jpeg' => function_exists('imagejpeg') && imagejpeg($image, $destinationPath, 88),
        'image/png' => function_exists('imagepng') && imagepng($image, $destinationPath, 6),
        'image/gif' => function_exists('imagegif') && imagegif($image, $destinationPath),
        'image/webp' => function_exists('imagewebp') && imagewebp($image, $destinationPath, 86),
        default => false,
    };
}

function deleteStoredUpload(?string $path): void
{
    StoredUploadPath::fromPublicPath($path)?->deleteIfFile();
}
