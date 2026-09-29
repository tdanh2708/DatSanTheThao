<?php
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'Sports Court Booking';
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isHome = $currentPage === 'index.php';
$isAdminUser = (current_user()['role'] ?? '') === 'admin';
$isAdminRoute = $isAdminUser && str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/');
$stylePath = dirname(__DIR__) . '/assets/css/style.css';
$styleVersion = is_file($stylePath) ? (string)filemtime($stylePath) : '1';
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#056044">
    <title><?= e($pageTitle) ?> | Sports Court Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css?v=' . $styleVersion)) ?>">
</head>
<body data-app-base="<?= e(app_base_path()) ?>" data-user-id="<?= (int)(current_user()['id'] ?? 0) ?>">
<header class="site-header">
    <nav class="site-nav" aria-label="Điều hướng chính">
        <div class="nav-inner">
            <a class="brand" href="<?= e(app_url('/')) ?>" aria-label="Sports Court Booking - Trang chủ">
                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="7"/><path d="M20 2c-5 0-8 6-5 10l5 8 5-8c3-4 0-10-5-10ZM38 20c0-5-6-8-10-5l-8 5 8 5c4 3 10 0 10-5ZM20 38c5 0 8-6 5-10l-5-8-5 8c-3 4 0 10 5 10ZM2 20c0 5 6 8 10 5l8-5-8-5C8 12 2 15 2 20Z"/></svg></span>
                <span>Sports Court Booking</span>
            </a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-menu" aria-label="Mở menu"><span></span><span></span><span></span></button>
            <div class="nav-menu" id="site-menu">
                <div class="nav-links">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= e(app_url('/')) ?>">Trang chủ</a>
                    <a class="nav-link <?= $currentPage === 'sports.php' ? 'active' : '' ?>" href="<?= e(app_url('/sports.php')) ?>">Môn thể thao</a>
                    <a class="nav-link <?= in_array($currentPage, ['courts.php', 'court_detail.php'], true) ? 'active' : '' ?>" href="<?= e(app_url('/courts.php')) ?>">Danh sách sân</a>
                    <a class="nav-link" href="<?= e(app_url('/#gioi-thieu')) ?>">Giới thiệu</a>
                    <?php if ($isAdminUser): ?><a class="nav-link <?= $isAdminRoute ? 'active' : '' ?>" href="<?= e(app_url('/admin/index.php')) ?>">Quản trị</a><?php endif; ?>
                    <?php if (current_user() && current_user()['role'] === 'user'): ?><a class="nav-link <?= in_array($currentPage, ['bookings.php','booking_detail.php'], true) ? 'active' : '' ?>" href="<?= e(app_url('/user/bookings.php')) ?>">Lịch sử đặt sân</a><?php endif; ?>
                </div>
                <div class="nav-account">
                    <?php if (current_user()): ?>
                        <details class="account-menu"><summary><?= e(current_user()['full_name']) ?></summary><div class="account-menu-dropdown">
                            <a href="<?= e(app_url('/user/profile.php')) ?>">Tài khoản</a>
                            <?php if (current_user()['role'] === 'user'): ?><a href="<?= e(app_url('/user/reviews.php')) ?>">Đánh giá của tôi</a><a href="<?= e(app_url('/chat/')) ?>">Chat hỗ trợ</a><?php endif; ?>
                            <?php if (current_user()['role'] === 'admin'): ?><a href="<?= e(app_url('/admin/index.php')) ?>">Quản trị</a><?php endif; ?>
                            <a href="<?= e(app_url('/auth/logout.php')) ?>">Đăng xuất</a>
                        </div></details>
                    <?php else: ?>
                        <a class="nav-link" href="<?= e(app_url('/auth/login.php')) ?>">Đăng nhập</a>
                        <a class="nav-account-button" href="<?= e(app_url('/auth/register.php')) ?>">Đăng ký</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
<?php if (current_user() && current_user()['role'] === 'user' && empty($disableSupportWidget)): ?>
<button class="support-float" type="button" data-chat-toggle aria-expanded="false" aria-controls="support-chat-widget" data-unread-badge><span>💬</span> Chat hỗ trợ <b>0</b></button>
<section class="support-chat-widget" id="support-chat-widget" data-chat data-widget="true" data-api="<?= e(app_url('chat/api.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" hidden>
    <header><div><strong>Hỗ trợ khách hàng</strong><small>Sports Court Booking</small></div><button type="button" data-chat-close aria-label="Đóng cửa sổ chat">×</button></header>
    <div class="chat-thread" data-chat-thread aria-live="polite"></div>
    <form class="chat-compose" data-chat-form><label class="chat-image-pick" title="Chọn ảnh" aria-label="Chọn ảnh"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="3.5" width="17" height="17" rx="3" stroke="currentColor" stroke-width="1.8"/><circle cx="8.5" cy="8.5" r="1.6" fill="currentColor"/><path d="m5.5 18 5.2-5.2a1.8 1.8 0 0 1 2.55 0L15 14.5l1.5-1.5a1.7 1.7 0 0 1 2.4 0l1.1 1.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><input type="file" name="image" accept="image/jpeg,image/png,image/webp" hidden data-chat-image></label><span data-chat-preview></span><textarea name="message" rows="2" maxlength="4000" class="form-control" placeholder="Nhập tin nhắn..." aria-label="Nhập tin nhắn..."></textarea><button class="btn btn-success">Gửi</button></form>
    <div class="small text-danger" data-chat-error></div><a class="support-chat-full" href="<?= e(app_url('chat/')) ?>">Mở trang chat đầy đủ</a>
</section>
<?php endif; ?>
<?php if ($isHome): ?><main class="home-main"><?php else: ?><main class="container page-main"><?php endif; ?>
<?php if (!empty($_SESSION['flash'])): [$msg, $type] = $_SESSION['flash']; unset($_SESSION['flash']); ?><div class="container flash-wrap"><div class="alert alert-<?= e($type) ?> alert-dismissible fade show"><?= e($msg) ?><button class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div></div><?php endif; ?>
