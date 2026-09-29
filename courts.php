<?php
require __DIR__ . '/config/database.php';
expire_pending_bank_transfers($pdo);

$pageTitle = 'Danh sách sân';
$sports = $pdo->query("SELECT id, name FROM sports WHERE status = 'active' ORDER BY name")->fetchAll();
$where = ["c.status = 'active'", "s.status = 'active'"];
$params = [];
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$location = mb_substr(trim((string)($_GET['location'] ?? '')), 0, 120);
$date = (string)($_GET['date'] ?? '');
$validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    && checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))
    && $date >= date('Y-m-d');
if (!$validDate) $date = '';

$sportId = filter_input(INPUT_GET, 'sport_id', FILTER_VALIDATE_INT);
if ($q !== '') {
    $where[] = '(c.name LIKE ? OR c.address LIKE ? OR s.name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($location !== '') {
    $where[] = 'c.address LIKE ?';
    $params[] = '%' . $location . '%';
}
if ($sportId) {
    $where[] = 'c.sport_id = ?';
    $params[] = $sportId;
}

$query = 'SELECT c.*, s.name AS sport_name,
        (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.court_id = c.id) AS avg_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.court_id = c.id) AS review_count
    FROM courts c JOIN sports s ON s.id = c.sport_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name';
$statement = $pdo->prepare($query);
$statement->execute($params);
$courts = $statement->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="listing-heading">
    <span class="eyebrow">KHÁM PHÁ ĐỊA ĐIỂM CHƠI</span>
    <h1>Tìm sân thể thao</h1>
    <p>Chọn môn thể thao và khu vực để tìm địa điểm phù hợp tại TP. Hồ Chí Minh.</p>
</section>

<form class="listing-search" method="get" action="<?= e(app_url('courts.php')) ?>">
    <label><span>Địa điểm</span><input name="location" value="<?= e($location) ?>" placeholder="Quận, phường hoặc khu vực"></label>
    <label><span>Môn thể thao</span><select name="sport_id"><option value="">Tất cả môn thể thao</option><?php foreach ($sports as $sport): ?><option value="<?= (int)$sport['id'] ?>" <?= $sportId === (int)$sport['id'] ? 'selected' : '' ?>><?= e($sport['name']) ?></option><?php endforeach; ?></select></label>
    <label><span>Ngày</span><input type="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= e($date) ?>"></label>
    <label class="listing-keyword"><span>Tên sân hoặc môn</span><input name="q" value="<?= e($q) ?>" placeholder="Nhập tên sân hoặc môn"></label>
    <button type="submit" class="search-submit">⌕ Tìm sân</button>
</form>

<div class="listing-results">
    <p><?= count($courts) ?> địa điểm phù hợp<?= $date !== '' ? ' · Ngày ' . e(date('d/m/Y', strtotime($date))) : '' ?></p>
    <p class="small text-secondary">Giá hiển thị là giá tham khảo hoặc giá bắt đầu từ nguồn công khai, không thay thế báo giá của sân. Khung giờ trên website là lịch mẫu; vui lòng xác nhận giờ mở cửa thực tế với địa điểm.</p>
    <div class="courts-grid">
        <?php foreach ($courts as $court):
            $rawImage = trim((string)($court['image'] ?? ''));
            $fallbackImage = sport_image_filename((string)$court['sport_name']);
            $image = court_image_url($rawImage !== '' ? $rawImage : $fallbackImage, (string)$court['sport_name']);
            $detailUrl = app_url('court_detail.php?id=' . (int)$court['id'] . ($date !== '' ? '&date=' . urlencode($date) : ''));
        ?>
            <article class="featured-card">
                <a class="featured-image" href="<?= e($detailUrl) ?>" aria-label="Xem chi tiết <?= e($court['name']) ?>">
                    <img src="<?= e($image) ?>" alt="Sân <?= e($court['sport_name']) ?> - <?= e($court['name']) ?>" loading="lazy">
                    <span class="sport-label"><?= e($court['sport_name']) ?></span>
                    <span class="image-arrow" aria-hidden="true">→</span>
                </a>
                <div class="featured-body">
                    <h2><a href="<?= e($detailUrl) ?>"><?= e($court['name']) ?></a></h2>
                    <p class="court-address">⌖ <?= e($court['address']) ?></p>
                    <?php if ((int)$court['review_count'] > 0): ?>
                        <p class="rating">★ <?= e($court['avg_rating']) ?> <small>(<?= (int)$court['review_count'] ?> đánh giá)</small></p>
                    <?php else: ?>
                        <p class="rating-empty">Chưa có đánh giá</p>
                    <?php endif; ?>
                    <?php if ($court['court_count'] !== null): ?><p class="court-address"><?= (int)$court['court_count'] ?> sân tại địa điểm</p><?php endif; ?>
                    <div class="featured-bottom">
                        <p class="court-price"><?= (float)$court['price_per_hour'] > 0 ? 'Tham khảo ' . money($court['price_per_hour']) : 'Giá cần xác nhận' ?><small>/giờ</small></p>
                        <a class="book-button" href="<?= e($detailUrl) ?>">Xem chi tiết</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$courts): ?><div class="empty-state"><span aria-hidden="true">⌕</span><h2>Chưa tìm thấy sân phù hợp</h2><p>Thử thay đổi môn thể thao, tên sân hoặc khu vực.</p><a class="book-button" href="<?= e(app_url('courts.php')) ?>">Xóa bộ lọc</a></div><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
