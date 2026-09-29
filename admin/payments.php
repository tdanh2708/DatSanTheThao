<?php
require __DIR__ . '/../config/database.php';
require_admin();
expire_pending_bank_transfers($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $paymentId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    try {
        $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT p.status,p.amount,p.payment_method,p.payment_submitted_at,p.booking_id,b.user_id,b.status booking_status FROM payments p JOIN bookings b ON b.id=p.booking_id WHERE p.id=? FOR UPDATE');
        $query->execute([$paymentId]);
        $payment = $query->fetch();
        if (!$payment || $payment['status'] !== 'pending') throw new RuntimeException('Khoản thanh toán không còn ở trạng thái chờ xác nhận.');
        if ($payment['booking_status'] === 'cancelled') throw new RuntimeException('Không thể xác nhận thanh toán cho đơn đã hủy.');
        if ($payment['payment_method'] === 'bank_transfer' && $payment['payment_submitted_at'] === null) throw new RuntimeException('Khách chưa báo đã chuyển khoản.');
        if ($payment['payment_method'] === 'venue' && !in_array($payment['booking_status'], ['confirmed','completed'], true)) throw new RuntimeException('Khách chưa hoàn tất đặt đơn thanh toán tại sân.');
        $update = $pdo->prepare("UPDATE payments SET status='paid',paid_at=NOW() WHERE id=? AND status='pending'");
        $update->execute([$paymentId]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Không thể cập nhật trạng thái thanh toán.');
        admin_log($pdo, 'payment_status', 'payment', $paymentId, 'Xác nhận đã nhận khoản thanh toán cho booking #' . (int)$payment['booking_id']);
        notify_user($pdo, 'user', (int)$payment['user_id'], 'payment_paid', 'Thanh toán đơn '.booking_code((int)$payment['booking_id']).' đã được xác nhận.', 'user/booking_detail.php?id='.(int)$payment['booking_id']);
        $pdo->commit();
        flash('Đã xác nhận thanh toán '.booking_code((int)$payment['booking_id']).'.');
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/admin/payments.php');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Không thể cập nhật thanh toán. Vui lòng thử lại.';
        if ($isAjax) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

$filterStatus = (string)($_GET['status'] ?? '');
$filterMethod = (string)($_GET['method'] ?? '');
$filterBooking = filter_input(INPUT_GET, 'booking', FILTER_VALIDATE_INT) ?: 0;
$validStatuses = ['pending','paid','failed','cancelled'];
$validMethods = ['bank_transfer','venue'];
$where = [];
$params = [];
if (in_array($filterStatus, $validStatuses, true)) { $where[]='p.status=?'; $params[]=$filterStatus; }
if (in_array($filterMethod, $validMethods, true)) { $where[]='p.payment_method=?'; $params[]=$filterMethod; }
if ($filterBooking > 0) { $where[]='p.booking_id=?'; $params[]=$filterBooking; }
$sql = "SELECT p.*,b.booking_date,b.status booking_status,u.full_name,c.name court_name,
    (SELECT GROUP_CONCAT(CONCAT(TIME_FORMAT(ts.start_time,'%H:%i'),'–',TIME_FORMAT(ts.end_time,'%H:%i')) ORDER BY ts.start_time SEPARATOR ', ')
     FROM booking_details d JOIN time_slots ts ON ts.id=d.time_slot_id WHERE d.booking_id=b.id) slots
    FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN users u ON u.id=b.user_id JOIN courts c ON c.id=b.court_id";
$statement = $pdo->prepare($sql . ($where ? ' WHERE '.implode(' AND ', $where) : '') . ' ORDER BY p.created_at DESC LIMIT 500');
$statement->execute($params);
$rows = $statement->fetchAll();
$pageTitle = 'Quản lý thanh toán';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<h1 class="mb-2">Quản lý thanh toán</h1>
<p class="text-secondary">Xác nhận thủ công sau khi đối chiếu giao dịch hoặc đã thu tiền tại sân.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="get" class="admin-search mb-3">
    <?php if ($filterBooking > 0): ?><input type="hidden" name="booking" value="<?= $filterBooking ?>"><?php endif; ?>
    <select class="form-select" name="method"><option value="">Tất cả phương thức</option><option value="bank_transfer" <?= $filterMethod==='bank_transfer'?'selected':'' ?>>Chuyển khoản</option><option value="venue" <?= $filterMethod==='venue'?'selected':'' ?>>Thanh toán tại sân</option></select>
    <select class="form-select" name="status"><option value="">Tất cả trạng thái</option><?php foreach ($validStatuses as $status): ?><option value="<?= e($status) ?>" <?= $filterStatus===$status?'selected':'' ?>><?= e(payment_status_label($status)) ?></option><?php endforeach; ?></select>
    <button class="btn btn-success">Lọc</button>
</form>
<div class="table-responsive admin-table-wrap admin-payments-table-wrap"><table class="table table-hover bg-white align-middle admin-payment-table"><thead><tr><th>ID</th><th>Đơn đặt</th><th>Khách hàng</th><th>Sân / thời gian</th><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($rows as $payment): ?><tr>
    <?php $paymentLabel = payment_status_label((string)$payment['status']); if ($payment['payment_method']==='venue' && $payment['status']==='pending') $paymentLabel='Chờ thanh toán tại sân'; $paymentBadge = match($payment['status']) {'paid'=>'is-paid','cancelled'=>'is-cancelled','failed'=>'is-failed',default=>'is-pending'}; ?>
    <td class="text-secondary">#<?= (int)$payment['id'] ?></td>
    <td><a class="admin-table-link" href="<?= e(app_url('admin/booking_detail.php?id='.(int)$payment['booking_id'])) ?>"><?= e(booking_code((int)$payment['booking_id'])) ?></a></td>
    <td class="admin-cell-primary"><?= e($payment['full_name']) ?></td>
    <td><strong class="admin-cell-primary"><?= e($payment['court_name']) ?></strong><small class="admin-cell-secondary"><?= e(date('d/m/Y',strtotime($payment['booking_date']))) ?> · <?= e($payment['slots'] ?: '—') ?></small></td>
    <td class="admin-money"><?= money($payment['amount']) ?></td>
    <td class="admin-nowrap"><?= $payment['payment_method']==='bank_transfer'?'Chuyển khoản':'Thanh toán tại sân' ?></td>
    <td><span class="admin-status-badge <?= $paymentBadge ?>"><?= e($paymentLabel) ?></span></td>
    <td class="admin-time-cell"><span><?= e(date('d/m/Y H:i',strtotime($payment['created_at']))) ?></span><?php if ($payment['payment_submitted_at']): ?><small>User báo: <?= e(date('d/m/Y H:i',strtotime($payment['payment_submitted_at']))) ?></small><?php endif; ?><?php if ($payment['paid_at']): ?><small>Đã trả: <?= e(date('d/m/Y H:i',strtotime($payment['paid_at']))) ?></small><?php endif; ?></td>
    <td><?php if ($payment['status']==='pending' && (($payment['payment_method']==='bank_transfer' && $payment['payment_submitted_at']) || ($payment['payment_method']==='venue' && in_array($payment['booking_status'],['confirmed','completed'],true)))): $confirmText='Bạn có chắc chắn đã nhận được khoản thanh toán này?'; ?>
        <form method="post" class="admin-action-form" data-confirm data-confirm-ajax data-confirm-title="Xác nhận thanh toán" data-confirm-message="<?= e($confirmText) ?>" data-confirm-tone="success" data-confirm-button="Xác nhận thanh toán" data-confirm-code="<?= e(booking_code((int)$payment['booking_id'])) ?>" data-confirm-customer="<?= e($payment['full_name']) ?>" data-confirm-court="<?= e($payment['court_name']) ?>" data-confirm-amount="<?= e(money($payment['amount'])) ?>" data-confirm-method="<?= $payment['payment_method']==='venue'?'Thanh toán tại sân':'Chuyển khoản ngân hàng' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$payment['id'] ?>"><button type="submit" class="btn btn-sm btn-success">Xác nhận thanh toán</button></form>
    <?php elseif ($payment['status']==='pending' && $payment['payment_method']==='bank_transfer'): ?><span class="text-secondary small">Chờ khách xác nhận chuyển khoản</span>
    <?php elseif ($payment['status']==='pending' && $payment['payment_method']==='venue'): ?><span class="text-secondary small">Chờ khách hoàn tất đặt đơn</span>
    <?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8">Chưa có giao dịch phù hợp.</td></tr><?php endif; ?></tbody></table></div>
</section></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
