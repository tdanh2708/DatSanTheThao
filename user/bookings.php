<?php
require __DIR__ . '/../config/database.php';
require_login();
expire_pending_bank_transfers($pdo);
$filter = (string)($_GET['filter'] ?? 'all');
$conditions = ['b.user_id=?'];
$params = [(int)current_user()['id']];
if ($filter === 'upcoming') { $conditions[] = "b.booking_date>=CURDATE() AND b.status IN ('pending','confirmed')"; }
elseif ($filter === 'completed') { $conditions[] = "b.status='completed'"; }
elseif ($filter === 'cancelled') { $conditions[] = "b.status='cancelled'"; }
elseif ($filter === 'pending_payment') { $conditions[] = "p.status='pending'"; }
elseif ($filter === 'paid') { $conditions[] = "p.status='paid'"; }
$statement = $pdo->prepare("SELECT b.*,c.name court_name,s.name sport_name,p.payment_method,p.status payment_status,p.payment_submitted_at,
    c.image court_image,
    (SELECT GROUP_CONCAT(CONCAT('Sân ',d.court_number,' ',TIME_FORMAT(ts.start_time,'%H:%i'),'-',TIME_FORMAT(ts.end_time,'%H:%i')) ORDER BY ts.start_time SEPARATOR ', ')
     FROM booking_details d JOIN time_slots ts ON ts.id=d.time_slot_id WHERE d.booking_id=b.id) slots,
    EXISTS(SELECT 1 FROM reviews r WHERE r.booking_id=b.id) reviewed
    FROM bookings b JOIN courts c ON c.id=b.court_id JOIN sports s ON s.id=c.sport_id
    LEFT JOIN payments p ON p.booking_id=b.id WHERE " . implode(' AND ', $conditions) . " ORDER BY b.created_at DESC");
$statement->execute($params);
$rows = $statement->fetchAll();
$pageTitle = 'Đơn đặt của tôi';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading-row"><div><span class="eyebrow">TÀI KHOẢN CỦA BẠN</span><h1>Đơn đặt của tôi</h1></div></div>
<nav class="booking-filters" aria-label="Lọc lịch sử đặt sân"><?php foreach(['all'=>'Tất cả','upcoming'=>'Sắp tới','completed'=>'Hoàn thành','cancelled'=>'Đã hủy','pending_payment'=>'Chờ thanh toán','paid'=>'Đã thanh toán'] as $key=>$label): ?><a class="<?= $filter===$key?'active':'' ?>" href="<?= e(app_url('user/bookings.php?filter='.$key)) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
<?php if (!$rows): ?><div class="alert alert-light">Bạn chưa có đơn đặt sân nào.</div><?php else: ?>
<div class="table-responsive"><table class="table table-hover align-middle bg-white">
    <thead><tr><th>Mã đơn</th><th>Sân / môn / ngày</th><th>Khung giờ</th><th>Tổng tiền</th><th>Đơn</th><th>Thanh toán</th><th>Thao tác</th></tr></thead>
    <tbody><?php foreach ($rows as $booking): $paymentLabel = ($booking['payment_status'] ?? '') === 'paid' ? 'Đã thanh toán' : (($booking['payment_method'] ?? '') === 'venue' && ($booking['payment_status'] ?? '') === 'pending' ? 'Chờ thanh toán tại sân' : (($booking['payment_method'] ?? '') === 'bank_transfer' && ($booking['payment_status'] ?? '') === 'pending' && !empty($booking['payment_submitted_at']) ? 'Chờ Admin xác nhận thanh toán' : ($booking['payment_status'] !== null ? payment_status_label((string)$booking['payment_status']) : 'Chưa ghi nhận (đơn cũ)'))); ?><tr>
        <td><?= e(booking_code((int)$booking['id'])) ?></td>
        <td><img class="booking-history-thumb" src="<?= e(court_image_url($booking['court_image'], (string)$booking['sport_name'])) ?>" alt=""><?= e($booking['court_name']) ?><br><small><?= e($booking['sport_name']) ?> · <?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?></small></td>
        <td><?= e($booking['slots'] ?? '—') ?></td>
        <td><?= money($booking['total_amount']) ?></td>
        <td><?= e(booking_status_label((string)$booking['status'])) ?></td>
        <td><?= e($paymentLabel) ?><br><small><?= ($booking['payment_method'] ?? '') === 'bank_transfer' ? 'Chuyển khoản ngân hàng' : (($booking['payment_method'] ?? '') === 'venue' ? 'Thanh toán trực tiếp tại sân' : '—') ?></small></td>
        <td><div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-success" href="<?= e(app_url('user/booking_detail.php?id=' . (int)$booking['id'])) ?>">Chi tiết</a>
            <?php if (in_array($booking['status'], ['pending','confirmed'], true) && $booking['booking_date'] > date('Y-m-d')): ?><form method="post" action="<?= e(app_url('booking_cancel.php')) ?>" data-confirm="Hủy đơn đặt sân này?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$booking['id'] ?>"><button class="btn btn-sm btn-outline-danger">Hủy</button></form><?php endif; ?>
            <?php if ($booking['status'] === 'completed' && !$booking['reviewed']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('user/reviews.php?booking_id=' . (int)$booking['id'])) ?>">Đánh giá</a><?php endif; ?>
        </div></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
