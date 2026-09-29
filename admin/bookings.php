<?php
require_once __DIR__ . '/../config/database.php';
require_admin();
expire_pending_bank_transfers($pdo);
complete_elapsed_confirmed_bookings($pdo);

$error = '';
$searchInput = $_GET['q'] ?? '';
$dateInput = $_GET['date'] ?? '';
$bookingStatusInput = $_GET['status'] ?? '';
$paymentStatusInput = $_GET['payment'] ?? '';
$paymentMethodInput = $_GET['method'] ?? '';
$filterValues = [
    'q' => is_string($searchInput) ? mb_substr(trim($searchInput), 0, 120) : '',
    'date' => is_string($dateInput) ? trim($dateInput) : '',
    'sport' => filter_var($_GET['sport'] ?? 0, FILTER_VALIDATE_INT) ?: 0,
    'status' => is_string($bookingStatusInput) ? $bookingStatusInput : '',
    'payment' => is_string($paymentStatusInput) ? $paymentStatusInput : '',
    'method' => is_string($paymentMethodInput) ? $paymentMethodInput : '',
];
$validBookingStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
$validPaymentStatuses = ['pending', 'paid', 'failed', 'cancelled'];
$validPaymentMethods = ['bank_transfer', 'venue'];
$isValidDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterValues['date'])
    && checkdate((int)substr($filterValues['date'], 5, 2), (int)substr($filterValues['date'], 8, 2), (int)substr($filterValues['date'], 0, 4));
if (!$isValidDate) $filterValues['date'] = '';
if (!in_array($filterValues['status'], $validBookingStatuses, true)) $filterValues['status'] = '';
if (!in_array($filterValues['payment'], $validPaymentStatuses, true)) $filterValues['payment'] = '';
if (!in_array($filterValues['method'], $validPaymentMethods, true)) $filterValues['method'] = '';
$returnParams = array_filter($filterValues, static fn($value) => $value !== '' && $value !== 0);
$returnPage = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
if ($returnPage > 1) $returnParams['page'] = $returnPage;
$listReturnUrl = app_url('admin/bookings.php' . ($returnParams ? '?' . http_build_query($returnParams) : ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare("SELECT b.id,b.user_id,b.status,b.booking_date,
                (SELECT MIN(t.start_time) FROM booking_details d JOIN time_slots t ON t.id=d.time_slot_id WHERE d.booking_id=b.id) first_slot_start,
                (SELECT MAX(t.end_time) FROM booking_details d JOIN time_slots t ON t.id=d.time_slot_id WHERE d.booking_id=b.id) last_slot_end,
                p.status payment_status
            FROM bookings b LEFT JOIN payments p ON p.booking_id=b.id
            WHERE b.id=? FOR UPDATE");
        $statement->execute([$id]);
        $booking = $statement->fetch();
        if (!$booking || !in_array($booking['status'], ['pending', 'confirmed'], true)) {
            throw new RuntimeException('Đơn không còn ở trạng thái có thể hủy.');
        }
        $lastSlotTimestamp = $booking['last_slot_end']
            ? strtotime($booking['booking_date'] . ' ' . $booking['last_slot_end'])
            : false;
        if (!$lastSlotTimestamp || $lastSlotTimestamp <= time()) {
            throw new RuntimeException('Không thể hủy đơn sau khi khung giờ chơi cuối cùng đã bắt đầu/kết thúc.');
        }

        $update = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND status IN ('pending','confirmed')");
        $update->execute([$id]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Đơn vừa thay đổi. Vui lòng tải lại trang.');
        $pdo->prepare('UPDATE booking_details SET slot_active=NULL WHERE booking_id=?')->execute([$id]);
        if ($booking['payment_status'] === 'pending') {
            $pdo->prepare("UPDATE payments SET status='cancelled' WHERE booking_id=? AND status='pending'")->execute([$id]);
        }
        admin_log($pdo, 'booking_cancel', 'booking', $id, 'Admin hủy đơn ' . booking_code($id));
        notify_user($pdo, 'user', (int)$booking['user_id'], 'booking_status', 'Đơn ' . booking_code($id) . ' đã được Admin hủy.', 'user/booking_detail.php?id=' . $id);
        $pdo->commit();
        flash('Đã hủy đơn ' . booking_code($id) . ' và giải phóng khung giờ.');
        $query = http_build_query($returnParams);
        redirect('/admin/bookings.php' . ($query ? '?' . $query : ''));
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Chưa thể hủy đơn. Vui lòng thử lại.';
    }
}

$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;
$conditions = [];
$params = [];
if ($filterValues['q'] !== '') {
    $term = $filterValues['q'];
    $searchParts = ['u.full_name LIKE ?', 'u.email LIKE ?', 'u.phone LIKE ?', 'c.name LIKE ?'];
    $like = '%' . $term . '%';
    array_push($params, $like, $like, $like, $like);
    if (preg_match('/^BK0*(\d+)$/i', $term, $matches)) {
        $searchParts[] = 'b.id=?';
        $params[] = (int)$matches[1];
    } elseif (ctype_digit($term)) {
        $searchParts[] = 'b.id=?';
        $params[] = (int)$term;
    }
    $conditions[] = '(' . implode(' OR ', $searchParts) . ')';
}
if ($filterValues['date'] !== '') { $conditions[] = 'b.booking_date=?'; $params[] = $filterValues['date']; }
if ($filterValues['sport'] > 0) { $conditions[] = 'c.sport_id=?'; $params[] = $filterValues['sport']; }
if ($filterValues['status'] !== '') { $conditions[] = 'b.status=?'; $params[] = $filterValues['status']; }
if ($filterValues['payment'] !== '') { $conditions[] = 'p.status=?'; $params[] = $filterValues['payment']; }
if ($filterValues['method'] !== '') { $conditions[] = 'p.payment_method=?'; $params[] = $filterValues['method']; }
$whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$countQuery = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN users u ON u.id=b.user_id JOIN courts c ON c.id=b.court_id LEFT JOIN payments p ON p.booking_id=b.id" . $whereSql);
$countQuery->execute($params);
$totalRows = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$listQuery = $pdo->prepare("SELECT b.*,u.full_name,u.email,u.phone,c.name court_name,c.sport_id,
        p.payment_method,p.status payment_status,p.payment_submitted_at,p.paid_at,
        (SELECT GROUP_CONCAT(CONCAT('Sân ',d.court_number,' · ',TIME_FORMAT(t.start_time,'%H:%i'),'–',TIME_FORMAT(t.end_time,'%H:%i')) ORDER BY t.start_time SEPARATOR ', ')
            FROM booking_details d JOIN time_slots t ON t.id=d.time_slot_id WHERE d.booking_id=b.id) slots,
        (SELECT MAX(t.end_time) FROM booking_details d JOIN time_slots t ON t.id=d.time_slot_id WHERE d.booking_id=b.id) last_slot_end,
        (SELECT MIN(t.start_time) FROM booking_details d JOIN time_slots t ON t.id=d.time_slot_id WHERE d.booking_id=b.id) first_slot_start
    FROM bookings b JOIN users u ON u.id=b.user_id JOIN courts c ON c.id=b.court_id
    LEFT JOIN payments p ON p.booking_id=b.id" . $whereSql . ' ORDER BY b.created_at DESC,b.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$listQuery->execute($params);
$rows = $listQuery->fetchAll();
$sports = $pdo->query("SELECT id,name FROM sports WHERE status='active' ORDER BY name")->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) total,
    SUM(status IN ('pending','confirmed')) active,
    SUM(status='completed') completed,
    SUM(status='cancelled') cancelled FROM bookings")->fetch();

$pageTitle = 'Quản lý đơn đặt sân';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<div class="admin-bookings-heading">
    <div><h1>Quản lý đơn đặt sân</h1><p>Theo dõi lịch đặt, thông tin khách hàng và trạng thái thanh toán.</p></div>
</div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="admin-booking-stats">
    <div><strong><?= (int)$stats['total'] ?></strong><span>Tổng đơn</span></div>
    <div><strong><?= (int)$stats['active'] ?></strong><span>Đang hoạt động</span></div>
    <div><strong><?= (int)$stats['completed'] ?></strong><span>Hoàn thành</span></div>
    <div><strong><?= (int)$stats['cancelled'] ?></strong><span>Đã hủy</span></div>
</div>
<form method="get" class="admin-booking-filters">
    <input class="form-control" name="q" value="<?= e($filterValues['q']) ?>" placeholder="Tìm mã đơn, khách hàng, email, điện thoại">
    <input type="date" class="form-control" name="date" value="<?= e($filterValues['date']) ?>" aria-label="Ngày chơi">
    <select class="form-select" name="sport"><option value="0">Tất cả môn</option><?php foreach ($sports as $sport): ?><option value="<?= (int)$sport['id'] ?>" <?= $filterValues['sport'] === (int)$sport['id'] ? 'selected' : '' ?>><?= e($sport['name']) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="status"><option value="">Tất cả trạng thái đơn</option><?php foreach ($validBookingStatuses as $status): ?><option value="<?= e($status) ?>" <?= $filterValues['status'] === $status ? 'selected' : '' ?>><?= e(booking_status_label($status)) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="payment"><option value="">Tất cả trạng thái thanh toán</option><?php foreach ($validPaymentStatuses as $status): ?><option value="<?= e($status) ?>" <?= $filterValues['payment'] === $status ? 'selected' : '' ?>><?= e(payment_status_label($status)) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="method"><option value="">Tất cả phương thức</option><option value="bank_transfer" <?= $filterValues['method'] === 'bank_transfer' ? 'selected' : '' ?>>Chuyển khoản ngân hàng</option><option value="venue" <?= $filterValues['method'] === 'venue' ? 'selected' : '' ?>>Thanh toán tại sân</option></select>
    <button class="btn btn-success" type="submit">Lọc</button>
    <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/bookings.php')) ?>">Đặt lại</a>
</form>
<div class="admin-bookings-table-wrap">
    <table class="table admin-bookings-table align-middle">
        <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Sân / lịch đặt</th><th>Tổng tiền</th><th>Phương thức</th><th>Thanh toán</th><th>Trạng thái đơn</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row):
            $code = booking_code((int)$row['id']);
            $methodLabel = $row['payment_method'] === 'bank_transfer' ? 'Chuyển khoản' : ($row['payment_method'] === 'venue' ? 'Thanh toán tại sân' : 'Đơn cũ');
            $paymentStatus = (string)($row['payment_status'] ?? '');
            $paymentLabel = $paymentStatus === '' ? 'Chưa ghi nhận (đơn cũ)' : payment_status_label($paymentStatus);
            if ($paymentStatus === 'pending' && $row['payment_method'] === 'venue') $paymentLabel = 'Chờ thanh toán tại sân';
            if ($paymentStatus === 'pending' && $row['payment_method'] === 'bank_transfer') $paymentLabel = $row['payment_submitted_at'] ? 'Chờ xác nhận' : 'Chờ thanh toán';
            $paymentBadge = match ($paymentStatus) { 'paid' => 'is-paid', 'cancelled' => 'is-cancelled', 'failed' => 'is-failed', default => 'is-pending' };
            $bookingBadge = match ($row['status']) { 'confirmed' => 'is-confirmed', 'completed' => 'is-paid', 'cancelled' => 'is-cancelled', default => 'is-pending' };
            $firstSlotTimestamp = $row['first_slot_start'] ? strtotime($row['booking_date'] . ' ' . $row['first_slot_start']) : false;
            $canCancel = in_array($row['status'], ['pending','confirmed'], true) && $firstSlotTimestamp && $firstSlotTimestamp > time();
            $cancelMessage = $paymentStatus === 'paid'
                ? 'Đơn đã được ghi nhận thanh toán. Hủy đơn sẽ không tự động hoàn tiền. Bạn có chắc muốn tiếp tục?'
                : 'Bạn có chắc muốn hủy đơn này? Khung giờ sẽ được mở lại cho khách khác.';
        ?>
            <tr>
                <td><a class="admin-table-link" href="<?= e(app_url('admin/booking_detail.php?id=' . (int)$row['id'])) ?>"><?= e($code) ?></a></td>
                <td><strong class="admin-cell-primary"><?= e($row['full_name']) ?></strong><small class="admin-cell-secondary"><?= e($row['email']) ?></small></td>
                <td class="admin-booking-court"><strong><?= e($row['court_name']) ?></strong><small><?= e(date('d/m/Y', strtotime($row['booking_date']))) ?><?= $row['slots'] ? ' · ' . e($row['slots']) : '' ?></small></td>
                <td class="admin-money"><?= money($row['total_amount']) ?></td>
                <td class="admin-nowrap"><?= e($methodLabel) ?></td>
                <td><span class="admin-status-badge <?= $paymentBadge ?>"><?= e($paymentLabel) ?></span></td>
                <td><span class="admin-status-badge <?= $bookingBadge ?>"><?= e(booking_status_label((string)$row['status'])) ?></span></td>
                <td><div class="admin-row-actions">
                    <a class="btn btn-sm btn-outline-success" href="<?= e(app_url('admin/booking_detail.php?id=' . (int)$row['id'])) ?>">Xem chi tiết</a>
                    <?php if ($canCancel): ?><form method="post" action="<?= e($listReturnUrl) ?>" data-confirm data-admin-booking-form data-confirm-title="Hủy đơn đặt sân" data-confirm-message="<?= e($cancelMessage) ?>" data-confirm-tone="danger" data-confirm-button="Hủy đơn" data-confirm-code="<?= e($code) ?>" data-confirm-customer="<?= e($row['full_name']) ?>" data-confirm-court="<?= e($row['court_name']) ?>" data-confirm-amount="<?= e(money($row['total_amount'])) ?>" data-confirm-method="<?= e($methodLabel) ?>" data-confirm-status="<?= e($paymentLabel) ?>">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-outline-danger" type="submit">Hủy đơn</button>
                    </form><?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center py-4">Không tìm thấy đơn đặt phù hợp.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($totalPages > 1): $paginationQuery = $filterValues; ?>
<nav class="admin-booking-pagination" aria-label="Phân trang đơn đặt">
    <?php $paginationQuery['page'] = max(1, $page - 1); ?><a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e(app_url('admin/bookings.php?' . http_build_query($paginationQuery))) ?>" aria-label="Trang trước">← Trước</a>
    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): $paginationQuery['page'] = $pageNumber; ?>
        <a class="<?= $pageNumber === $page ? 'active' : '' ?>" href="<?= e(app_url('admin/bookings.php?' . http_build_query($paginationQuery))) ?>"><?= $pageNumber ?></a>
    <?php endfor; ?>
    <?php $paginationQuery['page'] = min($totalPages, $page + 1); ?><a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= e(app_url('admin/bookings.php?' . http_build_query($paginationQuery))) ?>" aria-label="Trang sau">Sau →</a>
</nav>
<?php endif; ?>
</section></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
