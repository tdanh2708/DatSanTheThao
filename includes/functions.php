<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function app_base_path(): string
{
    static $basePath = null;
    if ($basePath !== null) return $basePath;
    $projectRoot = realpath(dirname(__DIR__));
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($projectRoot === false || $documentRoot === false) return $basePath = '';
    $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
    if (strcasecmp($projectRoot, $documentRoot) === 0) return $basePath = '';
    $prefix = $documentRoot . '/';
    if (strncasecmp($projectRoot, $prefix, strlen($prefix)) === 0) {
        return $basePath = '/' . trim(substr($projectRoot, strlen($prefix)), '/');
    }
    return $basePath = '';
}
function app_url(string $path = ''): string
{
    if (preg_match('#^(?:https?:)?//#i', $path)) return $path;
    return app_base_path() . '/' . ltrim($path, '/');
}
function court_image_url(mixed $image, string $sportName = ''): string
{
    $path = trim((string)$image);
    if ($path === '') return app_url('assets/images/other.png');
    if (preg_match('#^https?://#i', $path)) return $path;
    if (str_starts_with($path, '/')) $path = ltrim($path, '/');
    if (str_starts_with($path, 'uploads/')) return app_url($path);
    if (!str_starts_with($path, 'assets/images/')) $path = 'assets/images/' . $path;

    // Court records use these shared illustration files as placeholders.
    // Match them to the court's sport so Futsal/table tennis do not show a generic image.
    $assetName = strtolower(pathinfo($path, PATHINFO_FILENAME));
    $sharedIllustrations = ['badminton', 'basketball', 'football', 'futsal', 'other', 'pickleball', 'table-tennis', 'tennis', 'volleyball'];
    if ($sportName !== '' && in_array($assetName, $sharedIllustrations, true)) {
        $path = 'assets/images/' . sport_image_filename($sportName);
    }

    // Some existing court rows refer to .svg files whose contents are actually PNG.
    // Keep those rows valid without changing the database.
    $assetPath = dirname(__DIR__) . '/' . $path;
    if (!is_file($assetPath) && preg_match('/\.svg$/i', $path)) {
        $pngPath = preg_replace('/\.svg$/i', '.png', $path);
        if (is_string($pngPath) && is_file(dirname(__DIR__) . '/' . $pngPath)) $path = $pngPath;
    }
    return app_url($path);
}
function redirect(string $url): never { header('Location: ' . app_url($url)); exit; }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = [$message, $type]; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { $submitted=$_POST['csrf']??'';if(!is_string($submitted)||!hash_equals(csrf_token(),$submitted)){http_response_code(400);exit('Yêu cầu không hợp lệ.');} }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!current_user()) { flash('Vui lòng đăng nhập để tiếp tục.', 'warning'); redirect('/auth/login.php'); } }
function require_admin(): void { require_login(); if ((current_user()['role'] ?? '') !== 'admin') { http_response_code(403); exit('Bạn không có quyền truy cập.'); } }
function money(mixed $amount): string { return number_format((float)$amount, 0, ',', '.') . ' đ'; }
function sport_image_filename(string $sportName): string
{
    $name = mb_strtolower(trim($sportName), 'UTF-8');
    $name = strtr($name, [
        'á'=>'a','à'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a','â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
        'é'=>'e','è'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
        'í'=>'i','ì'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ó'=>'o','ò'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
        'ú'=>'u','ù'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y','đ'=>'d',
    ]);
    $key = preg_replace('/[^a-z0-9]+/', '', $name) ?? '';
    $mapping = [
        'bongda'=>'football.png','football'=>'football.png','soccer'=>'football.png',
        'bongro'=>'basketball.png','basketball'=>'basketball.png',
        'caulong'=>'badminton.png','badminton'=>'badminton.png',
        'tennis'=>'tennis.png','pickleball'=>'pickleball.png',
        'bongchuyen'=>'volleyball.png','volleyball'=>'volleyball.png',
        'futsal'=>'futsal.png',
        'bongban'=>'table-tennis.png','tabletennis'=>'table-tennis.png','pingpong'=>'table-tennis.png',
    ];
    $filename = $mapping[$key] ?? 'other.png';
    return is_file(dirname(__DIR__) . '/assets/images/' . $filename) ? $filename : 'other.png';
}
function booking_status_label(string $status): string
{
    return ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'cancelled' => 'Đã hủy', 'completed' => 'Đã hoàn thành'][$status] ?? 'Không xác định';
}
function payment_status_label(string $status): string
{
    return ['pending' => 'Chờ xác nhận', 'paid' => 'Đã thanh toán', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy'][$status] ?? 'Không xác định';
}
function expire_pending_bank_transfers(PDO $pdo): int
{
    if ($pdo->inTransaction()) return 0;

    try {
        $pdo->beginTransaction();
        $expiredQuery = $pdo->query("SELECT p.id payment_id,b.id booking_id,b.user_id
            FROM payments p JOIN bookings b ON b.id=p.booking_id
            WHERE p.payment_method='bank_transfer' AND p.status='pending'
              AND p.payment_submitted_at IS NOT NULL
              AND p.payment_submitted_at<=DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              AND b.status IN ('pending','confirmed')
            ORDER BY p.payment_submitted_at ASC LIMIT 100 FOR UPDATE");
        $expiredPayments = $expiredQuery->fetchAll();
        $cancelPayment = $pdo->prepare("UPDATE payments SET status='cancelled'
            WHERE id=? AND payment_method='bank_transfer' AND status='pending'
              AND payment_submitted_at IS NOT NULL
              AND payment_submitted_at<=DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $cancelBooking = $pdo->prepare("UPDATE bookings SET status='cancelled'
            WHERE id=? AND status IN ('pending','confirmed')");
        $releaseSlots = $pdo->prepare('UPDATE booking_details SET slot_active=NULL WHERE booking_id=?');
        $expiredCount = 0;

        foreach ($expiredPayments as $expired) {
            $cancelPayment->execute([(int)$expired['payment_id']]);
            if ($cancelPayment->rowCount() !== 1) continue;
            $cancelBooking->execute([(int)$expired['booking_id']]);
            if ($cancelBooking->rowCount() !== 1) throw new RuntimeException('Booking changed while expiring payment #' . (int)$expired['payment_id']);
            $releaseSlots->execute([(int)$expired['booking_id']]);
            notify_user(
                $pdo,
                'user',
                (int)$expired['user_id'],
                'bank_transfer_expired',
                'Đơn ' . booking_code((int)$expired['booking_id']) . ' đã tự hủy vì quá 5 phút chờ Admin xác nhận chuyển khoản.',
                'user/booking_detail.php?id=' . (int)$expired['booking_id']
            );
            $expiredCount++;
        }
        $pdo->commit();
        return $expiredCount;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Transfer expiration failed: ' . $exception->getMessage());
        return 0;
    }
}
function complete_elapsed_confirmed_bookings(PDO $pdo): int
{
    if ($pdo->inTransaction()) return 0;
    $statement = $pdo->prepare("UPDATE bookings b
        SET b.status='completed'
        WHERE b.status IN ('pending','confirmed')
          AND EXISTS (SELECT 1 FROM booking_details d WHERE d.booking_id=b.id)
          AND TIMESTAMP(b.booking_date, (SELECT MAX(t.end_time)
              FROM booking_details d JOIN time_slots t ON t.id=d.time_slot_id
              WHERE d.booking_id=b.id)) <= NOW()");
    $statement->execute();
    return $statement->rowCount();
}
function booking_code(int $id): string { return 'BK' . str_pad((string)$id, 6, '0', STR_PAD_LEFT); }
function vietqr_image_url(array $config, int $amount, string $bookingReference): string
{
    if ($amount <= 0 || $bookingReference === '') return '';
    $base = 'https://img.vietqr.io/image/' . rawurlencode((string)$config['bank_id']) . '-'
        . rawurlencode((string)$config['account_number']) . '-' . rawurlencode((string)$config['qr_template']) . '.png';
    return $base . '?' . http_build_query([
        'amount' => $amount,
        'addInfo' => $bookingReference,
        'accountName' => (string)$config['account_name'],
    ], '', '&', PHP_QUERY_RFC3986);
}
function set_flash(string $message, string $type = 'success'): void { flash($message, $type); }
function notify_user(PDO $pdo, string $role, ?int $userId, string $type, string $message, string $url): void
{
    $statement = $pdo->prepare('INSERT INTO notifications (recipient_role,recipient_id,notification_type,message,target_url) VALUES (?,?,?,?,?)');
    $statement->execute([$role, $userId, $type, mb_substr($message, 0, 255), $url]);
}
function admin_log(PDO $pdo, string $action, string $entityType, ?int $entityId, string $description): void
{
    if ((current_user()['role'] ?? '') !== 'admin') return;
    $statement = $pdo->prepare('INSERT INTO admin_logs (admin_id,action,entity_type,entity_id,description) VALUES (?,?,?,?,?)');
    $statement->execute([(int)current_user()['id'], $action, $entityType, $entityId, mb_substr($description, 0, 255)]);
}
