</main>
<footer class="site-footer" id="lien-he">
    <div class="footer-inner">
        <div class="footer-about"><a class="brand footer-brand" href="<?= e(app_url('/')) ?>"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="7"/><path d="M20 2c-5 0-8 6-5 10l5 8 5-8c3-4 0-10-5-10ZM38 20c0-5-6-8-10-5l-8 5 8 5c4 3 10 0 10-5ZM20 38c5 0 8-6 5-10l-5-8-5 8c-3 4 0 10 5 10ZM2 20c0 5 6 8 10 5l8-5-8-5C8 12 2 15 2 20Z"/></svg></span><span>Sports Court Booking</span></a><p>Tìm sân phù hợp, chọn khung giờ và sẵn sàng vận động cùng bạn bè.</p></div>
        <div><h2>Liên kết nhanh</h2><a href="<?= e(app_url('/courts.php')) ?>">Danh sách sân</a><a href="<?= e(app_url('/sports.php')) ?>">Môn thể thao</a><a href="<?= e(app_url('/auth/register.php')) ?>">Tạo tài khoản</a></div>
        <div><h2>Thông tin</h2><a href="<?= e(app_url('/#gioi-thieu')) ?>">Về chúng tôi</a><a href="<?= e(app_url('/user/bookings.php')) ?>">Đơn đặt sân</a><span>TP. Hồ Chí Minh</span></div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Sports Court Booking</span><span>Đồ án quản lý đặt sân thể thao</span></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $mainScriptPath = dirname(__DIR__) . '/assets/js/main.js'; $mainScriptVersion = is_file($mainScriptPath) ? (string)filemtime($mainScriptPath) : '1'; ?>
<script src="<?= e(app_url('assets/js/main.js?v=' . $mainScriptVersion)) ?>"></script>
</body>
</html>
