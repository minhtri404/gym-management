<?php

function resolve_trainer_avatar_url($trainerId, $avatar, $basePath)
{
    $basePath = rtrim((string) $basePath, '/') . '/';
    $avatar = trim((string) $avatar);

    if ($avatar !== '' && preg_match('#^https?://#i', $avatar)) {
        return $avatar;
    }

    if ($avatar !== '') {
        $fileName = basename($avatar);
        $uploadPath = __DIR__ . '/../../uploads/trainers/' . $fileName;

        if (is_file($uploadPath)) {
            return $basePath . 'uploads/trainers/' . rawurlencode($fileName);
        }

        $assetPath = __DIR__ . '/../../user/includes/assets/images/trainers/' . $fileName;

        if (is_file($assetPath)) {
            return $basePath . 'user/includes/assets/images/trainers/' . rawurlencode($fileName);
        }
    }

    $fallbackIndex = ((max(1, (int) $trainerId) - 1) % 3) + 1;

    return $basePath . 'user/includes/assets/images/trainers/trainer-' . $fallbackIndex . '.jpg';
}

function upload_trainer_avatar_file(array $file, string $uploadDir, int $trainerId = 0): array
{
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'message' => 'Không thể tải ảnh HLV. Vui lòng chọn lại file.',
        ];
    }

    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        return [
            'success' => false,
            'message' => 'Ảnh HLV không được vượt quá 3MB.',
        ];
    }

    $mimeType = '';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = (string) finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    if ($mimeType === '' && function_exists('mime_content_type')) {
        $mimeType = (string) mime_content_type($file['tmp_name']);
    }

    if (!isset($allowedTypes[$mimeType])) {
        return [
            'success' => false,
            'message' => 'Ảnh HLV chỉ hỗ trợ JPG, PNG hoặc WEBP.',
        ];
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        return [
            'success' => false,
            'message' => 'Không thể tạo thư mục lưu ảnh HLV.',
        ];
    }

    $prefix = $trainerId > 0 ? 'trainer_' . $trainerId : 'trainer';
    $fileName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
    $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => false,
            'message' => 'Không thể lưu ảnh HLV sau khi tải lên.',
        ];
    }

    return [
        'success' => true,
        'file_name' => $fileName,
    ];
}

function is_local_trainer_uploaded_file($avatar): bool
{
    $avatar = trim((string) $avatar);
    if ($avatar === '' || preg_match('#^https?://#i', $avatar)) {
        return false;
    }

    return is_file(__DIR__ . '/../../uploads/trainers/' . basename($avatar));
}
