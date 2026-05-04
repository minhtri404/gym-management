<?php

function validateContactInput(string $full_name, string $phone, string $message): array
{
    $errors = [];

    if ($full_name === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }

    if ($phone === '') {
        $errors[] = 'Vui lòng nhập số điện thoại.';
    }

    if ($message === '') {
        $errors[] = 'Vui lòng nhập nội dung liên hệ.';
    }

    return $errors;
}

function createContact(
    mysqli $conn,
    string $full_name,
    string $phone,
    string $email,
    string $subject,
    string $message,
    string $preferred_contact_method = 'phone'
): bool {
    $status = 'new';

    $stmt = $conn->prepare("
        INSERT INTO contacts (full_name, phone, email, subject, message, preferred_contact_method, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "sssssss",
        $full_name,
        $phone,
        $email,
        $subject,
        $message,
        $preferred_contact_method,
        $status
    );

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}