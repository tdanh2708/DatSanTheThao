<?php if (($booking['payment_method'] ?? '') === 'bank_transfer' && ($booking['payment_status'] ?? '') === 'pending'): ?>
<div class="modal fade" id="bankPaymentModal" tabindex="-1" aria-labelledby="bankPaymentTitle" aria-hidden="true"<?= !empty($autoOpenPayment) ? ' data-auto-open-payment' : '' ?>>
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h5" id="bankPaymentTitle">Thanh toán chuyển khoản</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
        <div class="modal-body text-center">
            <p><?= e($booking['court_name']) ?> · <?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?> · <?php foreach ($slots as $index => $slot): ?><?= $index ? ', ' : '' ?><?= e(substr($slot['start_time'], 0, 5) . '–' . substr($slot['end_time'], 0, 5)) ?><?php endforeach; ?></p>
            <p>Đơn <strong><?= e(booking_code((int)$booking['id'])) ?></strong></p>
            <div class="payment-total"><span>Tổng tiền cần thanh toán</span><strong><?= money($booking['total_amount']) ?></strong></div>
            <div data-payment-status data-endpoint="<?= e(app_url('payment_status.php')) ?>" data-booking-id="<?= (int)$booking['id'] ?>" data-status="<?= e((string)$booking['payment_status']) ?>">
                <?php if ($qrUrl !== ''): ?><img class="bank-qr" src="<?= e($qrUrl) ?>" alt="VietQR chuyển <?= money($amountFromBooking) ?> đến tài khoản MB Bank" data-vietqr-image>
                <?php else: ?><div class="alert alert-warning">Không thể tạo mã QR vì thông tin thanh toán hoặc số tiền chưa hợp lệ.</div><?php endif; ?>
                <p class="text-secondary small mb-2">Quét QR bằng ứng dụng ngân hàng. Số tiền và nội dung đã được điền sẵn.</p>
                <div class="transfer-bank-info">
                    <div><small>Ngân hàng</small><strong><?= e($paymentConfig['bank_name']) ?></strong></div>
                    <div><small>Chủ tài khoản</small><strong><?= e($paymentConfig['account_name']) ?></strong></div>
                    <div><small>Số tài khoản</small><span class="transfer-copy-row"><strong data-account-number><?= e($paymentConfig['account_number']) ?></strong><button type="button" class="copy-payment-button" data-copy-payment="account-number" title="Sao chép số tài khoản" aria-label="Sao chép số tài khoản">Sao chép</button></span></div>
                    <div><small>Nội dung chuyển khoản</small><span class="transfer-copy-row"><strong data-transfer-text><?= e($transferText) ?></strong><button type="button" class="copy-payment-button" data-copy-payment="transfer-code" title="Sao chép nội dung chuyển khoản" aria-label="Sao chép nội dung chuyển khoản">Sao chép</button></span></div>
                </div>
                <div class="copy-feedback" data-copy-feedback role="status" aria-live="polite" hidden></div>
                <?php if (!empty($booking['payment_submitted_at'])): ?>
                    <p class="payment-submitted-success mb-1">✓ Đã gửi xác nhận chuyển khoản</p>
                    <p class="payment-waiting mb-0">⏳ Đang chờ Admin xác nhận thanh toán</p>
                <?php else: ?>
                    <p class="payment-waiting mb-0">⏳ Chỉ xác nhận sau khi bạn đã hoàn tất chuyển khoản.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
            <?php if (empty($booking['payment_submitted_at'])): ?>
                <form method="post" action="<?= e(app_url('user/confirm_transfer.php')) ?>" data-disable-on-submit>
                    <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                    <button type="submit" class="btn btn-success">✓ Xác nhận thanh toán</button>
                </form>
            <?php endif; ?>
        </div>
    </div></div>
</div>
<?php endif; ?>
