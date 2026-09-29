<?php
require __DIR__ . '/config/database.php';
$pageTitle = 'Trang chủ';
$sports = $pdo->query("SELECT id, name FROM sports WHERE status='active' AND name IN ('Bóng đá','Bóng rổ','Tennis','Cầu lông','Pickleball','Bóng chuyền') ORDER BY FIELD(name,'Bóng đá','Bóng rổ','Tennis','Cầu lông','Pickleball','Bóng chuyền')")->fetchAll();
$allSports = $pdo->query("SELECT id,name FROM sports WHERE status='active' ORDER BY name")->fetchAll();
$sportsForHome = [];
foreach ($sports as $sport) {
    $sportsForHome[] = ['href' => 'courts.php?sport_id=' . (int)$sport['id'], 'name' => $sport['name'], 'image' => sport_image_filename((string)$sport['name'])];
}
$sportsForHome[] = ['href' => 'sports.php', 'name' => 'Khác', 'image' => 'other.png'];
$stmt = $pdo->query("SELECT c.*, s.name AS sport_name,
    (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.court_id=c.id) AS avg_rating,
    (SELECT COUNT(*) FROM reviews r WHERE r.court_id=c.id) AS review_count,
    (SELECT COUNT(*) FROM bookings b WHERE b.court_id=c.id AND b.status IN ('pending','confirmed','completed')) AS recent_bookings
    FROM courts c JOIN sports s ON s.id=c.sport_id
    WHERE c.status='active' AND s.status='active'
    ORDER BY recent_bookings DESC, c.id DESC LIMIT 4");
$courts = $stmt->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-backdrop" aria-hidden="true" data-hero-slider>
        <?php foreach (['hero-city-court.png', 'hero-night-court.png', 'hero-football-court.png', 'hero-multi-sport.png'] as $slideIndex => $slideImage): ?>
            <img class="hero-slide<?= $slideIndex === 0 ? ' is-active' : '' ?>" <?= $slideIndex === 0 ? 'src' : 'data-src' ?>="<?= e(app_url('assets/images/' . $slideImage)) ?>" alt=""<?= $slideIndex === 0 ? ' fetchpriority="high" decoding="async"' : '' ?>>
        <?php endforeach; ?>
    </div>
    <div class="hero-content content-width">
        <div class="location-kicker"><span aria-hidden="true">📍</span> Thành phố Hồ Chí Minh</div>
        <h1>Đặt sân thể thao dễ dàng<br>tại <span>TP. Hồ Chí Minh</span></h1>
        <p>Tìm kiếm, đặt sân và trải nghiệm thể thao cùng bạn bè.<br class="desktop-break"> Nhiều sân chất lượng, đa dạng môn thể thao, giá cả hợp lý.</p>
        <form class="hero-search" action="<?= e(app_url('courts.php')) ?>" method="get">
            <label class="search-field location-field"><span class="search-icon" aria-hidden="true">⌖</span><span class="visually-hidden">Địa điểm</span><input name="location" value="<?= e($_GET['location'] ?? 'TP. Hồ Chí Minh') ?>" aria-label="Địa điểm" placeholder="TP. Hồ Chí Minh"></label>
            <label class="search-field"><span class="search-icon" aria-hidden="true">♧</span><span class="visually-hidden">Môn thể thao</span><select name="sport_id" aria-label="Môn thể thao"><option value="">Chọn môn thể thao</option><?php foreach ($allSports as $sport): ?><option value="<?= (int)$sport['id'] ?>"><?= e($sport['name']) ?></option><?php endforeach; ?></select></label>
            <label class="search-field"><span class="search-icon" aria-hidden="true">▦</span><span class="visually-hidden">Ngày</span><input type="date" name="date" aria-label="Ngày" min="<?= date('Y-m-d') ?>"></label>
            <button class="search-submit" type="submit"><span aria-hidden="true">⌕</span> Tìm sân</button>
        </form>
    </div>
    <aside class="hero-promo"><span class="promo-icon" aria-hidden="true">♨</span><span><strong>Sẵn sàng ra sân?</strong><small>Chọn địa điểm và xem khung giờ còn chỗ</small></span></aside>
    <div class="hero-indicators" role="group" aria-label="Chọn ảnh banner">
        <?php foreach (['Sân thành phố', 'Sân buổi tối', 'Sân bóng đá', 'Đa môn thể thao'] as $slideIndex => $slideLabel): ?>
            <button type="button" class="hero-indicator<?= $slideIndex === 0 ? ' is-active' : '' ?>" data-hero-slide="<?= $slideIndex ?>" aria-label="<?= e($slideLabel) ?>" aria-pressed="<?= $slideIndex === 0 ? 'true' : 'false' ?>"></button>
        <?php endforeach; ?>
    </div>
</section>

<section class="section-block sports-section" aria-labelledby="sports-title">
    <div class="section-heading content-width"><div><span class="eyebrow">TÌM MÔN BẠN YÊU THÍCH</span><h2 id="sports-title">Danh sách môn thể thao</h2><p>Chọn môn thể thao bạn yêu thích để tìm sân phù hợp</p></div><a class="section-link" href="<?= e(app_url('sports.php')) ?>">Xem tất cả <span aria-hidden="true">→</span></a></div>
        <div class="sports-grid content-width"><?php foreach ($sportsForHome as $sport): ?><a class="sport-card" href="<?= e(app_url($sport['href'])) ?>"><img src="<?= e(app_url('assets/images/' . $sport['image'])) ?>" alt="Minh họa <?= e($sport['name']) ?>" loading="lazy"><span class="sport-shade"></span><span class="sport-card-name"><span class="sport-dot" aria-hidden="true">●</span><?= e($sport['name']) ?><span class="sport-arrow" aria-hidden="true">→</span></span></a><?php endforeach; ?></div>
</section>

<section class="section-block courts-section" aria-labelledby="courts-title">
    <div class="content-width">
        <div class="section-heading"><div><span class="eyebrow">ĐỊA ĐIỂM THỂ THAO TẠI TP. HỒ CHÍ MINH</span><h2 id="courts-title">Khám phá sân thể thao</h2><p>Thông tin được tổng hợp từ các nguồn công khai; vui lòng xác nhận với đơn vị vận hành trước khi đặt sân.</p></div><a class="section-link" href="<?= e(app_url('courts.php')) ?>">Xem tất cả <span aria-hidden="true">→</span></a></div>
        <div class="courts-grid"><?php foreach ($courts as $court): $fallbackImage = sport_image_filename((string)$court['sport_name']); $rawImage = trim((string)($court['image'] ?? '')); $image = court_image_url($rawImage !== '' ? $rawImage : $fallbackImage, (string)$court['sport_name']); $courtUrl = app_url('court_detail.php?id=' . (int)$court['id']); ?><article class="featured-card"><a class="featured-image" href="<?= e($courtUrl) ?>" aria-label="Xem sân <?= e($court['name']) ?>"><img src="<?= e($image) ?>" alt="Minh họa <?= e($court['sport_name']) ?> tại <?= e($court['name']) ?>" loading="lazy"><span class="sport-label"><?= e($court['sport_name']) ?></span><span class="image-arrow" aria-hidden="true">→</span></a><div class="featured-body"><h3><?= e($court['name']) ?></h3><p class="court-address"><span aria-hidden="true">⌖</span> <?= e($court['address']) ?></p><?php if ((int)$court['review_count'] > 0): ?><p class="rating"><span aria-hidden="true">★</span> <?= e($court['avg_rating']) ?> <small>(<?= (int)$court['review_count'] ?> đánh giá)</small></p><?php else: ?><p class="rating-empty">Chưa có đánh giá</p><?php endif; ?><?php if (($court['court_count'] ?? null) !== null): ?><p class="court-address"><?= (int)$court['court_count'] ?> sân tại địa điểm</p><?php endif; ?><div class="featured-bottom"><p class="court-price"><?php if ((float)$court['price_per_hour'] > 0): ?>Giá tham khảo <?= money($court['price_per_hour']) ?><small>/giờ</small><?php else: ?>Giá chưa cập nhật<?php endif; ?></p><a class="book-button" href="<?= e($courtUrl) ?>">Đặt sân</a></div></div></article><?php endforeach; ?><?php if (!$courts): ?><div class="empty-state"><h3>Chưa có sân được công bố</h3><p>Vui lòng quay lại sau.</p></div><?php endif; ?></div>
    </div>
</section>

<section class="section-block benefits-section" id="gioi-thieu"><div class="content-width"><div class="section-heading centered-heading"><div><span class="eyebrow">VẬN ĐỘNG THẬT DỄ DÀNG</span><h2>Chơi thể thao theo cách của bạn</h2><p>Mọi thứ bạn cần để lên lịch cho một trận đấu tuyệt vời.</p></div></div><div class="benefits-grid"><article><span class="benefit-icon">⌕</span><h3>Tìm sân nhanh chóng</h3><p>Tìm theo môn thể thao và khu vực bạn muốn chơi.</p></article><article><span class="benefit-icon">♧</span><h3>Nhiều môn thể thao</h3><p>Khám phá các sân phù hợp với sở thích của bạn.</p></article><article><span class="benefit-icon">₫</span><h3>Giá cả minh bạch</h3><p>Xem giá thuê sân rõ ràng trước khi đặt.</p></article><article><span class="benefit-icon">✓</span><h3>Đặt sân thuận tiện</h3><p>Chọn khung giờ và quản lý đơn đặt dễ dàng.</p></article></div><div class="steps-row"><div><span>01</span><strong>Tìm sân</strong></div><i aria-hidden="true"></i><div><span>02</span><strong>Chọn ngày và khung giờ</strong></div><i aria-hidden="true"></i><div><span>03</span><strong>Xác nhận đặt sân</strong></div></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
