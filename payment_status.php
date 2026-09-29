<?php
require __DIR__ . '/config/database.php';
require_login();
expire_pending_bank_transfers($pdo);
header('Content-Type: application/json; charset=utf-8');
$id = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT) ?: 0;
$query = $pdo->prepare('SELECT p.status,p.paid_at FROM payments p JOIN bookings b ON b.id=p.booking_id WHERE b.id=? AND b.user_id=?');
$query->execute([$id, (int)current_user()['id']]);
$payment = $query->fetch();
if (!$payment) { http_response_code(404); echo json_encode(['error'=>'Không tìm thấy thanh toán.']); exit; }
echo json_encode(['status'=>$payment['status'],'paid_at'=>$payment['paid_at']], JSON_UNESCAPED_UNICODE);
