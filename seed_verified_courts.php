<?php
declare(strict_types=1);

// Run from the command line only: php seed_verified_courts.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this seed from the command line only.\n");
}

require __DIR__ . '/config/database.php';

$sports = [
    'Bóng đá' => 'football.png',
    'Bóng rổ' => 'basketball.png',
    'Tennis' => 'tennis.png',
    'Cầu lông' => 'badminton.png',
    'Pickleball' => 'pickleball.png',
    'Bóng chuyền' => 'volleyball.png',
];

$courts = [
    ['Bóng đá', 'TBG Arena - Sân Bóng Đá Cỏ Nhân Tạo', '407/19 Đ. Nguyễn Xí, Phường 13, Bình Lợi Trung, TP. Hồ Chí Minh', 'Sân bóng đá cỏ nhân tạo 5 và 7 người. Giá thuê chưa được xác minh; vui lòng liên hệ đơn vị vận hành.', 'football.png'],
    ['Bóng đá', 'Fox Fields - An Phu Sports Park', '90 Song Hành, Phường Bình Trưng, TP. Hồ Chí Minh', 'Địa điểm sân bóng thuộc An Phu Sports Park của Fox Football Vietnam. Giá thuê sân chưa được xác minh.', 'football.png'],
    ['Bóng đá', 'Trung tâm Thể thao Phong Sơn - Cơ sở Lam Sơn', '320/1 Trần Bình Trọng, Phường Chợ Quán, TP. Hồ Chí Minh', 'Website Phong Sơn liệt kê sân bóng đá tại cơ sở Lam Sơn. Sức chứa và giá riêng chưa được công bố.', 'football.png'],
    ['Bóng đá', 'Trung tâm Thể thao Phong Sơn - Cơ sở Bình Hưng Hòa', '79/19 Đường số 4, Phường Bình Hưng Hòa, TP. Hồ Chí Minh', 'Website Phong Sơn liệt kê sân bóng đá tại cơ sở Bình Hưng Hòa. Sức chứa và giá riêng chưa được công bố.', 'football.png'],
    ['Bóng đá', 'Trung tâm Thể thao Phong Sơn - Cơ sở Lý Tự Trọng', '348 Hoàng Văn Thụ, Phường Tân Sơn Nhất, TP. Hồ Chí Minh', 'Website Phong Sơn liệt kê sân bóng đá tại cơ sở Lý Tự Trọng. Sức chứa và giá riêng chưa được công bố.', 'football.png'],
    ['Bóng đá', 'Trung tâm Thể thao Phong Sơn - Cơ sở Nam Sài Gòn', 'Trường THCS-THPT Nam Sài Gòn, đường Nguyễn Lương Bằng, Phường Tân Mỹ, TP. Hồ Chí Minh', 'Website Phong Sơn liệt kê sân bóng đá tại cơ sở Nam Sài Gòn. Sức chứa và giá riêng chưa được công bố.', 'football.png'],
    ['Bóng rổ', 'HYPERHALL - Basketball Court', '47 Đường số 29, Phường An Khánh, TP. Hồ Chí Minh', 'Địa điểm bóng rổ trong nhà; đơn vị đặt sân công bố có 3 sân. Giá thuê chưa được xác minh.', 'basketball.png'],
    ['Bóng rổ', 'The Lam Sport Center', '1387 đường Bình Đông, Phường 15, Quận 8, TP. Hồ Chí Minh', 'Website đơn vị vận hành xác nhận có một sân bóng rổ. Giá thuê chưa được xác minh.', 'basketball.png'],
    ['Bóng rổ', 'Sân Bóng Rổ Sai Gon Sparks', '70-72 Đường số 15, Khu dân cư Him Lam, Bình Chánh, TP. Hồ Chí Minh', 'Địa điểm và địa chỉ được liệt kê trong danh mục sân bóng rổ TP.HCM của LOOL. Giá và số lượng sân chưa xác minh.', 'basketball.png'],
    ['Bóng rổ', 'Double B Indoor Q7', '2/3 Tân Mỹ, Phường Tân Mỹ, TP. Hồ Chí Minh', 'Địa điểm và địa chỉ được liệt kê trong danh mục sân bóng rổ TP.HCM của LOOL. Giá và số lượng sân chưa xác minh.', 'basketball.png'],
    ['Bóng rổ', 'Trung tâm Thể thao Phong Sơn - Cơ sở Bình Hưng Hòa', '79/19 Đường số 4, Phường Bình Hưng Hòa, TP. Hồ Chí Minh', 'Website Phong Sơn liệt kê sân bóng rổ tại cơ sở Bình Hưng Hòa. Sức chứa và giá riêng chưa được công bố.', 'basketball.png'],
    ['Bóng rổ', 'Trung tâm Thể thao Phong Sơn - Cơ sở Lý Tự Trọng', '348 Hoàng Văn Thụ, Phường Tân Sơn Nhất, TP. Hồ Chí Minh', 'Website Phong Sơn liệt kê sân bóng rổ tại cơ sở Lý Tự Trọng. Sức chứa và giá riêng chưa được công bố.', 'basketball.png'],
    ['Tennis', 'CLB Tennis Tấn Trường', '96 đường Đào Trí, Phường Phú Thuận, Quận 7, TP. Hồ Chí Minh', 'Đơn vị đặt sân công bố địa điểm có 6 sân tennis. Giá thuê chưa được xác minh.', 'tennis.png'],
    ['Tennis', 'Sân Tennis Kevin 1 (outdoor)', '149/4 Bình Quới, Cư xá Thanh Đa, Bình Thạnh, TP. Hồ Chí Minh', 'Danh sách sân tennis TP.HCM ghi địa chỉ, giờ hoạt động và khoảng giá 130.000-270.000đ/giờ; số sân chưa xác minh.', 'tennis.png'],
    ['Tennis', 'Sân Tennis Kevin 3 (outdoor)', '102 Phổ Quang, Phường 2, Tân Bình, TP. Hồ Chí Minh', 'Danh sách sân tennis TP.HCM ghi địa chỉ, giờ hoạt động và khoảng giá 130.000-270.000đ/giờ; số sân chưa xác minh.', 'tennis.png'],
    ['Tennis', 'Tennis Nam Long Sport', '79 Đường D2, Phước Long B, TP. Thủ Đức, TP. Hồ Chí Minh', 'Danh sách sân tennis TP.HCM ghi địa chỉ, giờ hoạt động và khoảng giá 100.000-280.000đ/giờ; số sân chưa xác minh.', 'tennis.png'],
    ['Tennis', 'CLB Tennis Hoàng Thành Trung', 'Hẻm 141 Trần Não, Phường Bình An, TP. Thủ Đức, TP. Hồ Chí Minh', 'Danh sách sân tennis TP.HCM ghi địa chỉ, giờ hoạt động và khoảng giá 230.000-430.000đ/giờ; số sân chưa xác minh.', 'tennis.png'],
    ['Tennis', 'Tennis Fans League', '03 Đường Đông Tây 1, Khu phố 5, Phường Bình Trưng, TP. Hồ Chí Minh', 'LOOL ghi nhận địa điểm tennis này có 4 sân. Giá thuê chưa được công bố.', 'tennis.png'],
    ['Cầu lông', 'The B Ung Văn Khiêm - Badminton', '155-157 Ung Văn Khiêm, Phường Thạnh Mỹ Tây, TP. Hồ Chí Minh', 'Địa điểm tổ chức giải cầu lông; thông tin giá chưa được xác minh.', 'badminton.png'],
    ['Cầu lông', 'Sân cầu lông 42 Nguyễn Trung Nguyệt', '42 Nguyễn Trung Nguyệt, Phường Bình Trưng, TP. Hồ Chí Minh', 'Thư mục địa điểm ghi nhận 4 sân cầu lông. Giá chưa được công bố.', 'badminton.png'],
    ['Cầu lông', 'GREEN BADMINTON & PICKLEBALL', '154/9 Đ. Nguyễn Xí, Phường 26, Bình Thạnh, TP. Hồ Chí Minh', 'Địa điểm được liệt kê là sân cầu lông và pickleball; được ghi riêng cho cả hai môn. Giá chưa xác minh.', 'badminton.png'],
    ['Cầu lông', "D'NI SPORT", '85 Nguyễn Văn Quỳ, Phường Tân Thuận, TP. Hồ Chí Minh', 'LOOL ghi nhận 12 sân và giá công khai từ 50.000đ/giờ; giá thực tế thay đổi theo khung giờ.', 'badminton.png'],
    ['Cầu lông', 'Sân Cầu Lông Bóc', '20A Đường 16, Phường Phước Long A, TP. Hồ Chí Minh', 'LOOL ghi nhận 6 sân, hoạt động 05:00-22:00 và giá từ 80.000đ/giờ.', 'badminton.png'],
    ['Cầu lông', 'Rian Sports', '185B Nguyễn Oanh, Phường 10, Gò Vấp, TP. Hồ Chí Minh', 'LOOL liệt kê địa điểm và giá khoảng 60.000-130.000đ/giờ; số sân chưa xác minh.', 'badminton.png'],
    ['Cầu lông', 'Phong Sơn Tân Bình', '19 Hoa Bằng, Phường Tân Sơn Nhì, Tân Phú, TP. Hồ Chí Minh', 'Website Phong Sơn và LOOL xác nhận địa điểm cầu lông. LOOL hiển thị mức 90.000-110.000đ/giờ.', 'badminton.png'],
    ['Cầu lông', 'CLB Cầu Lông Bingo', '1095 TL10, Phường Tân Tạo, Bình Tân, TP. Hồ Chí Minh', 'LOOL liệt kê địa điểm và khoảng giá 30.000-100.000đ/giờ; số sân chưa xác minh.', 'badminton.png'],
    ['Pickleball', 'GREEN BADMINTON & PICKLEBALL', '154/9 Đ. Nguyễn Xí, Phường 26, Bình Thạnh, TP. Hồ Chí Minh', 'Địa điểm được liệt kê là sân cầu lông và pickleball; sức chứa cho môn này chưa xác minh.', 'pickleball.png'],
    ['Pickleball', 'Sân Pickleball Văn Thánh', '48/10 Điện Biên Phủ, Phường Thạnh Mỹ Tây, Bình Thạnh, TP. Hồ Chí Minh', 'Nguồn thứ cấp cập nhật tên phường mới; danh bạ khác còn ghi Phường 25. Giá chưa được xác minh.', 'pickleball.png'],
    ['Pickleball', 'Sân Pickle Pickle', '1 Đường số 38, Thảo Điền, An Khánh, TP. Hồ Chí Minh', 'Tên và địa chỉ được liệt kê trong danh bạ sân pickleball có Maps trực tiếp. Giá chưa xác minh.', 'pickleball.png'],
    ['Pickleball', 'Sân Pickleball Saigon', '2A Trương Văn Bang, Phường Bình Trưng Tây, TP. Hồ Chí Minh', 'Trang địa điểm ghi nhận 31 lượt đánh giá tại thời điểm truy cập; không lưu rating vào schema hiện tại.', 'pickleball.png'],
    ['Pickleball', 'Sân Pickleball Tân Phú - 168 Nguyễn Hữu Dật', '168 Nguyễn Hữu Dật, Phường Tây Thạnh, Tân Phú, TP. Hồ Chí Minh', 'Nền tảng địa điểm ghi nhận 10 sân và khung giờ hoạt động; giá niêm yết là liên hệ.', 'pickleball.png'],
    ['Pickleball', 'Sân Pickleball Bình Lợi - Pickleball Court Bình Lợi', '171/4 Bình Lợi, Phường 13, Bình Lợi Trung, TP. Hồ Chí Minh', 'Tên và địa chỉ được liệt kê trong danh bạ sân pickleball có Maps trực tiếp. Giá chưa xác minh.', 'pickleball.png'],
    ['Pickleball', 'Sân pickleball RUDAL', '28 Đường 12, An Khánh, TP. Hồ Chí Minh', 'Google Maps từ nguồn danh bạ chuyển đến địa điểm RUDAL tại vị trí này; thông tin quận/phường giữa nguồn hiện còn khác nhau.', 'pickleball.png'],
    ['Pickleball', 'Sân Pickleball The Dink', '191A Đường Hoàng Ngân, Phường Phú Định, Quận 8, TP. Hồ Chí Minh', 'Tên và địa chỉ được liệt kê trong danh bạ sân pickleball có Maps trực tiếp. Giá chưa xác minh.', 'pickleball.png'],
    ['Bóng chuyền', 'Sân bóng chuyền Minh Râu', 'Hẻm 373 Hà Huy Giáp, Thạnh Xuân, Quận 12, TP. Hồ Chí Minh', 'Website đơn vị vận hành xác nhận địa chỉ sân bóng chuyền. Giá tùy dịch vụ và chưa nhập.', 'volleyball.png'],
    ['Bóng chuyền', 'The Lam Sport Center', '1387 đường Bình Đông, Phường 15, Quận 8, TP. Hồ Chí Minh', 'Website đơn vị vận hành xác nhận có hai sân bóng chuyền cỡ tiêu chuẩn. Giá thuê chưa được xác minh.', 'volleyball.png'],
    ['Bóng chuyền', 'Trung tâm TDTT Thủ Đức', '26 Chương Dương, TP. Thủ Đức, TP. Hồ Chí Minh', 'LOOL ghi nhận 4 sân bóng chuyền tại địa chỉ này; giá chưa được công bố.', 'volleyball.png'],
    ['Bóng chuyền', 'Trung tâm Thể thao Phong Sơn - Cơ sở Lam Sơn', '320/1 Trần Bình Trọng, Phường Chợ Quán, TP. Hồ Chí Minh', 'Website Phong Sơn liệt kê sân bóng chuyền tại cơ sở Lam Sơn. Sức chứa và giá riêng chưa được công bố.', 'volleyball.png'],
    ['Bóng chuyền', 'Trung tâm Thể thao Phong Sơn - Cơ sở Nam Sài Gòn', 'Trường THCS-THPT Nam Sài Gòn, đường Nguyễn Lương Bằng, Phường Tân Mỹ, TP. Hồ Chí Minh', 'Website Phong Sơn liệt kê sân bóng chuyền tại cơ sở Nam Sài Gòn. Sức chứa và giá riêng chưa được công bố.', 'volleyball.png'],
    ['Bóng chuyền', 'Sân bóng chuyền Thăng Long', '68 Thăng Long, Phường 4, Tân Bình, TP. Hồ Chí Minh', 'Danh sách sân bóng chuyền TP.HCM ghi nhận địa chỉ, giờ hoạt động và một sân.', 'volleyball.png'],
    ['Bóng chuyền', 'Sân bóng chuyền Cà Phê Tre', '218 Vườn Lài, Phường An Phú Đông, Quận 12, TP. Hồ Chí Minh', 'Danh sách sân bóng chuyền TP.HCM ghi tên, địa chỉ và giờ hoạt động; số sân chưa công bố.', 'volleyball.png'],
];

// Illustrative estimates based on public HCMC market ranges, not venue quotations.
$hourlyEstimateBySport = [
    'Bóng đá' => 330000,
    'Bóng rổ' => 140000,
    'Tennis' => 200000,
    'Cầu lông' => 90000,
    'Pickleball' => 150000,
    'Bóng chuyền' => 140000,
];
$venueEstimate = [
    'The B Ung Văn Khiêm - Badminton' => [135000, 'ShopVNB công bố khoảng 80.000–190.000đ/giờ tùy khung; website dùng mức giữa ước tính 135.000đ, không phải giá xác nhận.'],
    'Sân Pickleball Tân Phú - 168 Nguyễn Hữu Dật' => [130000, 'Nguồn thị trường công bố khoảng 90.000–170.000đ/giờ tùy khung; website dùng mức giữa ước tính 130.000đ.'],
    'Sân pickleball RUDAL' => [120000, 'Nguồn đặt sân công bố các mức 80.000–160.000đ/giờ theo khung; website dùng mức giữa ước tính 120.000đ.'],
    "D'NI SPORT" => [50000, 'LOOL công khai giá khởi điểm 50.000đ/giờ; giá thực tế có thể thay đổi theo khung giờ, cần xác nhận trước khi đặt.'],
    'Sân Cầu Lông Bóc' => [80000, 'LOOL công khai giá khởi điểm 80.000đ/giờ; giá thực tế có thể thay đổi theo khung giờ, cần xác nhận trước khi đặt.'],
    'Phong Sơn Tân Bình' => [100000, 'LOOL hiển thị khoảng 90.000–110.000đ/giờ; website dùng mức giữa tham khảo 100.000đ.'],
    'Rian Sports' => [95000, 'LOOL hiển thị khoảng 60.000–130.000đ/giờ; website dùng mức giữa tham khảo 95.000đ.'],
    'CLB Cầu Lông Bingo' => [65000, 'LOOL hiển thị khoảng 30.000–100.000đ/giờ; website dùng mức giữa tham khảo 65.000đ.'],
    'Sân Tennis Kevin 1 (outdoor)' => [200000, 'Nguồn công khai khoảng 130.000–270.000đ/giờ; website dùng mức giữa tham khảo 200.000đ.'],
    'Sân Tennis Kevin 3 (outdoor)' => [200000, 'Nguồn công khai khoảng 130.000–270.000đ/giờ; website dùng mức giữa tham khảo 200.000đ.'],
    'Tennis Nam Long Sport' => [190000, 'Nguồn công khai khoảng 100.000–280.000đ/giờ; website dùng mức giữa tham khảo 190.000đ.'],
    'CLB Tennis Hoàng Thành Trung' => [330000, 'Nguồn công khai khoảng 230.000–430.000đ/giờ; website dùng mức giữa tham khảo 330.000đ.'],
];
$verifiedCourtCounts = [
    'HYPERHALL - Basketball Court' => 3,
    'The Lam Sport Center|Bóng rổ' => 1,
    'The Lam Sport Center|Bóng chuyền' => 2,
    'CLB Tennis Tấn Trường' => 6,
    'The B Ung Văn Khiêm - Badminton' => 12,
    'Sân cầu lông 42 Nguyễn Trung Nguyệt' => 4,
    'Sân Pickleball Văn Thánh' => 14,
    'Sân Pickleball Tân Phú - 168 Nguyễn Hữu Dật' => 10,
    'Sân pickleball RUDAL' => 6,
    "D'NI SPORT" => 12,
    'Sân Cầu Lông Bóc' => 6,
    'Tennis Fans League' => 4,
    'Trung tâm TDTT Thủ Đức' => 4,
    'Sân bóng chuyền Thăng Long' => 1,
];

$insertSport = $pdo->prepare('INSERT INTO sports (name, description) VALUES (?, ?)');
$findSport = $pdo->prepare('SELECT id FROM sports WHERE name = ? LIMIT 1');
$sportIds = [];

$pdo->beginTransaction();
try {
    foreach ($sports as $name => $image) {
        $findSport->execute([$name]);
        $sportId = $findSport->fetchColumn();
        if ($sportId === false) {
            $insertSport->execute([$name, 'Địa điểm ' . $name . ' tại TP. Hồ Chí Minh.']);
            $sportId = $pdo->lastInsertId();
            echo 'Added sport: ' . $name . PHP_EOL;
        }
        $sportIds[$name] = (int)$sportId;
    }

    $findCourt = $pdo->prepare('SELECT id FROM courts WHERE sport_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND LOWER(TRIM(address)) = LOWER(TRIM(?)) LIMIT 1');
    $insertCourt = $pdo->prepare('INSERT INTO courts (sport_id, name, description, address, price_per_hour, price_note, court_count, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'active\')');
    $setCourtDetails = $pdo->prepare('UPDATE courts SET price_per_hour = CASE WHEN price_per_hour = 0 OR price_note LIKE \'Giá tham khảo đồ án%\' THEN ? ELSE price_per_hour END, price_note = CASE WHEN price_note IS NULL OR price_note = \'\' OR price_note LIKE \'Giá tham khảo đồ án%\' THEN ? ELSE price_note END, court_count = CASE WHEN court_count IS NULL AND ? IS NOT NULL THEN ? ELSE court_count END WHERE id = ?');
    $addedCourts = 0;
    $existingCourts = 0;

    foreach ($courts as [$sport, $name, $address, $description, $image]) {
        [$estimate, $priceNote] = $venueEstimate[$name] ?? [$hourlyEstimateBySport[$sport], 'Giá tham khảo đồ án theo mặt bằng TP.HCM; không phải báo giá chính thức của địa điểm. Cần xác nhận trước khi đặt.'];
        $capacityKey = $name === 'The Lam Sport Center' ? $name . '|' . $sport : $name;
        $capacity = $verifiedCourtCounts[$capacityKey] ?? null;
        $sportId = $sportIds[$sport];
        $findCourt->execute([$sportId, $name, $address]);
        $existingId = $findCourt->fetchColumn();
        if ($existingId !== false) {
            $setCourtDetails->execute([$estimate, $priceNote, $capacity, $capacity, (int)$existingId]);
            $existingCourts++;
            continue;
        }
        $insertCourt->execute([$sportId, $name, $description, $address, $estimate, $priceNote, $capacity, $image]);
        $addedCourts++;
    }

    $pdo->commit();
    echo "Added courts: {$addedCourts}" . PHP_EOL;
    echo "Already present: {$existingCourts}" . PHP_EOL;
    echo 'Sports checked: ' . count($sports) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Seed failed; transaction rolled back: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
