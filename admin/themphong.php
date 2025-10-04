<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';

check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
$base_url = '/dkhp/';

$message = '';
$message_type = '';

// Khởi tạo các biến để giữ giá trị nếu có lỗi nhập liệu
$ten_phong_old = '';
$suc_chua_old = '';
$loai_phong_old = '';

// Xử lý POST request khi form được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten_phong = trim($_POST['ten_phong'] ?? '');
    $suc_chua = intval($_POST['suc_chua'] ?? 0);
    $loai_phong = trim($_POST['loai_phong'] ?? '');

    // Giữ lại giá trị đã nhập để điền lại vào form
    $ten_phong_old = $ten_phong;
    $suc_chua_old = $suc_chua;
    $loai_phong_old = $loai_phong;

    // Validate dữ liệu đầu vào
    if (empty($ten_phong) || $suc_chua <= 0 || empty($loai_phong)) {
        $message = "Vui lòng điền đầy đủ Tên phòng, Sức chứa và Loại phòng. Sức chứa phải lớn hơn 0.";
        $message_type = "error";
    } else {
        // Kiểm tra xem tên phòng đã tồn tại chưa
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM phong_hoc WHERE ten_phong = ?");
        if ($check_stmt === false) {
            $message = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
            $message_type = "error";
        } else {
            $check_stmt->bind_param("s", $ten_phong);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();

            if ($count > 0) {
                $message = "Tên phòng **" . htmlspecialchars($ten_phong) . "** đã tồn tại. Vui lòng chọn tên khác.";
                $message_type = "error";
            } else {
                // Thêm phòng học mới vào CSDL
                $stmt = $conn->prepare("INSERT INTO phong_hoc (ten_phong, suc_chua, loai_phong) VALUES (?, ?, ?)");
                if ($stmt === false) {
                    $message = "Lỗi chuẩn bị truy vấn: " . $conn->error;
                    $message_type = "error";
                } else {
                    $stmt->bind_param("sis", $ten_phong, $suc_chua, $loai_phong);

                    if ($stmt->execute()) {
                        $message = "Phòng học **" . htmlspecialchars($ten_phong) . "** đã được thêm thành công!";
                        $message_type = "success";
                        // Reset các biến để làm rỗng form sau khi thêm thành công
                        $ten_phong_old = '';
                        $suc_chua_old = '';
                        $loai_phong_old = '';
                    } else {
                        $message = "Lỗi khi thêm phòng học: " . $stmt->error;
                        $message_type = "error";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Phòng Học Mới</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainedit.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <?php
    include '../header.php';
    include 'menubar.php';
    ?>

    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Thêm Phòng Học Mới</h2>

            <?php if (!empty($message)) : ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <label for="ten_phong">Tên Phòng:</label>
                <input type="text" id="ten_phong" name="ten_phong" value="<?php echo htmlspecialchars($ten_phong_old); ?>" required>

                <label for="suc_chua">Sức chứa:</label>
                <input type="number" id="suc_chua" name="suc_chua" value="<?php echo htmlspecialchars($suc_chua_old); ?>" min="1" required>

                <label for="loai_phong">Loại phòng:</label>
                <select id="loai_phong" name="loai_phong" required>
                    <option value="">-- Chọn loại phòng --</option>
                    <option value="Lý thuyết" <?php echo ($loai_phong_old == 'Lý thuyết') ? 'selected' : ''; ?>>Lý thuyết</option>
                    <option value="Thực hành" <?php echo ($loai_phong_old == 'Thực hành') ? 'selected' : ''; ?>>Thực hành</option>
                    <option value="Thí nghiệm" <?php echo ($loai_phong_old == 'Thí nghiệm') ? 'selected' : ''; ?>>Thí nghiệm</option>
                    <option value="Hội trường" <?php echo ($loai_phong_old == 'Hội trường') ? 'selected' : ''; ?>>Hội trường</option>
                    <option value="Ngoài trời" <?php echo ($loai_phong_old == 'Ngoài trời') ? 'selected' : ''; ?>>Ngoài trời</option>
                </select>

                <button type="submit"><i class="fas fa-plus"></i> Thêm Phòng Học</button>
                <a href="/dkhp/admin/phong.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.alert').forEach(alertDiv => {
                if (alertDiv.innerHTML.trim() !== "") {
                    setTimeout(() => {
                        alertDiv.style.display = 'none';
                    }, 5000);
                }
            });
        });
    </script>
</body>
</html>