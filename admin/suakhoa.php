<?php
// PHP_SESSION_NONE kiểm tra nếu session chưa được bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn tương đối từ admin/ ra thư mục gốc dkhp/
include '../conn.php';
include '../function.php';

check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();

// Đường dẫn gốc của trang web (cho các liên kết CSS, JS, hình ảnh)
$base_url = '/dkhp/';

$ma_khoa = isset($_GET['id']) ? intval($_GET['id']) : 0;
$khoa_data = null; // Biến để lưu dữ liệu khoa hiện tại
$error = '';
$success = '';

// --- Xử lý POST request (Cập nhật thông tin khoa) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ma_khoa = intval($_POST['ma_khoa'] ?? 0); // Lấy lại mã khoa từ hidden field
    $ten_khoa_moi = trim($_POST['ten_khoa'] ?? '');

    // Validate input data
    if ($ma_khoa === 0 || empty($ten_khoa_moi)) {
        $_SESSION['message'] = "Vui lòng điền đầy đủ thông tin khoa và mã khoa hợp lệ.";
        $_SESSION['message_type'] = "error";
        // Giữ lại dữ liệu đã nhập để người dùng không phải nhập lại
        $khoa_data = [
            'ma_khoa' => $ma_khoa,
            'ten_khoa' => $ten_khoa_moi
        ];
    } else {
        // Kiểm tra xem tên khoa mới có bị trùng với khoa khác không (ngoại trừ khoa đang chỉnh sửa)
        $check_stmt = $conn->prepare("SELECT ma_khoa FROM khoa WHERE ten_khoa = ? AND ma_khoa <> ?");
        if ($check_stmt === false) {
            $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
            $_SESSION['message_type'] = "error";
        } else {
            $check_stmt->bind_param("si", $ten_khoa_moi, $ma_khoa);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['message'] = "Tên khoa **" . htmlspecialchars($ten_khoa_moi) . "** đã tồn tại. Vui lòng chọn tên khác.";
                $_SESSION['message_type'] = "error";
            } else {
                // Chuẩn bị câu lệnh UPDATE
                $stmt = $conn->prepare("UPDATE khoa SET ten_khoa = ? WHERE ma_khoa = ?");
                if ($stmt === false) {
                    $_SESSION['message'] = "Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error;
                    $_SESSION['message_type'] = "error";
                } else {
                    $stmt->bind_param("si", $ten_khoa_moi, $ma_khoa);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Cập nhật khoa **" . htmlspecialchars($ten_khoa_moi) . "** thành công!";
                        $_SESSION['message_type'] = "success";
                        header("Location: khoa.php"); // Chuyển hướng về trang danh sách khoa
                        exit();
                    } else {
                        $_SESSION['message'] = "Lỗi khi cập nhật khoa: " . $stmt->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
}

// --- Logic Lấy dữ liệu khoa để hiển thị lên form (GET request hoặc sau lỗi POST) ---
// Chỉ lấy dữ liệu nếu biến $khoa_data chưa được set (tức là không phải từ lỗi POST)
if ($khoa_data === null && $ma_khoa > 0) {
    $stmt = $conn->prepare("SELECT ma_khoa, ten_khoa FROM khoa WHERE ma_khoa = ?");
    if ($stmt === false) {
        $error = "Lỗi chuẩn bị truy vấn lấy dữ liệu khoa: " . $conn->error;
    } else {
        $stmt->bind_param("i", $ma_khoa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $khoa_data = $result->fetch_assoc();
        } else {
            $error = "Không tìm thấy khoa với Mã: " . htmlspecialchars($ma_khoa);
            $ma_khoa = 0; // Đặt về 0 để tránh hiển thị form rỗng nếu không tìm thấy
        }
        $stmt->close();
    }
} elseif ($ma_khoa === 0) {
    $error = "Không có Mã khoa được cung cấp để chỉnh sửa.";
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

$conn->close(); // Đóng kết nối CSDL sau khi tất cả các truy vấn đã được thực thi
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa Khoa</title>
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
            <h2>Chỉnh sửa Khoa</h2>

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

            <?php if ($khoa_data) : // Chỉ hiển thị form nếu tìm thấy dữ liệu khoa ?>
                <form method="POST">
                    <input type="hidden" name="ma_khoa" value="<?php echo htmlspecialchars($khoa_data['ma_khoa']); ?>">

                    <label for="ten_khoa">Tên Khoa:</label>
                    <input type="text" id="ten_khoa" name="ten_khoa" value="<?php echo htmlspecialchars($khoa_data['ten_khoa']); ?>" required>

                    <button type="submit"><i class="fas fa-save"></i> Cập nhật Khoa</button>
                    <a href="/dkhp/admin/khoa.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
                </form>
            <?php else : ?>
                <p>Không thể tải thông tin khoa hoặc Mã khoa không hợp lệ.</p>
                <a href="/dkhp/admin/khoa.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
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