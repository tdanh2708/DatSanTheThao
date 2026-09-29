<?php
require __DIR__ . '/../config/database.php';
require_login();

if ((current_user()['role'] ?? '') !== 'user') {
    http_response_code(403);
    exit('Chỉ chủ đơn mới có thể xác nhận đã chuyển khoản.');
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
    $query = $pdo->prepare("SELECT p.id payment_id,p.payment_method,p.status payment_status,p.payment_submitted_at,
            b.status booking_status,b.user_id
        FROM payments p JOIN bookings b ON b.id=p.booking_id
        WHERE b.id=? AND b.user_id=? FOR UPDATE");
    $query->execute([$bookingId, (int)current_user()['id']]);
    $payment = $query->fetch();
    if (!$payment) throw new RuntimeException('Không tìm thấy đơn đặt sân của bạn.');
    if ($payment['payment_method'] !== 'bank_transfer') throw new RuntimeException('Đơn này không sử dụng chuyển khoản ngân hàng.');
    if ($payment['payment_status'] !== 'pending') throw new RuntimeException('Khoản thanh toán không còn ở trạng thái chờ xác nhận.');
    if ($payment['booking_status'] === 'cancelled') throw new RuntimeException('Đơn đã bị hủy nên không thể báo chuyển khoản.');
    if (!in_array($payment['booking_status'], ['pending', 'confirmed'], true)) throw new RuntimeException('Trạng thái đơn không cho phép xác nhận chuyển khoản.');

    if ($payment['payment_submitted_at'] === null) {
        $update = $pdo->prepare("UPDATE payments SET payment_submitted_at=NOW()
            WHERE id=? AND payment_method='bank_transfer' AND status='pending' AND payment_submitted_at IS NULL");
        $update->execute([(int)$payment['payment_id']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Không thể ghi nhận xác nhận chuyển khoản. Vui lòng tải lại trang.');

        $confirmBooking = $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=? AND status='pending'");
        $confirmBooking->execute([$bookingId]);
        notify_user(
            $pdo,
            'admin',
            null,
            'transfer_submitted',
            'Khách đã báo chuyển khoản cho đơn ' . booking_code($bookingId) . '. Vui lòng đối chiếu giao dịch.',
            'admin/payments.php?status=pending&method=bank_transfer'
        );
    }

    $pdo->commit();
    flash('Đã ghi nhận bạn báo chuyển khoản. Thanh toán vẫn chờ Admin kiểm tra.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($exception instanceof RuntimeException ? $exception->getMessage() : 'Chưa thể xác nhận chuyển khoản. Vui lòng thử lại.', 'danger');
}
redirect('/user/bookings.php');
