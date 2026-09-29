<?php
require __DIR__ . '/../config/database.php';

if (current_user()) {
    redirect('/');
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Vui lòng nhập email hợp lệ và mật khẩu.';
    } else {
        $stmt = $pdo->prepare('SELECT id, full_name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;
            redirect($user['role'] === 'admin' ? '/admin/index.php' : '/');
        }
        $error = 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.';
    }
}

$pageTitle = 'Đăng nhập';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-layout" aria-labelledby="login-title">
    <aside class="auth-art">
        <a class="auth-back" href="<?= e(app_url('/')) ?>">← Về trang chủ</a>
        <div class="auth-art-content">
            <span class="auth-emblem" aria-hidden="true">✦</span>
            <p class="auth-kicker">SPORTS COURT BOOKING</p>
            <h2>Sẵn sàng cho trận đấu tiếp theo?</h2>
            <p>Tìm sân yêu thích, chọn khung giờ phù hợp và cùng bạn bè ra sân.</p>
        </div>
        <span class="auth-art-caption">Vận động mỗi ngày, vui khỏe mỗi giờ.</span>
    </aside>
    <div class="auth-form-panel">
        <div class="auth-heading">
            <span class="auth-mobile-mark" aria-hidden="true">✦</span>
            <h1 id="login-title">Chào mừng trở lại</h1>
            <p>Đăng nhập để tiếp tục đặt sân thể thao</p>
        </div>
        <?php if ($error !== ''): ?><div class="alert alert-danger auth-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(app_url('/auth/login.php')) ?>" class="auth-form">
            <?= csrf_field() ?>
            <label for="email" class="form-label">Email</label>
            <input id="email" class="form-control auth-input" type="email" name="email" value="<?= e($email) ?>" placeholder="ban@example.com" autocomplete="email" required maxlength="190">
            <label for="password" class="form-label mt-3">Mật khẩu</label>
            <div class="password-wrap">
                <input id="password" class="form-control auth-input" type="password" name="password" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Hiện mật khẩu" aria-pressed="false">Hiện</button>
            </div>
            <button class="auth-submit" type="submit">Đăng nhập</button>
        </form>
        <p class="auth-switch">Chưa có tài khoản? <a href="<?= e(app_url('/auth/register.php')) ?>">Đăng ký ngay</a></p>
        <a class="auth-home-link" href="<?= e(app_url('/')) ?>">← Quay về trang chủ</a>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
