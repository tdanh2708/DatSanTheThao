<?php
require __DIR__ . '/../config/database.php';
require_login();
if ((current_user()['role'] ?? '') !== 'user') { http_response_code(403); exit('Chỉ người dùng mới có thể đổi phương thức thanh toán.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Phương thức không hợp lệ.'); }
verify_csrf();
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT) ?: 0;
$method = (string)($_POST['payment_method'] ?? '');
if (!$bookingId || !in_array($method, ['bank_transfer','venue'], true)) {
    flash('Phương thức thanh toán không hợp lệ.', 'danger');
    redirect('/user/bookings.php');
}
try {
    $pdo->beginTransaction();
    $query = $pdo->prepare("SELECT p.id,p.payment_method,p.status,p.payment_submitted_at FROM payments p
        JOIN bookings b ON b.id=p.booking_id
        WHERE p.booking_id=? AND b.user_id=? AND b.status IN ('pending','confirmed') FOR UPDATE");
    $query->execute([$bookingId, (int)current_user()['id']]);
    $payment = $query->fetch();
    if (!$payment) throw new RuntimeException('Không tìm thấy thanh toán của đơn này.');
    if ($payment['status'] !== 'pending') throw new RuntimeException('Chỉ có thể đổi phương thức khi thanh toán còn chờ xác nhận.');
    if ($payment['payment_submitted_at'] !== null) throw new RuntimeException('Không thể đổi phương thức sau khi đã báo chuyển khoản.');
    if ($payment['payment_method'] !== $method) {
        $update = $pdo->prepare("UPDATE payments SET payment_method=? WHERE id=? AND status='pending'");
        $update->execute([$method, (int)$payment['id']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Không thể cập nhật phương thức thanh toán.');
    }
    $pdo->commit();
    flash($method === 'bank_transfer' ? 'Đã đổi sang chuyển khoản ngân hàng.' : 'Đã đổi sang thanh toán tại sân.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($exception instanceof RuntimeException ? $exception->getMessage() : 'Không thể đổi phương thức thanh toán. Vui lòng thử lại.', 'danger');
}
redirect('/user/booking_detail.php?id=' . $bookingId);
