<?php
require __DIR__ . '/../config/database.php';require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf();$id=filter_input(INPUT_POST,'conversation_id',FILTER_VALIDATE_INT);if(!$id){http_response_code(400);exit;}
$q=$pdo->prepare("UPDATE conversations SET status='closed' WHERE id=?");$q->execute([$id]);admin_log($pdo,'chat_close','conversation',$id,'Admin kết thúc cuộc trò chuyện');flash('Đã kết thúc cuộc trò chuyện.');redirect('/admin/messages.php?conversation_id='.$id);
