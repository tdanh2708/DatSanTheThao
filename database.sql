-- DatSanTheThao - import manually in phpMyAdmin; this file does not import itself.
CREATE DATABASE IF NOT EXISTS dat_san_the_thao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dat_san_the_thao;

CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(100) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,
 phone VARCHAR(30) NULL, role ENUM('user','admin') NOT NULL DEFAULT 'user',
 status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS sports (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE,
 description TEXT NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS courts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sport_id INT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL, description TEXT NULL, address VARCHAR(255) NOT NULL,
 price_per_hour DECIMAL(12,2) NOT NULL DEFAULT 0, price_note VARCHAR(255) NULL,
 court_count SMALLINT UNSIGNED NULL, image VARCHAR(255) NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_court_sport FOREIGN KEY (sport_id) REFERENCES sports(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS time_slots (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, start_time TIME NOT NULL, end_time TIME NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active', UNIQUE KEY uq_slot_time(start_time,end_time)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bookings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 court_id INT UNSIGNED NOT NULL, booking_date DATE NOT NULL, total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
 note VARCHAR(500) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_booking_court FOREIGN KEY (court_id) REFERENCES courts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 KEY ix_booking_date_court(booking_date,court_id), KEY ix_booking_user(user_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_details (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_id BIGINT UNSIGNED NOT NULL,
 time_slot_id INT UNSIGNED NOT NULL, price DECIMAL(12,2) NOT NULL,
 court_id INT UNSIGNED NOT NULL, court_number SMALLINT UNSIGNED NOT NULL DEFAULT 1, booking_date DATE NOT NULL,
 slot_active TINYINT UNSIGNED NULL DEFAULT 1,
 CONSTRAINT fk_detail_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_detail_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_detail_court FOREIGN KEY (court_id) REFERENCES courts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 UNIQUE KEY uq_court_date_slot_number(court_id,booking_date,time_slot_id,court_number,slot_active), KEY ix_detail_booking(booking_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 court_id INT UNSIGNED NOT NULL, booking_id BIGINT UNSIGNED NOT NULL UNIQUE,
 rating TINYINT UNSIGNED NOT NULL, comment TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_review_court FOREIGN KEY (court_id) REFERENCES courts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_review_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 booking_id BIGINT UNSIGNED NOT NULL,
 payment_method ENUM('venue','bank_transfer') NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
 transaction_code VARCHAR(100) NULL,
 payment_submitted_at DATETIME NULL DEFAULT NULL,
 paid_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_payments_booking (booking_id), KEY idx_payments_status(status),
 CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conversations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 status ENUM('open','closed') NOT NULL DEFAULT 'open',
 last_message_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_conversation_user(user_id), KEY ix_conversation_last_message(last_message_at),
 CONSTRAINT fk_conversation_user FOREIGN KEY(user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 conversation_id BIGINT UNSIGNED NOT NULL,
 sender_id INT UNSIGNED NOT NULL,
 message TEXT NULL,
 image_path VARCHAR(255) NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY ix_message_conversation(conversation_id,id), KEY ix_message_unread(conversation_id,is_read),
 CONSTRAINT fk_message_conversation FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_message_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 recipient_role ENUM('admin','user') NOT NULL,
 recipient_id INT UNSIGNED NULL,
 notification_type VARCHAR(40) NOT NULL,
 message VARCHAR(255) NOT NULL,
 target_url VARCHAR(255) NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY ix_notification_recipient(recipient_role,recipient_id,is_read,created_at),
 CONSTRAINT fk_notification_user FOREIGN KEY(recipient_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 admin_id INT UNSIGNED NOT NULL,
 action VARCHAR(60) NOT NULL,
 entity_type VARCHAR(40) NOT NULL,
 entity_id BIGINT UNSIGNED NULL,
 description VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY ix_admin_log_created(created_at),
 CONSTRAINT fk_admin_log_user FOREIGN KEY(admin_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO sports(name,description) VALUES
('Bóng đá','Sân bóng đá mini.'),('Cầu lông','Sân cầu lông trong nhà.'),('Tennis','Sân tennis tiêu chuẩn.'),
('Pickleball','Sân pickleball.'),('Bóng chuyền','Sân bóng chuyền.'),('Bóng rổ','Sân bóng rổ.'),
('Futsal','Sân futsal dành cho các trận bóng trong nhà, tập luyện và thi đấu theo nhóm.'),
('Bóng bàn','Tìm địa điểm bóng bàn phù hợp cho tập luyện, giao lưu và thi đấu.');
INSERT IGNORE INTO time_slots(start_time,end_time) VALUES
('06:00','07:00'),('07:00','08:00'),('08:00','09:00'),('09:00','10:00'),
('10:00','11:00'),('11:00','12:00'),('12:00','13:00'),('13:00','14:00'),('14:00','15:00'),('15:00','16:00'),
('16:00','17:00'),('17:00','18:00'),('18:00','19:00'),('19:00','20:00'),('20:00','21:00'),('21:00','22:00');
-- Admin is created via setup_admin.php after import; no plaintext password is stored here.
