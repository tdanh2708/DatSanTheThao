<?php
require __DIR__ . '/../config/database.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    $used = $pdo->prepare('SELECT COUNT(*) FROM booking_details WHERE time_slot_id=?');
    $used->execute([$id]);
    if ($id && (int)$used->fetchColumn() > 0) $error = 'Khung giờ đã được dùng trong lịch sử đặt và không thể sửa hoặc ẩn.';
    elseif ($action === 'toggle') {
        $statement = $pdo->prepare("UPDATE time_slots SET status=IF(status='active','inactive','active') WHERE id=?");
        $statement->execute([$id]); flash('Đã đổi trạng thái khung giờ.'); redirect('/admin/time_slots.php');
    } else {
        $start = (string)($_POST['start_time'] ?? '');
        $end = (string)($_POST['end_time'] ?? '');
        if (!preg_match('/^\d{2}:\d{2}$/',$start) || !preg_match('/^\d{2}:\d{2}$/',$end)
            || $start < '06:00' || $end > '22:00' || $start >= $end) $error = 'Khung giờ phải hợp lệ trong khoảng 06:00–22:00.';
        else {
            $overlap = $pdo->prepare("SELECT COUNT(*) FROM time_slots WHERE status='active' AND id<>? AND start_time<? AND end_time>?");
            $overlap->execute([$id,$end,$start]);
            if ((int)$overlap->fetchColumn() > 0) $error = 'Khung giờ bị trùng hoặc chồng lấn với khung khác.';
            else try {
                if ($id) { $statement=$pdo->prepare('UPDATE time_slots SET start_time=?,end_time=? WHERE id=?'); $statement->execute([$start,$end,$id]); }
                else { $statement=$pdo->prepare('INSERT INTO time_slots(start_time,end_time) VALUES(?,?)'); $statement->execute([$start,$end]); }
                flash('Đã lưu khung giờ.'); redirect('/admin/time_slots.php');
            } catch (PDOException) { $error = 'Không thể lưu khung giờ. Có thể giờ này đã tồn tại.'; }
        }
    }
}
$edit = null;
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) { $statement=$pdo->prepare('SELECT * FROM time_slots WHERE id=?'); $statement->execute([(int)$_GET['edit']]); $edit=$statement->fetch(); }
$rows = $pdo->query('SELECT * FROM time_slots ORDER BY start_time')->fetchAll();
$pageTitle = 'Quản lý khung giờ';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<h1 class="mb-4">Quản lý khung giờ</h1>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="card card-body mb-4" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>"><div class="row g-2"><div class="col-md-4"><label class="form-label">Bắt đầu</label><input class="form-control" type="time" name="start_time" min="06:00" max="21:00" required value="<?= e(isset($edit['start_time'])?substr($edit['start_time'],0,5):'') ?>"></div><div class="col-md-4"><label class="form-label">Kết thúc</label><input class="form-control" type="time" name="end_time" min="07:00" max="22:00" required value="<?= e(isset($edit['end_time'])?substr($edit['end_time'],0,5):'') ?>"></div><div class="col-md-4 align-self-end"><button class="btn btn-success">Lưu khung giờ</button></div></div></form>
<div class="table-responsive admin-table-wrap"><table class="table table-hover bg-white align-middle"><thead><tr><th>Khung giờ</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach($rows as $row): $slotActive=$row['status']==='active'; ?><tr><td><?= e(substr($row['start_time'],0,5).' – '.substr($row['end_time'],0,5)) ?></td><td><span class="admin-status-badge <?= $slotActive?'is-paid':'is-inactive' ?>"><?= $slotActive?'Đang hoạt động':'Đã ẩn' ?></span></td><td><div class="admin-row-actions"><a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('admin/time_slots.php?edit='.(int)$row['id'])) ?>">Chỉnh sửa</a><form method="post" data-confirm data-confirm-title="<?= $slotActive?'Ẩn khung giờ':'Hiện khung giờ' ?>" data-confirm-message="<?= $slotActive?'Tắt khung giờ này?':'Bật lại khung giờ này?' ?>" data-confirm-tone="<?= $slotActive?'danger':'success' ?>" data-confirm-button="<?= $slotActive?'Ẩn khung giờ':'Hiện khung giờ' ?>" data-confirm-court="<?= e(substr($row['start_time'],0,5).' – '.substr($row['end_time'],0,5)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm <?= $slotActive?'btn-outline-danger':'btn-outline-success' ?>"><?= $slotActive?'Ẩn khung giờ':'Hiện khung giờ' ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div>
</section></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
