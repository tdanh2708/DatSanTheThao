<?php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
function respond(array $data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if (!current_user()) respond(['error'=>'Vui lòng đăng nhập.'],401);
$user=current_user(); $isAdmin=($user['role']??'')==='admin'; $action=$_GET['action']??$_POST['action']??'';
if(!is_string($action))respond(['error'=>'Yêu cầu không hợp lệ.'],400);
$userId=(int)$user['id'];
$getConversation=function(?int $requested=null) use($pdo,$isAdmin,$userId,$user): array {
    if ($isAdmin && $requested) { $q=$pdo->prepare('SELECT c.*,u.full_name,u.email,u.phone,(SELECT COUNT(*) FROM bookings b WHERE b.user_id=c.user_id) booking_count FROM conversations c JOIN users u ON u.id=c.user_id WHERE c.id=?');$q->execute([$requested]);$c=$q->fetch();if(!$c)respond(['error'=>'Không tìm thấy cuộc trò chuyện.'],404);return $c; }
    if (!$isAdmin) { $q=$pdo->prepare('SELECT c.*,u.full_name,u.email,u.phone,(SELECT COUNT(*) FROM bookings b WHERE b.user_id=c.user_id) booking_count FROM conversations c JOIN users u ON u.id=c.user_id WHERE c.user_id=?');$q->execute([$userId]);$c=$q->fetch();if($c)return $c;if($requested)respond(['error'=>'Không có quyền truy cập.'],403);$q=$pdo->prepare("INSERT INTO conversations(user_id,status) VALUES(?,'open')");$q->execute([$userId]);return ['id'=>(int)$pdo->lastInsertId(),'user_id'=>$userId,'status'=>'open','full_name'=>$user['full_name'],'email'=>$user['email'],'phone'=>null,'booking_count'=>0]; }
    if(!$requested)respond(['error'=>'Thiếu conversation_id.'],400);
    $q=$pdo->prepare('SELECT c.*,u.full_name,u.email,u.phone,(SELECT COUNT(*) FROM bookings b WHERE b.user_id=c.user_id) booking_count FROM conversations c JOIN users u ON u.id=c.user_id WHERE c.id=?');$q->execute([$requested]);$c=$q->fetch();if(!$c)respond(['error'=>'Không tìm thấy cuộc trò chuyện.'],404);return $c;
};
if($action==='list' && $isAdmin){$q=$pdo->prepare("SELECT c.id,c.status,c.last_message_at,u.full_name,u.email,(SELECT message FROM messages m WHERE m.conversation_id=c.id AND (m.message IS NOT NULL OR m.image_path IS NOT NULL) ORDER BY m.id DESC LIMIT 1) last_text,(SELECT image_path FROM messages m WHERE m.conversation_id=c.id AND (m.message IS NOT NULL OR m.image_path IS NOT NULL) ORDER BY m.id DESC LIMIT 1) last_image,(SELECT COUNT(*) FROM messages m JOIN users sender ON sender.id=m.sender_id WHERE m.conversation_id=c.id AND sender.role='user' AND m.is_read=0 AND (m.message IS NOT NULL OR m.image_path IS NOT NULL)) unread FROM conversations c JOIN users u ON u.id=c.user_id WHERE (?='' OR u.full_name LIKE ? OR u.email LIKE ?) AND (?=0 OR EXISTS(SELECT 1 FROM messages m JOIN users sender ON sender.id=m.sender_id WHERE m.conversation_id=c.id AND sender.role='user' AND m.is_read=0 AND (m.message IS NOT NULL OR m.image_path IS NOT NULL))) ORDER BY c.last_message_at DESC,c.id DESC LIMIT 100");$term=trim((string)($_GET['q']??''));$like='%'.$term.'%';$unread=(int)($_GET['unread']??0);$q->execute([$term,$like,$like,$unread]);respond(['conversations'=>$q->fetchAll()]);}
if($action==='unread'){if($isAdmin){$unread=(int)$pdo->query("SELECT (SELECT COUNT(*) FROM messages m JOIN users u ON u.id=m.sender_id WHERE u.role='user' AND m.is_read=0 AND (m.message IS NOT NULL OR m.image_path IS NOT NULL))+(SELECT COUNT(*) FROM notifications WHERE recipient_role='admin' AND recipient_id IS NULL AND is_read=0)")->fetchColumn();}else{$q=$pdo->prepare("SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id=m.conversation_id JOIN users u ON u.id=m.sender_id WHERE c.user_id=? AND u.role='admin' AND m.is_read=0 AND (m.message IS NOT NULL OR m.image_path IS NOT NULL)");$q->execute([$userId]);$unread=(int)$q->fetchColumn();}respond(['unread'=>$unread]);}
$id=filter_input(INPUT_GET,'conversation_id',FILTER_VALIDATE_INT)?:filter_input(INPUT_POST,'conversation_id',FILTER_VALIDATE_INT);$conversation=$getConversation($id?:null);$conversationId=(int)$conversation['id'];
if($action==='poll'){$read=$pdo->prepare("UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id<>? AND is_read=0 AND (message IS NOT NULL OR image_path IS NOT NULL)");$read->execute([$conversationId,$userId]);$q=$pdo->prepare('SELECT m.id,m.sender_id,u.role sender_role,u.full_name,m.message,m.image_path,m.is_read,m.created_at,CASE WHEN m.message IS NULL AND m.image_path IS NULL THEN 1 ELSE 0 END is_deleted FROM (SELECT * FROM messages WHERE conversation_id=? ORDER BY id DESC LIMIT 100) m JOIN users u ON u.id=m.sender_id ORDER BY m.id ASC');$q->execute([$conversationId]);respond(['conversation'=>['id'=>$conversationId,'status'=>$conversation['status'],'name'=>$conversation['full_name'],'email'=>$conversation['email'],'phone'=>$conversation['phone']??null,'booking_count'=>(int)($conversation['booking_count']??0)],'messages'=>$q->fetchAll()]);}
if($action==='delete_message'){
    if(!$isAdmin)respond(['error'=>'Chỉ Admin được xóa tin nhắn.'],403);
    if($_SERVER['REQUEST_METHOD']!=='POST')respond(['error'=>'Method not allowed.'],405);
    $submittedCsrf=$_POST['csrf']??'';
    if(!is_string($submittedCsrf)||!hash_equals(csrf_token(),$submittedCsrf))respond(['error'=>'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'],400);
    $messageId=filter_input(INPUT_POST,'message_id',FILTER_VALIDATE_INT)?:0;
    if($messageId<=0)respond(['error'=>'Tin nhắn không hợp lệ.'],422);
    $imagePath=null;
    try{
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT image_path,message FROM messages WHERE id=? AND conversation_id=? FOR UPDATE');
        $q->execute([$messageId,$conversationId]);
        $message=$q->fetch();
        if(!$message)throw new RuntimeException('Không tìm thấy tin nhắn trong cuộc trò chuyện này.');
        if($message['message']===null&&$message['image_path']===null)throw new RuntimeException('Tin nhắn đã được xóa trước đó.');
        $imagePath=$message['image_path'];
        $delete=$pdo->prepare('DELETE FROM messages WHERE id=? AND conversation_id=?');
        $delete->execute([$messageId,$conversationId]);
        if($delete->rowCount()!==1)throw new RuntimeException('Tin nhắn vừa thay đổi. Vui lòng tải lại.');
        admin_log($pdo,'chat_message_delete','message',$messageId,'Admin xóa tin nhắn #' . $messageId . ' trong conversation #' . $conversationId);
        $pdo->commit();
        if(is_string($imagePath)&&preg_match('#^uploads/chat/[A-Za-z0-9_.-]+$#',$imagePath)){
            $uploadRoot=realpath(dirname(__DIR__).'/uploads/chat');
            $imageFile=realpath(dirname(__DIR__).'/'.$imagePath);
            if($uploadRoot&&$imageFile&&str_starts_with($imageFile,$uploadRoot.DIRECTORY_SEPARATOR)&&is_file($imageFile))@unlink($imageFile);
        }
        respond(['ok'=>true,'message_id'=>$messageId,'deleted'=>true]);
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();respond(['error'=>$error instanceof RuntimeException?$error->getMessage():'Chưa thể xóa tin nhắn.'],500);}
}
if($action==='send') { if($_SERVER['REQUEST_METHOD']!=='POST')respond(['error'=>'Method not allowed.'],405);verify_csrf();$rawText=$_POST['message']??'';if(!is_string($rawText))respond(['error'=>'Nội dung tin nhắn không hợp lệ.'],422);$text=trim($rawText);if(mb_strlen($text)>4000)respond(['error'=>'Tin nhắn tối đa 4.000 ký tự.'],422);$path=null;if(isset($_FILES['image'])&&$_FILES['image']['error']!==UPLOAD_ERR_NO_FILE){$f=$_FILES['image'];if($f['error']!==UPLOAD_ERR_OK||$f['size']>5*1024*1024)respond(['error'=>'Ảnh lỗi hoặc vượt quá 5 MB.'],422);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$imageInfo=@getimagesize($f['tmp_name']);if(!isset($extensions[$mime])||!$imageInfo||$imageInfo[0]>8000||$imageInfo[1]>8000)respond(['error'=>'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP hợp lệ (tối đa 8.000 × 8.000 px).'],422);$dir=dirname(__DIR__).'/uploads/chat';if(!is_dir($dir)&&!mkdir($dir,0750,true))respond(['error'=>'Không tạo được thư mục ảnh.'],500);$name='chat_'.bin2hex(random_bytes(16)).'.'.$extensions[$mime];if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name))respond(['error'=>'Không lưu được ảnh.'],500);$path='uploads/chat/'.$name;}
if($text===''&&$path===null)respond(['error'=>'Nhập tin nhắn hoặc chọn ảnh.'],422);$pdo->beginTransaction();try{if($conversation['status']==='closed'){$pdo->prepare("UPDATE conversations SET status='open' WHERE id=?")->execute([$conversationId]);}$q=$pdo->prepare('INSERT INTO messages(conversation_id,sender_id,message,image_path) VALUES(?,?,?,?)');$q->execute([$conversationId,$userId,$text?:null,$path]);$messageId=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=?')->execute([$conversationId]);if($isAdmin){notify_user($pdo,'user',(int)$conversation['user_id'],'chat_message','Bạn có tin nhắn hỗ trợ mới.','chat/');admin_log($pdo,'chat_message','conversation',$conversationId,'Admin gửi tin nhắn hỗ trợ');}else{notify_user($pdo,'admin',null,'chat_message',$conversation['full_name'].' gửi tin nhắn mới.','admin/messages.php?conversation_id='.$conversationId);}$pdo->commit();respond(['ok'=>true,'id'=>$messageId,'image_path'=>$path]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($path&&is_file(dirname(__DIR__).'/'.$path))@unlink(dirname(__DIR__).'/'.$path);respond(['error'=>'Chưa thể gửi tin nhắn.'],500);} }
if($action==='close'&&$isAdmin){verify_csrf();$pdo->prepare("UPDATE conversations SET status='closed' WHERE id=?")->execute([$conversationId]);admin_log($pdo,'chat_close','conversation',$conversationId,'Admin kết thúc cuộc trò chuyện');respond(['ok'=>true]);}
respond(['error'=>'Yêu cầu không hợp lệ.'],400);
