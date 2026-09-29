<?php
require __DIR__ . '/config/database.php';
require_login();
expire_pending_bank_transfers($pdo);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$statement = $pdo->prepare("SELECT b.*,c.name court_name,c.address,c.image court_image,s.name sport_name,
    p.payment_method,p.status payment_status,p.amount payment_amount,p.paid_at,p.payment_submitted_at
    FROM bookings b JOIN courts c ON c.id=b.court_id JOIN sports s ON s.id=c.sport_id
    LEFT JOIN payments p ON p.booking_id=b.id WHERE b.id=? AND b.user_id=?");
$statement->execute([$id, (int)current_user()['id']]);
$booking = $statement->fetch();
if (!$booking) { http_response_code(404); exit('Không tìm thấy đơn đặt sân.'); }
$statement = $pdo->prepare('SELECT ts.start_time,ts.end_time,d.court_number FROM booking_details d JOIN time_slots ts ON ts.id=d.time_slot_id WHERE d.booking_id=? ORDER BY ts.start_time');
$statement->execute([$id]);
$slots = $statement->fetchAll();
$pageTitle = 'Thanh toán đơn ' . booking_code((int)$booking['id']);
require __DIR__ . '/includes/header.php';
$isTransfer = ($booking['payment_method'] ?? '') === 'bank_transfer';
$isPaid = ($booking['payment_status'] ?? '') === 'paid';
$isAwaitingPayment = ($booking['payment_status'] ?? '') === 'pending';
$transferText = booking_code((int)$booking['id']);
$paymentConfig = $isTransfer && $isAwaitingPayment ? require __DIR__ . '/config/payment.php' : [];
$amountFromBooking = (int)round((float)$booking['total_amount']);
$qrUrl = $paymentConfig ? vietqr_image_url($paymentConfig, $amountFromBooking, $transferText) : '';
?>
<section class="booking-result card card-body mx-auto payment-result" style="max-width:760px">
    <a class="detail-back-button" href="<?= e(app_url('court_detail.php?id='.(int)$booking['court_id'])) ?>">← Quay lại</a>
    <span class="eyebrow">ĐẶT SÂN THÀNH CÔNG</span>
    <h1 class="h3"><?= $isPaid ? 'Thanh toán thành công' : ($isTransfer ? 'Hoàn tất chuyển khoản' : 'Thanh toán tại sân') ?></h1>
    <img class="booking-detail-image" src="<?= e(court_image_url($booking['court_image'], (string)$booking['sport_name'])) ?>" alt="<?= e($booking['court_name']) ?>">
    <div class="payment-summary-grid">
        <div><small>Mã đơn</small><strong><?= e(booking_code((int)$booking['id'])) ?></strong></div>
        <div><small>Sân</small><strong><?= e($booking['court_name']) ?></strong></div>
        <div><small>Môn thể thao</small><strong><?= e($booking['sport_name']) ?></strong></div>
        <div><small>Ngày chơi</small><strong><?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?></strong></div>
        <div><small>Khung giờ</small><strong><?php foreach ($slots as $index=>$slot): ?><?= $index?', ':'' ?><?= e(substr($slot['start_time'],0,5).'–'.substr($slot['end_time'],0,5)) ?><?php endforeach; ?></strong></div>
        <div><small>Trạng thái đơn</small><strong><?= e(booking_status_label((string)$booking['status'])) ?></strong></div>
    </div>
    <div class="payment-total"><span>Tổng tiền</span><strong><?= money($booking['total_amount']) ?></strong></div>
    <?php if ($isTransfer): ?>
        <?php if ($isPaid): ?><div class="alert alert-success mt-3"><strong>✓ Thanh toán thành công.</strong> Đơn <?= e(booking_code((int)$booking['id'])) ?> đã được quản trị viên xác nhận.</div>
        <?php elseif ($isAwaitingPayment): ?><section class="bank-payment-box"><h2 class="h5">Chuyển khoản ngân hàng</h2><p class="mb-2">Trạng thái: <strong>Đang chờ xác nhận thanh toán</strong></p><button type="button" class="btn btn-sm btn-success" data-open-payment>Hiển thị thông tin chuyển khoản</button></section>
        <?php else: ?><div class="alert alert-warning mt-3">Thanh toán đang ở trạng thái <?= e(payment_status_label((string)$booking['payment_status'])) ?>. Vui lòng xem lịch sử đặt sân hoặc liên hệ hỗ trợ.</div><?php endif; ?>
    <?php else: ?>
        <div class="venue-payment-note"><strong><?= $isPaid?'Đã thanh toán tại sân':'Thanh toán trực tiếp tại sân' ?></strong><p class="mb-0"><?= $isPaid?'Khoản thanh toán đã được nhân viên xác nhận.':'Bạn sẽ thanh toán khi đến sân. Trạng thái cập nhật sau khi nhân viên xác nhận đã thu tiền.' ?></p></div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mt-3"><a class="btn <?= $isTransfer ? 'btn-sm ' : '' ?>btn-success" href="<?= e(app_url('user/bookings.php')) ?>">Lịch sử đặt sân</a><a class="btn <?= $isTransfer ? 'btn-sm ' : '' ?>btn-outline-success" href="<?= e(app_url('user/booking_detail.php?id='.(int)$booking['id'])) ?>">Chi tiết đơn</a></div>
</section>
<?php if ($isTransfer && $isAwaitingPayment): $autoOpenPayment = true; require __DIR__ . '/includes/bank_payment_modal.php'; endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
