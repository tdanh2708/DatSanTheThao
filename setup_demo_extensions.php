<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this setup from the command line.\n");
}

require __DIR__ . '/config/database.php';

// Additive migration: existing booking, user, and venue rows are untouched.
$pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    payment_method ENUM('venue','bank_transfer') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    transaction_code VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payments_booking (booking_id),
    KEY idx_payments_status (status),
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$paymentSubmittedColumn = $pdo->query("SHOW COLUMNS FROM payments LIKE 'payment_submitted_at'")->fetch();
if (!$paymentSubmittedColumn) {
    $pdo->exec('ALTER TABLE payments ADD COLUMN payment_submitted_at DATETIME NULL DEFAULT NULL AFTER transaction_code');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    last_message_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conversation_user(user_id), KEY ix_conversation_last_message(last_message_at),
    CONSTRAINT fk_conversation_user FOREIGN KEY(user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
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
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
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
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    action VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_admin_log_created(created_at),
    CONSTRAINT fk_admin_log_user FOREIGN KEY(admin_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB");

$sportsData = [
    ['Futsal', 'Sân futsal tại TP. Hồ Chí Minh. Lượt đặt trên website hiện là mô phỏng đồ án.'],
    ['Bóng bàn', 'Câu lạc bộ bóng bàn tại TP. Hồ Chí Minh. Lượt đặt trên website hiện là mô phỏng đồ án.'],
];
$findSport = $pdo->prepare('SELECT id FROM sports WHERE name=? LIMIT 1');
$insertSport = $pdo->prepare('INSERT INTO sports (name,description) VALUES (?,?)');
$sportIds = [];
foreach ($sportsData as [$name, $description]) {
    $findSport->execute([$name]);
    $sportId = $findSport->fetchColumn();
    if ($sportId === false) {
        $insertSport->execute([$name, $description]);
        $sportId = $pdo->lastInsertId();
    }
    $sportIds[$name] = (int)$sportId;
}

// Venue names and addresses below are source-backed. Prices and booking
// availability are explicitly illustrative and are not venue quotations.
$venues = [
    ['Futsal', 'Sân Futsal Ngoài Trời Quận 6', 'Đường số 10, Phường 11, Quận 6, TP. Hồ Chí Minh', 'Địa điểm futsal ngoài trời tại Quận 6. Giá và lịch đặt trên website là dữ liệu mô phỏng.', 300000, null, 'futsal.png'],
    ['Futsal', 'Nhà Thi Đấu Lãnh Binh Thăng', '283 Lãnh Binh Thăng, Phường 8, Quận 11, TP. Hồ Chí Minh', 'Nhà thi đấu đa năng từng tổ chức futsal. Giá và lịch đặt trên website là dữ liệu mô phỏng.', 350000, null, 'futsal.png'],
    ['Futsal', 'Nhà Thi Đấu Quận 12', '493 Đường Dương Thị Mười, Phường Hiệp Thành, Quận 12, TP. Hồ Chí Minh', 'Nhà thi đấu có hoạt động futsal. Giá và lịch đặt trên website là dữ liệu mô phỏng.', 300000, null, 'futsal.png'],
    ['Bóng bàn', 'CLB Bóng bàn Quận 12 (Ý PingPong)', 'Số 9 Lê Quang Đạo, Phường Trung Mỹ Tây, TP. Hồ Chí Minh', 'Câu lạc bộ bóng bàn tại Trung tâm Cung ứng dịch vụ Văn hóa – Thể thao. Giá và lịch đặt mô phỏng.', 90000, null, 'table-tennis.png'],
    ['Bóng bàn', 'CLB Bóng bàn Lê Quý Đôn', '3B Lê Quý Đôn, Phường Phú Nhuận, TP. Hồ Chí Minh', 'Câu lạc bộ bóng bàn. Giá và lịch đặt trên website là dữ liệu mô phỏng.', 90000, null, 'table-tennis.png'],
    ['Bóng bàn', 'CLB Bóng bàn Phú Mỹ Hưng', 'Nhà sinh hoạt cộng đồng chung cư Sky Garden, Phường Tân Hưng, TP. Hồ Chí Minh', 'Câu lạc bộ bóng bàn theo danh sách hội viên Liên đoàn Bóng bàn TP.HCM. Giá và lịch đặt mô phỏng.', 90000, null, 'table-tennis.png'],
];

$findVenue = $pdo->prepare('SELECT id FROM courts WHERE sport_id=? AND LOWER(TRIM(name))=LOWER(TRIM(?)) AND LOWER(TRIM(address))=LOWER(TRIM(?)) LIMIT 1');
$insertVenue = $pdo->prepare("INSERT INTO courts (sport_id,name,description,address,price_per_hour,price_note,court_count,image,status)
    VALUES (?,?,?,?,?, 'Giá mô phỏng đồ án; không phải báo giá của địa điểm. Lịch đặt trên website chưa kết nối với đơn vị vận hành.', ?, ?, 'active')");
$added = ['Futsal' => 0, 'Bóng bàn' => 0];
foreach ($venues as [$sport, $name, $address, $description, $price, $capacity, $image]) {
    $sportId = $sportIds[$sport];
    $findVenue->execute([$sportId, $name, $address]);
    if ($findVenue->fetchColumn() !== false) continue;
    $insertVenue->execute([$sportId, $name, $description, $address, $price, $capacity, $image]);
    $added[$sport]++;
}

// The source reports tables, not a confirmed number of bookable court units.
// Clear only the initial demo value, and only while no booking references it.
$unknownTableCapacity = $pdo->prepare("UPDATE courts SET court_count=NULL
    WHERE name='CLB Bóng bàn Quận 12 (Ý PingPong)' AND court_count=10
      AND NOT EXISTS (SELECT 1 FROM bookings WHERE bookings.court_id=courts.id)");
$unknownTableCapacity->execute();

$summary = $pdo->query("SELECT s.name, COUNT(c.id) AS venue_count FROM sports s LEFT JOIN courts c ON c.sport_id=s.id AND c.status='active' WHERE s.name IN ('Futsal','Bóng bàn') GROUP BY s.id,s.name ORDER BY s.name")->fetchAll();
echo "Payments table is ready. Newly added Futsal venues: {$added['Futsal']}; Table Tennis venues: {$added['Bóng bàn']}.\n";
foreach ($summary as $row) echo $row['name'] . ': ' . (int)$row['venue_count'] . " active venues\n";
echo 'Active admin accounts: ' . (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn() . "\n";
