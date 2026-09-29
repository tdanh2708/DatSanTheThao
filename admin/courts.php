<?php
require __DIR__ . '/../config/database.php';
require_admin();

$error = '';
$sports = $pdo->query("SELECT id, name FROM sports ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        $statement = $pdo->prepare("UPDATE courts SET status=IF(status='active','inactive','active') WHERE id=?");
        $statement->execute([$id]);
        admin_log($pdo, 'court_status', 'court', $id, 'Thay đổi trạng thái sân #' . $id);
        flash('Đã cập nhật trạng thái sân.');
        redirect('/admin/courts.php');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $sportId = (int)($_POST['sport_id'] ?? 0);
    $address = trim((string)($_POST['address'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $countText = trim((string)($_POST['court_count'] ?? ''));
    $courtCount = $countText === '' ? null : filter_var($countText, FILTER_VALIDATE_INT);
    $priceNote = trim((string)($_POST['price_note'] ?? ''));
    $image = trim((string)($_POST['image'] ?? ''));

    $validSport = $pdo->prepare('SELECT COUNT(*) FROM sports WHERE id=?');
    $validSport->execute([$sportId]);
    if (!$name || mb_strlen($name) > 150 || !$sportId || !(int)$validSport->fetchColumn() || !$address || mb_strlen($address) > 255 || $price === false || $price < 0
        || ($countText !== '' && ($courtCount === false || $courtCount < 1 || $courtCount > 500))
        || strlen($priceNote) > 255 || strlen($image) > 255) {
        $error = 'Vui lòng nhập dữ liệu sân hợp lệ. Số sân để trống nếu chưa xác minh.';
    } else {
        if ($id) {
            $statement = $pdo->prepare('UPDATE courts SET sport_id=?,name=?,description=?,address=?,price_per_hour=?,price_note=?,court_count=?,image=? WHERE id=?');
            $statement->execute([$sportId, $name, $description ?: null, $address, $price, $priceNote ?: null, $courtCount, $image ?: null, $id]);
            admin_log($pdo, 'court_updated', 'court', $id, 'Cập nhật sân ' . $name);
        } else {
            $statement = $pdo->prepare('INSERT INTO courts (sport_id,name,description,address,price_per_hour,price_note,court_count,image) VALUES (?,?,?,?,?,?,?,?)');
            $statement->execute([$sportId, $name, $description ?: null, $address, $price, $priceNote ?: null, $courtCount, $image ?: null]);
            admin_log($pdo, 'court_created', 'court', (int)$pdo->lastInsertId(), 'Tạo sân ' . $name);
        }
        flash('Đã lưu sân.');
        redirect('/admin/courts.php');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM courts WHERE id=?');
    $statement->execute([(int)$_GET['edit']]);
    $edit = $statement->fetch();
}
$q = trim((string)($_GET['q'] ?? ''));
$filterSport = filter_input(INPUT_GET, 'sport_id', FILTER_VALIDATE_INT) ?: 0;
$statement = $pdo->prepare('SELECT c.*,s.name sport_name FROM courts c JOIN sports s ON s.id=c.sport_id WHERE (?="" OR c.name LIKE ? OR c.address LIKE ?) AND (?=0 OR c.sport_id=?) ORDER BY c.id DESC LIMIT 500');
$like = '%' . $q . '%';
$statement->execute([$q,$like,$like,$filterSport,$filterSport]);
$rows = $statement->fetchAll();
$pageTitle = 'Quản lý sân';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<h1 class="mb-4">Quản lý sân</h1>
<div class="card card-body mb-4">
    <h2 class="h5"><?= $edit ? 'Chỉnh sửa sân' : 'Thêm sân mới' ?></h2>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="row g-3 admin-court-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="col-md-6"><label class="form-label">Tên sân</label><input class="form-control" name="name" required placeholder="Nhập tên sân" value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Môn thể thao</label><select class="form-select" name="sport_id" required><?php foreach ($sports as $sport): ?><option value="<?= (int)$sport['id'] ?>" <?= (int)($edit['sport_id'] ?? 0) === (int)$sport['id'] ? 'selected' : '' ?>><?= e($sport['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Địa chỉ</label><input class="form-control" name="address" required placeholder="Địa chỉ sân" value="<?= e($edit['address'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Số sân</label><input class="form-control" name="court_count" type="number" min="1" max="500" placeholder="Để trống nếu chưa rõ" value="<?= e($edit['court_count'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Giá mỗi giờ</label><input class="form-control" name="price" type="number" min="0" step="1000" required placeholder="Giá/giờ" value="<?= e($edit['price_per_hour'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Đường dẫn ảnh</label><input class="form-control" name="image" placeholder="Tùy chọn" value="<?= e($edit['image'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="3" placeholder="Thông tin ngắn về sân"><?= e($edit['description'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">Ghi chú giá / cơ sở (nội bộ)</label><input class="form-control" name="price_note" maxlength="255" placeholder="Ghi chú tùy chọn" value="<?= e($edit['price_note'] ?? '') ?>"></div>
        <div class="col-12"><button class="btn btn-success"><?= $edit ? 'Lưu thay đổi' : 'Thêm sân' ?></button><?php if ($edit): ?> <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/courts.php')) ?>">Hủy chỉnh sửa</a><?php endif; ?></div>
    </form>
</div>
<form class="admin-search mb-3" method="get"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Tìm tên sân hoặc địa chỉ"><select class="form-select" name="sport_id"><option value="0">Tất cả môn</option><?php foreach($sports as $sport): ?><option value="<?= (int)$sport['id'] ?>" <?= $filterSport===(int)$sport['id']?'selected':'' ?>><?= e($sport['name']) ?></option><?php endforeach; ?></select><button class="btn btn-success">Lọc</button></form>
<div class="table-responsive admin-table-wrap"><table class="table table-hover bg-white align-middle admin-courts-table">
    <thead><tr><th>Sân</th><th>Môn thể thao</th><th>Số sân</th><th>Giá/giờ</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr>
        <td class="admin-court-name"><strong><?= e($row['name']) ?></strong><small><?= e($row['address']) ?></small></td>
        <td><?= e($row['sport_name']) ?></td>
        <td class="admin-nowrap"><?= $row['court_count'] === null ? '—' : (int)$row['court_count'] ?></td>
        <td class="admin-money"><?= (float)$row['price_per_hour'] > 0 ? money($row['price_per_hour']) : 'Chưa cập nhật' ?></td>
        <td><span class="admin-status-badge <?= $row['status']==='active'?'is-paid':'is-inactive' ?>"><?= $row['status']==='active'?'Đang hoạt động':'Đã ẩn' ?></span></td>
        <td><div class="admin-row-actions"><a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('admin/courts.php?edit='.(int)$row['id'])) ?>">Chỉnh sửa</a><form method="post" data-confirm data-confirm-title="<?= $row['status']==='active'?'Ẩn sân':'Hiện sân' ?>" data-confirm-message="<?= $row['status']==='active'?'Sân sẽ được ẩn khỏi danh sách khách hàng.':'Sân sẽ hiển thị trở lại cho khách hàng.' ?>" data-confirm-tone="<?= $row['status']==='active'?'danger':'success' ?>" data-confirm-button="<?= $row['status']==='active'?'Ẩn sân':'Hiện sân' ?>" data-confirm-court="<?= e($row['name']) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-sm <?= $row['status']==='active'?'btn-outline-danger':'btn-outline-success' ?>"><?= $row['status']==='active'?'Ẩn sân':'Hiện sân' ?></button></form></div></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?php ?></section></div><?php require __DIR__ . '/../includes/footer.php'; ?>
