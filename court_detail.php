<?php
require __DIR__ . '/config/database.php';
expire_pending_bank_transfers($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit('Không tìm thấy sân.');
}

$statement = $pdo->prepare("SELECT c.*, s.name AS sport_name
    FROM courts c JOIN sports s ON s.id = c.sport_id
    WHERE c.id = ? AND c.status = 'active' AND s.status = 'active'");
$statement->execute([$id]);
$court = $statement->fetch();
if (!$court) {
    http_response_code(404);
    exit('Không tìm thấy sân.');
}

$today = date('Y-m-d');
$date = (string)($_GET['date'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))
    || $date < $today) {
    $date = $today;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    require_login();
    verify_csrf();

    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
    $comment = trim((string)($_POST['comment'] ?? ''));
    if ($rating === false || $rating < 1 || $rating > 5 || $comment === '' || mb_strlen($comment) > 1000) {
        flash('Chọn số sao từ 1 đến 5 và nhập nhận xét tối đa 1.000 ký tự.', 'danger');
        redirect('/court_detail.php?id=' . $id . '#review-form');
    }

    $eligible = $pdo->prepare("SELECT b.id FROM bookings b
        WHERE b.user_id = ? AND b.court_id = ? AND b.status = 'completed'
          AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.booking_id = b.id)
        ORDER BY b.booking_date DESC, b.id DESC LIMIT 1");
    $eligible->execute([(int)current_user()['id'], $id]);
    $bookingId = $eligible->fetchColumn();
    if (!$bookingId) {
        flash('Bạn cần có lượt đặt sân đã hoàn thành và chưa được đánh giá.', 'warning');
        redirect('/court_detail.php?id=' . $id . '#review-form');
    }

    try {
        $insert = $pdo->prepare('INSERT INTO reviews (user_id, court_id, booking_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([(int)current_user()['id'], $id, (int)$bookingId, $rating, $comment]);
        $reviewId = (int)$pdo->lastInsertId();
        notify_user($pdo, 'admin', null, 'review_new', 'Có đánh giá mới cho sân #' . $id, 'admin/reviews.php');
        admin_log($pdo, 'review_created', 'review', $reviewId, 'Khách gửi đánh giá cho sân #' . $id);
        flash('Cảm ơn bạn đã gửi đánh giá.');
    } catch (PDOException $exception) {
        flash('Chưa thể lưu đánh giá. Vui lòng tải lại trang và thử lại.', 'danger');
    }
    redirect('/court_detail.php?id=' . $id . '#reviews-title');
}

$statement = $pdo->prepare("SELECT ts.id, ts.start_time, ts.end_time, c.court_count,
        COALESCE(c.court_count, 1) AS capacity,
        GREATEST(COALESCE(c.court_count, 1) - COUNT(DISTINCT CASE
            WHEN b.id IS NOT NULL THEN bd.court_number END), 0) AS available_courts
    FROM time_slots ts JOIN courts c ON c.id = ?
    LEFT JOIN booking_details bd ON bd.court_id = c.id AND bd.booking_date = ? AND bd.slot_active = 1
    LEFT JOIN time_slots booked_slot ON booked_slot.id = bd.time_slot_id
        AND booked_slot.start_time < ts.end_time AND booked_slot.end_time > ts.start_time
    LEFT JOIN bookings b ON b.id = bd.booking_id AND booked_slot.id IS NOT NULL
        AND b.status IN ('pending','confirmed','completed')
    WHERE ts.status = 'active' AND ts.start_time >= '06:00:00' AND ts.end_time <= '22:00:00'
    GROUP BY ts.id, ts.start_time, ts.end_time, c.court_count
    ORDER BY ts.start_time");
$statement->execute([$id, $date]);
$slots = $statement->fetchAll();

$statement = $pdo->prepare('SELECT ROUND(AVG(rating), 1) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE court_id = ?');
$statement->execute([$id]);
$reviewSummary = $statement->fetch();
$reviewCount = (int)$reviewSummary['review_count'];

$reviewPage = max(1, (int)($_GET['review_page'] ?? 1));
$reviewsPerPage = 10;
$reviewPages = max(1, (int)ceil($reviewCount / $reviewsPerPage));
$reviewPage = min($reviewPage, $reviewPages);
$reviewOffset = ($reviewPage - 1) * $reviewsPerPage;
$statement = $pdo->prepare('SELECT r.rating, r.comment, r.created_at, u.full_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.court_id = ? ORDER BY r.created_at DESC LIMIT ' . $reviewsPerPage . ' OFFSET ' . $reviewOffset);
$statement->execute([$id]);
$reviews = $statement->fetchAll();

$eligible = $pdo->prepare("SELECT b.id FROM bookings b
    WHERE b.user_id = ? AND b.court_id = ? AND b.status = 'completed'
      AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.booking_id = b.id)
    LIMIT 1");
if (current_user()) {
    $eligible->execute([(int)current_user()['id'], $id]);
    $canReview = (bool)$eligible->fetchColumn();
} else {
    $canReview = false;
}

$bookingCountStatement = $pdo->prepare("SELECT COUNT(DISTINCT b.id) FROM bookings b WHERE b.court_id = ? AND b.status IN ('pending','confirmed','completed')");
$bookingCountStatement->execute([$id]);
$bookingCount = (int)$bookingCountStatement->fetchColumn();

$pageTitle = $court['name'];
require __DIR__ . '/includes/header.php';

$descriptionParts = preg_split('/(?<=[.!?;])\s+/u', trim((string)($court['description'] ?? ''))) ?: [];
$descriptionParts = array_filter($descriptionParts, static fn(string $part): bool => !preg_match('/giá|nguồn|google maps|website|danh bạ|danh sách|được liệt kê|công bố|chưa được|chưa xác minh|chưa xác nhận|tham khảo|ước tính|vui lòng liên hệ/iu', $part));
$publicDescription = rtrim(trim(implode(' ', $descriptionParts)), " \t\n\r\0\x0B;,. ");
$rawImage = trim((string)($court['image'] ?? ''));
$image = court_image_url($rawImage !== '' ? $rawImage : sport_image_filename((string)$court['sport_name']), (string)$court['sport_name']);
?>
<div class="court-detail-page">
    <nav class="detail-breadcrumb" aria-label="Đường dẫn">
        <a href="<?= e(app_url('/')) ?>">Trang chủ</a><span aria-hidden="true">›</span>
        <a href="<?= e(app_url('courts.php?sport_id=' . (int)$court['sport_id'])) ?>"><?= e($court['sport_name']) ?></a><span aria-hidden="true">›</span>
        <span aria-current="page"><?= e($court['name']) ?></span>
    </nav>

    <section class="court-detail-overview" aria-labelledby="court-title">
        <div class="court-detail-photo"><img src="<?= e($image) ?>" alt="Sân <?= e($court['sport_name']) ?> - <?= e($court['name']) ?>"></div>
        <div class="court-detail-info">
            <span class="detail-sport-tag"><?= e($court['sport_name']) ?></span>
            <h1 id="court-title"><?= e($court['name']) ?></h1>
            <a class="detail-rating-link" href="#reviews-title" aria-label="Xem đánh giá">
                <?php if ($reviewCount): ?><span aria-hidden="true">★</span> <?= e($reviewSummary['average_rating']) ?> <small>(<?= $reviewCount ?> đánh giá)</small><?php else: ?><span>Chưa có đánh giá</span><?php endif; ?>
            </a>
            <p class="detail-address"><span aria-hidden="true">📍</span> <?= e($court['address']) ?></p>
            <div class="detail-facts">
                <div><span>Giá sân</span><strong><?= (float)$court['price_per_hour'] > 0 ? money($court['price_per_hour']) . ' / giờ' : 'Liên hệ địa điểm' ?></strong></div>
                <?php if ($court['court_count'] !== null): ?><div><span>Số sân</span><strong><?= (int)$court['court_count'] ?> sân</strong></div><?php endif; ?>
                <?php if (current_user() && current_user()['role']==='user'): ?><button class="btn btn-outline-success" type="button" data-chat-prefill="<?= e('Tôi cần hỗ trợ về sân: '.$court['name']) ?>">Hỏi Admin</button><?php endif; ?>
                <div><span>Lượt đặt</span><strong><?= $bookingCount ?></strong></div>
                <div><span>Khung đặt</span><strong>06:00 – 22:00</strong></div>
            </div>
        </div>
    </section>

    <section class="detail-description-card">
        <span class="eyebrow">THÔNG TIN SÂN</span>
        <h2>Về địa điểm này</h2>
        <p><?= $publicDescription !== '' ? nl2br(e($publicDescription)) : 'Xem giá sân và lịch còn trống để chọn khung giờ phù hợp.' ?></p>
    </section>

    <section class="detail-schedule-card detail-schedule-section" aria-labelledby="schedule-title">
        <div class="detail-section-heading">
            <div><span class="eyebrow">LỊCH ĐẶT SÂN</span><h2 id="schedule-title">Chọn ngày và khung giờ</h2></div>
            <form method="get" class="detail-date-form">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <label for="booking-date">Ngày đặt</label>
                <div class="detail-date-control"><input id="booking-date" type="date" name="date" min="<?= e($today) ?>" value="<?= e($date) ?>" required><button type="submit">Xem lịch</button></div>
            </form>
        </div>
        <p class="detail-schedule-note">Chọn một hoặc nhiều khung giờ còn trống. Lịch hiển thị từ 06:00 đến 22:00.</p>
        <div class="slot-legend" aria-label="Chú giải trạng thái khung giờ">
            <span><i class="legend-free"></i>Còn trống</span><span><i class="legend-selected"></i>Đang chọn</span><span><i class="legend-full"></i>Đã đặt</span><span><i class="legend-past"></i>Đã qua</span>
        </div>

        <form method="post" action="<?= e(app_url('booking_process.php')) ?>" class="detail-booking-form" data-booking-form data-price-per-hour="<?= e($court['price_per_hour']) ?>">
            <?php if (current_user()): ?>
                <input type="hidden" name="court_id" value="<?= (int)$id ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="booking_date" value="<?= e($date) ?>">
                <fieldset class="payment-method-picker"><legend class="detail-note-label">Chọn phương thức thanh toán</legend>
                    <label class="payment-method-option"><input type="radio" name="payment_method" value="bank_transfer" required><span><strong>Chuyển khoản ngân hàng</strong><small>QR được tạo theo tổng tiền và mã booking.</small></span></label>
                    <label class="payment-method-option"><input type="radio" name="payment_method" value="venue" required checked><span><strong>Thanh toán trực tiếp tại sân</strong><small>Thanh toán khi đến sân; trạng thái cập nhật sau khi thu tiền.</small></span></label>
                </fieldset>
            <?php endif; ?>
            <div class="slot-grid">
                <?php foreach ($slots as $slot):
                    $isFull = (int)$slot['available_courts'] === 0;
                    $isPast = $date === $today && $slot['start_time'] <= date('H:i:s');
                    $isUnavailable = $isFull || $isPast;
                    $duration = max(0, (strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 3600);
                    $slotClass = $isPast ? 'is-past' : ($isFull ? 'is-full' : 'is-available');
                ?>
                    <label class="slot-option <?= $slotClass ?>">
                        <?php if (current_user()): ?><input type="checkbox" name="slots[]" value="<?= (int)$slot['id'] ?>" data-slot-hours="<?= e($duration) ?>" <?= $isUnavailable ? 'disabled' : '' ?>><?php endif; ?>
                        <span class="slot-time"><?= e(substr($slot['start_time'], 0, 5)) ?> – <?= e(substr($slot['end_time'], 0, 5)) ?></span>
                        <span class="slot-status"><?php if ($isPast): ?>Đã qua<?php elseif ($isFull): ?>Đã đặt<?php else: ?>Còn trống<?php endif; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if (!$slots): ?><p class="detail-empty-slots">Chưa có khung giờ hoạt động.</p><?php endif; ?>
            <?php if (current_user()): ?>
                <label class="detail-note-label" for="booking-note">Ghi chú <span>(không bắt buộc)</span></label>
                <textarea class="form-control" id="booking-note" name="note" maxlength="500" rows="2" placeholder="Nhập ghi chú cho địa điểm"></textarea>
                <div class="booking-summary"><span>Tổng tạm tính <small data-booking-hours>0 giờ</small></span><strong data-booking-total><?= money(0) ?></strong></div>
                <button class="detail-submit-button" type="submit" data-booking-submit disabled>Tiếp tục đặt sân</button>
            <?php else: ?>
                <a class="detail-submit-button" href="<?= e(app_url('auth/login.php')) ?>">Đăng nhập để đặt sân</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="detail-reviews" aria-labelledby="reviews-title">
        <div class="review-heading">
            <div><span class="eyebrow">TRẢI NGHIỆM NGƯỜI DÙNG</span><h2 id="reviews-title">Đánh giá khách hàng</h2></div>
            <div class="review-average">
                <?php if ($reviewCount): ?><strong><?= e($reviewSummary['average_rating']) ?> <span>/ 5</span></strong><span class="review-stars" aria-label="<?= e($reviewSummary['average_rating']) ?> trên 5 sao"><?= str_repeat('★', (int)round((float)$reviewSummary['average_rating'])) ?><span class="review-count"><?= $reviewCount ?> đánh giá</span></span><?php else: ?><strong>Chưa có đánh giá</strong><?php endif; ?>
            </div>
        </div>

        <div class="review-content-grid">
            <div class="review-list">
                <?php foreach ($reviews as $review): ?>
                    <article class="detail-review">
                        <div class="review-person"><strong><?= e($review['full_name']) ?></strong><time datetime="<?= e($review['created_at']) ?>"><?= e(date('d/m/Y', strtotime($review['created_at']))) ?></time></div>
                        <div class="review-stars" aria-label="<?= (int)$review['rating'] ?> trên 5 sao"><?= str_repeat('★', (int)$review['rating']) ?><span class="review-stars-muted"><?= str_repeat('☆', 5 - (int)$review['rating']) ?></span></div>
                        <p><?= e($review['comment']) ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (!$reviews): ?><p class="detail-empty-reviews">Chưa có đánh giá. Hãy chia sẻ trải nghiệm sau khi hoàn thành lượt đặt sân.</p><?php endif; ?>
                <?php if ($reviewPages > 1): ?><nav class="review-pagination" aria-label="Phân trang đánh giá"><?php for ($page = 1; $page <= $reviewPages; $page++): ?><a class="<?= $page === $reviewPage ? 'is-current' : '' ?>" href="<?= e(app_url('court_detail.php?id=' . $id . '&review_page=' . $page . '#reviews-title')) ?>"><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
            </div>

            <div class="review-form-card" id="review-form">
                <h3>Đánh giá của bạn</h3>
                <?php if (!current_user()): ?>
                    <p>Đăng nhập và hoàn thành lượt đặt sân để gửi đánh giá.</p>
                    <a class="review-login-link" href="<?= e(app_url('auth/login.php')) ?>">Đăng nhập</a>
                <?php elseif ($canReview): ?>
                    <form method="post" action="<?= e(app_url('court_detail.php?id=' . $id . '#review-form')) ?>" class="review-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="review">
                        <fieldset class="star-picker"><legend>Chọn số sao</legend>
                            <div class="star-options" role="radiogroup" aria-label="Đánh giá từ 1 đến 5 sao">
                                <?php for ($star = 5; $star >= 1; $star--): ?><input id="rating-<?= $star ?>" type="radio" name="rating" value="<?= $star ?>" required><label for="rating-<?= $star ?>" aria-label="<?= $star ?> sao">★</label><?php endfor; ?>
                            </div>
                            <span class="rating-hint" data-rating-hint>Chọn mức đánh giá</span>
                        </fieldset>
                        <label for="review-comment">Nhận xét</label>
                        <textarea id="review-comment" name="comment" maxlength="1000" rows="4" required placeholder="Chia sẻ trải nghiệm của bạn..."></textarea>
                        <button class="review-submit" type="submit">Gửi đánh giá</button>
                    </form>
                <?php else: ?>
                    <p>Bạn có thể đánh giá sau khi một lượt đặt sân của mình được hoàn thành.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
