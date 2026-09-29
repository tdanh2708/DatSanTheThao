<?php
require __DIR__ . '/../config/database.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    if ($id !== (int)current_user()['id']) {
        $statement = $pdo->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=? AND role='user'");
        $statement->execute([$id]);
        if($statement->rowCount()) admin_log($pdo, 'user_status', 'user', $id, 'Thay đổi trạng thái tài khoản user #' . $id);
        flash($statement->rowCount() ? 'Đã cập nhật trạng thái tài khoản.' : 'Không tìm thấy người dùng.', $statement->rowCount() ? 'success' : 'warning');
    } else flash('Không thể tự khóa tài khoản đang sử dụng.', 'warning');
    redirect('/admin/users.php');
}
$q = trim((string)($_GET['q'] ?? ''));
$statement = $pdo->prepare("SELECT u.id,u.full_name,u.email,u.phone,u.role,u.status,u.created_at,COUNT(b.id) booking_count
    FROM users u LEFT JOIN bookings b ON b.user_id=u.id WHERE u.role='user'
      AND (?='' OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
    GROUP BY u.id ORDER BY u.created_at DESC LIMIT 500");
$like = '%' . $q . '%';
$statement->execute([$q,$like,$like,$like]);
$rows = $statement->fetchAll();
$pageTitle = 'Quản lý người dùng';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<h1 class="mb-3">Quản lý người dùng</h1>
<form class="admin-search mb-3" method="get"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Tìm theo tên, email hoặc điện thoại"><button class="btn btn-success">Tìm</button></form>
<div class="table-responsive"><table class="table bg-white align-middle"><thead><tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Vai trò</th><th>Số đơn</th><th>Ngày tạo</th><th>Trạng thái</th><th></th></tr></thead><tbody>
<?php foreach($rows as $user): $locking = $user['status']==='active'; ?><tr><td><?= (int)$user['id'] ?></td><td><a href="<?= e(app_url('admin/user_detail.php?id='.(int)$user['id'])) ?>"><?= e($user['full_name']) ?></a></td><td><?= e($user['email']) ?></td><td><?= e($user['phone'] ?? '—') ?></td><td><?= e($user['role']) ?></td><td><?= (int)$user['booking_count'] ?></td><td><?= e(date('d/m/Y',strtotime($user['created_at']))) ?></td><td><span class="admin-status-badge <?= $locking?'is-paid':'is-inactive' ?>"><?= $locking?'Đang hoạt động':'Đã khóa' ?></span></td><td><form method="post" data-confirm data-confirm-title="<?= $locking?'Khóa tài khoản':'Mở khóa tài khoản' ?>" data-confirm-message="<?= $locking?'Tài khoản sẽ không thể đăng nhập cho đến khi được mở lại.':'Tài khoản sẽ có thể đăng nhập lại.' ?>" data-confirm-tone="<?= $locking?'danger':'success' ?>" data-confirm-button="<?= $locking?'Khóa tài khoản':'Mở khóa tài khoản' ?>" data-confirm-customer="<?= e($user['full_name']) ?>" data-confirm-email="<?= e($user['email']) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><button class="btn btn-sm <?= $locking?'btn-outline-danger':'btn-outline-success' ?>"><?= $locking?'Khóa tài khoản':'Mở khóa tài khoản' ?></button></form></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="9">Không tìm thấy người dùng.</td></tr><?php endif; ?></tbody></table></div>
</section></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
