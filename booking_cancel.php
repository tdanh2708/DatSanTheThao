<?php
require __DIR__ . '/config/database.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Phương thức không hợp lệ.'); }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    flash('Mã đơn đặt sân không hợp lệ.', 'danger');
    redirect('/user/bookings.php');
}
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare("SELECT b.status,b.booking_date,p.status payment_status
        FROM bookings b LEFT JOIN payments p ON p.booking_id=b.id
        WHERE b.id=? AND b.user_id=? FOR UPDATE");
    $statement->execute([$id, current_user()['id']]);
    $booking = $statement->fetch();
    $canCancel = $booking
        && in_array($booking['status'], ['pending','confirmed'], true)
        && $booking['booking_date'] > date('Y-m-d')
        && $booking['payment_status'] !== 'paid';
    if ($canCancel) {
        $update = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND user_id=? AND status IN ('pending','confirmed') AND booking_date>CURDATE()");
        $update->execute([$id, current_user()['id']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Booking changed before cancellation.');
        $release = $pdo->prepare('UPDATE booking_details SET slot_active=NULL WHERE booking_id=?');
        $release->execute([$id]);
        $payment = $pdo->prepare("UPDATE payments SET status='cancelled' WHERE booking_id=? AND status='pending'");
        $payment->execute([$id]);
        notify_user($pdo, 'admin', null, 'booking_cancelled', 'Khách đã hủy đơn đặt sân #' . $id, 'admin/bookings.php');
        $pdo->commit();
        flash('Đã hủy đơn đặt sân. Khung giờ đã được trả lại.');
    } else {
        $pdo->rollBack();
        flash('Không thể hủy đơn này. Đơn đã thanh toán không thể hủy tại đây; vui lòng liên hệ Admin nếu cần hỗ trợ.', 'warning');
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('Chưa thể hủy đơn. Vui lòng thử lại.', 'danger');
}
redirect('/user/bookings.php');
