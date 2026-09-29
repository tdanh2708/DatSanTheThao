<?php
require __DIR__ . '/../config/database.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    $statement = $pdo->prepare('DELETE FROM reviews WHERE id=?');
    $statement->execute([$id]);
    admin_log($pdo, 'review_deleted', 'review', (int)$id, 'Admin xóa đánh giá #' . (int)$id);
    flash($statement->rowCount() ? 'Đã xóa đánh giá.' : 'Không tìm thấy đánh giá.', $statement->rowCount() ? 'success' : 'warning');
    redirect('/admin/reviews.php');
}
$rows = $pdo->query('SELECT r.id,r.rating,r.comment,r.created_at,u.full_name,c.name court_name FROM reviews r JOIN users u ON u.id=r.user_id JOIN courts c ON c.id=r.court_id ORDER BY r.created_at DESC LIMIT 500')->fetchAll();
$pageTitle = 'Quản lý đánh giá';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<h1 class="mb-3">Quản lý đánh giá</h1>
<div class="table-responsive"><table class="table bg-white align-middle"><thead><tr><th>Người đánh giá</th><th>Sân</th><th>Số sao</th><th>Nội dung</th><th>Ngày</th><th></th></tr></thead><tbody>
<?php foreach($rows as $review): ?><tr><td><?= e($review['full_name']) ?></td><td><?= e($review['court_name']) ?></td><td><span class="review-stars"><?= str_repeat('★',(int)$review['rating']) ?><?= str_repeat('☆',5-(int)$review['rating']) ?></span></td><td><?= nl2br(e($review['comment'] ?? '')) ?></td><td><?= e(date('d/m/Y',strtotime($review['created_at']))) ?></td><td><form method="post" data-confirm data-confirm-title="Xóa đánh giá" data-confirm-message="Xóa đánh giá này? Thao tác này không thể hoàn tác." data-confirm-tone="danger" data-confirm-button="Xóa đánh giá" data-confirm-customer="<?= e($review['full_name']) ?>" data-confirm-court="<?= e($review['court_name']) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$review['id'] ?>"><button class="btn btn-sm btn-outline-danger">Xóa đánh giá</button></form></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6">Chưa có đánh giá.</td></tr><?php endif; ?></tbody></table></div>
</section></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
