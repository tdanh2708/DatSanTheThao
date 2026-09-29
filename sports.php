<?php
require __DIR__ . '/config/database.php';

$pageTitle = 'Môn thể thao';
$sportDetails = [
    'bóng đá' => 'Sân bóng đá phù hợp cho các trận đấu phong trào, tập luyện và giao lưu cùng bạn bè.',
    'bóng rổ' => 'Khám phá các sân bóng rổ phù hợp cho tập luyện, thi đấu và vui chơi.',
    'tennis' => 'Tìm sân tennis phù hợp cho tập luyện cá nhân, đánh đôi và thi đấu.',
    'cầu lông' => 'Khám phá các sân cầu lông trong nhà với nhiều khung giờ đặt sân.',
    'pickleball' => 'Tìm sân pickleball phù hợp cho người mới bắt đầu, tập luyện và giao lưu.',
    'bóng chuyền' => 'Khám phá các sân bóng chuyền dành cho tập luyện và thi đấu theo nhóm.',
    'futsal' => 'Sân futsal dành cho các trận bóng trong nhà, tập luyện và thi đấu theo nhóm.',
    'bóng bàn' => 'Tìm địa điểm bóng bàn phù hợp cho tập luyện, giao lưu và thi đấu.',
];

$sports = $pdo->query("SELECT s.id, s.name, COUNT(c.id) AS venue_count
    FROM sports s
    LEFT JOIN courts c ON c.sport_id = s.id AND c.status = 'active'
    WHERE s.status = 'active'
    GROUP BY s.id, s.name
    ORDER BY FIELD(s.name, 'Bóng đá', 'Bóng rổ', 'Tennis', 'Cầu lông', 'Pickleball', 'Bóng chuyền', 'Futsal', 'Bóng bàn'), s.name")
    ->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="sports-page" aria-labelledby="sports-title">
    <div class="sports-page-heading">
        <span class="eyebrow">KHÁM PHÁ MÔN THỂ THAO</span>
        <h1 id="sports-title">Môn thể thao</h1>
        <p>Khám phá các môn thể thao phổ biến và tìm sân phù hợp tại TP. Hồ Chí Minh.</p>
        <p class="sports-page-subtitle">Chọn môn thể thao để xem địa điểm, mức giá và lịch sân còn trống.</p>
    </div>

    <div class="sports-catalog-grid">
        <?php foreach ($sports as $sport):
            $key = mb_strtolower($sport['name']);
            $image = sport_image_filename((string)$sport['name']);
            $description = $sportDetails[$key] ?? 'Tìm địa điểm phù hợp và chọn khung giờ chơi.';
            $courtsUrl = app_url('courts.php?sport_id=' . (int)$sport['id']);
        ?>
            <article class="sports-catalog-card">
                <a class="sports-catalog-image" href="<?= e($courtsUrl) ?>" aria-label="Xem sân <?= e($sport['name']) ?>">
                    <img src="<?= e(app_url('assets/images/' . $image)) ?>" alt="Minh họa <?= e($sport['name']) ?>" loading="lazy">
                    <span class="sports-catalog-image-shade" aria-hidden="true"></span>
                </a>
                <div class="sports-catalog-body">
                    <h2><?= e($sport['name']) ?></h2>
                    <p><?= e($description) ?></p>
                    <div class="sports-catalog-footer">
                        <span class="sports-catalog-count"><?= (int)$sport['venue_count'] ?> địa điểm</span>
                        <a class="sports-catalog-button" href="<?= e($courtsUrl) ?>">Xem sân</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
