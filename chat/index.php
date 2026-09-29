<?php
require __DIR__ . '/../config/database.php';
require_login();
$pageTitle='Chat hỗ trợ';
$disableSupportWidget=true;
require __DIR__ . '/../includes/header.php';
?>
<section class="chat-page card card-body" data-chat data-api="<?= e(app_url('chat/api.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-role="user" data-prefill="<?= e((string)($_GET['prefill']??'')) ?>">
<h1 class="h3">Hỗ trợ khách hàng</h1><p class="text-secondary">Sports Court Booking · Nhắn tin trực tiếp với quản trị viên.</p>
<div class="chat-thread" data-chat-thread aria-live="polite"></div>
<form class="chat-compose" data-chat-form><label class="chat-image-pick" title="Chọn ảnh" aria-label="Chọn ảnh"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="3.5" width="17" height="17" rx="3" stroke="currentColor" stroke-width="1.8"/><circle cx="8.5" cy="8.5" r="1.6" fill="currentColor"/><path d="m5.5 18 5.2-5.2a1.8 1.8 0 0 1 2.55 0L15 14.5l1.5-1.5a1.7 1.7 0 0 1 2.4 0l1.1 1.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><input type="file" name="image" accept="image/jpeg,image/png,image/webp" hidden data-chat-image></label><span data-chat-preview></span><textarea name="message" rows="2" maxlength="4000" class="form-control" placeholder="Nhập tin nhắn..." aria-label="Nhập tin nhắn..."></textarea><button class="btn btn-success">Gửi</button></form><div class="small text-danger" data-chat-error></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
