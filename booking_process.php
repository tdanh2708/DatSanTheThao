<?php
require __DIR__ . '/config/database.php';
require_login();
expire_pending_bank_transfers($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}
verify_csrf();

$courtId = filter_input(INPUT_POST, 'court_id', FILTER_VALIDATE_INT);
$date = (string)($_POST['booking_date'] ?? '');
$slots = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['slots'] ?? [])))));
$note = trim((string)($_POST['note'] ?? ''));
$paymentMethod = (string)($_POST['payment_method'] ?? '');
$validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    && checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4));

if (!$courtId || !$validDate || $date < date('Y-m-d') || !$slots || strlen($note) > 500
    || !in_array($paymentMethod, ['venue', 'bank_transfer'], true)) {
    flash('Thông tin đặt sân chưa hợp lệ.', 'danger');
    redirect('/courts.php');
}

try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare("SELECT id, price_per_hour, court_count FROM courts WHERE id=? AND status='active' FOR UPDATE");
    $statement->execute([$courtId]);
    $court = $statement->fetch();
    if (!$court || (float)$court['price_per_hour'] <= 0) {
        throw new RuntimeException('Sân hiện chưa có giá đặt hợp lệ hoặc không còn hoạt động.');
    }

    $marks = implode(',', array_fill(0, count($slots), '?'));
    $statement = $pdo->prepare("SELECT id,start_time,end_time FROM time_slots WHERE status='active'
        AND start_time>='06:00:00' AND end_time<='22:00:00' AND id IN ($marks) ORDER BY start_time FOR UPDATE");
    $statement->execute($slots);
    $foundSlots = $statement->fetchAll();
    if (count($foundSlots) !== count($slots)) throw new RuntimeException('Một số khung giờ không hợp lệ.');

    $hours = 0.0;
    $previousEnd = null;
    foreach ($foundSlots as $slot) {
        if ($date === date('Y-m-d') && $slot['start_time'] <= date('H:i:s')) {
            throw new RuntimeException('Không thể đặt khung giờ đã qua.');
        }
        if ($previousEnd !== null && $slot['start_time'] < $previousEnd) {
            throw new RuntimeException('Các khung giờ bạn chọn bị chồng lấn.');
        }
        $previousEnd = $slot['end_time'];
        $hours += (strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 3600;
    }
    if ($hours <= 0) throw new RuntimeException('Thời lượng đặt sân không hợp lệ.');
    $total = (float)$court['price_per_hour'] * $hours;

    $statement = $pdo->prepare("INSERT INTO bookings(user_id,court_id,booking_date,total_amount,status,note)
        VALUES(?,?,?,?,'pending',?)");
    $statement->execute([(int)current_user()['id'], $courtId, $date, $total, $note ?: null]);
    $bookingId = (int)$pdo->lastInsertId();

    $capacity = max(1, (int)($court['court_count'] ?? 1));
    $occupiedQuery = $pdo->prepare("SELECT DISTINCT d.court_number FROM booking_details d
        JOIN bookings b ON b.id=d.booking_id JOIN time_slots occupied_slot ON occupied_slot.id=d.time_slot_id
        WHERE d.court_id=? AND d.booking_date=? AND d.slot_active=1
          AND b.status IN ('pending','confirmed','completed')
          AND occupied_slot.start_time<? AND occupied_slot.end_time>? ORDER BY d.court_number");
    $insertDetail = $pdo->prepare('INSERT INTO booking_details(booking_id,time_slot_id,price,court_id,court_number,booking_date) VALUES(?,?,?,?,?,?)');
    foreach ($foundSlots as $slot) {
        $occupiedQuery->execute([$courtId, $date, $slot['end_time'], $slot['start_time']]);
        $occupiedNumbers = array_map('intval', $occupiedQuery->fetchAll(PDO::FETCH_COLUMN));
        $courtNumber = null;
        for ($number = 1; $number <= $capacity; $number++) {
            if (!in_array($number, $occupiedNumbers, true)) { $courtNumber = $number; break; }
        }
        if ($courtNumber === null) throw new RuntimeException('Khung giờ vừa kín chỗ. Vui lòng chọn lại.');
        $slotHours = (strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 3600;
        $insertDetail->execute([$bookingId, (int)$slot['id'], (float)$court['price_per_hour'] * $slotHours, $courtId, $courtNumber, $date]);
    }

    $payment = $pdo->prepare("INSERT INTO payments(booking_id,payment_method,amount,status) VALUES(?,?,?,'pending')");
    $payment->execute([$bookingId, $paymentMethod, $total]);
    $notify = $pdo->prepare("INSERT INTO notifications(recipient_role,notification_type,message,target_url) VALUES('admin','booking_new',?,?)");
    $notify->execute(['Có đơn đặt sân mới #' . $bookingId, 'admin/bookings.php']);
    $notify->execute(['Có khoản thanh toán chờ xử lý cho đơn #' . $bookingId, 'admin/payments.php']);
    $pdo->commit();
    redirect('/user/booking_detail.php?id=' . $bookingId);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($error instanceof PDOException ? 'Chưa thể lưu đơn đặt sân. Vui lòng kiểm tra khung giờ và thử lại.' : $error->getMessage(), 'danger');
    redirect('/court_detail.php?id=' . $courtId . '&date=' . urlencode($date));
}
