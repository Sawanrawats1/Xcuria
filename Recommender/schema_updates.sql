-- schema_updates.sql
-- Run this once against your existing `xcuria` database (phpMyAdmin, or:
--   mysql -u root -P 3309 xcuria < schema_updates.sql
-- ) before using the attendance / suitability-scoring features.
-- Does NOT touch any existing table — purely additive.

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('present', 'absent') NOT NULL DEFAULT 'absent',
    marked_by INT NOT NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (event_id, student_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id)
);
