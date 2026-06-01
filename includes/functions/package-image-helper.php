<?php

function resolve_package_image_url($image, $basePath, $fallback = '../../assets/images/ambitious-studio-rick-barrett-1RNQ11ZODJM-unsplash.jpg')
{
    $image = trim((string) $image);

    if ($image !== '') {
        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        return rtrim($basePath, '/') . '/uploads/packages/' . rawurlencode($image);
    }

    return $fallback;
}

function is_local_package_image($image)
{
    $image = trim((string) $image);
    return $image !== '' && !preg_match('#^https?://#i', $image);
}

function upload_package_image_file(array $file, string $uploadDir, int $packageId = 0): array
{
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (empty($file['name'] ?? '')) {
        return ['success' => false, 'message' => 'Vui lòng chọn ảnh gói tập.'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    $fileSize = (int) ($file['size'] ?? 0);
    $fileError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($fileError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Tải ảnh lên thất bại.'];
    }

    if ($fileSize > 3 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Ảnh gói tập phải nhỏ hơn 3MB.'];
    }

    if (!is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'File ảnh tải lên không hợp lệ.'];
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['success' => false, 'message' => 'Không thể tạo thư mục lưu ảnh gói tập.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!isset($allowedMimeTypes[$mimeType])) {
        return ['success' => false, 'message' => 'Chỉ chấp nhận file jpg, jpeg, png hoặc webp.'];
    }

    $extension = $allowedMimeTypes[$mimeType];
    $prefix = $packageId > 0 ? 'package_' . $packageId : 'package_new';
    $fileName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        return ['success' => false, 'message' => 'Không thể lưu file ảnh gói tập.'];
    }

    return [
        'success' => true,
        'file_name' => $fileName,
        'full_path' => $targetPath,
    ];
}
