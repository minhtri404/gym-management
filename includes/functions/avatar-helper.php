<?php

function resolve_user_avatar_url($avatar, $basePath, $fallbackName = 'User', $fallbackBackground = '111827', $fallbackColor = 'ffffff')
{
    $avatar = trim((string)$avatar);

    if ($avatar !== '') {
        if (preg_match('#^https?://#i', $avatar)) {
            return $avatar;
        }

        $avatarUrl = rtrim($basePath, '/') . '/uploads/avatars/' . rawurlencode($avatar);
        $avatarPath = __DIR__ . '/../../uploads/avatars/' . $avatar;

        if (is_file($avatarPath)) {
            $avatarUrl .= '?v=' . filemtime($avatarPath);
        }

        return $avatarUrl;
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($fallbackName)
        . '&background=' . urlencode($fallbackBackground)
        . '&color=' . urlencode($fallbackColor);
}

function is_local_avatar_file($avatar)
{
    $avatar = trim((string)$avatar);

    return $avatar !== '' && !preg_match('#^https?://#i', $avatar);
}
