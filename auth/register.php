<?php
require __DIR__ . '/../config/database.php';

if (current_user()) {
    redirect('/');
}

$errors = [];
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Họ và tên cần từ 2 đến 100 ký tự.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Email không hợp lệ.';
    }
    if ($phone !== '' && (!preg_match('/^[+0-9().\s-]{8,20}$/', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 8)) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Mật khẩu cần ít nhất 8 ký tự.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Hai mật khẩu không khớp.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email đã được sử dụng. Vui lòng đăng nhập hoặc dùng email khác.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone !== '' ? $phone : null, 'user']);
                $userId = (int)$pdo->lastInsertId();

                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $userId,
                    'full_name' => $name,
                    'email' => $email,
                    'role' => 'user',
                    'status' => 'active',
                ];

                flash('Đăng ký thành công. Chào mừng bạn đến với Sports Court Booking.');
                redirect('/');
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $errors[] = 'Email đã được sử dụng. Vui lòng dùng email khác.';
                } else {
                    $errors[] = 'Chưa thể tạo tài khoản lúc này. Vui lòng thử lại sau.';
                    error_log('Registration failed: ' . $exception->getMessage());
                }
            }
        }
    }
}

$pageTitle = 'Đăng ký';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-layout register-layout" aria-labelledby="register-title">
    <aside class="auth-art">
        <a class="auth-back" href="<?= e(app_url('/')) ?>">← Về trang chủ</a>
        <div class="auth-art-content">
            <span class="auth-emblem" aria-hidden="true">✦</span>
            <p class="auth-kicker">THAM GIA CỘNG ĐỒNG</p>
            <h2>Mỗi ngày thêm năng lượng</h2>
            <p>Tạo tài khoản để lưu thông tin và quản lý những lượt đặt sân của bạn.</p>
        </div>
        <span class="auth-art-caption">Tìm sân. Hẹn bạn. Cùng chơi.</span>
    </aside>
    <div class="auth-form-panel">
        <div class="auth-heading">
            <span class="auth-mobile-mark" aria-hidden="true">✦</span>
            <h1 id="register-title">Tạo tài khoản</h1>
            <p>Đăng ký để tìm kiếm và đặt sân thể thao thuận tiện</p>
        </div>
        <?php if ($errors): ?><div class="alert alert-danger auth-alert" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="<?= e(app_url('/auth/register.php')) ?>" class="auth-form">
            <?= csrf_field() ?>
            <label for="full_name" class="form-label">Họ và tên</label>
            <input id="full_name" class="form-control auth-input" name="full_name" value="<?= e($name) ?>" placeholder="Nguyễn Văn A" autocomplete="name" minlength="2" maxlength="100" required>
            <label for="register_email" class="form-label mt-3">Email</label>
            <input id="register_email" class="form-control auth-input" type="email" name="email" value="<?= e($email) ?>" placeholder="ban@example.com" autocomplete="email" maxlength="190" required>
            <label for="phone" class="form-label mt-3">Số điện thoại <span class="optional-label">(không bắt buộc)</span></label>
            <input id="phone" class="form-control auth-input" type="tel" name="phone" value="<?= e($phone) ?>" placeholder="09xxxxxxxx" autocomplete="tel" maxlength="20">
            <div class="register-password-grid">
                <div><label for="register_password" class="form-label mt-3">Mật khẩu</label><div class="password-wrap"><input id="register_password" class="form-control auth-input" type="password" name="password" placeholder="Tối thiểu 8 ký tự" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-password-toggle="register_password" aria-label="Hiện mật khẩu" aria-pressed="false">Hiện</button></div></div>
                <div><label for="confirm_password" class="form-label mt-3">Xác nhận mật khẩu</label><div class="password-wrap"><input id="confirm_password" class="form-control auth-input" type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-password-toggle="confirm_password" aria-label="Hiện mật khẩu" aria-pressed="false">Hiện</button></div></div>
            </div>
            <button class="auth-submit" type="submit">Đăng ký</button>
        </form>
        <p class="auth-switch">Đã có tài khoản? <a href="<?= e(app_url('/auth/login.php')) ?>">Đăng nhập</a></p>
        <a class="auth-home-link" href="<?= e(app_url('/')) ?>">← Quay về trang chủ</a>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
