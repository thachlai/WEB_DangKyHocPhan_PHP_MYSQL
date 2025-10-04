<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';

check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
include '../header.php';
include 'menubar.php';

$success = '';
$error = '';

// Khởi tạo biến để giữ giá trị form
$ten_hocki = '';
$nam_hoc = ''; // Vẫn cần biến này để giữ giá trị năm đã chọn

// Tạo danh sách các năm cho dropdown
$current_year = date('Y');
$years_to_display = [];
// Hiển thị 5 năm trước và 5 năm sau năm hiện tại
for ($i = $current_year - 5; $i <= $current_year + 5; $i++) {
    $years_to_display[] = $i . '-' . ($i + 1); // Định dạng ví dụ: 2024-2025
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ten_hocki = trim($_POST['ten_hocki'] ?? '');
    $nam_hoc = trim($_POST['nam_hoc'] ?? ''); // Lấy giá trị từ dropdown

    // --- Kiểm tra dữ liệu đầu vào ---
    if (empty($ten_hocki) || empty($nam_hoc)) {
        $error = "Vui lòng điền đầy đủ Tên học kỳ và chọn Năm học.";
    } else {
        // Kiểm tra xem học kỳ đã tồn tại chưa (kết hợp ten_hocki và nam_hoc)
        $check_stmt = $conn->prepare("SELECT id_hocki FROM hocki WHERE ten_hocki = ? AND nam_hoc = ?");
        if ($check_stmt === false) {
            $error = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
        } else {
            $check_stmt->bind_param("ss", $ten_hocki, $nam_hoc); // 'ss' vì cả hai đều là chuỗi
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Học kỳ **" . htmlspecialchars($ten_hocki) . "** năm **" . htmlspecialchars($nam_hoc) . "** đã tồn tại.";
            } else {
                // Chuẩn bị truy vấn INSERT
                $stmt = $conn->prepare("INSERT INTO hocki (ten_hocki, nam_hoc) VALUES (?, ?)");
                if ($stmt === false) {
                    $error = "Lỗi chuẩn bị truy vấn thêm học kỳ: " . $conn->error;
                } else {
                    $stmt->bind_param("ss", $ten_hocki, $nam_hoc); // 'ss' vì cả hai đều là chuỗi

                    if ($stmt->execute()) {
                        $success = "Thêm học kỳ **" . htmlspecialchars($ten_hocki) . "** năm **" . htmlspecialchars($nam_hoc) . "** thành công!";
                        // Xóa dữ liệu form sau khi thêm thành công
                        $ten_hocki = '';
                        $nam_hoc = ''; // Reset năm học đã chọn
                    } else {
                        $error = "Lỗi khi thêm học kỳ: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Học kỳ Mới</title>
    <link rel="stylesheet" href="css/mainadd.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Thêm Học kỳ Mới</h2>

            <?php if ($success) echo "<div class='alert success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert error'>$error</div>"; ?>

            <form method="POST">
                <label for="ten_hocki">Tên Học kỳ:</label>
                <input type="text" id="ten_hocki" name="ten_hocki" value="<?php echo htmlspecialchars($ten_hocki); ?>" required>

                <label for="nam_hoc">Năm học:</label>
                <select id="nam_hoc" name="nam_hoc" required>
                    <option value="">-- Chọn Năm học --</option>
                    <?php foreach ($years_to_display as $year_option) : ?>
                        <option value="<?php echo htmlspecialchars($year_option); ?>"
                            <?php echo ($nam_hoc == $year_option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit"><i class="fas fa-plus"></i> Thêm Học kỳ</button>
                <a href="/dkhp/admin/hocki.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
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