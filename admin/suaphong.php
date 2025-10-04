<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
$base_url = '/dkhp/';

$error = '';
$success = '';

$id_phong = isset($_GET['id']) ? intval($_GET['id']) : 0;
$phong_data = null; // Biến để lưu dữ liệu phòng học hiện tại

// --- Xử lý POST request (Cập nhật thông tin phòng học) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_phong_post = intval($_POST['id_phong'] ?? 0); // Lấy lại ID phòng từ hidden field
    $ten_phong_moi = trim($_POST['ten_phong'] ?? '');
    $suc_chua_moi = intval($_POST['suc_chua'] ?? 0);
    $loai_phong_moi = trim($_POST['loai_phong'] ?? ''); // Lấy giá trị từ dropdown

    // Giữ lại dữ liệu đã nhập để điền lại vào form nếu có lỗi
    $phong_data = [
        'id_phong' => $id_phong_post,
        'ten_phong' => $ten_phong_moi,
        'suc_chua' => $suc_chua_moi,
        'loai_phong' => $loai_phong_moi
    ];

    // Validate input data
    if ($id_phong_post === 0 || empty($ten_phong_moi) || $suc_chua_moi <= 0 || empty($loai_phong_moi)) {
        $_SESSION['message'] = "Vui lòng điền đầy đủ thông tin phòng học và ID phòng hợp lệ. Sức chứa phải lớn hơn 0.";
        $_SESSION['message_type'] = "error";
    } else {
        // Kiểm tra xem tên phòng mới có bị trùng với phòng khác không (ngoại trừ phòng đang chỉnh sửa)
        $check_stmt = $conn->prepare("SELECT id_phong FROM phong_hoc WHERE ten_phong = ? AND id_phong <> ?");
        if ($check_stmt === false) {
            $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
            $_SESSION['message_type'] = "error";
        } else {
            $check_stmt->bind_param("si", $ten_phong_moi, $id_phong_post);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['message'] = "Tên phòng **" . htmlspecialchars($ten_phong_moi) . "** đã tồn tại. Vui lòng chọn tên khác.";
                $_SESSION['message_type'] = "error";
            } else {
                // Chuẩn bị câu lệnh UPDATE
                $stmt = $conn->prepare("UPDATE phong_hoc SET ten_phong = ?, suc_chua = ?, loai_phong = ? WHERE id_phong = ?");
                if ($stmt === false) {
                    $_SESSION['message'] = "Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error;
                    $_SESSION['message_type'] = "error";
                } else {
                    $stmt->bind_param("sisi", $ten_phong_moi, $suc_chua_moi, $loai_phong_moi, $id_phong_post);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Cập nhật phòng học **" . htmlspecialchars($ten_phong_moi) . "** thành công!";
                        $_SESSION['message_type'] = "success";
                        header("Location: phong.php"); // Chuyển hướng về trang danh sách phòng học
                        exit();
                    } else {
                        $_SESSION['message'] = "Lỗi khi cập nhật phòng học: " . $stmt->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
    // Gán lại ID để tải dữ liệu nếu có lỗi POST
    $id_phong = $id_phong_post;
}

// --- Logic Lấy dữ liệu phòng học để hiển thị lên form (GET request hoặc sau lỗi POST) ---
// Chỉ lấy dữ liệu nếu biến $phong_data chưa được set (tức là không phải từ lỗi POST)
if ($phong_data === null && $id_phong > 0) {
    $stmt = $conn->prepare("SELECT id_phong, ten_phong, suc_chua, loai_phong FROM phong_hoc WHERE id_phong = ?");
    if ($stmt === false) {
        $error = "Lỗi chuẩn bị truy vấn lấy dữ liệu phòng học: " . $conn->error;
    } else {
        $stmt->bind_param("i", $id_phong);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $phong_data = $result->fetch_assoc();
        } else {
            $error = "Không tìm thấy phòng học với ID: " . htmlspecialchars($id_phong);
            $id_phong = 0; // Đặt về 0 để tránh hiển thị form rỗng nếu không tìm thấy
        }
        $stmt->close();
    }
} elseif ($id_phong === 0) {
    $error = "Không có ID phòng học được cung cấp để chỉnh sửa.";
}

// Lấy thông báo từ session (nếu có)
if (isset($_SESSION['message'])) {
    if (isset($_SESSION['message_type']) && $_SESSION['message_type'] === 'success') {
        $success = $_SESSION['message'];
    } else {
        $error = $_SESSION['message'];
    }
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$conn->close(); // Đóng kết nối CSDL
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa Phòng Học</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainedit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <?php
    include '../header.php';
    include 'menubar.php';
    ?>

    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Chỉnh sửa Phòng Học</h2>

            <?php if ($success) : ?>
                <div class="alert success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="alert error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($phong_data) : // Chỉ hiển thị form nếu tìm thấy dữ liệu phòng học ?>
                <form method="POST">
                    <input type="hidden" name="id_phong" value="<?php echo htmlspecialchars($phong_data['id_phong']); ?>">

                    <label for="ten_phong">Tên Phòng:</label>
                    <input type="text" id="ten_phong" name="ten_phong" value="<?php echo htmlspecialchars($phong_data['ten_phong']); ?>" required>

                    <label for="suc_chua">Sức chứa:</label>
                    <input type="number" id="suc_chua" name="suc_chua" value="<?php echo htmlspecialchars($phong_data['suc_chua']); ?>" min="1" required>

                    <label for="loai_phong">Loại phòng:</label>
                    <select id="loai_phong" name="loai_phong" required>
                        <option value="">-- Chọn loại phòng --</option>
                        <option value="Lý thuyết" <?php echo ($phong_data['loai_phong'] == 'Lý thuyết') ? 'selected' : ''; ?>>Lý thuyết</option>
                        <option value="Thực hành" <?php echo ($phong_data['loai_phong'] == 'Thực hành') ? 'selected' : ''; ?>>Thực hành</option>
                        <option value="Thí nghiệm" <?php echo ($phong_data['loai_phong'] == 'Thí nghiệm') ? 'selected' : ''; ?>>Thí nghiệm</option>
                        <option value="Hội trường" <?php echo ($phong_data['loai_phong'] == 'Hội trường') ? 'selected' : ''; ?>>Hội trường</option>
                        <option value="Ngoài trời" <?php echo ($phong_data['loai_phong'] == 'Ngoài trời') ? 'selected' : ''; ?>>Ngoài trời</option>
                    </select>

                    <button type="submit"><i class="fas fa-save"></i> Cập nhật Phòng Học</button>
                    <a href="/dkhp/admin/phong.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
                </form>
            <?php else : ?>
                <p>Không thể tải thông tin phòng học hoặc ID phòng không hợp lệ.</p>
                <a href="/dkhp/admin/phong.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
            <?php endif; ?>
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