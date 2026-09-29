<?php
require __DIR__ . '/../config/database.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    if ($action === 'toggle') {
        $statement = $pdo->prepare("UPDATE sports SET status=IF(status='active','inactive','active') WHERE id=? AND NOT(status='active' AND EXISTS(SELECT 1 FROM courts WHERE courts.sport_id=sports.id AND courts.status='active'))");
        $statement->execute([$id]);
        if($statement->rowCount()) admin_log($pdo, 'sport_status', 'sport', $id, 'Thay đổi trạng thái môn #' . $id);
        flash($statement->rowCount() ? 'Đã cập nhật trạng thái môn.' : 'Không thể ẩn môn đang có sân hoạt động.', $statement->rowCount() ? 'success' : 'warning');
        redirect('/admin/sports.php');
    }
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100 || mb_strlen($description) > 2000) $error = 'Tên hoặc mô tả môn thể thao không hợp lệ.';
    else {
        try {
            if ($id) { $statement = $pdo->prepare('UPDATE sports SET name=?,description=? WHERE id=?'); $statement->execute([$name,$description ?: null,$id]); admin_log($pdo,'sport_updated','sport',$id,'Cập nhật môn '.$name); }
            else { $statement = $pdo->prepare('INSERT INTO sports(name,description) VALUES(?,?)'); $statement->execute([$name,$description ?: null]); admin_log($pdo,'sport_created','sport',(int)$pdo->lastInsertId(),'Tạo môn '.$name); }
            flash('Đã lưu môn thể thao.'); redirect('/admin/sports.php');
        } catch (PDOException) { $error = 'Tên môn này đã tồn tại.'; }
    }
}
$edit = null;
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) { $statement=$pdo->prepare('SELECT * FROM sports WHERE id=?'); $statement->execute([(int)$_GET['edit']]); $edit=$statement->fetch(); }
$rows = $pdo->query('SELECT s.*,COUNT(c.id) court_count FROM sports s LEFT JOIN courts c ON c.sport_id=s.id GROUP BY s.id ORDER BY s.name')->fetchAll();
$pageTitle = 'Quản lý môn thể thao';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<h1 class="mb-4">Quản lý môn thể thao</h1>
<div class="card card-body mb-4"><h2 class="h5"><?= $edit ? 'Chỉnh sửa môn thể thao' : 'Thêm môn thể thao' ?></h2><?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>"><div class="col-md-3"><input class="form-control" name="name" maxlength="100" placeholder="Tên môn" required value="<?= e($edit['name']??'') ?>"></div><div class="col-md-7"><input class="form-control" name="description" maxlength="2000" placeholder="Mô tả" value="<?= e($edit['description']??'') ?>"></div><div class="col-md-2 d-grid"><button class="btn btn-success"><?= $edit ? 'Lưu thay đổi' : 'Thêm môn' ?></button></div></form></div>
<div class="table-responsive admin-table-wrap"><table class="table table-hover bg-white align-middle"><thead><tr><th>ID</th><th>Tên</th><th>Số sân</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach($rows as $row): $sportActive=$row['status']==='active'; ?><tr><td><?= (int)$row['id'] ?></td><td><?= e($row['name']) ?></td><td><?= (int)$row['court_count'] ?></td><td><span class="admin-status-badge <?= $sportActive?'is-paid':'is-inactive' ?>"><?= $sportActive?'Đang hoạt động':'Đã ẩn' ?></span></td><td><div class="admin-row-actions"><a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('admin/sports.php?edit='.(int)$row['id'])) ?>">Chỉnh sửa</a><form method="post" data-confirm data-confirm-title="<?= $sportActive?'Ẩn môn thể thao':'Hiện môn thể thao' ?>" data-confirm-message="<?= $sportActive?'Ẩn môn khỏi danh sách khách hàng?':'Hiện môn trở lại cho khách hàng?' ?>" data-confirm-tone="<?= $sportActive?'danger':'success' ?>" data-confirm-button="<?= $sportActive?'Ẩn môn':'Hiện môn' ?>" data-confirm-court="<?= e($row['name']) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm <?= $sportActive?'btn-outline-danger':'btn-outline-success' ?>"><?= $sportActive?'Ẩn môn':'Hiện môn' ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div>
</section></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
