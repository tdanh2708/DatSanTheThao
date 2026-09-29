<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this demo seeder from the command line.\n");
}

require __DIR__ . '/config/database.php';

$demoUsers = [
    ['Nguyễn Minh Anh', 'minhanh'],
    ['Trần Hoàng Nam', 'hoangnam'],
    ['Lê Minh Khang', 'minhkhang'],
    ['Phạm Gia Huy', 'giahuy'],
    ['Nguyễn Thanh Hương', 'thanhhuong'],
    ['Trần Ngọc Mai', 'ngocmai'],
    ['Võ Quốc Bảo', 'quocbao'],
    ['Phan Thảo Vy', 'thaovy'],
    ['Đặng Tuấn Kiệt', 'tuankiet'],
    ['Bùi Khánh Linh', 'khanhlinh'],
];
$demoPassword = 'DemoCourt2026!';
$demoMarker = '[DEMO] Sports Court Booking';
$reviewComments = [
    'Sân sạch, mặt sân khá tốt.',
    'Không gian thoáng, buổi tối ánh sáng ổn.',
    'Đặt sân thuận tiện, nhân viên hỗ trợ tốt.',
    'Vị trí hơi khó tìm nhưng chất lượng sân tốt.',
    'Mặt sân ổn, phù hợp chơi cùng bạn bè.',
    'Khu vực thay đồ gọn gàng và sạch sẽ.',
    'Trải nghiệm tốt, khung giờ bắt đầu đúng hẹn.',
    'Sân rộng, thiết bị được bảo quản khá tốt.',
];
$ratings = [5, 4, 5, 3, 4, 5, 4, 2, 5, 4, 3, 5, 4, 5, 3, 4, 5, 4, 2, 5, 4, 3, 5, 4];

try {
    $pdo->beginTransaction();

    // Deactivate only out-of-range slots. Preserve every row for booking history.
    $pdo->exec("UPDATE time_slots SET status = 'inactive' WHERE start_time < '06:00:00' OR end_time > '22:00:00'");

    $users = [];
    $findUser = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $insertUser = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, 'user', 'active')");
    $updateUser = $pdo->prepare("UPDATE users SET full_name=? WHERE id=? AND role='user'");
    foreach ($demoUsers as [$name, $emailName]) {
        $email = 'demo.' . $emailName . '@example.test';
        $findUser->execute([$email]);
        $userId = $findUser->fetchColumn();
        if (!$userId) {
            $insertUser->execute([$name, $email, password_hash($demoPassword, PASSWORD_DEFAULT)]);
            $userId = $pdo->lastInsertId();
        } else {
            $updateUser->execute([$name, (int)$userId]);
        }
        $users[] = ['id' => (int)$userId, 'name' => $name, 'email' => $email];
    }

    $slots = $pdo->query("SELECT id, start_time, end_time FROM time_slots
        WHERE status = 'active' AND start_time >= '06:00:00' AND end_time <= '22:00:00'
        ORDER BY start_time")->fetchAll();
    $courts = $pdo->query("SELECT id, price_per_hour FROM courts WHERE status = 'active' AND price_per_hour > 0 ORDER BY id LIMIT 34")->fetchAll();
    if (count($slots) < 1 || count($courts) < 34) {
        throw new RuntimeException('Cần ít nhất một khung giờ hợp lệ và 34 sân đang hoạt động có giá để tạo bộ dữ liệu demo.');
    }

    $existingDemo = $pdo->prepare('SELECT id, user_id, court_id, booking_date, status FROM bookings WHERE note = ? AND court_id = ? AND booking_date = ? LIMIT 1');
    $insertBooking = $pdo->prepare('INSERT INTO bookings (user_id, court_id, booking_date, total_amount, status, note) VALUES (?, ?, ?, ?, ?, ?)');
    $insertDetail = $pdo->prepare('INSERT INTO booking_details (booking_id, time_slot_id, price, court_id, court_number, booking_date) VALUES (?, ?, ?, ?, ?, ?)');
    $occupiedQuery = $pdo->prepare("SELECT DISTINCT d.court_number FROM booking_details d
        JOIN bookings b ON b.id = d.booking_id JOIN time_slots prior ON prior.id = d.time_slot_id
        WHERE d.court_id = ? AND d.booking_date = ? AND d.slot_active = 1
          AND b.status IN ('pending','confirmed','completed')
          AND prior.start_time < ? AND prior.end_time > ?");
    $demoBookings = [];
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Ho_Chi_Minh'));

    foreach ($courts as $index => $court) {
        $courtId = (int)$court['id'];
        $isCompleted = $index < 24 || $index >= 30;
        $date = $isCompleted
            ? $today->modify('-' . ($index + 1) . ' days')->format('Y-m-d')
            : $today->modify('+' . ($index - 23) . ' days')->format('Y-m-d');
        $existingDemo->execute([$demoMarker, $courtId, $date]);
        $booking = $existingDemo->fetch();
        if (!$booking) {
            $placed = false;
            $slotOffset = ($index * 3 + 5) % count($slots);
            for ($attempt = 0; $attempt < count($slots); $attempt++) {
                $slot = $slots[($slotOffset + $attempt) % count($slots)];
                $occupiedQuery->execute([$courtId, $date, $slot['end_time'], $slot['start_time']]);
                $occupiedNumbers = array_map('intval', $occupiedQuery->fetchAll(PDO::FETCH_COLUMN));
                $capacityQuery = $pdo->prepare('SELECT COALESCE(court_count, 1) FROM courts WHERE id = ?');
                $capacityQuery->execute([$courtId]);
                $capacity = max(1, (int)$capacityQuery->fetchColumn());
                $courtNumber = null;
                for ($number = 1; $number <= $capacity; $number++) {
                    if (!in_array($number, $occupiedNumbers, true)) {
                        $courtNumber = $number;
                        break;
                    }
                }
                if ($courtNumber === null) continue;

                $price = (float)$court['price_per_hour'] * ((strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 3600);
                $userId = $users[$index % count($users)]['id'];
                $status = $isCompleted ? 'completed' : 'confirmed';
                $insertBooking->execute([$userId, $courtId, $date, $price, $status, $demoMarker]);
                $bookingId = (int)$pdo->lastInsertId();
                $insertDetail->execute([$bookingId, (int)$slot['id'], $price, $courtId, $courtNumber, $date]);
                $booking = ['id' => $bookingId, 'user_id' => $userId, 'court_id' => $courtId, 'booking_date' => $date, 'status' => $status];
                $placed = true;
                break;
            }
            if (!$placed) continue;
        }
        $demoBookings[] = $booking;
    }

    $insertReview = $pdo->prepare('INSERT INTO reviews (user_id, court_id, booking_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $hasReview = $pdo->prepare('SELECT id FROM reviews WHERE booking_id = ? LIMIT 1');
    $reviewNumber = 0;
    foreach ($demoBookings as $booking) {
        if ($reviewNumber >= count($ratings)) break;
        if ($booking['status'] !== 'completed') continue;
        $hasReview->execute([(int)$booking['id']]);
        if ($hasReview->fetchColumn()) {
            $reviewNumber++;
            continue;
        }
        $reviewDate = (new DateTimeImmutable((string)$booking['booking_date'], new DateTimeZone('Asia/Ho_Chi_Minh')))
            ->modify('+1 day')->setTime(12, 0)->format('Y-m-d H:i:s');
        $insertReview->execute([
            (int)$booking['user_id'],
            (int)$booking['court_id'],
            (int)$booking['id'],
            $ratings[$reviewNumber],
            $reviewComments[$reviewNumber % count($reviewComments)],
            $reviewDate,
        ]);
        $reviewNumber++;
    }

    $pdo->commit();
    fwrite(STDOUT, "Demo seed completed. Demo users: " . count($users) . '; demo bookings: ' . count($demoBookings) . '; demo reviews: ' . $reviewNumber . ".\n");
    fwrite(STDOUT, "Active booking slots: " . count($slots) . " (06:00-22:00).\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Demo seed failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
