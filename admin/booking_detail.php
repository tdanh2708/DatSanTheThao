<?php
require_once __DIR__ . '/../config/database.php';
require_admin();
expire_pending_bank_transfers($pdo);
complete_elapsed_confirmed_bookings($pdo);
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare("SELECT b.id,b.user_id,b.status,b.booking_date,
                (SELECT MIN(t.start_time) FROM booking_details d JOIN time_slots t ON t.id=d.time_slot_id WHERE d.booking_id=b.id) first_slot_start,
                p.status payment_status
            FROM bookings b LEFT JOIN payments p ON p.booking_id=b.id
            WHERE b.id=? FOR UPDATE");
        $statement->execute([$postId]);
        $locked = $statement->fetch();
        $firstStart = $locked && $locked['first_slot_start'] ? strtotime($locked['booking_date'] . ' ' . $locked['first_slot_start']) : false;
        if (!$locked || !in_array($locked['status'], ['pending','confirmed'], true)) throw new RuntimeException('Đơn không còn ở trạng thái có thể hủy.');
        if (!$firstStart || $firstStart <= time()) throw new RuntimeException('Không thể hủy đơn sau khi khung giờ chơi đầu tiên đã bắt đầu.');

        $update = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND status IN ('pending','confirmed')");
        $update->execute([$postId]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Đơn vừa thay đổi. Vui lòng tải lại trang.');
        $pdo->prepare('UPDATE booking_details SET slot_active=NULL WHERE booking_id=?')->execute([$postId]);
        if ($locked['payment_status'] === 'pending') $pdo->prepare("UPDATE payments SET status='cancelled' WHERE booking_id=? AND status='pending'")->execute([$postId]);
        admin_log($pdo, 'booking_cancel', 'booking', $postId, 'Admin hủy đơn ' . booking_code($postId));
        notify_user($pdo, 'user', (int)$locked['user_id'], 'booking_status', 'Đơn ' . booking_code($postId) . ' đã được Admin hủy.', 'user/booking_detail.php?id=' . $postId);
        $pdo->commit();
        flash('Đã hủy đơn ' . booking_code($postId) . ' và giải phóng khung giờ.');
        redirect('/admin/booking_detail.php?id=' . $postId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Chưa thể hủy đơn. Vui lòng thử lại.';
        $id = $postId;
    }
}

$query = $pdo->prepare("SELECT b.*,u.id owner_id,u.full_name,u.email,u.phone,c.name court_name,c.address,s.name sport_name,
        p.id payment_id,p.payment_method,p.status payment_status,p.payment_submitted_at,p.paid_at
    FROM bookings b JOIN users u ON u.id=b.user_id JOIN courts c ON c.id=b.court_id
    JOIN sports s ON s.id=c.sport_id LEFT JOIN payments p ON p.booking_id=b.id WHERE b.id=?");
$query->execute([$id]);
$booking = $query->fetch();
if (!$booking) { http_response_code(404); exit('Không tìm thấy đơn đặt sân.'); }
$query = $pdo->prepare('SELECT t.start_time,t.end_time,d.court_number,d.price,d.booking_date FROM booking_details d JOIN time_slots t ON t.id=d.time_slot_id WHERE d.booking_id=? ORDER BY t.start_time');
$query->execute([$id]);
$slots = $query->fetchAll();
$query = $pdo->prepare('SELECT id FROM conversations WHERE user_id=?');
$query->execute([(int)$booking['owner_id']]);
$conversationId = $query->fetchColumn();
$paymentMethodLabel = match ($booking['payment_method'] ?? '') { 'bank_transfer' => 'Chuyển khoản ngân hàng', 'venue' => 'Thanh toán tại sân', default => 'Đơn cũ / chưa ghi nhận' };
$paymentStatus = (string)($booking['payment_status'] ?? '');
$paymentLabel = $paymentStatus === '' ? 'Chưa ghi nhận (đơn cũ)' : payment_status_label($paymentStatus);
if ($paymentStatus === 'pending' && $booking['payment_method'] === 'venue') $paymentLabel = 'Chờ thanh toán tại sân';
if ($paymentStatus === 'pending' && $booking['payment_method'] === 'bank_transfer') $paymentLabel = $booking['payment_submitted_at'] ? 'Chờ xác nhận thanh toán' : 'Chờ thanh toán';
$paymentBadge = match ($paymentStatus) { 'paid' => 'is-paid', 'cancelled' => 'is-cancelled', 'failed' => 'is-failed', default => 'is-pending' };
$bookingBadge = match ($booking['status']) { 'confirmed' => 'is-confirmed', 'completed' => 'is-paid', 'cancelled' => 'is-cancelled', default => 'is-pending' };
$firstStart = $slots ? strtotime($booking['booking_date'] . ' ' . $slots[0]['start_time']) : false;
$canCancel = in_array($booking['status'], ['pending','confirmed'], true) && $firstStart && $firstStart > time();
$cancelMessage = $paymentStatus === 'paid'
    ? 'Đơn này đã được ghi nhận thanh toán. Việc hủy không tự động hoàn tiền. Bạn có chắc muốn tiếp tục?'
    : 'Bạn có chắc muốn hủy đơn này? Khung giờ sẽ được giải phóng cho khách khác.';
$pageTitle = 'Chi tiết ' . booking_code((int)$booking['id']);
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<div class="admin-booking-detail-heading">
    <div><a class="detail-back-button" href="<?= e(app_url('admin/bookings.php')) ?>">← Quản lý đơn đặt</a><span class="eyebrow">CHI TIẾT ĐƠN ĐẶT</span><h1><?= e(booking_code((int)$booking['id'])) ?></h1></div>
    <div class="admin-booking-detail-actions">
        <?php if ($canCancel): ?><form method="post" action="<?= e(app_url('admin/booking_detail.php?id=' . (int)$booking['id'])) ?>" data-confirm data-confirm-title="Hủy đơn đặt sân" data-confirm-message="<?= e($cancelMessage) ?>" data-confirm-tone="danger" data-confirm-button="Hủy đơn" data-confirm-code="<?= e(booking_code((int)$booking['id'])) ?>" data-confirm-customer="<?= e($booking['full_name']) ?>" data-confirm-court="<?= e($booking['court_name']) ?>" data-confirm-amount="<?= e(money($booking['total_amount'])) ?>" data-confirm-method="<?= e($paymentMethodLabel) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$booking['id'] ?>"><button class="btn btn-outline-danger" type="submit">Hủy đơn</button>
        </form><?php endif; ?>
        <?php if ($booking['payment_id']): ?><a class="btn btn-outline-success" href="<?= e(app_url('admin/payments.php?booking=' . (int)$booking['id'])) ?>">Xem thanh toán</a><?php endif; ?>
    </div>
</div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="admin-booking-detail-grid">
    <section class="admin-booking-info-card">
        <h2>Khách hàng</h2>
        <dl><dt>Họ tên</dt><dd><a href="<?= e(app_url('admin/user_detail.php?id=' . (int)$booking['owner_id'])) ?>"><?= e($booking['full_name']) ?></a></dd>
            <dt>Email</dt><dd><?= e($booking['email']) ?></dd>
            <dt>Số điện thoại</dt><dd><?= e($booking['phone'] ?: 'Chưa cung cấp') ?></dd></dl>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('admin/messages.php?' . ($conversationId ? 'conversation_id=' . (int)$conversationId : 'user_id=' . (int)$booking['owner_id']))) ?>">Nhắn khách hàng</a>
    </section>
    <section class="admin-booking-info-card">
        <h2>Thông tin sân</h2>
        <dl><dt>Sân</dt><dd><?= e($booking['court_name']) ?></dd>
            <dt>Môn</dt><dd><?= e($booking['sport_name']) ?></dd>
            <dt>Địa chỉ</dt><dd><?= e($booking['address']) ?></dd>
            <dt>Ngày chơi</dt><dd><?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?></dd></dl>
    </section>
    <section class="admin-booking-info-card admin-booking-slot-card">
        <h2>Khung giờ đã đặt</h2>
        <?php if ($slots): ?><div class="admin-booking-slot-list"><?php foreach ($slots as $slot): ?><div><strong>Sân <?= (int)$slot['court_number'] ?></strong><span><?= e(substr($slot['start_time'], 0, 5)) ?>–<?= e(substr($slot['end_time'], 0, 5)) ?></span><b><?= money($slot['price']) ?></b></div><?php endforeach; ?></div><?php else: ?><p class="text-secondary mb-0">Không có thông tin khung giờ.</p><?php endif; ?>
    </section>
    <section class="admin-booking-info-card">
        <h2>Thanh toán</h2>
        <dl><dt>Tổng tiền</dt><dd class="admin-detail-total"><?= money($booking['total_amount']) ?></dd>
            <dt>Phương thức</dt><dd><?= e($paymentMethodLabel) ?></dd>
            <dt>Trạng thái thanh toán</dt><dd><span class="admin-status-badge <?= $paymentBadge ?>"><?= e($paymentLabel) ?></span></dd>
            <?php if ($booking['payment_submitted_at']): ?><dt>Khách báo đã chuyển</dt><dd><?= e(date('d/m/Y H:i', strtotime($booking['payment_submitted_at']))) ?></dd><?php endif; ?>
            <?php if ($booking['paid_at']): ?><dt>Thanh toán lúc</dt><dd><?= e(date('d/m/Y H:i', strtotime($booking['paid_at']))) ?></dd><?php endif; ?></dl>
    </section>
    <section class="admin-booking-info-card">
        <h2>Trạng thái đơn</h2>
        <p><span class="admin-status-badge <?= $bookingBadge ?>"><?= e(booking_status_label((string)$booking['status'])) ?></span></p>
        <dl><dt>Đặt lúc</dt><dd><?= e(date('d/m/Y H:i', strtotime($booking['created_at']))) ?></dd></dl>
        <?php if ($booking['status'] === 'cancelled'): ?><p class="small text-secondary mb-0">Cơ sở dữ liệu hiện không lưu thời điểm hoặc người hủy đơn.</p><?php endif; ?>
    </section>
</div>
</section></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
