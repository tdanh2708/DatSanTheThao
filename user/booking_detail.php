<?php
require __DIR__ . '/../config/database.php';
require_login();
expire_pending_bank_transfers($pdo);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$statement = $pdo->prepare("SELECT b.*,c.name court_name,c.address,c.image court_image,s.name sport_name,p.payment_method,p.status payment_status,p.transaction_code,p.payment_submitted_at
    FROM bookings b JOIN courts c ON c.id=b.court_id JOIN sports s ON s.id=c.sport_id
    LEFT JOIN payments p ON p.booking_id=b.id WHERE b.id=? AND b.user_id=?");
$statement->execute([$id, (int)current_user()['id']]);
$booking = $statement->fetch();
if (!$booking) { http_response_code(404); exit('Không tìm thấy đơn đặt sân.'); }
$statement = $pdo->prepare('SELECT ts.start_time,ts.end_time,d.court_number,d.price FROM booking_details d JOIN time_slots ts ON ts.id=d.time_slot_id WHERE d.booking_id=? ORDER BY ts.start_time');
$statement->execute([$id]);
$slots = $statement->fetchAll();
$pageTitle = 'Chi tiết ' . booking_code((int)$booking['id']);
require __DIR__ . '/../includes/header.php';
?>
<?php
$isPaymentPending = ($booking['payment_status'] ?? '') === 'pending' && in_array($booking['status'], ['pending','confirmed'], true);
$paymentMethodLabel = ($booking['payment_method'] ?? '') === 'bank_transfer' ? 'Chuyển khoản ngân hàng' : (($booking['payment_method'] ?? '') === 'venue' ? 'Thanh toán tại sân' : 'Chưa ghi nhận');
$paymentState = (string)($booking['payment_status'] ?? '');
$paymentStateLabel = $paymentState === 'paid' ? 'Đã thanh toán' : (($booking['payment_method'] ?? '') === 'venue' && $paymentState === 'pending' ? 'Chờ thanh toán tại sân' : (($booking['payment_method'] ?? '') === 'bank_transfer' && $paymentState === 'pending' && !empty($booking['payment_submitted_at']) ? 'Chờ Admin xác nhận thanh toán' : ($paymentState !== '' ? payment_status_label($paymentState) : 'Chưa ghi nhận')));
$paymentBadgeClass = match ($paymentState) {
    'paid' => 'payment-state-paid',
    'pending' => 'payment-state-pending',
    'cancelled', 'failed' => 'payment-state-cancelled',
    default => 'payment-state-unknown',
};
$showBankPayment = ($booking['payment_method'] ?? '') === 'bank_transfer' && $isPaymentPending;
$canChangePayment = $isPaymentPending && empty($booking['payment_submitted_at']);
$paymentConfig = $showBankPayment ? require __DIR__ . '/../config/payment.php' : [];
$amountFromBooking = (int)round((float)$booking['total_amount']);
$transferText = booking_code((int)$booking['id']);
$qrUrl = $paymentConfig ? vietqr_image_url($paymentConfig, $amountFromBooking, $transferText) : '';
?>
<section class="booking-detail-card card">
    <div class="booking-detail-heading"><a class="detail-back-button" href="<?= e(app_url('user/bookings.php')) ?>">← Quay lại</a><div class="booking-detail-title-row"><div><span class="eyebrow">CHI TIẾT ĐƠN ĐẶT</span><h1><?= e(booking_code((int)$booking['id'])) ?></h1></div><span class="booking-status-badge status-<?= e($booking['status']) ?>"><?= e(booking_status_label((string)$booking['status'])) ?></span></div></div>
    <div class="booking-place-card"><img class="booking-detail-image" src="<?= e(court_image_url($booking['court_image'], (string)$booking['sport_name'])) ?>" alt="<?= e($booking['court_name']) ?>"><div class="booking-place-info"><span class="eyebrow"><?= e($booking['sport_name']) ?></span><h2><?= e($booking['court_name']) ?></h2><p class="booking-place-address">⌖ <?= e($booking['address']) ?></p><div class="booking-place-facts"><div><small>Ngày chơi</small><strong><?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?></strong></div><div><small>Đặt lúc</small><strong><?= e(date('d/m/Y H:i', strtotime($booking['created_at']))) ?></strong></div><div><small>Trạng thái thanh toán</small><strong><span class="payment-state-badge <?= e($paymentBadgeClass) ?>"><?= e($paymentStateLabel) ?></span></strong></div></div></div></div>
    <div class="booking-detail-section"><h2>Thời gian đã đặt</h2><div class="booking-time-list"><?php foreach ($slots as $slot): ?><div class="booking-time-row"><strong>Sân <?= (int)$slot['court_number'] ?></strong><span><?= e(substr($slot['start_time'],0,5)) ?> – <?= e(substr($slot['end_time'],0,5)) ?></span><b><?= money($slot['price']) ?></b></div><?php endforeach; ?></div></div>
    <div class="booking-detail-section payment-breakdown"><h2>Chi tiết thanh toán</h2><div class="payment-line"><span>Phương thức</span><strong><?= e($paymentMethodLabel) ?></strong></div><div class="payment-line"><span>Trạng thái</span><strong><span class="payment-state-badge <?= e($paymentBadgeClass) ?>"><?= e($paymentStateLabel) ?></span></strong></div><div class="payment-line payment-grand-total"><span>Tổng cộng</span><strong><?= money($booking['total_amount']) ?></strong></div>
        <div class="booking-detail-actions">
            <?php if ($showBankPayment && empty($booking['payment_submitted_at'])): ?><button class="btn btn-success" type="button" data-open-payment>Tiếp tục thanh toán</button><?php elseif ($showBankPayment): ?><span class="payment-submitted-success align-self-center">✓ Đã xác nhận chuyển khoản · Chờ Admin</span><button class="btn btn-outline-success" type="button" data-open-payment>Hiển thị thông tin chuyển khoản</button><?php endif; ?>
            <?php if (($booking['payment_method'] ?? '') === 'venue' && $paymentState === 'pending' && $booking['status'] === 'pending'): ?><form method="post" action="<?= e(app_url('user/complete_venue_booking.php')) ?>" data-disable-on-submit><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>"><button class="btn btn-success" type="submit">Hoàn tất đặt đơn</button></form><?php endif; ?>
            <?php if ($canChangePayment && in_array($booking['payment_method'], ['venue','bank_transfer'], true)): ?><button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#changePaymentModal">Đổi phương thức thanh toán</button><?php endif; ?>
            <button class="btn btn-outline-secondary" type="button" data-chat-prefill="<?= e('Tôi cần hỗ trợ về đơn '.booking_code((int)$booking['id']).'.') ?>">Liên hệ hỗ trợ</button>
            <?php if (in_array($booking['status'], ['pending','confirmed'], true) && $booking['booking_date'] > date('Y-m-d')): ?><form method="post" action="<?= e(app_url('booking_cancel.php')) ?>" data-confirm="Bạn chắc chắn muốn hủy đơn này?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$booking['id'] ?>"><button class="btn btn-outline-danger">Hủy đặt sân</button></form><?php endif; ?>
        </div>
    </div>
</section>
<?php if ($canChangePayment): ?><div class="modal fade" id="changePaymentModal" tabindex="-1" aria-labelledby="changePaymentTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="<?= e(app_url('user/payment_method.php')) ?>"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>"><div class="modal-header"><h2 class="modal-title h5" id="changePaymentTitle">Đổi phương thức thanh toán</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><p>Đơn <?= e(booking_code((int)$booking['id'])) ?> · Tổng tiền <strong><?= money($booking['total_amount']) ?></strong></p><label class="payment-method-option"><input type="radio" name="payment_method" value="bank_transfer" <?= $booking['payment_method']==='bank_transfer'?'checked':'' ?>><span><strong>Chuyển khoản ngân hàng</strong><small>QR động theo số tiền và mã đơn; Admin xác nhận thủ công.</small></span></label><label class="payment-method-option mt-2"><input type="radio" name="payment_method" value="venue" <?= $booking['payment_method']==='venue'?'checked':'' ?>><span><strong>Thanh toán tại sân</strong><small>Thanh toán khi đến sử dụng sân.</small></span></label></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-success">Xác nhận thay đổi</button></div></form></div></div></div><?php endif; ?>
<?php if ($showBankPayment) { $autoOpenPayment = false; require __DIR__ . '/../includes/bank_payment_modal.php'; } ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
