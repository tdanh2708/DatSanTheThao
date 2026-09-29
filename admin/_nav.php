<?php
$adminUnreadCount = (int)$pdo->query("SELECT (SELECT COUNT(*) FROM messages m JOIN users u ON u.id=m.sender_id WHERE u.role='user' AND m.is_read=0 AND (m.message IS NOT NULL OR m.image_path IS NOT NULL))+(SELECT COUNT(*) FROM notifications WHERE recipient_role='admin' AND recipient_id IS NULL AND is_read=0)")->fetchColumn();
$adminNavItems = [
    'index.php' => ['Dashboard', 'index.php'],
    'courts.php' => ['Quản lý sân', 'courts.php'],
    'sports.php' => ['Môn thể thao', 'sports.php'],
    'bookings.php' => ['Đơn đặt sân', 'bookings.php'],
    'payments.php' => ['Thanh toán', 'payments.php'],
    'users.php' => ['Người dùng', 'users.php'],
    'reviews.php' => ['Đánh giá', 'reviews.php'],
    'messages.php' => ['Tin nhắn', 'messages.php'],
    'notifications.php' => ['Thông báo', 'notifications.php'],
    'time_slots.php' => ['Khung giờ', 'time_slots.php'],
    'logs.php' => ['Nhật ký hoạt động', 'logs.php'],
];
?>
<div class="admin-layout">
    <aside class="admin-sidebar" aria-label="Điều hướng quản trị">
        <strong>Quản trị viên</strong>
        <?php foreach ($adminNavItems as $file => [$label, $path]): ?>
            <a class="<?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === $file || ($file === 'bookings.php' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'booking_detail.php')) ? 'active' : '' ?>" href="<?= e(app_url('admin/' . $path)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="admin-logout" href="<?= e(app_url('auth/logout.php')) ?>">Đăng xuất</a>
    </aside>
    <section class="admin-content">
        <?php if (isset($adminUnreadCount)): ?><div class="admin-topbar"><span>Sports Court Booking — ADMIN</span><a href="<?= e(app_url('admin/notifications.php')) ?>" data-admin-unread aria-label="Thông báo, <?= (int)$adminUnreadCount ?> chưa đọc">🔔 Thông báo <b><?= (int)$adminUnreadCount ?></b></a></div><?php endif; ?>
        <div class="modal fade admin-confirm-modal" id="adminConfirmModal" tabindex="-1" aria-labelledby="adminConfirmTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h2 class="modal-title h5" id="adminConfirmTitle" data-admin-confirm-title>Xác nhận thao tác</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
                <div class="modal-body"><p class="mb-3" data-admin-confirm-message></p><dl class="admin-confirm-details" data-admin-confirm-details hidden></dl><div class="alert alert-danger py-2 mb-0" data-admin-confirm-error role="alert" hidden></div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button type="button" class="btn btn-success" data-admin-confirm-submit>Xác nhận</button></div>
            </div></div>
        </div>
