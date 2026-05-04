<?php

function getUserById(mysqli $conn, int $user_id): ?array
{
    $stmt = $conn->prepare("SELECT id, full_name, email, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function findMemberByUserContact(mysqli $conn, string $phone, string $email): ?array
{
    $stmt = $conn->prepare("
        SELECT id, full_name, phone, email, package_id, start_date, end_date, status
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");
    $stmt->bind_param("ss", $phone, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();

    return $member ?: null;
}

function getWorkoutPlansByMemberId(mysqli $conn, int $member_id): array
{
    $items = [];

    $stmt = $conn->prepare("
        SELECT id, goal, level, days_per_week, health_note, ai_response, status, created_at
        FROM ai_workout_plans
        WHERE member_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();

    return $items;
}

function getMealPlansByMemberId(mysqli $conn, int $member_id): array
{
    $items = [];

    $stmt = $conn->prepare("
        SELECT id, goal, body_type, meals_per_day, health_note, ai_response, status, created_at
        FROM ai_meal_plans
        WHERE member_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();

    return $items;
}

function planStatusBadge(string $status): string
{
    $status = trim($status);

    switch ($status) {
        case 'active':
            return '<span class="badge bg-success">Đang dùng</span>';
        case 'inactive':
            return '<span class="badge bg-secondary">Ngưng</span>';
        default:
            return '<span class="badge bg-dark">' . htmlspecialchars($status) . '</span>';
    }
}

function formatAiResponse(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '<p class="mb-0 text-muted">Chưa có nội dung.</p>';
    }

    $lines = preg_split('/\r\n|\r|\n/', $text);
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^[-*•]\s+(.+)$/u', $line, $matches)) {
            if (!$inList) {
                $html .= '<ul class="ai-plan-list mb-3">';
                $inList = true;
            }
            $html .= '<li>' . htmlspecialchars($matches[1]) . '</li>';
        } else {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<p class="ai-plan-paragraph">' . nl2br(htmlspecialchars($line)) . '</p>';
        }
    }

    if ($inList) {
        $html .= '</ul>';
    }

    return $html;
}

function parseAiPlanByDay(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $lines = preg_split('/\r\n|\r|\n/', $text);
    $days = [];
    $currentTitle = '';
    $currentContent = [];

    $dayPattern = '/^(ngày\s*\d+|thu\s*\d+|thứ\s*\d+|day\s*\d+)\s*[:\-]?\s*(.*)$/iu';

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            if (!empty($currentContent)) {
                $currentContent[] = '';
            }
            continue;
        }

        if (preg_match($dayPattern, $line, $matches)) {
            if ($currentTitle !== '') {
                $days[] = [
                    'title' => $currentTitle,
                    'content' => trim(implode("\n", $currentContent)),
                ];
            }

            $prefix = trim($matches[1]);
            $suffix = trim($matches[2]);

            $currentTitle = $suffix !== '' ? $prefix . ': ' . $suffix : $prefix;
            $currentContent = [];
        } else {
            if ($currentTitle === '') {
                $currentTitle = 'Tổng quan kế hoạch';
            }
            $currentContent[] = $line;
        }
    }

    if ($currentTitle !== '') {
        $days[] = [
            'title' => $currentTitle,
            'content' => trim(implode("\n", $currentContent)),
        ];
    }

    return $days;
}

function renderWorkoutPlanByDay(string $text): string
{
    $days = parseAiPlanByDay($text);

    if (empty($days)) {
        return formatAiResponse($text);
    }

    $html = '<div class="workout-day-grid">';

    foreach ($days as $day) {
        $html .= '<div class="workout-day-card">';
        $html .= '<div class="workout-day-title">' . htmlspecialchars($day['title']) . '</div>';
        $html .= '<div class="workout-day-content">' . formatAiResponse($day['content']) . '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}