CREATE TABLE IF NOT EXISTS trainer_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    goal VARCHAR(255) NOT NULL,
    note TEXT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_trainer_bookings_trainer_id (trainer_id),
    INDEX idx_trainer_bookings_member_id (member_id),
    INDEX idx_trainer_bookings_status (status),
    CONSTRAINT fk_trainer_bookings_trainer
        FOREIGN KEY (trainer_id) REFERENCES trainers(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_trainer_bookings_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
