<?php
require __DIR__ . '/../config/database.php';
require_admin();
$stats = [
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
    'courts' => (int)$pdo->query("SELECT COUNT(*) FROM courts WHERE status='active'")->fetchColumn(),
    'bookings' => (int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
    'confirmed' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetchColumn(),
    'completed' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn(),
    'revenue' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn(),
    'payment_pending' => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn(),
    'messages_unread' => (int)$pdo->query("SELECT COUNT(*) FROM messages m JOIN users u ON u.id=m.sender_id WHERE u.role='user' AND m.is_read=0")->fetchColumn(),
    'reviews' => (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
    'revenue_today' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND DATE(paid_at)=CURDATE()")->fetchColumn(),
    'revenue_month' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND YEAR(paid_at)=YEAR(CURDATE()) AND MONTH(paid_at)=MONTH(CURDATE())")->fetchColumn(),
];
$recentBookings = $pdo->query("SELECT b.id,b.booking_date,b.total_amount,b.status,u.full_name,c.name court_name
    FROM bookings b JOIN users u ON u.id=b.user_id JOIN courts c ON c.id=b.court_id
    ORDER BY b.created_at DESC LIMIT 8")->fetchAll();
$popularCourts = $pdo->query("SELECT c.id,c.name,s.name sport_name,COUNT(DISTINCT CASE WHEN b.status IN ('pending','confirmed','completed') THEN b.id END) booking_count,COALESCE(SUM(CASE WHEN p.status='paid' THEN p.amount ELSE 0 END),0) revenue
    FROM courts c JOIN sports s ON s.id=c.sport_id LEFT JOIN bookings b ON b.court_id=c.id AND b.status IN ('pending','confirmed','completed')
    LEFT JOIN payments p ON p.booking_id=b.id WHERE c.status='active' GROUP BY c.id,c.name,s.name ORDER BY booking_count DESC,c.name LIMIT 5")->fetchAll();
$recentMessages=$pdo->query("SELECT m.conversation_id,m.message,m.image_path,m.created_at,u.full_name FROM messages m JOIN conversations c ON c.id=m.conversation_id JOIN users u ON u.id=c.user_id WHERE u.role='user' ORDER BY m.id DESC LIMIT 5")->fetchAll();
$revenueBySport=$pdo->query("SELECT s.name,COALESCE(SUM(CASE WHEN p.status='paid' THEN p.amount ELSE 0 END),0) revenue FROM sports s LEFT JOIN courts c ON c.sport_id=s.id LEFT JOIN bookings b ON b.court_id=c.id LEFT JOIN payments p ON p.booking_id=b.id GROUP BY s.id,s.name ORDER BY revenue DESC,s.name")->fetchAll();
$pageTitle = 'Bảng điều khiển';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<h1 class="mb-4">Bảng điều khiển</h1>
<div class="admin-stat-grid">
    <?php foreach ([['users','Tổng người dùng','admin/users.php'],['courts','Sân đang hoạt động','admin/courts.php'],['bookings','Tổng đơn đặt','admin/bookings.php'],['pending','Chờ xác nhận','admin/bookings.php'],['confirmed','Đã xác nhận','admin/bookings.php'],['completed','Đã hoàn thành','admin/bookings.php'],['payment_pending','Thanh toán chờ xử lý','admin/payments.php'],['messages_unread','Tin nhắn chưa đọc','admin/messages.php'],['reviews','Tổng đánh giá','admin/reviews.php']] as [$key,$label,$url]): ?>
        <a class="admin-stat-card" href="<?= e(app_url($url)) ?>"><span><?= e($label) ?></span><strong><?= $stats[$key] ?></strong></a>
    <?php endforeach; ?>
    <article class="admin-stat-card admin-stat-revenue"><span>Doanh thu đã thanh toán</span><strong><?= money($stats['revenue']) ?></strong></article>
    <article class="admin-stat-card"><span>Doanh thu hôm nay</span><strong><?= money($stats['revenue_today']) ?></strong></article><article class="admin-stat-card"><span>Doanh thu tháng này</span><strong><?= money($stats['revenue_month']) ?></strong></article>
</div>
<div class="row g-4 mt-1">
    <div class="col-lg-8"><div class="card card-body h-100"><h2 class="h5">Đặt sân gần đây</h2><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Mã đơn</th><th>Người đặt</th><th>Sân / ngày</th><th>Tiền</th><th>Trạng thái</th></tr></thead><tbody>
        <?php foreach ($recentBookings as $booking): ?><tr><td>#<?= (int)$booking['id'] ?></td><td><?= e($booking['full_name']) ?></td><td><?= e($booking['court_name']) ?><br><small><?= e(date('d/m/Y',strtotime($booking['booking_date']))) ?></small></td><td><?= money($booking['total_amount']) ?></td><td><?= e(booking_status_label((string)$booking['status'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$recentBookings): ?><tr><td colspan="5">Chưa có đơn đặt.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card card-body h-100"><h2 class="h5">Sân được đặt nhiều</h2><ol class="admin-popular-list"><?php foreach ($popularCourts as $court): ?><li><strong><?= e($court['name']) ?></strong><span><?= e($court['sport_name']) ?> · <?= (int)$court['booking_count'] ?> lượt · <?= money($court['revenue']) ?></span></li><?php endforeach; ?></ol></div></div>
    <div class="col-lg-6"><div class="card card-body"><h2 class="h5">Tin nhắn mới</h2><?php foreach($recentMessages as $message): ?><p><a href="<?= e(app_url('admin/messages.php?conversation_id='.(int)$message['conversation_id'])) ?>"><strong><?= e($message['full_name']) ?></strong> · <?= e($message['message']?:'Đã gửi ảnh') ?></a><small class="d-block text-secondary"><?= e(date('d/m H:i',strtotime($message['created_at']))) ?></small></p><?php endforeach; ?><?php if(!$recentMessages): ?><p class="text-secondary">Chưa có tin nhắn hỗ trợ.</p><?php endif; ?></div></div>
    <div class="col-lg-6"><div class="card card-body"><h2 class="h5">Doanh thu theo môn</h2><?php foreach($revenueBySport as $sportRevenue): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e($sportRevenue['name']) ?></span><strong><?= money($sportRevenue['revenue']) ?></strong></div><?php endforeach; ?></div></div>
</div>
</section></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
