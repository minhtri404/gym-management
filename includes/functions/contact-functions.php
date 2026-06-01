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
    // Ensure contacts table exists (try to create if missing)
    if (!ensureContactsTableExists($conn)) {
        return false;
    }

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

function ensureContactsTableExists(mysqli $conn): bool
{
    $check = $conn->query("SHOW TABLES LIKE 'contacts'");
    if ($check && $check->num_rows > 0) {
        return true;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `contacts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `full_name` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `subject` VARCHAR(255) DEFAULT NULL,
        `message` TEXT NOT NULL,
        `preferred_contact_method` VARCHAR(50) DEFAULT 'phone',
        `status` VARCHAR(50) DEFAULT 'new',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_contacts_status` (`status`),
        INDEX `idx_contacts_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    return $conn->query($sql) !== false;
}