<?php
declare(strict_types=1);

// Add nullable venue capacity and per-court booking assignments without deleting data.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this migration from the command line only.\n");
}
require __DIR__ . '/config/database.php';

$hasColumn = static function (PDO $pdo, string $table, string $column): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $statement->execute([$table, $column]);
    return (int)$statement->fetchColumn() > 0;
};
$hasIndex = static function (PDO $pdo, string $table, string $index): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $statement->execute([$table, $index]);
    return (int)$statement->fetchColumn() > 0;
};

if (!$hasIndex($pdo, 'booking_details', 'ix_booking_detail_court')) {
    $pdo->exec('ALTER TABLE booking_details ADD KEY ix_booking_detail_court (court_id)');
    echo "Added a court_id index to preserve foreign-key support during index replacement.\n";
}

if (!$hasColumn($pdo, 'courts', 'court_count')) {
    $pdo->exec('ALTER TABLE courts ADD COLUMN court_count SMALLINT UNSIGNED NULL AFTER price_per_hour');
    echo "Added courts.court_count (nullable).\n";
}
if (!$hasColumn($pdo, 'courts', 'price_note')) {
    $pdo->exec('ALTER TABLE courts ADD COLUMN price_note VARCHAR(255) NULL AFTER price_per_hour');
    echo "Added courts.price_note for price estimate/source notes.\n";
}
if (!$hasColumn($pdo, 'booking_details', 'court_number')) {
    $pdo->exec('ALTER TABLE booking_details ADD COLUMN court_number SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER court_id');
    echo "Added booking_details.court_number (existing rows default to 1).\n";
}
$slotActive = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='booking_details' AND COLUMN_NAME='slot_active'")->fetchColumn();
if ($slotActive === 'NO') {
    $pdo->exec('ALTER TABLE booking_details MODIFY slot_active TINYINT UNSIGNED NULL DEFAULT 1');
    echo "Made booking_details.slot_active nullable so canceled reservations can release their unique slot.\n";
}
$pdo->exec("UPDATE booking_details d JOIN bookings b ON b.id=d.booking_id SET d.slot_active=NULL WHERE b.status='cancelled' AND d.slot_active=1");
$indexColumns = $pdo->prepare('SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
$indexColumns->execute(['booking_details', 'uq_court_date_slot_number']);
$currentIndexColumns = (string)$indexColumns->fetchColumn();
if ($currentIndexColumns !== 'court_id,booking_date,time_slot_id,court_number,slot_active') {
    if ($currentIndexColumns !== '') $pdo->exec('ALTER TABLE booking_details DROP INDEX uq_court_date_slot_number');
    $pdo->exec('ALTER TABLE booking_details ADD UNIQUE KEY uq_court_date_slot_number (court_id, booking_date, time_slot_id, court_number, slot_active)');
    echo "Ensured per-court active-slot uniqueness index.\n";
}
if ($hasIndex($pdo, 'booking_details', 'uq_court_date_slot')) {
    $pdo->exec('ALTER TABLE booking_details DROP INDEX uq_court_date_slot');
    echo "Removed old single-booking-per-venue index after adding the new index.\n";
}

$insertSlot = $pdo->prepare('INSERT IGNORE INTO time_slots (start_time, end_time) VALUES (?, ?)');
for ($hour = 5; $hour < 24; $hour++) {
    $insertSlot->execute([sprintf('%02d:00', $hour), sprintf('%02d:00', $hour + 1)]);
}
echo "Ensured hourly booking slots from 05:00 to 24:00.\n";
