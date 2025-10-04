<?php
// PHP_SESSION_NONE kiểm tra nếu session chưa được bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn tương đối từ admin/ ra thư mục gốc dkhp/
include '../conn.php';
include '../function.php'; // Đảm bảo file này chứa check_admin()

// Gọi hàm kiểm tra quyền Admin
// check_admin() sẽ sử dụng $_SESSION và redirect nếu không đủ quyền
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
// Include header và menubar
// Lưu ý: header.php của bạn nên chứa <!DOCTYPE html>, <html>, <head>, và <body> mở
include '../header.php'; // dkhp/header.php
include 'menubar.php';   // dkhp/admin/menubar.php

$success = '';
$error = '';

// Khởi tạo biến để giữ giá trị form sau khi submit thất bại hoặc lần đầu load trang
$ten_khoa = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ten_khoa = trim($_POST['ten_khoa'] ?? '');

    // --- Validate dữ liệu đầu vào ---
    if (empty($ten_khoa)) {
        $error = "Vui lòng điền tên khoa.";
    } elseif (strlen($ten_khoa) > 255) { // Giới hạn độ dài tên khoa
        $error = "Tên khoa không được vượt quá 255 ký tự.";
    } else {
        // Kiểm tra trùng tên khoa trong bảng `khoa`
        $check = $conn->prepare("SELECT ma_khoa FROM khoa WHERE ten_khoa = ?");
        $check->bind_param("s", $ten_khoa);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Tên khoa này đã tồn tại.";
        } else {
            // Chuẩn bị câu lệnh INSERT cho bảng `khoa`
            $stmt = $conn->prepare("INSERT INTO khoa (ten_khoa) VALUES (?)");
            $stmt->bind_param("s", $ten_khoa);

            if ($stmt->execute()) {
                $success = "Thêm khoa **" . htmlspecialchars($ten_khoa) . "** thành công!";
                // Xóa dữ liệu form sau khi thêm thành công để người dùng thêm khoa mới
                $ten_khoa = ''; 
            } else {
                $error = "Lỗi khi thêm khoa: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close(); // Đóng statement kiểm tra tên khoa
    }
}
$conn->close(); // Đóng kết nối CSDL
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Khoa Mới</title>
    <link rel="stylesheet" href="css/mainadd.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <main>
        <div class="form-container">
            <h2>Thêm Khoa Mới</h2>

            <?php if ($success) echo "<div class='alert success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert error'>$error</div>"; ?>

            <form method="POST">
                <label for="ten_khoa">Tên Khoa:</label>
                <input type="text" id="ten_khoa" name="ten_khoa" value="<?php echo htmlspecialchars($ten_khoa); ?>" required>
                <small style="display: block; margin-top: -15px; margin-bottom: 5px; color: #888;">Tên khoa không được để trống và không quá 255 ký tự.</small>

                <button type="submit"><i class="fas fa-plus"></i> Thêm Khoa</button>
                <a href="/dkhp/admin/khoa.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách Khoa</a>
            </form>
        </div>
    </main>
    
    <script>
        // JS để đóng thông báo (giữ lại từ trang them_tk.php)
        document.querySelectorAll('.alert').forEach(alertDiv => {
            if (alertDiv.innerHTML.trim() !== "") {
                setTimeout(() => {
                    alertDiv.style.display = 'none';
                }, 5000); // Ẩn sau 5 giây
            }
        });
    </script>
    </body>
</html>