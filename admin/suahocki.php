<?php
// Luôn bắt đầu session ở đầu file
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

$id_hocki = isset($_GET['id']) ? intval($_GET['id']) : 0;
$hocki_data = null; // Biến để lưu dữ liệu học kỳ hiện tại
$error = '';
$success = '';

// Tạo danh sách các năm cho dropdown, giống như trong themhocki.php
$current_year = date('Y');
$years_to_display = [];
for ($i = $current_year - 5; $i <= $current_year + 5; $i++) {
    $years_to_display[] = $i . '-' . ($i + 1);
}

// --- Xử lý POST request (Cập nhật thông tin học kỳ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_hocki_post = intval($_POST['id_hocki'] ?? 0);
    $ten_hocki_moi = trim($_POST['ten_hocki'] ?? '');
    $nam_hoc_moi = trim($_POST['nam_hoc'] ?? '');
    $trangthai_moi = isset($_POST['trangthai']) ? intval($_POST['trangthai']) : 0; // Lấy giá trị trạng thái từ dropdown/input

    // Giữ lại dữ liệu đã nhập để người dùng không phải nhập lại nếu có lỗi
    $hocki_data = [
        'id_hocki' => $id_hocki_post,
        'ten_hocki' => $ten_hocki_moi,
        'nam_hoc' => $nam_hoc_moi,
        'trangthai' => $trangthai_moi // Bao gồm cả trạng thái
    ];

    // Validate input data
    if ($id_hocki_post === 0 || empty($ten_hocki_moi) || empty($nam_hoc_moi)) {
        $_SESSION['message'] = "Vui lòng điền đầy đủ Tên học kỳ và chọn Năm học. ID học kỳ không hợp lệ.";
        $_SESSION['message_type'] = "error";
    } else {
        // Kiểm tra xem tên học kỳ và năm học mới có bị trùng với học kỳ khác không (ngoại trừ học kỳ đang chỉnh sửa)
        $check_stmt = $conn->prepare("SELECT id_hocki FROM hocki WHERE ten_hocki = ? AND nam_hoc = ? AND id_hocki <> ?");
        if ($check_stmt === false) {
            $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
            $_SESSION['message_type'] = "error";
        } else {
            $check_stmt->bind_param("ssi", $ten_hocki_moi, $nam_hoc_moi, $id_hocki_post);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['message'] = "Học kỳ **" . htmlspecialchars($ten_hocki_moi) . " - " . htmlspecialchars($nam_hoc_moi) . "** đã tồn tại. Vui lòng chọn tên hoặc năm học khác.";
                $_SESSION['message_type'] = "error";
            } else {
                // Chuẩn bị câu lệnh UPDATE cho học kỳ hiện tại
                $stmt = $conn->prepare("UPDATE hocki SET ten_hocki = ?, nam_hoc = ?, trangthai = ? WHERE id_hocki = ?");
                if ($stmt === false) {
                    $_SESSION['message'] = "Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error;
                    $_SESSION['message_type'] = "error";
                } else {
                    $stmt->bind_param("ssii", $ten_hocki_moi, $nam_hoc_moi, $trangthai_moi, $id_hocki_post);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Cập nhật học kỳ **" . htmlspecialchars($ten_hocki_moi) . " - " . htmlspecialchars($nam_hoc_moi) . "** thành công!";
                        $_SESSION['message_type'] = "success";
                        header("Location: hocki.php");
                        exit();
                    } else {
                        $_SESSION['message'] = "Lỗi khi cập nhật học kỳ: " . $stmt->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
    $id_hocki = $id_hocki_post;
}

// --- Logic Lấy dữ liệu học kỳ để hiển thị lên form (GET request hoặc sau lỗi POST) ---
if ($hocki_data === null && $id_hocki > 0) {
    $stmt = $conn->prepare("SELECT id_hocki, ten_hocki, nam_hoc, trangthai FROM hocki WHERE id_hocki = ?");
    if ($stmt === false) {
        $error = "Lỗi chuẩn bị truy vấn lấy dữ liệu học kỳ: " . $conn->error;
    } else {
        $stmt->bind_param("i", $id_hocki);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $hocki_data = $result->fetch_assoc();
        } else {
            $error = "Không tìm thấy học kỳ với ID: " . htmlspecialchars($id_hocki);
            $id_hocki = 0;
        }
        $stmt->close();
    }
} elseif ($id_hocki === 0) {
    $error = "Không có ID học kỳ được cung cấp để chỉnh sửa.";
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa Học Kỳ</title>
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
            <h2>Chỉnh sửa Học Kỳ</h2>

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

            <?php if ($hocki_data) : ?>
                <form method="POST">
                    <input type="hidden" name="id_hocki" value="<?php echo htmlspecialchars($hocki_data['id_hocki']); ?>">

                    <label for="ten_hocki">Tên Học Kỳ:</label>
                    <input type="text" id="ten_hocki" name="ten_hocki" value="<?php echo htmlspecialchars($hocki_data['ten_hocki']); ?>" required>

                    <label for="nam_hoc">Năm học:</label>
                    <select id="nam_hoc" name="nam_hoc" required>
                        <option value="">-- Chọn Năm học --</option>
                        <?php foreach ($years_to_display as $year_option) : ?>
                            <option value="<?php echo htmlspecialchars($year_option); ?>"
                                <?php echo (isset($hocki_data['nam_hoc']) && $hocki_data['nam_hoc'] == $year_option) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="trangthai">Trạng thái:</label>
                    <select id="trangthai" name="trangthai" required>
                        <option value="0" <?php echo (isset($hocki_data['trangthai']) && $hocki_data['trangthai'] == 0) ? 'selected' : ''; ?>>Hiện </option>
                        <option value="1" <?php echo (isset($hocki_data['trangthai']) && $hocki_data['trangthai'] == 1) ? 'selected' : ''; ?>>Ẩn </option>
                    </select>

                    <button type="submit"><i class="fas fa-save"></i> Cập nhật Học Kỳ</button>
                    <a href="/dkhp/admin/hocki.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
                </form>
            <?php else : ?>
                <p>Không thể tải thông tin học kỳ hoặc ID học kỳ không hợp lệ.</p>
                <a href="/dkhp/admin/hocki.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
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