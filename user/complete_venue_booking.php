<?php
require __DIR__ . '/../config/database.php';
require_login();

if ((current_user()['role'] ?? '') !== 'user') {
    http_response_code(403);
    exit('Chỉ chủ đơn mới có thể hoàn tất đặt sân.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}
verify_csrf();
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT) ?: 0;
if ($bookingId <= 0) {
    flash('Mã đơn không hợp lệ.', 'danger');
    redirect('/user/bookings.php');
}

try {
    $pdo->beginTransaction();
    $query = $pdo->prepare("SELECT p.id payment_id,p.payment_method,p.status payment_status,
            b.status booking_status
        FROM payments p JOIN bookings b ON b.id=p.booking_id
        WHERE b.id=? AND b.user_id=? FOR UPDATE");
    $query->execute([$bookingId, (int)current_user()['id']]);
    $payment = $query->fetch();
    if (!$payment) throw new RuntimeException('Không tìm thấy đơn đặt sân của bạn.');
    if ($payment['payment_method'] !== 'venue') throw new RuntimeException('Đơn này không sử dụng thanh toán tại sân.');
    if ($payment['payment_status'] !== 'pending') throw new RuntimeException('Trạng thái thanh toán không còn phù hợp để hoàn tất đặt đơn.');
    if ($payment['booking_status'] === 'pending') {
        $confirmBooking = $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=? AND user_id=? AND status='pending'");
        $confirmBooking->execute([$bookingId, (int)current_user()['id']]);
        if ($confirmBooking->rowCount() !== 1) throw new RuntimeException('Đơn vừa thay đổi. Vui lòng tải lại trang.');
    } elseif ($payment['booking_status'] !== 'confirmed') {
        throw new RuntimeException('Đơn không còn ở trạng thái có thể hoàn tất.');
    }

    $pdo->commit();
    flash('Đơn đã được xác nhận. Bạn sẽ thanh toán tại sân; chưa có khoản tiền nào được ghi nhận.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($exception instanceof RuntimeException ? $exception->getMessage() : 'Chưa thể hoàn tất đặt đơn. Vui lòng thử lại.', 'danger');
}
redirect('/user/bookings.php');
